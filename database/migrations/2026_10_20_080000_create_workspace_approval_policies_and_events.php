<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_approval_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('module', 20);
            $t->string('mode', 24); // manager_partner / partner_only / manager_final
            $t->boolean('active')->default(true);
            $t->text('reason');
            $t->foreignId('changed_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id','workspace_id','module'], 'approval_policy_workspace_module_unique');
        });
        foreach (['leave_requests', 'conveyance_claims'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                // Immutable workflow snapshot on new submissions; NULL means
                // existing, legacy Manager→Partner behavior.
                $t->string('approval_mode', 24)->nullable();
            });
        }
        Schema::create('workspace_approval_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('module', 20);
            $t->unsignedBigInteger('request_id');
            $t->foreignId('actor_membership_id')->constrained('memberships')->restrictOnDelete();
            $t->string('action', 24); // approve / reject
            $t->string('previous_status', 32);
            $t->string('new_status', 32);
            $t->string('approval_mode', 24);
            $t->text('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['company_id','workspace_id','module','request_id'], 'approval_events_request_lookup');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('workspace_approval_events');
        foreach (['leave_requests','conveyance_claims'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('approval_mode'));
        }
        Schema::dropIfExists('workspace_approval_policies');
    }
};
