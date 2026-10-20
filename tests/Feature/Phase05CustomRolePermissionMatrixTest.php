<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkspaceAccess;
use App\Support\WorkspaceRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase05CustomRolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => ucfirst($slug).' Professional Services',
            'active' => true, 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => 'practice', 'name' => 'Practice',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $members = [];
        foreach (['partner', 'manager', 'manager2', 'student'] as $label) {
            $u = User::factory()->create(['name' => $slug.' '.$label]);
            $member = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $workspace,
                'user_id' => $u->id, 'role' => $label === 'manager2' ? 'manager' : $label,
                'active' => true, 'monthly_allowance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $members[$label] = (object)['user' => $u, 'id' => $member];
        }

        return compact('company', 'workspace', 'members');
    }

    private function role(array $t, array $permissions): int
    {
        return DB::table('workspace_custom_roles')->insertGetId([
            'company_id' => $t['company'], 'workspace_id' => $t['workspace'],
            'slug' => 'operations', 'name' => 'Operations Manager',
            'description' => 'Scoped operations role.',
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'active' => true, 'created_by' => $t['members']['partner']->id,
            'updated_by' => $t['members']['partner']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createRole(string $slug, array $permissions)
    {
        return [
            'name' => 'Operations Role', 'slug' => $slug,
            'description' => 'Explicitly scoped role',
            'permissions' => $permissions,
            'reason' => 'Required for audited workspace delegation.',
        ];
    }

    public function test_partner_can_create_a_custom_role_assign_it_and_permissions_apply_to_real_routes(): void
    {
        $t = $this->tenant('role-firm');
        $prefix = '/c/role-firm/app';
        $partner = $t['members']['partner'];
        $manager = $t['members']['manager'];

        $this->actingAs($partner->user)->get("$prefix/settings/roles")->assertOk()
            ->assertSeeText('Custom Role & Permission Studio');
        $this->post("$prefix/settings/roles", $this->createRole('operations', [
            'organization.view', 'attendance.propose',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $role = DB::table('workspace_custom_roles')->where('slug', 'operations')->firstOrFail();
        $this->assertDatabaseHas('tenant_audit_events', [
            'workspace_id' => $t['workspace'], 'action' => 'custom_role.created',
            'entity_id' => $role->id,
        ]);
        $this->post("$prefix/settings/roles/assign", [
            'membership_id' => $manager->id, 'role_id' => $role->id,
            'reason' => 'Manager now handles organizational reporting.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($manager->user)
            ->get("$prefix/organization/units")->assertOk();
        $this->post("$prefix/organization/units", [
            'name' => 'Accounts Department', 'slug' => 'accounts-dept', 'kind' => 'department',
        ])->assertForbidden();

        $actor = DB::table('memberships')->where('id', $manager->id)->firstOrFail();
        $this->assertTrue(WorkspaceAccess::allows($actor, 'attendance.propose'));
        $this->assertFalse(WorkspaceRoles::allows($actor, 'payroll.manage'));
        $this->assertFalse(WorkspaceRoles::allows($actor, 'organization.manage'));
        $this->assertDatabaseHas('memberships', ['id' => $manager->id, 'role' => 'manager']);
    }

    public function test_permission_edits_disable_and_revoke_effective_access_immediately(): void
    {
        $t = $this->tenant('revoke-firm');
        $path = '/c/revoke-firm/app';
        $partner = $t['members']['partner'];
        $manager = $t['members']['manager'];
        $this->actingAs($partner->user)->post("$path/settings/roles",
            $this->createRole('operations', ['organization.manage'])
        )->assertRedirect();
        $role = DB::table('workspace_custom_roles')->where('slug', 'operations')->firstOrFail();
        $this->post("$path/settings/roles/assign", [
            'membership_id' => $manager->id, 'role_id' => $role->id,
            'reason' => 'Manager handles local organization structure.',
        ])->assertRedirect();

        $this->actingAs($manager->user)->post("$path/organization/units", [
            'name' => 'Managed Department', 'slug' => 'managed-dept', 'kind' => 'department',
        ])->assertRedirect();
        $this->assertDatabaseHas('organization_units', [
            'company_id' => $t['company'], 'workspace_id' => $t['workspace'],
            'slug' => 'managed-dept',
        ]);

        $this->actingAs($partner->user)->post("$path/settings/roles/{$role->id}", [
            ...$this->createRole('operations', ['organization.view']),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager->user)->post("$path/organization/units", [
            'name' => 'Another Department', 'slug' => 'another-dept', 'kind' => 'department',
        ])->assertForbidden();
        $this->get("$path/organization/units")->assertOk();

        $this->actingAs($partner->user)->post("$path/settings/roles/{$role->id}/status", [
            'active' => false, 'reason' => 'Permissions removed at the end of the audit.',
        ])->assertRedirect();
        $this->actingAs($manager->user)->get("$path/organization/units")->assertForbidden();

        $this->actingAs($partner->user)->post("$path/settings/roles/assign", [
            'membership_id' => $manager->id, 'role_id' => '',
            'reason' => 'Remove access after completion of assigned work.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('workspace_role_assignments', [
            'membership_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'workspace_id' => $t['workspace'], 'action' => 'custom_role.assignment.changed',
            'entity_id' => $manager->id,
        ]);
    }

    public function test_manager_cannot_create_role_assign_self_or_reach_other_workspace_and_student_cannot_be_promoted(): void
    {
        $a = $this->tenant('first-workspace');
        $b = $this->tenant('second-workspace');
        $path = '/c/first-workspace/app';
        $manager = $a['members']['manager'];

        $this->actingAs($manager->user)->get("$path/settings/roles")->assertForbidden();
        $this->post("$path/settings/roles", $this->createRole('privileged', [
            'organization.manage',
        ]))->assertForbidden();
        $this->post("$path/settings/roles/assign", [
            'membership_id' => $manager->id, 'role_id' => 1,
            'reason' => 'This must not let managers escalate.',
        ])->assertForbidden();

        $this->actingAs($a['members']['partner']->user)
            ->post("$path/settings/roles", $this->createRole('operations', [
                'organization.manage',
            ]))->assertRedirect();
        $role = DB::table('workspace_custom_roles')->where('company_id', $a['company'])->firstOrFail();

        $this->post("$path/settings/roles/assign", [
            'membership_id' => $a['members']['student']->id,
            'role_id' => $role->id, 'reason' => 'Students must not be promoted.',
        ])->assertNotFound();
        $this->post("$path/settings/roles/assign", [
            'membership_id' => $b['members']['manager']->id,
            'role_id' => $role->id, 'reason' => 'Cross company grants must fail.',
        ])->assertNotFound();

        $this->actingAs($b['members']['partner']->user)
            ->get('/c/second-workspace/app/settings/roles')
            ->assertOk()->assertDontSee('Operations Role');
        $this->post('/c/second-workspace/app/settings/roles/assign', [
            'membership_id' => $b['members']['manager']->id,
            'role_id' => $role->id, 'reason' => 'Cross company role injection is blocked.',
        ])->assertNotFound();
        $this->assertDatabaseCount('workspace_role_assignments', 0);
    }

    public function test_invalid_permission_names_duplicate_slugs_and_partner_role_invariants(): void
    {
        $t = $this->tenant('policy-firm');
        $path = '/c/policy-firm/app';
        $this->actingAs($t['members']['partner']->user)->post("$path/settings/roles",
            $this->createRole('custom-one', ['payroll.manage', 'platform.admin'])
        )->assertSessionHasErrors('permissions.0');

        $this->post("$path/settings/roles",
            $this->createRole('custom-one', ['organization.view'])
        )->assertRedirect();
        $this->post("$path/settings/roles",
            $this->createRole('custom-one', ['organization.manage'])
        )->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('workspace_custom_roles', 1);

        $partner = DB::table('memberships')->where('id', $t['members']['partner']->id)->firstOrFail();
        $student = DB::table('memberships')->where('id', $t['members']['student']->id)->firstOrFail();
        $this->assertTrue(WorkspaceRoles::allows($partner, 'organization.manage'));
        $this->assertFalse(WorkspaceRoles::allows($student, 'organization.view'));
        $this->assertFalse(WorkspaceRoles::allows($student, 'attendance.propose'));
        $this->assertFalse(WorkspaceRoles::allows($partner, 'platform.admin'));
    }
}
