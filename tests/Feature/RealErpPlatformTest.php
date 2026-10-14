<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RealErpPlatformTest extends TestCase
{
    use RefreshDatabase;

    private function platformOwner(): User
    {
        $user = User::factory()->create(['password'=>Hash::make('PlatformSecret42Strong')]);
        $user->forceFill(['is_platform_admin'=>true])->save();
        return $user;
    }

    public function test_only_explicit_platform_owner_can_access_central_operations(): void
    {
        $normal = User::factory()->create();
        $this->actingAs($normal)->get('/platform/app')->assertForbidden();
        $this->actingAs($normal)->post('/platform/app/companies', [
            'company_slug'=>'test-firm', 'company_name'=>'Test Chartered Accountants',
            'partner_name'=>'Sample Partner', 'partner_email'=>'sample@example.test',
        ])->assertForbidden();

        $owner = $this->platformOwner();
        $this->actingAs($owner)->get('/platform/app')->assertOk()
            ->assertSee('Organization Management')
            ->assertSee('does not automatically join a company');
    }

    public function test_platform_login_rejects_non_owner_credentials(): void
    {
        $normal = User::factory()->create(['password'=>Hash::make('SomeStrongPassword42')]);
        $this->post('/platform/app/login', [
            'email'=>$normal->email, 'password'=>'SomeStrongPassword42',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_platform_can_create_isolated_company_with_invited_inactive_partner(): void
    {
        Mail::fake();
        $owner = $this->platformOwner();
        $this->actingAs($owner)->post('/platform/app/companies', [
            'company_slug'=>'firm-alpha',
            'company_name'=>'Firm Alpha Chartered Accountants',
            'partner_name'=>'First Partner User',
            'partner_email'=>'firstpartner@example.test',
        ])->assertRedirect();

        $firm = DB::table('companies')->where('slug','firm-alpha')->firstOrFail();
        $workspace = DB::table('workspaces')->where('company_id',$firm->id)->firstOrFail();
        $invitedUser = User::where('email','firstpartner@example.test')->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'company_id'=>$firm->id, 'workspace_id'=>$workspace->id,
            'user_id'=>$invitedUser->id, 'role'=>'partner', 'active'=>0,
        ]);
        $this->assertDatabaseCount('attendance_sessions',0);
        $this->assertDatabaseCount('leave_requests',0);
        $this->assertDatabaseCount('conveyance_claims',0);

        $this->get('/c/firm-alpha/app')->assertForbidden();
    }

    public function test_platform_can_add_partner_without_access_to_their_private_records(): void
    {
        Mail::fake();
        $owner=$this->platformOwner();

        $companyId=DB::table('companies')->insertGetId([
            'slug'=>'rac','name'=>'Rahman Anis & Co.','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($owner)->post("/platform/app/companies/$companyId/partners", [
            'workspace_slug'=>'partner-02','workspace_name'=>'Partner Unit 02',
            'partner_email'=>'secondpartner@example.test','partner_name'=>'Second Partner User',
        ])->assertRedirect();

        $this->assertDatabaseHas('workspaces',[
            'company_id'=>$companyId,'slug'=>'partner-02',
        ]);
        $this->assertDatabaseHas('memberships',[
            'company_id'=>$companyId,'role'=>'partner','active'=>0,
        ]);

        $this->get('/c/rac/app/attendance')->assertForbidden();
        $this->get('/platform/app')->assertOk()->assertDontSee('Private attendance document');
    }

    public function test_platform_dashboard_does_not_show_raw_tenant_financial_records(): void
    {
        $owner = $this->platformOwner();
        $this->actingAs($owner)->get('/platform/app')
            ->assertOk()
            ->assertDontSee('Monthly Gross Payroll')
            ->assertDontSee('Student Salary Register')
            ->assertDontSee('Payroll Statement');
    }
}