<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            // Configurable initial values, not application constants.
            $t->unsignedSmallInteger('late_review_days')->default(3);
            $t->boolean('grace_late_exempt')->default(true);
        });
        Schema::create('workspace_hr_policy_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('manager_mode', 24)->default('approval_required');
            $t->foreignId('updated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamps();
            $t->unique('workspace_id');
        });
        Schema::create('workspace_hr_policy_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->date('effective_date');
            $t->json('rules_snapshot');
            $t->string('status', 20)->default('pending');
            $t->boolean('sensitive')->default(false);
            $t->string('manager_mode_snapshot', 24);
            $t->text('reason');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'status', 'effective_date'], 'hr_revision_effective_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_hr_policy_revisions');
        Schema::dropIfExists('workspace_hr_policy_permissions');
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn(['late_review_days', 'grace_late_exempt']);
        });
    }
};
