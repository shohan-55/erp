<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\ConveyanceClaim;
use App\Models\LeaveRequest;
use App\Models\OperationalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalWorkflowController extends Controller
{
    private function scope(Request $request, string $slug): array
    {
        $company = Company::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $membership = CompanyMembership::query()->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)->where('is_active', true)
            ->whereNotNull('workspace_id')->firstOrFail();

        return [$company, $membership];
    }

    private function studentIds(Company $company, CompanyMembership $actor): array
    {
        return CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('role', 'student')
            ->when($actor->role === 'manager',
                fn ($q) => $q->where('manager_membership_id', $actor->id))
            ->when($actor->role === 'student',
                fn ($q) => $q->whereKey($actor->id))
            ->pluck('id')->all();
    }

    public function index(Request $request, string $companySlug)
    {
        [$company, $actor] = $this->scope($request, $companySlug);
        $studentIds = $this->studentIds($company, $actor);

        $leaves = LeaveRequest::query()->where('company_id', $company->id)
            ->where('workspace_id', $actor->workspace_id)->whereIn('membership_id', $studentIds)
            ->latest()->limit(40)->get();

        $claims = ConveyanceClaim::query()->where('company_id', $company->id)
            ->where('workspace_id', $actor->workspace_id)->whereIn('membership_id', $studentIds)
            ->latest()->limit(40)->get();

        $notifications = OperationalNotification::query()->where('company_id', $company->id)
            ->where('workspace_id', $actor->workspace_id)->where('membership_id', $actor->id)
            ->latest()->limit(20)->get();

        return view('operational.workflows', compact('company', 'actor', 'leaves', 'claims', 'notifications'));
    }

    public function submitLeave(Request $request, string $companySlug)
    {
        [$company, $actor] = $this->scope($request, $companySlug);
        abort_unless($actor->role === 'student', 403);
        $data = $request->validate([
            'type' => ['required','in:annual,sick,emergency'],
            'starts_on' => ['required','date_format:Y-m-d'],
            'ends_on' => ['required','date_format:Y-m-d','after_or_equal:starts_on'],
            'reason' => ['required','string','min:5','max:2000'],
        ]);
        if (\Carbon\CarbonImmutable::parse($data['starts_on'])->diffInDays(\Carbon\CarbonImmutable::parse($data['ends_on'])) > 60) {
            throw ValidationException::withMessages(['ends_on' => 'Maximum single leave request is 60 days.']);
        }

        LeaveRequest::create(array_merge($data, [
            'company_id'=>$company->id,'workspace_id'=>$actor->workspace_id,
            'membership_id'=>$actor->id,'status'=>'pending',
        ]));

        return back()->with('status','Leave application submitted for review.');
    }

    public function submitClaim(Request $request, string $companySlug)
    {
        [$company, $actor] = $this->scope($request, $companySlug);
        abort_unless($actor->role === 'student', 403);
        $data = $request->validate([
            'journey_date' => ['required','date_format:Y-m-d'],
            'amount' => ['required','numeric','gt:0','lte:50000','decimal:0,2'],
            'journey_route' => ['required','string','min:3','max:255'],
            'purpose' => ['required','string','min:5','max:2000'],
        ]);

        ConveyanceClaim::create(array_merge($data, [
            'company_id'=>$company->id,'workspace_id'=>$actor->workspace_id,
            'membership_id'=>$actor->id,'status'=>'pending',
        ]));

        return back()->with('status','Conveyance claim submitted for review. No payment has been made.');
    }

    public function reviewLeave(Request $request, string $companySlug, int $requestId)
    {
        [$company, $actor] = $this->scope($request, $companySlug);
        abort_unless(in_array($actor->role, ['partner','manager'], true),403);
        $data = $request->validate(['decision'=>['required','in:approved,rejected']]);
        DB::transaction(function () use ($company, $actor, $requestId, $data) {
            CompanyMembership::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $item = LeaveRequest::query()->where('company_id',$company->id)
                ->where('workspace_id',$actor->workspace_id)->whereKey($requestId)
                ->lockForUpdate()->firstOrFail();
            abort_unless(in_array($item->membership_id, $this->studentIds($company,$actor), true), 403);
            if ($item->status !== 'pending') {
                throw ValidationException::withMessages(['decision'=>'This request has already been reviewed.']);
            }
            $item->update(['status'=>$data['decision'],'reviewer_membership_id'=>$actor->id,'reviewed_at'=>now()]);
            OperationalNotification::create(['company_id'=>$company->id,'workspace_id'=>$actor->workspace_id,
                'membership_id'=>$item->membership_id,'title'=>'Leave request reviewed',
                'body'=>'Your leave request #'.$item->id.' was '.$data['decision'].'.']);
        },3);
        return back()->with('status','Leave decision recorded.');
    }

    public function reviewClaim(Request $request, string $companySlug, int $requestId)
    {
        [$company, $actor] = $this->scope($request, $companySlug);
        abort_unless(in_array($actor->role, ['partner','manager'], true),403);
        $data = $request->validate(['decision'=>['required','in:approved,rejected']]);
        DB::transaction(function () use ($company, $actor, $requestId, $data) {
            CompanyMembership::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $item = ConveyanceClaim::query()->where('company_id',$company->id)
                ->where('workspace_id',$actor->workspace_id)->whereKey($requestId)
                ->lockForUpdate()->firstOrFail();
            abort_unless(in_array($item->membership_id, $this->studentIds($company,$actor), true), 403);
            if ($item->status !== 'pending') {
                throw ValidationException::withMessages(['decision'=>'This claim has already been reviewed.']);
            }
            $item->update(['status'=>$data['decision'],'reviewer_membership_id'=>$actor->id,'reviewed_at'=>now()]);
            OperationalNotification::create(['company_id'=>$company->id,'workspace_id'=>$actor->workspace_id,
                'membership_id'=>$item->membership_id,'title'=>'Conveyance claim reviewed',
                'body'=>'Your conveyance claim #'.$item->id.' was '.$data['decision'].'. Payment is not automatic.']);
        },3);
        return back()->with('status','Claim decision recorded. No funds disbursed.');
    }

    public function markRead(Request $request, string $companySlug, int $notificationId)
    {
        [$company, $actor] = $this->scope($request, $companySlug);
        OperationalNotification::query()->where('company_id',$company->id)
            ->where('workspace_id',$actor->workspace_id)
            ->where('membership_id',$actor->id)->whereKey($notificationId)
            ->firstOrFail()->update(['read_at'=>now()]);
        return back()->with('status','Notification marked as read.');
    }
}
