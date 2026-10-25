<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayrollDisbursementEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function seedPayroll(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug'=>'rac', 'name'=>'Sample Practice', 'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00', 'office_end'=>'18:00:00',
            'grace_minutes'=>10, 'weekly_holidays'=>'[5,6]',
            'active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id'=>$company, 'slug'=>'partner-a', 'name'=>'Partner A',
            'active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $secondWorkspace = DB::table('workspaces')->insertGetId([
            'company_id'=>$company, 'slug'=>'partner-b', 'name'=>'Partner B',
            'active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
        ]);

        $people = [];
        foreach ([
            ['name'=>'partner', 'role'=>'partner', 'workspace'=>$workspace],
            ['name'=>'other_partner', 'role'=>'partner', 'workspace'=>$secondWorkspace],
            ['name'=>'student', 'role'=>'student', 'workspace'=>$workspace],
        ] as $p) {
            $user = User::factory()->create();
            $membership = DB::table('memberships')->insertGetId([
                'company_id'=>$company, 'workspace_id'=>$p['workspace'],
                'user_id'=>$user->id, 'role'=>$p['role'], 'active'=>true,
                'monthly_allowance'=>$p['role']==='student'?'8000.00':'0.00',
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            $people[$p['name']] = [$user, $membership];
        }

        $run = DB::table('payroll_runs')->insertGetId([
            'company_id'=>$company, 'workspace_id'=>$workspace,
            'period_month'=>'2026-09-01', 'status'=>'approved',
            'policy_snapshot'=>'{}', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id'=>$run,'membership_id'=>$people['student'][1],
            'base_allowance'=>'8000.00', 'approved_claims'=>'150.75',
            'adjustment'=>'0.00', 'total_amount'=>'8150.75',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);

        return [$run, $people];
    }

    private function details(array $override = []): array
    {
        return array_merge([
            'action'=>'record_paid',
            'payment_reference'=>'TRANSFER-DEMO-4382',
            'payment_channel'=>'bank_transfer',
            'payment_date'=>CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'reconciled_total'=>'8150.75',
            'payment_evidence'=>UploadedFile::fake()->create('payment-proof.pdf', 35, 'application/pdf'),
        ], $override);
    }

    public function test_paid_requires_all_evidence_and_exact_reconciliation(): void
    {
        Storage::fake('local');
        [$run, $people] = $this->seedPayroll();
        $this->actingAs($people['partner'][0]);
        $url="/c/rac/app/payroll/$run/transition";

        $this->post($url, [
            'action'=>'record_paid',
            'payment_reference'=>'TRANSFER-DEMO-4382',
        ])->assertSessionHasErrors(['payment_channel', 'payment_date', 'reconciled_total', 'payment_evidence']);

        $this->post($url, $this->details(['reconciled_total'=>'8150.74']))
            ->assertSessionHasErrors('reconciled_total');

        $this->post($url, $this->details(['payment_date'=>CarbonImmutable::now('Asia/Dhaka')->addDay()->toDateString()]))
            ->assertSessionHasErrors('payment_date');

        $this->assertDatabaseHas('payroll_runs', ['id'=>$run, 'status'=>'approved']);
        $this->assertSame([], Storage::disk('local')->allFiles('private-payroll-evidence'));

        $this->post($url, $this->details())->assertRedirect();
        $paid = DB::table('payroll_runs')->where('id', $run)->first();
        $this->assertSame('paid', $paid->status);
        $this->assertSame('bank_transfer', $paid->payment_channel);
        $this->assertSame(815075, (int) round(((float) $paid->reconciled_total) * 100));
        $this->assertNotEmpty($paid->payment_evidence_path);
        $this->assertTrue(Storage::disk('local')->exists($paid->payment_evidence_path));

        $this->get("/c/rac/app/payroll/$run/payment-evidence")->assertOk();
    }

    public function test_payment_proof_is_not_readable_by_student_or_another_partner(): void
    {
        Storage::fake('local');
        [$run, $people] = $this->seedPayroll();
        $this->actingAs($people['partner'][0])
            ->post("/c/rac/app/payroll/$run/transition", $this->details())
            ->assertRedirect();

        $this->actingAs($people['other_partner'][0])
            ->get("/c/rac/app/payroll/$run/payment-evidence")->assertNotFound();
        $this->actingAs($people['student'][0])
            ->get("/c/rac/app/payroll/$run/payment-evidence")->assertForbidden();

        $this->assertDatabaseHas('payroll_runs', ['id'=>$run,'status'=>'paid']);
    }
}
