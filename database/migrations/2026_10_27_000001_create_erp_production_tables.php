<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind')->default('Chartered Accountants');
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->integer('workspaces')->default(1);
            $table->integer('seats')->default(10);
            $table->string('plan')->default('Practice');
            $table->string('region')->default('Dhaka');
            $table->string('initials', 5)->default('RA');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('firm_id')->nullable()->constrained('firms')->cascadeOnDelete();
            $table->string('student_code')->nullable();
            $table->string('role')->default('student');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('partner_unit')->nullable()->default('Partner Unit 01');
            $table->boolean('can_proxy_attendance')->default(false);
            $table->boolean('can_edit_attendance')->default(false);
            $table->boolean('can_approve_leave')->default(false);
            $table->boolean('can_approve_claims')->default(false);
            $table->decimal('monthly_allowance', 10, 2)->default(8000.00);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('work_mode')->default('office');
            $table->string('client_site')->nullable();

            $table->timestamp('in_time')->nullable();
            $table->decimal('in_lat', 10, 7)->nullable();
            $table->decimal('in_lng', 10, 7)->nullable();
            $table->string('in_location_name')->nullable();

            $table->timestamp('out_time')->nullable();
            $table->decimal('out_lat', 10, 7)->nullable();
            $table->decimal('out_lng', 10, 7)->nullable();
            $table->string('out_location_name')->nullable();

            $table->string('status')->default('On time');
            $table->boolean('is_proxy')->default(false);
            $table->foreignId('proxy_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('edit_remarks')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('leave_type')->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status')->default('Awaiting Manager');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('conveyance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('claim_date');
            $table->decimal('amount', 10, 2);
            $table->string('route');
            $table->text('purpose');
            $table->string('status')->default('Manager Review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conveyance_claims');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firm_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn([
                'student_code', 'role', 'partner_unit',
                'can_proxy_attendance', 'can_edit_attendance',
                'can_approve_leave', 'can_approve_claims', 'monthly_allowance'
            ]);
        });
        Schema::dropIfExists('firms');
    }
};
