<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_review_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('kind', 25); // missed_checkout, missed_checkin, client_duty
            $table->date('work_date');
            $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
            $table->timestamp('proposed_start_at')->nullable();
            $table->timestamp('proposed_end_at')->nullable();
            $table->string('client_site', 180)->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, manager_reviewed, approved, rejected
            $table->string('manager_recommendation', 10)->nullable(); // approve, reject
            $table->foreignId('manager_reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','workspace_id','status'], 'attendance_review_workspace_queue');
            $table->index(['membership_id','work_date','kind','status'], 'attendance_review_member_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_review_requests');
    }
};
