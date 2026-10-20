<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // NULL = historical request, never inferred to be delegated.
            $table->foreignId('initiated_by')->nullable()
                ->constrained('memberships')->nullOnDelete();
            $table->text('delegation_reason')->nullable();
            $table->foreignId('withdrawn_by')->nullable()
                ->constrained('memberships')->nullOnDelete();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->index(['company_id','workspace_id','initiated_by'],
                'leave_initiator_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('leave_initiator_scope_idx');
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropColumn(['delegation_reason','withdrawal_reason','withdrawn_at']);
        });
    }
};
