<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 70)->unique();
            $t->string('name');
            $t->string('timezone', 60)->default('Asia/Dhaka');
            $t->time('office_start')->default('10:00:00');
            $t->time('office_end')->default('18:00:00');
            $t->unsignedSmallInteger('grace_minutes')->default(10);
            $t->json('weekly_holidays')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('slug', 80);
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'slug']);
            $t->unique(['id', 'company_id']);
        });

        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('manager_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('role', 24); // partner, manager, student
            $t->decimal('monthly_allowance', 12, 2)->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'user_id']);
            $t->unique(['id', 'company_id', 'workspace_id']);
            $t->index(['company_id', 'workspace_id', 'role']);
        });

        Schema::create('attendance_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->date('work_date');
            $t->string('work_mode', 20); // office / client
            $t->string('client_site')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->boolean('late')->default(false);
            $t->boolean('holiday')->default(false);
            $t->string('correction_reason')->nullable();
            $t->foreignId('corrected_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamps();
            $t->index(['membership_id', 'ended_at']);
            $t->index(['company_id', 'workspace_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->string('leave_type', 20);
            $t->date('date_from');
            $t->date('date_to');
            $t->text('reason');
            $t->string('status', 30)->default('pending'); // pending, manager_reviewed, approved, rejected
            $t->foreignId('reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'status']);
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->date('period_month');
            $t->string('status', 20)->default('draft'); // draft, reviewed, approved, paid, locked
            $t->json('policy_snapshot');
            $t->foreignId('last_action_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('payment_reference')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'workspace_id', 'period_month']);
        });

        Schema::create('conveyance_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $t->date('journey_date');
            $t->string('journey_route');
            $t->text('purpose');
            $t->decimal('amount', 12, 2);
            $t->string('receipt_path')->nullable();
            $t->string('status', 20)->default('pending'); // pending, approved, rejected, paid
            $t->foreignId('reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->foreignId('reserved_payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $t->foreignId('paid_payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'status']);
            $t->index(['membership_id', 'journey_date']);
        });

        Schema::create('payroll_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained()->restrictOnDelete();
            $t->decimal('base_allowance', 12, 2);
            $t->decimal('approved_claims', 12, 2)->default(0);
            $t->decimal('adjustment', 12, 2)->default(0); // no automatic penalties
            $t->decimal('total_amount', 12, 2);
            $t->timestamps();
            $t->unique(['payroll_run_id', 'membership_id']);
        });

        Schema::create('tenant_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('recipient_membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->string('title');
            $t->text('message');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['recipient_membership_id', 'read_at']);
        });

        Schema::create('tenant_audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('actor_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('action', 100);
            $t->string('entity_type', 50);
            $t->unsignedBigInteger('entity_id');
            $t->json('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['company_id', 'workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['tenant_audit_events','tenant_notifications','payroll_lines','conveyance_claims','payroll_runs','leave_requests','attendance_sessions','memberships','workspaces','companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};