<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_form_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('memberships')->restrictOnDelete();
            $t->string('form_key', 60);
            $t->unsignedInteger('version');
            $t->string('title', 160);
            $t->text('description')->nullable();
            $t->json('fields_json');
            $t->timestamps();
            $t->unique(['company_id', 'workspace_id', 'form_key', 'version'], 'ws_form_version_unique');
            $t->index(['company_id', 'workspace_id', 'id'], 'ws_form_scope');
        });

        Schema::create('workspace_form_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('template_id')->constrained('workspace_form_templates')->restrictOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->json('values_json');
            $t->timestamp('submitted_at');
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'template_id', 'id'], 'ws_form_sub_scope');
            $t->index(['company_id', 'workspace_id', 'membership_id', 'id'], 'ws_form_sub_owner');
        });

        Schema::create('workspace_reminder_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('memberships')->restrictOnDelete();
            $t->string('rule_type', 40)->default('milestone_due');
            $t->unsignedTinyInteger('days_before')->default(3);
            $t->boolean('active')->default(false);
            $t->timestamps();
            $t->unique(['company_id', 'workspace_id', 'rule_type'], 'ws_reminder_rule_unique');
        });

        Schema::create('workspace_reminder_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->constrained('workspace_reminder_rules')->restrictOnDelete();
            $t->foreignId('milestone_id')->constrained('audit_engagement_milestones')->restrictOnDelete();
            $t->foreignId('recipient_membership_id')->constrained('memberships')->restrictOnDelete();
            $t->date('due_on');
            $t->string('kind', 12); // upcoming or overdue
            $t->timestamp('created_at');
            $t->unique(['rule_id', 'milestone_id', 'due_on', 'kind'], 'ws_reminder_exactly_once');
            $t->index(['recipient_membership_id', 'created_at'], 'ws_reminder_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_reminder_deliveries');
        Schema::dropIfExists('workspace_reminder_rules');
        Schema::dropIfExists('workspace_form_submissions');
        Schema::dropIfExists('workspace_form_templates');
    }
};
