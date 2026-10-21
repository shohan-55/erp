<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->date('joined_on')->nullable();
            $t->string('staff_code',50)->nullable();
            $t->string('designation',100)->nullable();
            $t->string('articleship_registration',100)->nullable();
            $t->date('articleship_started_on')->nullable();
            $t->date('articleship_ends_on')->nullable();
            $t->date('employment_ended_on')->nullable();
            $t->string('employment_status',20)->default('active'); // active, exited
            $t->timestamps();
            $t->unique(['company_id','workspace_id','membership_id'],'staff_profile_membership_unique');
            $t->unique(['company_id','workspace_id','staff_code'],'staff_profile_partner_code_unique');
        });
        Schema::create('staff_compensation_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->date('effective_month');
            $t->decimal('old_amount',12,2);
            $t->decimal('new_amount',12,2);
            $t->text('reason');
            $t->foreignId('authorized_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id','workspace_id','membership_id','effective_month'],'staff_salary_effective_idx');
        });
        Schema::create('staff_lifecycle_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->string('event_type',35);
            $t->json('details')->nullable();
            $t->foreignId('performed_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id','workspace_id','membership_id','created_at'],'staff_events_tenant_member');
        });
        Schema::create('staff_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->string('document_type',30);
            $t->string('title',160);
            $t->string('storage_path');
            $t->string('original_filename',255);
            $t->foreignId('uploaded_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id','workspace_id','membership_id'],'staff_documents_member_scope');
        });
        Schema::create('staff_transfer_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('source_workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('target_workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('source_membership_id')->constrained('memberships')->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->foreignId('new_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('status',15)->default('pending');
            $t->text('reason');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['company_id','target_workspace_id','status'],'staff_transfer_target_inbox');
            $t->index(['company_id','source_workspace_id','status'],'staff_transfer_source_inbox');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('staff_transfer_requests');
        Schema::dropIfExists('staff_documents');
        Schema::dropIfExists('staff_lifecycle_events');
        Schema::dropIfExists('staff_compensation_changes');
        Schema::dropIfExists('staff_profiles');
    }
};
