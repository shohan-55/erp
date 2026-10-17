<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CustomDomainDnsVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformCustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Laravel's test request factory uses localhost for relative URLs.
        // The production canonical hostname is set by APP_URL on cPanel.
        config()->set('app.url', 'http://localhost');
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        return $user;
    }

    private function company(string $slug = 'rac'): int
    {
        return DB::table('companies')->insertGetId([
            'name' => $slug === 'rac' ? 'Rahman Anis & Co.' : 'Other Company',
            'slug' => $slug, 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function mockDns(bool $valid): object
    {
        $checker = new class extends CustomDomainDnsVerifier {
            public bool $valid = false;
            public function verified(string $hostname, string $token): bool
            {
                return $this->valid;
            }
        };
        $checker->valid = $valid;
        $this->app->instance(CustomDomainDnsVerifier::class, $checker);
        return $checker;
    }

    private function register(int $company, string $hostname = 'erp.clientcompany.com'): void
    {
        $this->post("/platform/app/companies/$company/custom-domain", [
            'hostname' => $hostname,
        ])->assertRedirect();
    }

    public function test_only_mfa_guarded_platform_admin_can_manage_domains(): void
    {
        $company = $this->company();
        $member = User::factory()->create();

        $this->post("/platform/app/companies/$company/custom-domain", [
            'hostname' => 'erp.clientcompany.com',
        ])->assertRedirect();

        $this->actingAs($member)->post("/platform/app/companies/$company/custom-domain", [
            'hostname' => 'erp.clientcompany.com',
        ])->assertForbidden();

        $this->post("/platform/app/companies/$company/custom-domain/disable", [
            'confirmation_hostname' => 'erp.clientcompany.com',
        ])->assertForbidden();
        $this->assertDatabaseCount('company_custom_domains', 0);
    }

    public function test_reserved_hostname_requires_dns_tls_and_audit_without_exposing_tenant_data(): void
    {
        $company = $this->company();
        $this->actingAs($this->owner());
        $this->register($company);
        $domain = DB::table('company_custom_domains')->first();
        $this->assertNotNull($domain);
        $this->assertFalse((bool) $domain->active);
        $this->assertNull($domain->verified_at);
        $this->assertSame(48, strlen($domain->verification_token));

        $this->get("/platform/app/companies/$company")->assertOk()
            ->assertSee('Custom Domain Control')
            ->assertSee('_dhakawala-erp.erp.clientcompany.com')
            ->assertSee('dhakawala-erp-verification=')
            ->assertSee('Check DNS Ownership & CNAME');

        $this->post("/platform/app/companies/$company/custom-domain/tls", [
            'confirmation_hostname' => 'erp.clientcompany.com', 'tls_ready' => '1',
        ])->assertSessionHasErrors('confirmation_hostname');

        $dns = $this->mockDns(false);
        $this->post("/platform/app/companies/$company/custom-domain/verify")
            ->assertSessionHasErrors('hostname');
        $this->assertDatabaseHas('company_custom_domains', ['company_id' => $company, 'active' => 0]);

        $dns->valid = true;
        $this->post("/platform/app/companies/$company/custom-domain/verify")->assertRedirect();
        $this->assertNotNull(DB::table('company_custom_domains')->where('company_id', $company)->value('verified_at'));

        $this->post("/platform/app/companies/$company/custom-domain/tls", [
            'confirmation_hostname' => 'wrong.clientcompany.com', 'tls_ready' => '1',
        ])->assertSessionHasErrors('confirmation_hostname');

        $this->post("/platform/app/companies/$company/custom-domain/tls", [
            'confirmation_hostname' => 'erp.clientcompany.com', 'tls_ready' => '1',
        ])->assertRedirect();

        $dns->valid = false;
        $this->post("/platform/app/companies/$company/custom-domain/activate", [
            'confirmation_hostname' => 'erp.clientcompany.com',
        ])->assertSessionHasErrors('confirmation_hostname');

        $dns->valid = true;
        $this->post("/platform/app/companies/$company/custom-domain/activate", [
            'confirmation_hostname' => 'erp.clientcompany.com',
        ])->assertRedirect();
        $this->assertDatabaseHas('company_custom_domains', ['company_id' => $company, 'active' => 1]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company, 'action' => 'custom_domain.activated', 'subject_type' => 'custom_domain',
        ]);
        $this->get('/platform/app')->assertSee('Active Custom Domains')->assertSee('erp.clientcompany.com');
    }

    public function test_matching_client_host_serves_login_and_rejects_other_company(): void
    {
        $company = $this->company();
        $this->company('outsider');
        $this->actingAs($this->owner());
        $this->register($company);
        $this->mockDns(true);
        $this->post("/platform/app/companies/$company/custom-domain/verify")->assertRedirect();
        $this->post("/platform/app/companies/$company/custom-domain/tls", [
            'confirmation_hostname' => 'erp.clientcompany.com', 'tls_ready' => '1',
        ])->assertRedirect();
        $this->post("/platform/app/companies/$company/custom-domain/activate", [
            'confirmation_hostname' => 'erp.clientcompany.com',
        ])->assertRedirect();

        $this->get('https://erp.clientcompany.com/login')->assertOk()
            ->assertSee('Rahman Anis & Co.')
            ->assertSee('Sign in to continue');

        $this->get('https://erp.clientcompany.com/c/rac/app/login')->assertOk();
        $this->get('https://erp.clientcompany.com/c/outsider/app/login')->assertNotFound();
        $this->get('https://unregistered.clientcompany.com/login')->assertNotFound();
        $this->get('https://unregistered.clientcompany.com/c/rac/app/login')->assertNotFound();
        $this->get('https://erp.clientcompany.com/platform/app/login')->assertNotFound();
    }

    public function test_disable_and_replace_rotate_challenge_without_erasing_tenant(): void
    {
        $company = $this->company();
        $this->actingAs($this->owner());
        $this->register($company);
        $oldToken = DB::table('company_custom_domains')->value('verification_token');
        $this->mockDns(true);
        $this->post("/platform/app/companies/$company/custom-domain/verify")->assertRedirect();
        $this->post("/platform/app/companies/$company/custom-domain/tls", [
            'confirmation_hostname' => 'erp.clientcompany.com', 'tls_ready' => '1',
        ])->assertRedirect();
        $this->post("/platform/app/companies/$company/custom-domain/activate", [
            'confirmation_hostname' => 'erp.clientcompany.com',
        ])->assertRedirect();

        $this->post("/platform/app/companies/$company/custom-domain", [
            'hostname' => 'portal.clientcompany.com',
        ])->assertSessionHasErrors('hostname');

        $this->post("/platform/app/companies/$company/custom-domain/disable", [
            'confirmation_hostname' => 'erp.clientcompany.com',
        ])->assertRedirect();

        $this->get('https://erp.clientcompany.com/login')->assertNotFound();
        $this->get('http://localhost/c/rac/app/login')->assertOk();

        $this->post("/platform/app/companies/$company/custom-domain", [
            'hostname' => 'portal.clientcompany.com',
        ])->assertRedirect();
        $row = DB::table('company_custom_domains')->where('company_id', $company)->first();
        $this->assertSame('portal.clientcompany.com', $row->hostname);
        $this->assertNotEquals($oldToken, $row->verification_token);
        $this->assertNull($row->verified_at);
        $this->assertNull($row->tls_approved_at);
        $this->assertFalse((bool) $row->active);
    }

    public function test_rejects_apex_platform_domains_reserved_names_and_cross_company_reuse(): void
    {
        $company = $this->company();
        $second = $this->company('other');
        $this->actingAs($this->owner());

        foreach (['example.com', 'erp.dhakawala.com', 'foo.dhakawala.com',
            '127.0.0.1', 'erp.example.local', 'https://erp.example.com'] as $bad) {
            $this->post("/platform/app/companies/$company/custom-domain", [
                'hostname' => $bad,
            ])->assertSessionHasErrors('hostname');
        }

        $this->register($company);
        $this->post("/platform/app/companies/$second/custom-domain", [
            'hostname' => 'erp.clientcompany.com',
        ])->assertSessionHasErrors('hostname');

        $this->assertDatabaseCount('company_custom_domains', 1);
    }
}
