<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // Delegation is explicit and partner-controlled, not implied by role.
            $table->boolean('can_manage_student_features')->default(false);
        });

        Schema::create('student_feature_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('feature_key', 40);
            // Partner establishes the maximum access. Delegated Manager may
            // restrict further but cannot elevate beyond the Partner's limit.
            $table->boolean('partner_enabled')->default(true);
            $table->boolean('manager_enabled')->default(true);
            $table->foreignId('partner_updated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('manager_updated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();
            $table->unique(['membership_id', 'feature_key'], 'student_feature_member_key_unique');
            $table->index(['company_id', 'workspace_id', 'membership_id'], 'student_feature_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_feature_controls');
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('can_manage_student_features');
        });
    }
};
