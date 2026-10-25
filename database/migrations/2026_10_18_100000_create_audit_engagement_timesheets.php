<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('code', 40);
            $t->string('name', 180);
            $t->string('industry', 100)->nullable();
            $t->string('contact_name', 120)->nullable();
            $t->string('contact_email', 180)->nullable();
            $t->boolean('active')->default(true);
            $t->foreignId('created_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'workspace_id', 'code'], 'audit_client_partner_code');
            $t->index(['company_id', 'workspace_id', 'active']);
        });
        Schema::create('audit_engagements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('audit_client_id')->constrained('audit_clients')->restrictOnDelete();
            $t->string('code', 50);
            $t->string('title', 180);
            $t->string('engagement_type', 28); // statutory, internal, tax, advisory, other
            $t->date('period_end')->nullable();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('budget_hours', 9, 2)->default(0);
            $t->string('status', 20)->default('open'); // open, closed
            $t->foreignId('created_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'workspace_id', 'code'], 'audit_engagement_partner_code');
            $t->index(['company_id', 'workspace_id', 'status']);
        });
        Schema::create('audit_engagement_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('assigned_by')->constrained('memberships')->restrictOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['engagement_id', 'membership_id'], 'audit_engagement_assignment_unique');
            $t->index(['company_id', 'workspace_id', 'membership_id'], 'audit_assign_member_scope');
        });
        Schema::create('audit_timesheets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->date('work_date');
            $t->decimal('hours', 5, 2);
            $t->boolean('billable')->default(true); // reporting only; not client invoices
            $t->text('work_description');
            $t->string('status', 20)->default('submitted'); // submitted, manager_reviewed, approved, rejected, withdrawn
            $t->foreignId('manager_reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('manager_recommendation', 10)->nullable(); // approve, reject
            $t->foreignId('decided_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'engagement_id', 'work_date'], 'audit_timesheet_engagement_period');
            $t->index(['company_id', 'workspace_id', 'membership_id', 'work_date'], 'audit_timesheet_student_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_timesheets');
        Schema::dropIfExists('audit_engagement_assignments');
        Schema::dropIfExists('audit_engagements');
        Schema::dropIfExists('audit_clients');
    }
};
