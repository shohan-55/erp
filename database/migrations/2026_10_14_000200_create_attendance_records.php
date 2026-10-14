<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->date('work_date');
            $table->string('status')->default('present');
            $table->timestamps();
            $table->unique(['company_id', 'membership_id', 'work_date']);
            $table->index(['company_id', 'workspace_id', 'work_date']);
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_day_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->enum('mode', ['office', 'client']);
            $table->string('client_site')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'membership_id', 'is_open']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('attendance_days');
    }
};
