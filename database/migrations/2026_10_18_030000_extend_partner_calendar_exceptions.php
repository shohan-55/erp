<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workspace_day_overrides', function (Blueprint $table) {
            $table->foreignId('authorized_by')->nullable()->change();
            $table->string('holiday_category', 32)->default('partner_decision');
            $table->time('office_start')->nullable();
            $table->time('office_end')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->nullable();
            // Historical Partner-authorized entries stay approved.
            $table->string('status', 16)->default('approved');
            $table->foreignId('proposed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['company_id', 'workspace_id', 'work_date', 'status'], 'day_override_status_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_day_overrides', function (Blueprint $table) {
            $table->dropIndex('day_override_status_lookup');
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->foreignId('authorized_by')->nullable(false)->change();
            $table->dropColumn([
                'holiday_category', 'office_start', 'office_end', 'grace_minutes',
                'status', 'reviewed_at',
            ]);
        });
    }
};
