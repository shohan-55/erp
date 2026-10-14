<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id','workspace_id','payroll_run_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payroll_events');
    }
};
