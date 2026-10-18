<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceSwitchController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $options] = $this->available($request, $firmSlug);
        if ($options->isEmpty()) {
            abort(403, 'No active workspace membership.');
        }

        return response()->view('erp.workspace-switch', compact('company', 'options', 'firmSlug'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function select(Request $request, string $firmSlug)
    {
        $input = $request->validate(['membership_id' => ['required', 'integer', 'min:1']]);
        [$company, $options] = $this->available($request, $firmSlug);

        $membership = $options->firstWhere('id', (int) $input['membership_id']);
        if (! $membership) {
            throw ValidationException::withMessages([
                'membership_id' => 'The selected Partner workspace is not available for your account.',
            ]);
        }

        // Selection is session-bound to BOTH the authenticated user and firm.
        // ID alone can never grant access to another Partner's private records.
        $request->session()->regenerate();
        $request->session()->put('erp.active_membership', [
            'company_id' => (int) $company->id,
            'user_id' => (int) $request->user()->id,
            'membership_id' => (int) $membership->id,
        ]);

        return redirect()->route('erp.dashboard', ['firmSlug' => $firmSlug]);
    }

    private function available(Request $request, string $firmSlug): array
    {
        $company = DB::table('companies')->where('slug', $firmSlug)
            ->where('active', true)->whereNull('archived_at')->first();
        abort_unless($company && $request->user(), 403);

        $options = DB::table('memberships as m')
            ->join('workspaces as w', function ($join) {
                $join->on('w.id', '=', 'm.workspace_id')
                    ->on('w.company_id', '=', 'm.company_id');
            })
            ->where('m.company_id', $company->id)
            ->where('m.user_id', $request->user()->id)
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->where('w.active', true)
            ->select('m.id', 'm.role', 'w.name as workspace_name', 'w.slug as workspace_slug')
            ->orderBy('w.name')->get();

        return [$company, $options];
    }
}
