<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\CustomDomainDnsVerifier;
use App\Support\SaasBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformCustomDomainController extends Controller
{
    private function activeCompany(int $companyId): object
    {
        $company = DB::table('companies')->where('id', $companyId)
            ->where('active', true)->whereNull('archived_at')->first();
        abort_unless($company, 404);
        return $company;
    }

    public function store(Request $request, int $companyId)
    {
        $input = strtolower(trim((string) $request->input('hostname', '')));
        $request->merge(['hostname' => $input]);
        $data = $request->validate(['hostname' => [
            'required', 'string', 'max:253',
            function ($attribute, $value, $fail) {
                $labels = explode('.', $value);
                $canonical = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
                if (count($labels) < 3 || ! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
                    || filter_var($value, FILTER_VALIDATE_IP)
                    || $value === $canonical || str_ends_with($value, '.dhakawala.com')
                    || ! preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $value)
                    || in_array(end($labels), ['local','test','internal','localhost'], true)) {
                    $fail('Enter a valid client-owned subdomain such as erp.example.com.');
                }
            },
        ]]);

        $this->activeCompany($companyId);
        DB::transaction(function () use ($companyId, $data) {
            $row = DB::table('company_custom_domains')
                ->where('company_id', $companyId)->lockForUpdate()->first();

            if ($row && $row->active) {
                throw ValidationException::withMessages(['hostname' =>
                    'Disable the current domain before requesting a replacement.']);
            }
            if (DB::table('company_custom_domains')->where('hostname', $data['hostname'])
                ->when($row, fn ($q) => $q->where('id', '!=', $row->id))->exists()) {
                throw ValidationException::withMessages(['hostname' =>
                    'This hostname is already reserved for another company.']);
            }

            $values = [
                'hostname' => $data['hostname'],
                'verification_token' => Str::random(48),
                'verified_at' => null, 'tls_approved_at' => null, 'active' => false,
                'updated_at' => now(),
            ];
            if ($row) {
                DB::table('company_custom_domains')->where('id', $row->id)->update($values);
                $id = $row->id;
            } else {
                $id = DB::table('company_custom_domains')->insertGetId([
                    ...$values, 'company_id' => $companyId, 'created_at' => now(),
                ]);
            }
            $this->audit($companyId, 'custom_domain.requested', $id, $data['hostname']);
        }, 3);

        return back()->with('success', 'Domain reserved. Publish the CNAME and TXT records, then verify.');
    }

    public function verify(int $companyId, CustomDomainDnsVerifier $checker)
    {
        $this->activeCompany($companyId);
        $domain = DB::table('company_custom_domains')->where('company_id', $companyId)->first();
        abort_unless($domain, 404);

        if (! $checker->verified($domain->hostname, $domain->verification_token)) {
            throw ValidationException::withMessages(['hostname' =>
                'DNS check failed. Verify the CNAME target and exact TXT token, then retry after propagation.']);
        }

        DB::transaction(function () use ($companyId, $domain) {
            $locked = DB::table('company_custom_domains')->where('id', $domain->id)
                ->where('company_id', $companyId)->lockForUpdate()->first();
            abort_unless($locked && $locked->hostname === $domain->hostname
                && hash_equals($locked->verification_token, $domain->verification_token), 409);
            if (! $locked->verified_at) {
                DB::table('company_custom_domains')->where('id', $locked->id)
                    ->update(['verified_at' => now(), 'updated_at' => now()]);
                $this->audit($companyId, 'custom_domain.dns_verified', $locked->id, $locked->hostname);
            }
        }, 3);
        return back()->with('success', 'DNS ownership and CNAME verified. TLS hosting review is required next.');
    }

    public function approveTls(Request $request, int $companyId)
    {
        $data = $request->validate([
            'confirmation_hostname' => ['required', 'string', 'max:253'],
            'tls_ready' => ['accepted'],
        ]);
        $this->activeCompany($companyId);

        DB::transaction(function () use ($companyId, $data) {
            $row = DB::table('company_custom_domains')->where('company_id', $companyId)
                ->lockForUpdate()->first();
            abort_unless($row, 404);
            if (! $row->verified_at || ! hash_equals($row->hostname, $data['confirmation_hostname'])) {
                throw ValidationException::withMessages(['confirmation_hostname' =>
                    'First verify DNS, then type the exact hostname.']);
            }
            if (! $row->tls_approved_at) {
                DB::table('company_custom_domains')->where('id', $row->id)
                    ->update(['tls_approved_at' => now(), 'updated_at' => now()]);
                $this->audit($companyId, 'custom_domain.tls_approved', $row->id, $row->hostname);
            }
        }, 3);
        return back()->with('success', 'Operator recorded HTTPS/vhost verification. Domain is NOT active until separately enabled.');
    }

    public function activate(Request $request, int $companyId, CustomDomainDnsVerifier $checker)
    {
        $data = $request->validate(['confirmation_hostname' => ['required', 'string', 'max:253']]);
        $this->activeCompany($companyId);

        $row = DB::table('company_custom_domains')->where('company_id', $companyId)->first();
        abort_unless($row, 404);
        if (! $row->verified_at || ! $row->tls_approved_at
            || ! hash_equals($row->hostname, $data['confirmation_hostname'])
            || ! $checker->verified($row->hostname, $row->verification_token)) {
            throw ValidationException::withMessages(['confirmation_hostname' =>
                'DNS, approved HTTPS/vhost and exact hostname are required before activation.']);
        }

        DB::transaction(function () use ($companyId, $row) {
            $locked = DB::table('company_custom_domains')->where('id', $row->id)
                ->where('company_id', $companyId)->lockForUpdate()->first();
            abort_unless($locked && $locked->hostname === $row->hostname
                && $locked->verification_token === $row->verification_token, 409);
            if (! $locked->active) {
                DB::table('company_custom_domains')->where('id', $locked->id)
                    ->update(['active' => true, 'updated_at' => now()]);
                $this->audit($companyId, 'custom_domain.activated', $locked->id, $locked->hostname);
            }
        }, 3);
        return back()->with('success', 'Custom domain activated; verify login and tenancy routing on an actual device.');
    }

    public function disable(Request $request, int $companyId)
    {
        $data = $request->validate(['confirmation_hostname' => ['required', 'string', 'max:253']]);
        DB::transaction(function () use ($companyId, $data) {
            $row = DB::table('company_custom_domains')->where('company_id', $companyId)
                ->lockForUpdate()->first();
            abort_unless($row, 404);
            if (! hash_equals($row->hostname, $data['confirmation_hostname'])) {
                throw ValidationException::withMessages(['confirmation_hostname' =>
                    'Type the exact hostname to disable this domain.']);
            }
            if ($row->active) {
                DB::table('company_custom_domains')->where('id', $row->id)
                    ->update(['active' => false, 'updated_at' => now()]);
                $this->audit($companyId, 'custom_domain.disabled', $row->id, $row->hostname);
            }
        }, 3);
        return back()->with('success', 'Custom domain disabled. Central /company-slug login remains available for active firms.');
    }

    private function audit(int $companyId, string $action, int $id, string $hostname): void
    {
        DB::table('platform_control_events')->insert([
            'company_id' => $companyId,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => 'custom_domain',
            'subject_id' => $id,
            'details' => json_encode(['hostname' => $hostname], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
