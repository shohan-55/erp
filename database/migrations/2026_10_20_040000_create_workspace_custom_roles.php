<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additive, workspace-scoped role definitions. Existing immutable base
        // membership roles, payroll owners and old access grants are untouched.
        Schema::create('workspace_custom_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug'], 'custom_role_workspace_slug_unique');
            $table->index(['company_id', 'workspace_id', 'active'], 'custom_role_scope_active');
        });
        Schema::create('workspace_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->unique()->constrained('memberships')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('workspace_custom_roles')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'workspace_id', 'role_id'], 'custom_role_assignment_scope');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('workspace_role_assignments');
        Schema::dropIfExists('workspace_custom_roles');
    }
};
