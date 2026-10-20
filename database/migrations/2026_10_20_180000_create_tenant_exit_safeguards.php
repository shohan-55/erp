<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_exit_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('memberships')->restrictOnDelete();
            $t->string('state',32)->default('awaiting_consent');
            $t->string('reason',1000);
            $t->date('retention_until');
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('prepared_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('customer_acknowledged_at')->nullable();
            $t->timestamp('restore_attested_at')->nullable();
            $t->foreignId('restore_attested_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->char('manifest_sha256',64)->nullable();
            $t->string('block_reason',300)->nullable();
            $t->timestamps();
            $t->index(['company_id','state','id'], 'tenant_exit_state_scope');
        });
        Schema::create('tenant_exit_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('case_id')->constrained('tenant_exit_cases')->restrictOnDelete();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->string('decision',12)->default('pending');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['case_id','membership_id'], 'tenant_exit_vote_unique');
            $t->index(['company_id','workspace_id','case_id'], 'tenant_exit_vote_scope');
        });
        Schema::create('tenant_exit_artifacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('case_id')->constrained('tenant_exit_cases')->restrictOnDelete();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('kind',20); // table | file | manifest
            $t->string('source_name',100);
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unsignedInteger('part_number')->default(1);
            $t->string('storage_key',320);
            $t->unsignedBigInteger('byte_size');
            $t->char('sha256',64);
            $t->timestamps();
            $t->unique(['case_id','storage_key'], 'tenant_exit_artifact_path_unique');
            $t->index(['company_id','case_id','kind'], 'tenant_exit_artifact_scope');
        });
        Schema::create('tenant_exit_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('case_id')->constrained('tenant_exit_cases')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('released_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->string('reason',1000);
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
            $t->index(['company_id','case_id','released_at'], 'tenant_exit_hold_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_exit_holds');
        Schema::dropIfExists('tenant_exit_artifacts');
        Schema::dropIfExists('tenant_exit_votes');
        Schema::dropIfExists('tenant_exit_cases');
    }
};
