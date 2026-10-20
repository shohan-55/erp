<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_feature_policy_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('memberships')->restrictOnDelete();
            $table->string('feature_key', 60);
            $table->string('operation', 12); // enable, disable, reset
            $table->date('effective_date'); // first of a FUTURE local month
            $table->json('eligible_partner_ids'); // immutable electorate snapshot
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending/approved/rejected/suspended
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'feature_key', 'status', 'effective_date'], 'feature_proposals_effective_idx');
            $table->index(['company_id', 'status', 'created_at'], 'feature_proposals_register_idx');
        });
        Schema::create('company_feature_policy_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')
                ->constrained('company_feature_policy_proposals')->cascadeOnDelete();
            $table->foreignId('partner_membership_id')->constrained('memberships')->restrictOnDelete();
            $table->string('decision', 10);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['proposal_id', 'partner_membership_id'], 'feature_proposal_unique_voter');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('company_feature_policy_votes');
        Schema::dropIfExists('company_feature_policy_proposals');
    }
};
