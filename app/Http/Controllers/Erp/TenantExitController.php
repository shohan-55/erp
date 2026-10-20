<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantExitPackage;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantExitController extends Controller
{
    private function partner(Request $request): array
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        abort_unless($company && $company->active && !$company->archived_at && !$company->deleted_at,404);
        return [$company,$workspace,$actor];
    }

    private function companyCase(object $actor,int $id,bool $lock=false): object
    {
        $query=DB::table('tenant_exit_cases')->where('id',$id)
            ->where('company_id',$actor->company_id);
        $case=($lock?$query->lockForUpdate():$query)->first();
        abort_unless($case,404);
        return $case;
    }

    private function consented(object $case): bool
    {
        $votes=DB::table('tenant_exit_votes')->where('case_id',$case->id)
            ->where('company_id',$case->company_id)->get();
        if($votes->isEmpty() || !$votes->every(fn($v)=>$v->decision==='approve'))return false;
        $voters=$votes->pluck('membership_id')->map(fn($id)=>(int)$id)->sort()->values()->all();
        $partners=DB::table('memberships')->where('company_id',$case->company_id)
            ->where('role','partner')->where('active',true)
            ->whereNull('platform_suspended_at')->pluck('id')
            ->map(fn($id)=>(int)$id)->sort()->values()->all();
        // Fail closed when company Partner governance changes after approval.
        return $voters===$partners;
    }

    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=$this->partner($request);
        $cases=DB::table('tenant_exit_cases')->where('company_id',$company->id)
            ->orderByDesc('id')->limit(15)->get();
        $current=$cases->first();
        $votes=$current?DB::table('tenant_exit_votes as v')
            ->join('memberships as m','m.id','=','v.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('v.case_id',$current->id)->where('v.company_id',$company->id)
            ->select('v.*','u.name','m.role')->orderBy('v.id')->get():collect();
        // Do not expose other Partners' source content, even on inventory screens.
        $artifactPage=max(1,min(100000,(int)$request->query('export_page',1)));
        $artifactCount=$current && $this->consented($current)
            && in_array($current->state,['prepared','integrity_verified','acknowledged','restore_attested'],true)
            ?DB::table('tenant_exit_artifacts')->where('company_id',$company->id)
                ->where('case_id',$current->id)->count():0;
        $artifacts=$artifactCount
            ?DB::table('tenant_exit_artifacts')->where('company_id',$company->id)
                ->where('case_id',$current->id)->orderBy('id')
                ->offset(($artifactPage-1)*100)->limit(100)->get():collect();
        $holds=$current?DB::table('tenant_exit_holds')
            ->where('company_id',$company->id)->where('case_id',$current->id)
            ->orderByDesc('id')->limit(25)->get():collect();
        $inventory=TenantExitPackage::inventory((int)$company->id);
        return response()->view('erp.tenant-exit',compact(
            'company','workspace','actor','firmSlug','cases','current','votes','artifacts','artifactPage','artifactCount','holds','inventory'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function requestExport(Request $request,string $firmSlug)
    {
        [$company,,$actor]=$this->partner($request);
        $input=$request->validate([
            'confirmation_slug'=>['required','string','max:70'],
            'reason'=>['required','string','min:20','max:1000'],
            'retention_until'=>['required','date_format:Y-m-d',
                'after_or_equal:'.now($company->timezone)->addDays(
                    (int)config('erp_exit.minimum_retention_days',30))->toDateString()],
        ]);
        if(!hash_equals($company->slug,$input['confirmation_slug'])){
            throw ValidationException::withMessages(['confirmation_slug'=>'Enter the exact company slug.']);
        }
        DB::transaction(function()use($company,$actor,$input){
            DB::table('companies')->where('id',$company->id)->lockForUpdate()->firstOrFail();
            if(DB::table('tenant_exit_cases')->where('company_id',$company->id)
                ->whereNotIn('state',['blocked','rejected'])->exists()){
                throw ValidationException::withMessages(['reason'=>
                    'An existing approved or pending exit case must be resolved before a new request.']);
            }
            $partners=DB::table('memberships')->where('company_id',$company->id)
                ->where('role','partner')->where('active',true)
                ->whereNull('platform_suspended_at')->orderBy('id')->get();
            if($partners->isEmpty() || !$partners->contains('id',$actor->id)){
                throw ValidationException::withMessages(['reason'=>'An active Home Partner must request an export.']);
            }
            $id=DB::table('tenant_exit_cases')->insertGetId([
                'company_id'=>$company->id,'requested_by'=>$actor->id,
                'reason'=>trim($input['reason']),
                'retention_until'=>$input['retention_until'],
                'state'=>'awaiting_consent','created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($partners as $member){
                DB::table('tenant_exit_votes')->insert([
                    'case_id'=>$id,'company_id'=>$company->id,
                    'workspace_id'=>$member->workspace_id,
                    'membership_id'=>$member->id,
                    'decision'=>'pending','created_at'=>now(),'updated_at'=>now(),
                ]);
                // Cross-workspace Partner alerts must not call TenantOps::notify:
                // that helper deliberately enforces the actor's Home Partner.
                DB::table('tenant_notifications')->insert([
                    'company_id'=>$company->id,'workspace_id'=>$member->workspace_id,
                    'recipient_membership_id'=>$member->id,
                    'title'=>'Company exit export consent',
                    'message'=>'A company-wide data export requires every Partner to approve explicitly.',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            TenantOps::audit($actor,'exit.requested','tenant_exit_case',$id,[
                'retention_until'=>$input['retention_until'],
                'partner_votes_required'=>$partners->count(),
            ]);
        },3);
        return back()->with('success','Exit export requested; all active Partner memberships must approve.');
    }

    public function vote(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=$this->partner($request);
        $input=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($actor,$id,$input){
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->firstOrFail();
            $case=$this->companyCase($actor,$id,true);
            if($case->state!=='awaiting_consent'){
                throw ValidationException::withMessages(['decision'=>'Consent decisions are closed.']);
            }
            $vote=DB::table('tenant_exit_votes')->where('company_id',$actor->company_id)
                ->where('case_id',$id)->where('membership_id',$actor->id)
                ->lockForUpdate()->first();
            abort_unless($vote,403);
            if($vote->decision!=='pending'){
                throw ValidationException::withMessages(['decision'=>'This Partner has already voted.']);
            }
            DB::table('tenant_exit_votes')->where('id',$vote->id)->update([
                'decision'=>$input['decision'],'decided_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'exit.partner.voted','tenant_exit_case',$id,[
                'decision'=>$input['decision'],'reason'=>trim($input['reason']),
            ]);
            if($input['decision']==='reject'){
                DB::table('tenant_exit_cases')->where('id',$id)->update([
                    'state'=>'rejected','block_reason'=>'A Partner rejected company-wide export.',
                    'updated_at'=>now(),
                ]);
                return;
            }
            if($this->consented($case)){
                DB::table('tenant_exit_cases')->where('id',$id)->update([
                    'state'=>'approved','approved_at'=>now(),'updated_at'=>now(),
                ]);
            }
        },3);
        return back()->with('success','Your independent Partner consent decision was recorded.');
    }

    public function verify(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=$this->partner($request);
        $case=$this->companyCase($actor,$id);
        abort_unless($case->state==='prepared' && $this->consented($case),409);
        if(!TenantExitPackage::verify($id)){
            throw ValidationException::withMessages(['integrity'=>'Encrypted artifact checksum verification failed.']);
        }
        DB::transaction(function()use($actor,$id){
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->firstOrFail();
            $case=$this->companyCase($actor,$id,true);
            abort_unless($case->state==='prepared',409);
            DB::table('tenant_exit_cases')->where('id',$id)->update([
                'state'=>'integrity_verified','verified_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'exit.integrity.verified','tenant_exit_case',$id,[
                'manifest_sha256'=>$case->manifest_sha256,
                'claim'=>'encrypted artifacts hash checked; NOT a restore test',
            ]);
        },3);
        return back()->with('success','All stored artifact hashes verified; independent restore remains required.');
    }

    public function download(Request $request,string $firmSlug,int $id,int $artifactId)
    {
        [,,$actor]=$this->partner($request);
        $case=$this->companyCase($actor,$id);
        abort_unless($this->consented($case)
            && in_array($case->state,['prepared','integrity_verified','acknowledged','restore_attested'],true),403);
        $part=DB::table('tenant_exit_artifacts')
            ->where('id',$artifactId)->where('case_id',$id)
            ->where('company_id',$actor->company_id)->first();
        abort_unless($part,404);
        $bytes=TenantExitPackage::content($part);
        TenantOps::audit($actor,'exit.artifact.downloaded','tenant_exit_case',$id,[
            'artifact_id'=>$part->id,'kind'=>$part->kind,
        ]);
        $filename='company-exit-'.$id.'-artifact-'.$part->id.
            ($part->kind==='table'?'.jsonl':($part->kind==='manifest'?'.json':'.bin'));
        return response($bytes,200,[
            'Content-Type'=>'application/octet-stream',
            'Content-Disposition'=>'attachment; filename="'.$filename.'"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function acknowledge(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=$this->partner($request);
        $input=$request->validate([
            'manifest_sha256'=>['required','regex:/^[a-f0-9]{64}$/'],
            'confirmation'=>['required','in:RECEIVED'],
        ]);
        DB::transaction(function()use($actor,$id,$input){
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->firstOrFail();
            $case=$this->companyCase($actor,$id,true);
            abort_unless($case->state==='integrity_verified' && $this->consented($case),409);
            abort_unless((int)$case->requested_by===(int)$actor->id,403);
            if(!hash_equals($case->manifest_sha256,$input['manifest_sha256'])){
                throw ValidationException::withMessages(['manifest_sha256'=>'Manifest checksum does not match.']);
            }
            DB::table('tenant_exit_cases')->where('id',$id)->update([
                'state'=>'acknowledged','customer_acknowledged_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'exit.customer.received','tenant_exit_case',$id,[
                'manifest_sha256'=>$case->manifest_sha256,
                'warning'=>'Acknowledgement alone never proves independent restore',
            ]);
        },3);
        return back()->with('success','Customer package receipt acknowledged. Independent staging restore is still required.');
    }

    public function attestRestore(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=$this->partner($request);
        $input=$request->validate([
            'manifest_sha256'=>['required','regex:/^[a-f0-9]{64}$/'],
            'restore_evidence'=>['required','string','min:30','max:1000'],
            'confirmation'=>['required','in:INDEPENDENT_RESTORE_TESTED'],
        ]);
        DB::transaction(function()use($actor,$id,$input){
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->firstOrFail();
            $case=$this->companyCase($actor,$id,true);
            abort_unless($case->state==='acknowledged' && $this->consented($case),409);
            $requester=DB::table('memberships')->where('id',$case->requested_by)
                ->where('company_id',$actor->company_id)->firstOrFail();
            // A distinct human account, not just another workspace role label.
            abort_unless((int)$requester->user_id!==(int)$actor->user_id,403);
            if(!hash_equals($case->manifest_sha256,$input['manifest_sha256'])){
                throw ValidationException::withMessages(['manifest_sha256'=>'Restore evidence references another manifest.']);
            }
            DB::table('tenant_exit_cases')->where('id',$id)->update([
                'state'=>'restore_attested','restore_attested_at'=>now(),
                'restore_attested_by'=>$actor->id,'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'exit.restore.attested','tenant_exit_case',$id,[
                'manifest_sha256'=>$case->manifest_sha256,
                'evidence'=>trim($input['restore_evidence']),
                'disclaimer'=>'User attestation; staging restoration cannot be independently proven by this app',
            ]);
        },3);
        return back()->with('success','Independent Partner restore attestation recorded; purge remains disabled.');
    }

    public function placeHold(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=$this->partner($request);
        $input=$request->validate(['reason'=>['required','string','min:20','max:1000']]);
        DB::transaction(function()use($actor,$id,$input){
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->firstOrFail();
            $this->companyCase($actor,$id,true);
            $holdId=DB::table('tenant_exit_holds')->insertGetId([
                'company_id'=>$actor->company_id,'case_id'=>$id,
                'created_by'=>$actor->id,'reason'=>trim($input['reason']),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'exit.hold.placed','tenant_exit_hold',$holdId);
        },3);
        return back()->with('success','Legal/contract retention hold placed. No data has been removed.');
    }

    public function releaseHold(Request $request,string $firmSlug,int $id,int $holdId)
    {
        [,,$actor]=$this->partner($request);
        $input=$request->validate(['reason'=>['required','string','min:20','max:1000']]);
        DB::transaction(function()use($actor,$id,$holdId,$input){
            DB::table('companies')->where('id',$actor->company_id)->lockForUpdate()->firstOrFail();
            $this->companyCase($actor,$id,true);
            $hold=DB::table('tenant_exit_holds')->where('id',$holdId)
                ->where('case_id',$id)->where('company_id',$actor->company_id)
                ->lockForUpdate()->first();
            abort_unless($hold,404);
            abort_unless($hold->released_at===null,409);
            DB::table('tenant_exit_holds')->where('id',$holdId)->update([
                'released_at'=>now(),'released_by'=>$actor->id,'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'exit.hold.released','tenant_exit_hold',$holdId,[
                'release_reason'=>trim($input['reason']),
            ]);
        },3);
        return back()->with('success','Hold release recorded; irreversible purge is still disabled.');
    }

    public function preflight(Request $request,string $firmSlug,int $id)
    {
        [,,$actor]=$this->partner($request);
        $case=$this->companyCase($actor,$id);
        $holds=DB::table('tenant_exit_holds')->where('case_id',$id)
            ->where('company_id',$actor->company_id)->whereNull('released_at')->count();
        $vaultHolds=DB::table('erp_vault_objects')->where('company_id',$actor->company_id)
            ->where('legal_hold',true)->count();
        $pending=DB::table('erp_vault_uploads')->where('company_id',$actor->company_id)
            ->where('status','pending')->count();
        return response()->json([
            'company'=>$request->attributes->get('tenant.company')?->slug,
            'case_id'=>$id,'state'=>$case->state,
            'manifest_sha256'=>$case->manifest_sha256,
            'required_retention_until'=>$case->retention_until,
            'retention_elapsed'=>now()->toDateString()>=$case->retention_until,
            'open_legal_holds'=>$holds,'held_vault_files'=>$vaultHolds,
            'pending_vault_uploads'=>$pending,
            'customer_receipt_recorded'=>(bool)$case->customer_acknowledged_at,
            'independent_restore_attested'=>(bool)$case->restore_attested_at,
            'physical_purge_enabled'=>false,
            'blocking_reasons'=>array_values(array_filter([
                $case->state!=='restore_attested'?'No completed independent restore attestation':null,
                $holds>0?'Active contractual/legal holds':null,
                $vaultHolds>0?'Held private vault objects':null,
                $pending>0?'Unfinished vault uploads':null,
                now()->toDateString()<$case->retention_until?'Contract retention has not elapsed':null,
                'Phase 18 deliberately has no irreversible purge implementation or release approval',
            ])),
        ],200)->header('Cache-Control','private, no-store, max-age=0');
    }
}
