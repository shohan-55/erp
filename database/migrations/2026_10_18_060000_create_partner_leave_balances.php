<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_leave_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('leave_year');
            $t->decimal('annual_days', 6, 1)->default(0);
            $t->decimal('sick_days', 6, 1)->default(0);
            $t->decimal('casual_days', 6, 1)->default(0);
            $t->decimal('emergency_days', 6, 1)->default(0);
            $t->decimal('carry_forward_limit', 6, 1)->default(0);
            $t->unsignedSmallInteger('annual_notice_days')->default(0);
            $t->boolean('count_holidays')->default(false);
            $t->foreignId('set_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id','workspace_id','leave_year'], 'leave_policy_workspace_year_unique');
        });
        Schema::create('leave_balance_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->unsignedSmallInteger('leave_year');
            $t->string('leave_type', 20);
            $t->decimal('days', 6, 1); // Positive credits only; every action audited.
            $t->date('expires_on')->nullable();
            $t->text('reason');
            $t->foreignId('authorized_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id','workspace_id','membership_id','leave_year'], 'leave_adj_member_year_scope');
        });
        Schema::table('leave_requests', function (Blueprint $t) {
            $t->string('day_portion', 16)->default('full'); // full, morning, afternoon
            $t->decimal('charge_days', 6, 1)->nullable();
            $t->foreignId('policy_id')->nullable()->constrained('workspace_leave_policies')->nullOnDelete();
            $t->index(['company_id','workspace_id','membership_id','date_from'], 'leave_request_member_calendar_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $t) {
            $t->dropIndex('leave_request_member_calendar_idx');
            $t->dropConstrainedForeignId('policy_id');
            $t->dropColumn(['day_portion','charge_days']);
        });
        Schema::dropIfExists('leave_balance_adjustments');
        Schema::dropIfExists('workspace_leave_policies');
    }
};
