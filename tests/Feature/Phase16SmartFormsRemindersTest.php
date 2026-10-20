<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase16SmartFormsRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug = 'smart-forms'): array
    {
        $company = DB::table('companies')->insertGetId([
            'name' => 'Isolated Professional Firm', 'slug' => $slug,
            'timezone' => 'Asia/Dhaka', 'active' => true,
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ws = DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'name' => 'Confidential Partner Practice',
            'slug' => 'partner', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $people = [];
        foreach (['partner', 'manager', 'student'] as $role) {
            $user = User::factory()->create();
            $id = DB::table('memberships')->insertGetId([
                'company_id' => $company, 'workspace_id' => $ws,
                'user_id' => $user->id, 'role' => $role,
                'active' => true, 'monthly_allowance' => '5000.00',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $people[$role] = (object) ['id' => $id, 'user' => $user];
        }
        DB::table('memberships')->where('id', $people['student']->id)
            ->update(['manager_membership_id' => $people['manager']->id]);
        return compact('company', 'ws', 'slug', 'people');
    }

    private function form(array $f, array $fields = []): array
    {
        return [
            'form_key' => 'client_visit_report',
            'title' => 'Client Visit Documentation',
            'description' => 'Staff report audit site visit notes.',
            'fields' => json_encode($fields ?: [
                ['key' => 'visited_on', 'label' => 'Date of Visit', 'type' => 'date',
                    'required' => true, 'options' => []],
                ['key' => 'finding', 'label' => 'Fieldwork Notes', 'type' => 'text',
                    'required' => true, 'options' => []],
                ['key' => 'outcome', 'label' => 'Outcome', 'type' => 'select',
                    'required' => false, 'options' => ['Complete', 'Follow-up']],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function url(array $f, string $tail = 'forms'): string
    {
        return '/c/'.$f['slug'].'/app/'.$tail;
    }

    private function submit(array $f, int $id, string $finding = 'Verified client documents.')
    {
        return $this->post($this->url($f, 'forms/'.$id.'/submissions'), [
            'values' => [
                'visited_on' => now('Asia/Dhaka')->toDateString(),
                'finding' => $finding,
                'outcome' => 'Complete',
            ],
        ]);
    }

    public function test_form_version_is_immutable_and_only_partner_can_publish(): void
    {
        $f = $this->firm();
        $this->actingAs($f['people']['manager']->user)
            ->post($this->url($f), $this->form($f))->assertForbidden();
        $this->actingAs($f['people']['partner']->user)
            ->post($this->url($f), $this->form($f))
            ->assertRedirect()->assertSessionHasNoErrors();
        $first = DB::table('workspace_form_templates')->firstOrFail();
        $this->assertSame(1, (int) $first->version);
        $this->actingAs($f['people']['student']->user)
            ->submit($f, $first->id)->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($f['people']['partner']->user)
            ->post($this->url($f), array_merge($this->form($f), [
                'title' => 'Client Visit Documentation Updated',
            ]))->assertRedirect()->assertSessionHasNoErrors();
        $new = DB::table('workspace_form_templates')->orderByDesc('id')->firstOrFail();
        $this->assertSame(2, (int) $new->version);
        $this->assertSame('Client Visit Documentation', $first->title);
        $this->assertDatabaseHas('workspace_form_submissions', [
            'template_id' => $first->id,
            'membership_id' => $f['people']['student']->id,
        ]);
        $this->actingAs($f['people']['student']->user)
            ->submit($f, $first->id)->assertSessionHasErrors('values');
        $this->assertDatabaseCount('workspace_form_submissions', 1);
        $this->get($this->url($f))->assertOk()
            ->assertSeeText('Client Visit Documentation Updated')
            ->assertSeeText('My Recent Responses');
    }

    public function test_strict_field_schema_and_extra_response_data_are_rejected(): void
    {
        $f = $this->firm();
        $this->actingAs($f['people']['partner']->user);
        $duplicate = [
            ['key' => 'duplicate', 'label' => 'One Field', 'type' => 'text',
                'required' => true, 'options' => []],
            ['key' => 'duplicate', 'label' => 'Another Field', 'type' => 'text',
                'required' => true, 'options' => []],
        ];
        $this->post($this->url($f), $this->form($f, $duplicate))
            ->assertSessionHasErrors('fields');
        $malicious = [
            ['key' => 'code', 'label' => 'Custom Code', 'type' => 'php',
                'required' => true, 'options' => []],
        ];
        $this->post($this->url($f), $this->form($f, $malicious))
            ->assertSessionHasErrors('fields');
        $this->assertDatabaseCount('workspace_form_templates', 0);

        $this->post($this->url($f), $this->form($f))->assertRedirect();
        $id = (int) DB::table('workspace_form_templates')->value('id');
        $this->actingAs($f['people']['student']->user)
            ->post($this->url($f, 'forms/'.$id.'/submissions'), [
                'values' => ['visited_on' => '2026-10-20',
                    'finding' => 'Valid visit', 'outcome' => 'Complete',
                    'hidden_admin_flag' => 'yes'],
            ])->assertSessionHasErrors('values');
        $this->post($this->url($f, 'forms/'.$id.'/submissions'), [
            'values' => ['visited_on' => '2026-10-20',
                'finding' => 'Valid visit', 'outcome' => 'Unlisted'],
        ])->assertSessionHasErrors('values.outcome');
        $this->assertDatabaseCount('workspace_form_submissions', 0);
    }

    public function test_partner_export_escapes_spreadsheet_formula_and_staff_cannot_export(): void
    {
        $f = $this->firm();
        $this->actingAs($f['people']['partner']->user)
            ->post($this->url($f), $this->form($f))->assertRedirect();
        $id = (int) DB::table('workspace_form_templates')->value('id');
        $this->actingAs($f['people']['student']->user)
            ->submit($f, $id, '=HYPERLINK("https://example.invalid")')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get($this->url($f, 'forms/'.$id.'/export'))->assertForbidden();

        $export = $this->actingAs($f['people']['partner']->user)
            ->get($this->url($f, 'forms/'.$id.'/export'));
        $export->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Date of Visit', $csv);
        $this->assertDatabaseHas('tenant_audit_events', [
            'entity_type' => 'workspace_form_template',
            'action' => 'forms.exported',
        ]);
    }

    public function test_cross_company_form_ids_and_results_are_never_accessible(): void
    {
        $a = $this->firm('forms-company-a');
        $b = $this->firm('forms-company-b');
        $this->actingAs($a['people']['partner']->user)
            ->post($this->url($a), $this->form($a))->assertRedirect();
        $id = (int) DB::table('workspace_form_templates')->value('id');
        $this->actingAs($a['people']['student']->user)
            ->submit($a, $id)->assertRedirect();
        $this->actingAs($b['people']['partner']->user)
            ->get($this->url($b))->assertOk()
            ->assertDontSeeText('Client Visit Documentation');
        $this->get($this->url($b, 'forms/'.$id.'/export'))->assertNotFound();
        $this->actingAs($b['people']['student']->user)
            ->submit($b, $id)->assertNotFound();
    }

    private function milestone(array $f, string $due): int
    {
        $client = DB::table('audit_clients')->insertGetId([
            'company_id' => $f['company'], 'workspace_id' => $f['ws'],
            'code' => 'C'.$f['company'], 'name' => 'Internal Test Client',
            'active' => true, 'created_by' => $f['people']['partner']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $eng = DB::table('audit_engagements')->insertGetId([
            'company_id' => $f['company'], 'workspace_id' => $f['ws'],
            'audit_client_id' => $client, 'code' => 'A'.$f['company'],
            'title' => 'Audit Engagement', 'engagement_type' => 'statutory',
            'starts_on' => now('Asia/Dhaka')->subMonth()->toDateString(),
            'ends_on' => now('Asia/Dhaka')->addMonth()->toDateString(),
            'budget_hours' => '12.00', 'status' => 'open',
            'created_by' => $f['people']['partner']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('audit_engagement_milestones')->insertGetId([
            'company_id' => $f['company'], 'workspace_id' => $f['ws'],
            'engagement_id' => $eng,
            'membership_id' => $f['people']['student']->id,
            'created_by' => $f['people']['partner']->id,
            'title' => 'Deliver audit file', 'due_on' => $due,
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_opt_in_reminders_are_exactly_once_and_private_even_on_retries(): void
    {
        $f = $this->firm('reminders-company');
        $due = now('Asia/Dhaka')->addDay()->toDateString();
        $id = $this->milestone($f, $due);
        Artisan::call('erp:milestone-reminders');
        $this->assertDatabaseCount('tenant_notifications', 0);
        $this->assertDatabaseCount('workspace_reminder_deliveries', 0);

        $this->actingAs($f['people']['manager']->user)
            ->post($this->url($f, 'settings/reminder-rules'), [
                'active' => 1, 'days_before' => 3,
                'reason' => 'Team needs advanced audit milestone deadline notifications.',
            ])->assertForbidden();
        $this->actingAs($f['people']['partner']->user)
            ->post($this->url($f, 'settings/reminder-rules'), [
                'active' => 1, 'days_before' => 3,
                'reason' => 'Notify staff of upcoming audit file submission deadlines.',
            ])->assertRedirect()->assertSessionHasNoErrors();
        Artisan::call('erp:milestone-reminders', ['--dry-run' => true]);
        $this->assertDatabaseCount('workspace_reminder_deliveries', 0);
        Artisan::call('erp:milestone-reminders');
        Artisan::call('erp:milestone-reminders');
        $this->assertDatabaseCount('workspace_reminder_deliveries', 1);
        $this->assertDatabaseHas('tenant_notifications', [
            'company_id' => $f['company'], 'workspace_id' => $f['ws'],
            'recipient_membership_id' => $f['people']['student']->id,
            'title' => 'Engagement milestone deadline approaching',
        ]);

        $this->travel(3)->days();
        Artisan::call('erp:milestone-reminders');
        Artisan::call('erp:milestone-reminders');
        $this->assertDatabaseCount('workspace_reminder_deliveries', 2);
        $this->assertDatabaseHas('workspace_reminder_deliveries', [
            'milestone_id' => $id, 'kind' => 'overdue', 'due_on' => $due,
        ]);
        $this->assertDatabaseCount('tenant_notifications', 2);
    }

    public function test_reminders_never_send_to_other_firms_or_inactive_workers(): void
    {
        $a = $this->firm('reminders-a');
        $b = $this->firm('reminders-b');
        $due = now('Asia/Dhaka')->addDay()->toDateString();
        $this->milestone($a, $due);
        $this->milestone($b, $due);
        $this->actingAs($a['people']['partner']->user)
            ->post($this->url($a, 'settings/reminder-rules'), [
                'active' => 1, 'days_before' => 3,
                'reason' => 'Notify active fieldwork staff within this workspace.',
            ])->assertRedirect();
        Artisan::call('erp:milestone-reminders');
        $this->assertDatabaseCount('tenant_notifications', 1);
        $this->assertDatabaseMissing('tenant_notifications', [
            'recipient_membership_id' => $b['people']['student']->id,
        ]);
        DB::table('memberships')->where('id', $a['people']['student']->id)
            ->update(['active' => false]);
        $this->travel(3)->days();
        Artisan::call('erp:milestone-reminders');
        $this->assertDatabaseCount('tenant_notifications', 1);
        $this->actingAs($b['people']['partner']->user)
            ->get($this->url($b, 'settings/reminder-rules'))->assertOk()
            ->assertDontSeeText('Notify active fieldwork staff');
    }
}
