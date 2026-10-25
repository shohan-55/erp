<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_identity_link_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->unique()->constrained('memberships')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'accepted_at', 'expires_at'], 'erp_link_user_pending_idx');
            $table->index(['company_id', 'workspace_id'], 'erp_link_workspace_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('erp_identity_link_invitations');
    }
};
