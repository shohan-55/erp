<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignId('actor_membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->timestamp('old_checked_in_at');
            $table->timestamp('old_checked_out_at')->nullable();
            $table->timestamp('new_checked_in_at');
            $table->timestamp('new_checked_out_at');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id','workspace_id','attendance_session_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
