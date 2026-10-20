<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conveyance_claims', function (Blueprint $t) {
            $t->foreignId('initiated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->text('delegation_reason')->nullable();
            $t->foreignId('withdrawn_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->text('withdrawal_reason')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->index(['company_id', 'workspace_id', 'initiated_by'],
                'claim_delegated_initiator_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conveyance_claims', function (Blueprint $t) {
            $t->dropIndex('claim_delegated_initiator_idx');
            $t->dropConstrainedForeignId('initiated_by');
            $t->dropConstrainedForeignId('withdrawn_by');
            $t->dropColumn(['delegation_reason', 'withdrawal_reason', 'withdrawn_at']);
        });
    }
};
