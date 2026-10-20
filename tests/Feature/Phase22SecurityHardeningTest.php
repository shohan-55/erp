<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class Phase22SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function company(string $slug='secure-phase22',?User $user=null):array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Security Sample '.$slug,
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'partner-one','name'=>'Partner One',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $user=$user??User::factory()->create([
            'password'=>Hash::make('ComplexSecurityPassword2026!'),
        ]);
        $member=DB::table('memberships')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,'user_id'=>$user->id,
            'role'=>'partner','active'=>true,'monthly_allowance'=>'0.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return compact('company','workspace','member','user','slug');
    }

    private function apiToken(array $firm,string $raw):int
    {
        return DB::table('erp_integration_tokens')->insertGetId([
            'company_id'=>$firm['company'],'workspace_id'=>$firm['workspace'],
            'membership_id'=>$firm['member'],'user_id'=>$firm['user']->id,
            'name'=>'Sensitive Reporting Token','token_hash'=>hash('sha256',$raw),
            'scopes'=>'["attendance:read"]','expires_at'=>now()->addWeek(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_password_reset_revokes_api_credentials_across_all_companies_and_sessions():void
    {
        $user=User::factory()->create([
            'password'=>Hash::make('ComplexSecurityPassword2026!'),
        ]);
        $a=$this->company('recovery-one',$user);
        $b=$this->company('recovery-two',$user);
        $this->apiToken($a,'dw20_'.str_repeat('a',64));
        $this->apiToken($b,'dw20_'.str_repeat('b',64));
        $token=Password::broker()->createToken($user);
        $this->post('/c/recovery-one/app/reset-password',[
            'token'=>$token,'email'=>$user->email,
            'password'=>'NewComplexSecurity2026!',
            'password_confirmation'=>'NewComplexSecurity2026!',
        ])->assertRedirect('/c/recovery-one/app/login')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewComplexSecurity2026!',$user->fresh()->password));
        $this->assertSame(2,DB::table('erp_integration_tokens')
            ->where('user_id',$user->id)->whereNotNull('revoked_at')->count());
        $this->assertDatabaseHas('erp_security_events',[
            'company_id'=>$a['company'],'user_id'=>$user->id,
            'kind'=>'account.password.reset','severity'=>'high',
        ]);
        $this->assertDatabaseHas('erp_security_events',[
            'user_id'=>$user->id,'kind'=>'integration.tokens.revoked',
        ]);
        $this->getJson('/api/v1/companies/recovery-two/me',[
            'Authorization'=>'Bearer dw20_'.str_repeat('b',64),
        ])->assertUnauthorized();
        $this->assertDatabaseHas('memberships',['id'=>$b['member'],'active'=>true]);
    }

    public function test_mfa_downgrade_revokes_all_existing_api_credentials_without_deleting_tenants():void
    {
        $a=$this->company('mfa-downgrade');
        $b=$this->company('mfa-other',$a['user']);
        $this->apiToken($a,'dw20_'.str_repeat('c',64));
        $this->apiToken($b,'dw20_'.str_repeat('d',64));
        $secret=Totp::generateSecret();
        DB::table('users')->where('id',$a['user']->id)->update([
            'mfa_secret'=>Crypt::encryptString($secret),
            'mfa_confirmed_at'=>now(),'mfa_last_step'=>null,
            'mfa_recovery_codes'=>'[]',
        ]);
        $this->actingAs($a['user'])->withSession([
            'erp_mfa_verified_user_id'=>$a['user']->id,
            'erp_mfa_context'=>'firm','erp_mfa_firm_slug'=>$a['slug'],
        ]);
        $code=Totp::code($secret,intdiv(time(),30));
        $this->post('/c/mfa-downgrade/app/settings/security/mfa/disable',[
            'password'=>'ComplexSecurityPassword2026!','code'=>$code,
        ])->assertRedirect('/c/mfa-downgrade/app/settings/security');
        $this->assertSame(2,DB::table('erp_integration_tokens')
            ->where('user_id',$a['user']->id)->whereNotNull('revoked_at')->count());
        $this->assertDatabaseHas('erp_security_events',[
            'kind'=>'account.mfa.disabled','severity'=>'high','user_id'=>$a['user']->id,
        ]);
        $this->assertDatabaseHas('tenant_audit_events',[
            'company_id'=>$a['company'],'action'=>'account.mfa.disabled',
        ]);
        $this->assertDatabaseHas('companies',['id'=>$b['company'],'active'=>true]);
        $this->assertNull(DB::table('users')->where('id',$a['user']->id)->value('mfa_secret'));
    }

    public function test_already_consumed_totp_cannot_be_replayed_to_disable_mfa():void
    {
        $firm=$this->company('mfa-replay');
        $secret=Totp::generateSecret();$step=intdiv(time(),30);
        DB::table('users')->where('id',$firm['user']->id)->update([
            'mfa_secret'=>Crypt::encryptString($secret),
            'mfa_confirmed_at'=>now(),'mfa_last_step'=>$step,
            'mfa_recovery_codes'=>'[]',
        ]);
        $this->actingAs($firm['user'])->withSession([
            'erp_mfa_verified_user_id'=>$firm['user']->id,
        ]);
        $this->post('/c/mfa-replay/app/settings/security/mfa/disable',[
            'password'=>'ComplexSecurityPassword2026!',
            'code'=>Totp::code($secret,$step),
        ])->assertSessionHasErrors('code');
        $this->assertNotNull(DB::table('users')->where('id',$firm['user']->id)->value('mfa_secret'));
        $this->assertDatabaseCount('erp_security_events',0);
    }

    public function test_login_monitoring_never_stores_email_password_token_or_raw_ip():void
    {
        $f=$this->company('login-monitoring');
        $this->post('/c/login-monitoring/app/login',[
            'email'=>$f['user']->email,'password'=>'NotCorrect!',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('erp_security_events',[
            'company_id'=>$f['company'],'kind'=>'login.denied','severity'=>'warning',
            'user_id'=>null,
        ]);
        $this->post('/c/login-monitoring/app/login',[
            'email'=>$f['user']->email,'password'=>'ComplexSecurityPassword2026!',
        ])->assertRedirect();
        $this->assertDatabaseHas('erp_security_events',[
            'company_id'=>$f['company'],'kind'=>'login.accepted','user_id'=>$f['user']->id,
        ]);
        $rows=DB::table('erp_security_events')->get()->toArray();
        $this->assertCount(2,$rows);
        foreach($rows as $row){
            $serialized=json_encode($row);
            $this->assertStringNotContainsString($f['user']->email,$serialized);
            $this->assertStringNotContainsString('NotCorrect!',$serialized);
            $this->assertStringNotContainsString('ComplexSecurityPassword2026!',$serialized);
            $this->assertStringNotContainsString('dw20_',$serialized);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$row->daily_ip_fingerprint);
        }
    }

    public function test_recent_security_activity_is_private_and_does_not_expose_other_tenants():void
    {
        $f=$this->company('visible-sec');
        $other=$this->company('hidden-sec');
        DB::table('erp_security_events')->insert([
            'company_id'=>$f['company'],'user_id'=>$f['user']->id,
            'kind'=>'integration.token.created','severity'=>'notice',
            'daily_ip_fingerprint'=>str_repeat('f',64),'created_at'=>now(),
        ]);
        DB::table('erp_security_events')->insert([
            'company_id'=>$other['company'],'user_id'=>$other['user']->id,
            'kind'=>'account.password.reset','severity'=>'high',
            'daily_ip_fingerprint'=>str_repeat('e',64),'created_at'=>now(),
        ]);
        $this->actingAs($f['user'])
            ->get('/c/visible-sec/app/settings/security')->assertOk()
            ->assertSeeText('Recent Account Security Activity')
            ->assertSeeText('integration · token · created')
            ->assertDontSeeText('account · password · reset')
            ->assertDontSee(str_repeat('f',64));
        $this->actingAs($other['user'])->get('/c/visible-sec/app/settings/security')
            ->assertForbidden();
    }

    public function test_security_response_headers_on_private_success_and_denied_requests():void
    {
        $f=$this->company('headers-sec');
        $response=$this->actingAs($f['user'])->get('/c/headers-sec/app/settings/security');
        $response->assertOk()->assertHeader('X-Content-Type-Options','nosniff')
            ->assertHeader('X-Frame-Options','DENY')
            ->assertHeader('Referrer-Policy','no-referrer');
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'none'",
            (string)$response->headers->get('Content-Security-Policy'));
        $denied=$this->get('/c/headers-sec/app/payroll');
        $this->assertStringContainsString('no-store',(string)$denied->headers->get('Cache-Control'));
    }

    public function test_security_reporting_is_bounded_aggregate_non_mutating_and_cli_only():void
    {
        $f=$this->company('cli-security');
        $other=$this->company('cli-other');
        foreach([[$f,'login.denied','warning'],[$f,'login.accepted','notice'],
            [$other,'account.password.reset','high']] as [$data,$kind,$severity]){
            DB::table('erp_security_events')->insert([
                'company_id'=>$data['company'],'user_id'=>null,
                'kind'=>$kind,'severity'=>$severity,
                'daily_ip_fingerprint'=>str_repeat('a',64),'created_at'=>now(),
            ]);
        }
        $before=DB::table('erp_security_events')->count();
        $this->assertSame(0,Artisan::call('erp:security:report',[
            '--company'=>(string)$f['company'],'--days'=>7,'--json'=>true,
        ]));
        $json=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame($f['company'],$json['company_id']);
        $this->assertCount(2,$json['counts']);
        $this->assertSame(2,array_sum(array_column($json['counts'],'count')));
        $this->assertStringNotContainsString(str_repeat('a',64),Artisan::output());
        $this->assertSame($before,DB::table('erp_security_events')->count());
        $this->assertSame(1,Artisan::call('erp:security:report',['--days'=>31]));
        $this->assertSame(1,Artisan::call('erp:security:report',['--company'=>'abc']));
    }
}
