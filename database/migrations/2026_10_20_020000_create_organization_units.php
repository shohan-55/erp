<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing Partner-workspace identifiers, memberships, and records are
        // unchanged. Business-type organizational units are optional overlays.
        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->string('slug', 80);
            $table->string('name', 160);
            $table->string('kind', 24);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug'], 'org_units_workspace_slug_unique');
            $table->index(['company_id', 'workspace_id', 'parent_id', 'active'], 'org_units_scope_parent_index');
            $table->unique(['id', 'company_id', 'workspace_id'], 'org_units_scope_identity_unique');
        });

        Schema::create('organization_unit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_unit_id', 'membership_id'], 'org_assignment_member_unique');
            $table->index(['company_id', 'workspace_id', 'membership_id'], 'org_assignment_scope_member_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_unit_assignments');
        Schema::dropIfExists('organization_units');
    }
};
