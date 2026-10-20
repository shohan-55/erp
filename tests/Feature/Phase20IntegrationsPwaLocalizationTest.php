<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class Phase20IntegrationsPwaLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
        config()->set('app.url','http://localhost');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function firm(string $slug='phase20-a'):array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'Phase 20 Firm '.$slug,'slug'=>$slug,'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'name'=>'One Partner Practice','slug'=>'one',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach(['partner','student'] as $role) {
            $user=User::factory()->create(['password'=>Hash::make('StrongIntegrationPassword42!')]);
            $member=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,'user_id'=>$user->id,
                'role'=>$role,'active'=>true,'monthly_allowance'=>'1000.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[$role]=(object)['user'=>$user,'id'=>$member];
        }
        return compact('company','workspace','slug','people');
    }

    private function makeToken(array $firm,string $role='student',array $scopes=['attendance:read','leave:read','notifications:read']):string
    {
        $this->actingAs($firm['people'][$role]->user);
        $r=$this->post("/c/{$firm['slug']}/app/settings/integrations/tokens",[
            'name'=>'Private Reporting Connector','password'=>'StrongIntegrationPassword42!',
            'days'=>7,'scopes'=>$scopes,
        ]);
        $r->assertRedirect()->assertSessionHasNoErrors();
        $value=(string)session('integration_token_once');
        $this->assertMatchesRegularExpression('/^dw20_[a-f0-9]{64}$/D',$value);
        $this->assertDatabaseHas('erp_integration_tokens',[
            'company_id'=>$firm['company'],'workspace_id'=>$firm['workspace'],
            'membership_id'=>$firm['people'][$role]->id,
            'token_hash'=>hash('sha256',$value),
        ]);
        $this->assertDatabaseMissing('erp_integration_tokens',['token_hash'=>$value]);
        return $value;
    }

    private function api(array $firm,string $endpoint):string
    {
        return "/api/v1/companies/{$firm['slug']}/$endpoint";
    }

    private function ownFixture(array $firm,string $text):void
    {
        $student=$firm['people']['student']->id;
        DB::table('attendance_sessions')->insert([
            'company_id'=>$firm['company'],'workspace_id'=>$firm['workspace'],
            'membership_id'=>$student,'work_date'=>now()->toDateString(),
            'work_mode'=>'office','started_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('leave_requests')->insert([
            'company_id'=>$firm['company'],'workspace_id'=>$firm['workspace'],
            'membership_id'=>$student,'date_from'=>now()->toDateString(),
            'date_to'=>now()->toDateString(),'leave_type'=>'casual',
            'reason'=>$text,'status'=>'pending',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('tenant_notifications')->insert([
            'company_id'=>$firm['company'],'workspace_id'=>$firm['workspace'],
            'recipient_membership_id'=>$student,'title'=>$text,'message'=>$text,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_readonly_api_tokens_are_hashed_scoped_private_and_work_without_browser_session():void
    {
        $a=$this->firm('phase20-alpha');
        $b=$this->firm('phase20-beta');
        $this->ownFixture($a,'Tenant Alpha Private Info');
        $this->ownFixture($b,'Tenant Beta Secret');
        $token=$this->makeToken($a);
        auth()->logout();
        $this->getJson($this->api($a,'attendance'))->assertUnauthorized();
        $this->withToken($token)->getJson($this->api($a,'me'))
            ->assertOk()->assertJsonPath('data.workspace_id',$a['workspace'])
            ->assertJsonPath('data.role','student');
        $this->withToken($token)->getJson($this->api($a,'attendance'))
            ->assertOk()->assertJsonCount(1,'data');
        $this->withToken($token)->getJson($this->api($a,'leaves'))
            ->assertOk()->assertSeeText('pending')->assertDontSeeText('Tenant Beta Secret');
        $this->withToken($token)->getJson($this->api($a,'notifications'))
            ->assertOk()->assertSeeText('Tenant Alpha Private Info')
            ->assertDontSeeText('Tenant Beta Secret');
        $this->withToken($token)->getJson($this->api($b,'notifications'))->assertUnauthorized();
        $this->withToken($token)->postJson($this->api($a,'leaves'),[
            'status'=>'approved',
        ])->assertStatus(405);
        $this->assertDatabaseHas('leave_requests',[
            'company_id'=>$a['company'],'membership_id'=>$a['people']['student']->id,'status'=>'pending',
        ]);
    }

    public function test_scope_feature_revocation_and_expiry_fail_closed():void
    {
        $f=$this->firm();
        $this->ownFixture($f,'Classified Work');
        $token=$this->makeToken($f,'student',['attendance:read']);
        $this->withToken($token)->getJson($this->api($f,'leaves'))->assertForbidden();
        $this->withToken($token)->getJson($this->api($f,'attendance'))->assertOk();
        DB::table('erp_feature_controls')->insert([
            'company_id'=>$f['company'],'scope_key'=>'user:'.$f['people']['student']->id,
            'feature_key'=>'attendance','enabled'=>false,
            'reason'=>'Partner has restricted attendance API access.',
            'updated_by'=>$f['people']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->withToken($token)->getJson($this->api($f,'attendance'))->assertForbidden();
        DB::table('erp_feature_controls')->delete();
        DB::table('erp_integration_tokens')->where('company_id',$f['company'])
            ->update(['expires_at'=>now()->subMinute()]);
        $this->withToken($token)->getJson($this->api($f,'me'))->assertUnauthorized();
        DB::table('erp_integration_tokens')->where('company_id',$f['company'])
            ->update(['expires_at'=>now()->addDay(),'revoked_at'=>now()]);
        $this->withToken($token)->getJson($this->api($f,'me'))->assertUnauthorized();
    }

    public function test_suspension_and_deactivated_membership_invalidate_tokens_immediately():void
    {
        $f=$this->firm();
        $token=$this->makeToken($f);
        $this->withToken($token)->getJson($this->api($f,'me'))->assertOk();
        DB::table('memberships')->where('id',$f['people']['student']->id)
            ->update(['platform_suspended_at'=>now()]);
        $this->withToken($token)->getJson($this->api($f,'me'))->assertUnauthorized();
        DB::table('memberships')->where('id',$f['people']['student']->id)
            ->update(['platform_suspended_at'=>null,'active'=>false]);
        $this->withToken($token)->getJson($this->api($f,'me'))->assertUnauthorized();
        DB::table('memberships')->where('id',$f['people']['student']->id)->update(['active'=>true]);
        DB::table('companies')->where('id',$f['company'])->update(['active'=>false]);
        $this->withToken($token)->getJson($this->api($f,'me'))->assertUnauthorized();
    }

    public function test_tokens_are_personal_revocable_password_confirmed_and_limited():void
    {
        $f=$this->firm();
        $token=$this->makeToken($f);
        $id=(int)DB::table('erp_integration_tokens')->value('id');
        $this->actingAs($f['people']['partner']->user)
            ->post("/c/{$f['slug']}/app/settings/integrations/tokens/$id/revoke",[
                'password'=>'StrongIntegrationPassword42!',
            ])->assertNotFound();
        $this->actingAs($f['people']['student']->user)
            ->post("/c/{$f['slug']}/app/settings/integrations/tokens/$id/revoke",[
                'password'=>'not-the-password',
            ])->assertSessionHasErrors('password');
        $this->post("/c/{$f['slug']}/app/settings/integrations/tokens/$id/revoke",[
            'password'=>'StrongIntegrationPassword42!',
        ])->assertRedirect();
        auth()->logout();
        $this->withToken($token)->getJson($this->api($f,'me'))->assertUnauthorized();
        $this->assertDatabaseHas('tenant_audit_events',[
            'company_id'=>$f['company'],'action'=>'integration.token.revoked',
            'entity_id'=>$id,
        ]);
    }

    public function test_bangla_english_session_locale_and_private_manifest():void
    {
        $f=$this->firm();
        $this->actingAs($f['people']['student']->user);
        $url="/c/{$f['slug']}/app/settings/integrations";
        $this->get($url)->assertOk()->assertSeeText('Integrations & Mobile');
        $this->post("$url/locale",['language'=>'bn'])->assertRedirect();
        $this->get($url)->assertOk()->assertSeeText('ইন্টিগ্রেশন ও মোবাইল');
        $this->post("$url/locale",['language'=>'fr'])->assertSessionHasErrors('language');
        $this->get("$url/manifest.webmanifest")->assertOk()
            ->assertJsonPath('start_url',"/c/{$f['slug']}/app")
            ->assertJsonPath('display','standalone')
            ->assertDontSee('Tenant Beta Secret');
        $this->get('/sw-erp.js')->assertOk()->assertSee('erp-offline-shell-v1');
        $this->get('/offline-erp.html')->assertOk()->assertSee('offline',false);
    }

    public function test_date_range_limits_and_other_member_data_never_leak():void
    {
        $f=$this->firm();
        $this->ownFixture($f,'Student Only Confidential');
        $token=$this->makeToken($f,'partner',['leave:read','notifications:read']);
        $this->withToken($token)->getJson($this->api($f,'leaves'))
            ->assertOk()->assertJsonCount(0,'data');
        $this->withToken($token)->getJson($this->api($f,'notifications'))
            ->assertOk()->assertJsonCount(0,'data');
        $this->withToken($token)->getJson($this->api($f,'leaves').'?from=2026-01-01&to=2026-12-31')
            ->assertUnprocessable();
        $this->withToken($token)->getJson($this->api($f,'notifications').'?limit=1000')
            ->assertUnprocessable();
    }
}
