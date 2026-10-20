<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_engagement_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->string('title',180);
            $t->text('description')->nullable();
            $t->date('due_on');
            $t->string('status',24)->default('open'); // open, submitted, manager_reviewed, rejected, completed
            $t->text('submission_note')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('manager_reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('manager_recommendation',10)->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->foreignId('created_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id','workspace_id','engagement_id','status'], 'eng_milestone_progress_scope');
            $t->index(['company_id','workspace_id','membership_id','status'], 'eng_milestone_member_scope');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_engagement_milestones');
    }
};
