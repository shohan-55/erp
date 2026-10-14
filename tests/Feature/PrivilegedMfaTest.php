<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivilegedMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // MFA MUST be exercised here; legacy unrelated tests bypass only in testing.
        config()->set('erp.mfa_test_bypass', false);
    }

    private function tenantPartner(): User
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug'=>'rac', 'name'=>'RA&C Security Test',
            'timezone'=>'Asia/Dhaka', 'office_start'=>'10:00:00',
            'office_end'=>'18:00:00', 'grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]', 'active'=>true,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id'=>$companyId, 'slug'=>'partner-1',
            'name'=>'Partner Unit 01', 'active'=>true,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $partner = User::factory()->create([
            'password'=>Hash::make('ComplexPassword2026'),
        ]);
        DB::table('memberships')->insert([
            'company_id'=>$companyId, 'workspace_id'=>$workspaceId,
            'user_id'=>$partner->id, 'role'=>'partner', 'active'=>true,
            'monthly_allowance'=>'0.00', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return $partner;
    }

    public function test_totp_matches_known_rfc_vector_and_prevents_counter_replay(): void
    {
        $secret = Totp::encode('12345678901234567890');
        $this->assertSame('287082', Totp::code($secret, 1));
        $this->assertSame(1, Totp::matchingStep($secret, '287082', null, 59));
        $this->assertNull(Totp::matchingStep($secret, '287082', 1, 59));
        $this->assertNull(Totp::matchingStep($secret, 'incorrect', null, 59));
    }

    public function test_partner_without_mfa_can_use_dashboard_payroll_and_recommended_security_settings(): void
    {
        $partner = $this->tenantPartner();
        $this->actingAs($partner)->withSession([
            'erp_mfa_context' => 'firm', 'erp_mfa_firm_slug' => 'rac',
        ]);

        $this->get('/c/rac/app')->assertOk();
        $this->get('/c/rac/app/payroll')->assertOk();
        $this->get('/c/rac/app/settings/security')->assertOk()
            ->assertSee('Recommended')
            ->assertSee('Enable Two-Step Verification')
            ->assertSee('Settings / Security');
        $this->get('/security/mfa/setup')->assertOk()->assertSee('Not now');
    }

    public function test_security_settings_require_active_tenant_membership(): void
    {
        $partner = $this->tenantPartner();
        $this->get('/c/rac/app/settings/security')->assertRedirect();
        $this->actingAs(User::factory()->create());
        $this->get('/c/rac/app/settings/security')->assertForbidden();
        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'NoAccess1234', 'code' => '123456',
        ])->assertForbidden();
    }

    public function test_enrolled_partner_cannot_bypass_mfa_for_security_settings(): void
    {
        $partner = $this->tenantPartner();
        DB::table('users')->where('id', $partner->id)->update([
            'mfa_secret' => Crypt::encryptString(Totp::generateSecret()),
            'mfa_confirmed_at' => now(),
        ]);
        $this->actingAs($partner);
        $this->get('/c/rac/app/settings/security')->assertRedirect('/security/mfa/challenge');
        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'ComplexPassword2026', 'code' => '123456',
        ])->assertRedirect('/security/mfa/challenge');
    }

    public function test_partner_can_disable_existing_mfa_only_with_password_and_fresh_code(): void
    {
        $partner = $this->tenantPartner();
        $secret = Totp::generateSecret();
        DB::table('users')->where('id', $partner->id)->update([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_confirmed_at' => now(),
            'mfa_recovery_codes' => json_encode([hash('sha256', 'SAFE2345CODE6789')]),
        ]);

        $this->actingAs($partner)->withSession([
            'erp_mfa_context' => 'firm', 'erp_mfa_firm_slug' => 'rac',
            'erp_mfa_verified_user_id' => $partner->id,
        ]);
        $this->get('/c/rac/app/settings/security')->assertOk()
            ->assertSee('Turn off two-step verification');

        $valid = Totp::code($secret, intdiv(time(), 30));
        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'incorrect-password', 'code' => $valid,
        ])->assertSessionHasErrors('code');
        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'ComplexPassword2026', 'code' => 'incorrect',
        ])->assertSessionHasErrors('code');
        $this->assertNotNull(DB::table('users')->where('id', $partner->id)->first()->mfa_confirmed_at);

        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'ComplexPassword2026', 'code' => $valid,
        ])->assertRedirect('/c/rac/app/settings/security');
        $record = DB::table('users')->where('id', $partner->id)->first();
        $this->assertNull($record->mfa_confirmed_at);
        $this->assertNull($record->mfa_secret);
        $this->assertNull($record->mfa_recovery_codes);
        $this->get('/c/rac/app')->assertOk();
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => DB::table('companies')->where('slug', 'rac')->value('id'),
            'action' => 'account.mfa.disabled',
        ]);
    }

    public function test_partner_can_disable_mfa_with_unused_recovery_code(): void
    {
        $partner = $this->tenantPartner();
        $secret = Totp::generateSecret();
        $code = 'BACKUP2345EXAMPLE';
        DB::table('users')->where('id', $partner->id)->update([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_confirmed_at' => now(),
            'mfa_last_step' => intdiv(time(), 30),
            'mfa_recovery_codes' => json_encode([hash('sha256', $code)]),
        ]);
        $this->actingAs($partner)->withSession(['erp_mfa_verified_user_id' => $partner->id]);

        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'ComplexPassword2026', 'code' => $code,
        ])->assertRedirect('/c/rac/app/settings/security');
        $this->assertNull(DB::table('users')->where('id', $partner->id)->first()->mfa_secret);
    }

    public function test_platform_operator_with_tenant_membership_cannot_disable_mfa(): void
    {
        $operator = $this->tenantPartner();
        $secret = Totp::generateSecret();
        DB::table('users')->where('id', $operator->id)->update([
            'is_platform_admin' => true,
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_confirmed_at' => now(),
        ]);
        $this->actingAs($operator)->withSession(['erp_mfa_verified_user_id' => $operator->id]);
        $this->post('/c/rac/app/settings/security/mfa/disable', [
            'password' => 'ComplexPassword2026',
            'code' => Totp::code($secret, intdiv(time(), 30)),
        ])->assertForbidden();
        $this->assertNotNull(DB::table('users')->where('id', $operator->id)->first()->mfa_confirmed_at);
    }

    public function test_mfa_setup_qr_is_self_hosted_and_private(): void
    {
        $operator = User::factory()->create(['is_platform_admin' => true]);
        $response = $this->actingAs($operator)->get('/security/mfa/setup');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('js/qrcode-generator.js')
            ->assertSee('data-otp-uri=', false)
            ->assertSee('otpauth://totp/', false)
            ->assertDontSee('api.qrserver.com')
            ->assertDontSee('chart.googleapis.com');

        $this->assertFileExists(public_path('js/qrcode-generator.js'));
        $this->assertSame(
            Totp::provisioningUri(session('erp_mfa_pending_secret'), $operator->email),
            $response->viewData('provisioningUri')
        );
    }

    public function test_enrollment_requires_correct_password_and_code_and_stores_encrypted_secret(): void
    {
        $partner = $this->tenantPartner();
        $this->actingAs($partner)->withSession([
            'erp_mfa_context'=>'firm', 'erp_mfa_firm_slug'=>'rac',
        ]);

        $this->get('/security/mfa/setup')->assertOk()->assertSee('Set up your authenticator');
        $secret = session('erp_mfa_pending_secret');
        $this->assertNotEmpty($secret);
        $code = Totp::code($secret, intdiv(time(), 30));

        $this->post('/security/mfa/setup', [
            'password'=>'wrong', 'code'=>$code,
        ])->assertSessionHasErrors('code');

        $this->assertNull(DB::table('users')->where('id', $partner->id)->first()->mfa_confirmed_at);

        $this->post('/security/mfa/setup', [
            'password'=>'ComplexPassword2026', 'code'=>$code,
        ])->assertRedirect('/security/mfa/recovery');

        $saved = DB::table('users')->where('id', $partner->id)->first();
        $this->assertNotEquals($secret, $saved->mfa_secret);
        $this->assertSame($secret, Crypt::decryptString($saved->mfa_secret));
        $this->assertCount(10, json_decode($saved->mfa_recovery_codes, true));

        $this->get('/security/mfa/recovery')
            ->assertOk()->assertSee('Save your recovery codes');

        $this->get('/c/rac/app')->assertOk();
    }

    public function test_enabled_partner_must_challenge_and_cannot_reuse_totp(): void
    {
        $partner = $this->tenantPartner();
        $secret = Totp::generateSecret();
        DB::table('users')->where('id', $partner->id)->update([
            'mfa_secret'=>Crypt::encryptString($secret),
            'mfa_confirmed_at'=>now(),
            'mfa_recovery_codes'=>'[]',
        ]);
        $partner->refresh();
        $this->actingAs($partner)->withSession([
            'erp_mfa_context'=>'firm', 'erp_mfa_firm_slug'=>'rac',
        ]);
        $this->get('/c/rac/app')->assertRedirect('/security/mfa/challenge');

        $this->post('/security/mfa/challenge', ['code'=>'00000'])
            ->assertSessionHasErrors('code');

        $code = Totp::code($secret, intdiv(time(), 30));
        $this->post('/security/mfa/challenge', ['code'=>$code])
            ->assertRedirect('/c/rac/app');

        $this->get('/c/rac/app')->assertOk();

        $this->withSession(['erp_mfa_verified_user_id'=>null]);
        $this->post('/security/mfa/challenge', ['code'=>$code])
            ->assertSessionHasErrors('code');
    }

    public function test_recovery_codes_work_once_after_password_factor(): void
    {
        $partner = $this->tenantPartner();
        $secret = Totp::generateSecret();
        $recovery = 'ABCD2345EFGH6789';
        DB::table('users')->where('id', $partner->id)->update([
            'mfa_secret'=>Crypt::encryptString($secret),
            'mfa_confirmed_at'=>now(),
            'mfa_recovery_codes'=>json_encode([hash('sha256', $recovery)]),
        ]);
        $partner->refresh();

        $this->actingAs($partner)->withSession([
            'erp_mfa_context'=>'firm','erp_mfa_firm_slug'=>'rac',
        ]);
        $this->post('/security/mfa/challenge', ['code'=>$recovery])
            ->assertRedirect('/c/rac/app');

        $this->assertSame('[]', DB::table('users')->where('id', $partner->id)->first()->mfa_recovery_codes);
        $this->withSession(['erp_mfa_verified_user_id'=>null]);
        $this->post('/security/mfa/challenge', ['code'=>$recovery])
            ->assertSessionHasErrors('code');
    }

    public function test_platform_admin_cannot_create_company_until_mfa_completed(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin'=>true, 'password'=>Hash::make('ComplexPassword2026'),
        ]);
        $this->actingAs($admin);
        $this->get('/platform/app')->assertRedirect('/security/mfa/setup');
        $this->post('/platform/app/companies', [
            'company_name'=>'Unauthorized Example', 'company_slug'=>'unauthorized',
            'partner_name'=>'Example Partner', 'partner_email'=>'partner@example.test',
        ])->assertRedirect('/security/mfa/setup');
        $this->assertDatabaseCount('companies', 0);
    }
}
