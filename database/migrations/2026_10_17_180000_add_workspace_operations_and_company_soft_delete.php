<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Delete is a reversible removal, never a database cascade over payroll.
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });

        Schema::create('workspace_operational_policy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('memberships')->restrictOnDelete();
            $table->date('effective_date');
            $table->unsignedSmallInteger('leave_max_days')->default(61);
            $table->unsignedSmallInteger('annual_leave_notice_days')->default(0);
            $table->decimal('conveyance_max_per_claim', 12, 2)->default(50000);
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'workspace_id', 'status', 'effective_date'], 'workspace_policy_lookup');
        });

        Schema::create('workspace_attendance_policy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('memberships')->restrictOnDelete();
            $table->date('effective_date');
            $table->time('office_start');
            $table->time('office_end');
            $table->unsignedSmallInteger('grace_minutes');
            $table->json('weekly_holidays');
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'workspace_id', 'status', 'effective_date'], 'workspace_attendance_policy_lookup');
        });

        Schema::create('workspace_holiday_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('memberships')->restrictOnDelete();
            $table->date('work_date');
            $table->string('day_type', 12);
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'workspace_id', 'status', 'work_date'], 'workspace_holiday_request_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_holiday_requests');
        Schema::dropIfExists('workspace_attendance_policy_requests');
        Schema::dropIfExists('workspace_operational_policy_requests');
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
    }
};
