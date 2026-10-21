<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase06TimedDelegationTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $slug): array
    {
        $company = DB::table('companies')->insertGetId([
            'name' => ucfirst($slug).' Company', 'slug' => $slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'practice',
            'name' => 'Main Practice', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $members = [];
        foreach (['partner', 'manager', 'manager2', 'student'] as $name) {
            $user = User::factory()->create();
            $id = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $workspace,
                'user_id' => $user->id,
                'role' => $name === 'manager2' ? 'manager' : $name,
                'active' => true, 'monthly_allowance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $members[$name] = (object) ['id' => $id, 'user' => $user];
        }

        return compact('company', 'workspace', 'members');
    }

    private function delegate(array $t, object $grantor, object $subject, array $changes = [])
    {
        return $this->actingAs($grantor->user)
            ->post('/c/'.DB::table('companies')->where('id', $t['company'])->value('slug')
                .'/app/settings/company-access', [
                'membership_id' => $subject->id,
                'access_role' => 'moderator',
                'delegation_mode' => 'limited',
                'permissions' => ['organization.view'],
                'reason' => 'Supervisor confirmed a delegated scoped assignment.',
                ...$changes,
            ]);
    }

    public function test_partner_can_grant_full_supported_operations_without_platform_or_payroll_powers(): void
    {
        $t = $this->group('timed-firm');
        $p = $t['members']['partner'];
        $m = $t['members']['manager'];
        $this->delegate($t, $p, $m, [
            'access_role' => 'company_admin',
            'delegation_mode' => 'full',
            'permissions' => ['access.delegate'],
            'expires_at' => now()->addDays(7)->format('Y-m-d\\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $grant = DB::table('workspace_access_grants')->where('membership_id', $m->id)->firstOrFail();
        $this->assertSame('full', $grant->delegation_mode);
        $actor = DB::table('memberships')->where('id', $m->id)->firstOrFail();
        $this->assertTrue(WorkspaceAccess::canManage($actor));
        $this->assertTrue(WorkspaceAccess::allows($actor, 'organization.manage'));
        $this->assertTrue(WorkspaceAccess::allows($actor, 'attendance.propose'));
        $this->assertFalse(WorkspaceAccess::allows($actor, 'payroll.manage'));
        $this->assertFalse(WorkspaceAccess::allows($actor, 'attendance.final'));
        $this->assertFalse(WorkspaceAccess::allows($actor, 'platform.admin'));

        $this->actingAs($m->user)->post('/c/timed-firm/app/organization/units', [
            'name' => 'Advisory Team', 'slug' => 'advisory-team', 'kind' => 'team',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('organization_units', [
            'company_id' => $t['company'], 'slug' => 'advisory-team',
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $t['company'], 'action' => 'workspace_access.granted',
        ]);
    }

    public function test_scheduled_start_expiry_and_revocation_take_effect_on_every_request(): void
    {
        $t = $this->group('expiry-firm');
        $p = $t['members']['partner'];
        $m = $t['members']['manager'];
        $this->delegate($t, $p, $m, [
            'permissions' => ['organization.manage'],
            'starts_at' => now()->addDay()->format('Y-m-d\\TH:i'),
            'expires_at' => now()->addDays(2)->format('Y-m-d\\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $url = '/c/expiry-firm/app/organization/units';
        $this->actingAs($m->user)->get($url)->assertForbidden();
        $original = now()->toDateTimeString();
        try {
            \Illuminate\Support\Carbon::setTestNow(
                \Illuminate\Support\Carbon::parse($original)->addHours(26)
            );
            $this->get($url)->assertOk();

            \Illuminate\Support\Carbon::setTestNow(
                \Illuminate\Support\Carbon::parse($original)->addHours(54)
            );
            $this->get($url)->assertForbidden();
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }

        $grant = DB::table('workspace_access_grants')->where('membership_id', $m->id)->firstOrFail();
        $this->actingAs($p->user)->post(
            "/c/expiry-firm/app/settings/company-access/{$grant->id}/revoke",
            ['reason' => 'Security team ended the temporary assignment.']
        )->assertRedirect();
        $this->actingAs($m->user)->get($url)->assertForbidden();
        $this->assertDatabaseHas('tenant_audit_events', [
            'action' => 'workspace_access.revoked', 'entity_id' => $grant->id,
        ]);
    }

    public function test_delegated_company_admin_cannot_escalate_or_prolong_child_and_parent_revoke_kills_child(): void
    {
        $t = $this->group('parent-firm');
        $p = $t['members']['partner'];
        $admin = $t['members']['manager'];
        $mod = $t['members']['manager2'];
        $this->delegate($t, $p, $admin, [
            'access_role' => 'company_admin',
            'permissions' => ['access.delegate', 'organization.view'],
            'expires_at' => now()->addDays(8)->format('Y-m-d\\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->delegate($t, $admin, $mod, [
            'access_role' => 'company_admin',
            'expires_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors('access_role');

        $this->delegate($t, $admin, $mod, [
            'delegation_mode' => 'full',
            'expires_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors('access_role');

        $this->delegate($t, $admin, $mod, [
            'permissions' => ['attendance.propose'],
            'expires_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors('permissions');

        $this->delegate($t, $admin, $mod, [
            'expires_at' => now()->addDays(45)->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors('expires_at');

        $this->delegate($t, $admin, $mod, [
            'expires_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $parent = DB::table('workspace_access_grants')->where('membership_id', $admin->id)->firstOrFail();
        $child = DB::table('workspace_access_grants')->where('membership_id', $mod->id)->firstOrFail();
        $this->assertSame((int) $parent->id, (int) $child->parent_grant_id);
        $this->actingAs($mod->user)->get('/c/parent-firm/app/organization/units')->assertOk();
        $this->actingAs($mod->user)->post('/c/parent-firm/app/organization/units', [
            'name' => 'Unauthorized Office', 'slug' => 'unauthorized-office', 'kind' => 'office',
        ])->assertForbidden();

        $this->actingAs($p->user)->post(
            "/c/parent-firm/app/settings/company-access/{$parent->id}/revoke",
            ['reason' => 'Entire delegated chain was recalled by the Partner.']
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('workspace_access_grants')->find($child->id)->revoked_at);
        $this->actingAs($mod->user)->get('/c/parent-firm/app/organization/units')->assertForbidden();
        $this->actingAs($admin->user)->get('/c/parent-firm/app/settings/company-access')->assertForbidden();
    }

    public function test_unauthorized_manager_cannot_give_grants_and_cross_tenant_target_is_rejected(): void
    {
        $a = $this->group('first-partner');
        $b = $this->group('second-partner');
        $this->delegate($a, $a['members']['manager'], $a['members']['manager2'])
            ->assertForbidden();
        $this->delegate($a, $a['members']['partner'], $b['members']['manager'], [
            'permissions' => ['organization.view'],
        ])->assertNotFound();
        $this->assertDatabaseCount('workspace_access_grants', 0);
    }

    public function test_moderator_cannot_receive_access_delegate_and_indefinite_child_grant_is_refused(): void
    {
        $t = $this->group('moderator-firm');
        $this->delegate($t, $t['members']['partner'], $t['members']['manager'], [
            'permissions' => ['access.delegate'],
        ])->assertSessionHasErrors('permissions');

        $this->delegate($t, $t['members']['partner'], $t['members']['manager'], [
            'access_role' => 'company_admin', 'permissions' => ['access.delegate', 'organization.view'],
        ])->assertRedirect();
        $this->delegate($t, $t['members']['manager'], $t['members']['manager2'])
            ->assertSessionHasErrors('expires_at');
    }
}
