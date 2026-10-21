<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A single verified ERP identity may be invited to more than one
        // isolated Partner workspace. Existing membership IDs never change;
        // payroll, leave and attendance continue to reference membership IDs.
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropUnique('memberships_company_id_user_id_unique');
            $table->unique(['company_id', 'workspace_id', 'user_id'], 'memberships_firm_workspace_identity_unique');
            $table->index(['company_id', 'user_id', 'active'], 'memberships_firm_user_active_lookup');
        });
    }

    public function down(): void
    {
        // Prevent silent data loss if one account has multiple workspace roles:
        // reverting requires explicit consolidation, never arbitrary deletion.
        if (\Illuminate\Support\Facades\DB::table('memberships')
            ->select('company_id', 'user_id')
            ->groupBy('company_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new \RuntimeException('Cannot restore one-membership-per-firm uniqueness while multi-workspace identities exist.');
        }

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_firm_user_active_lookup');
            $table->dropUnique('memberships_firm_workspace_identity_unique');
            $table->unique(['company_id', 'user_id'], 'memberships_company_id_user_id_unique');
        });
    }
};
