<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_integration_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('name',80);
            $t->char('token_hash',64)->unique();
            $t->json('scopes');
            $t->timestamp('expires_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['company_id','workspace_id','membership_id','revoked_at'],'integration_token_scope');
            $t->index(['expires_at','revoked_at'],'integration_token_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_integration_tokens');
    }
};
