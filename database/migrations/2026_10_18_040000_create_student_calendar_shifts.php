<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_calendar_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->uuid('batch_ref');
            $table->date('work_date');
            $table->string('day_type', 12); // working or holiday
            $table->time('office_start')->nullable();
            $table->time('office_end')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->nullable();
            $table->text('reason');
            $table->string('status', 12); // pending, approved, rejected
            $table->foreignId('proposed_by')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','workspace_id','student_membership_id','work_date','status'], 'student_shift_member_date_scope');
            $table->index(['company_id','workspace_id','batch_ref'], 'student_shift_batch_scope');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('student_calendar_shifts');
    }
};
