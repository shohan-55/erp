<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_policy_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('proposed_by')->constrained('memberships')->restrictOnDelete();
            $t->date('effective_date');
            $t->time('office_start');
            $t->time('office_end');
            $t->unsignedSmallInteger('grace_minutes');
            $t->json('weekly_holidays');
            $t->json('eligible_partner_ids');
            $t->text('justification');
            $t->string('status', 20)->default('pending'); // pending, approved, rejected, suspended
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status', 'effective_date']);
        });

        Schema::create('company_policy_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proposal_id')->constrained('company_policy_proposals')->cascadeOnDelete();
            $t->foreignId('partner_membership_id')->constrained('memberships')->restrictOnDelete();
            $t->string('decision', 10); // approve/reject
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['proposal_id', 'partner_membership_id']);
        });

        Schema::create('workspace_policy_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('authorized_by')->constrained('memberships')->restrictOnDelete();
            $t->date('effective_date');
            $t->time('office_start');
            $t->time('office_end');
            $t->unsignedSmallInteger('grace_minutes');
            $t->json('weekly_holidays');
            $t->timestamps();
            $t->unique(['workspace_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_policy_overrides');
        Schema::dropIfExists('company_policy_votes');
        Schema::dropIfExists('company_policy_proposals');
    }
};