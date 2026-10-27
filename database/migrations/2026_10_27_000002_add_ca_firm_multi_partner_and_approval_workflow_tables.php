<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Partner Policies & Dashboard Grid Configurations
        if (! Schema::hasTable('partner_policies')) {
            Schema::create('partner_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
                $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
                $table->string('partner_unit')->default('Partner Unit 01');

                // Attendance Rules
                $table->string('office_start')->default('10:00');
                $table->string('office_end')->default('18:00');
                $table->integer('grace_minutes')->default(10);

                // Conveyance Approval Rules
                $table->decimal('manager_conveyance_limit', 10, 2)->default(300.00);
                $table->integer('conveyance_late_days')->default(3);

                // Configurable Student Dashboard Grids (JSON)
                $table->json('student_grids')->nullable();

                // Configurable Manager Permissions & Feature Visibility (JSON)
                $table->json('manager_permissions')->nullable();

                $table->timestamps();
            });
        }

        // 2. Extend Leave Requests for Multi-Tier Approval
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'partner_id')) {
                $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('leave_requests', 'manager_remarks')) {
                $table->text('manager_remarks')->nullable();
            }
            if (! Schema::hasColumn('leave_requests', 'partner_remarks')) {
                $table->text('partner_remarks')->nullable();
            }
        });

        // 3. Extend Conveyance Claims for Multi-Tier Approval, Limits, and Client Tagging
        Schema::table('conveyance_claims', function (Blueprint $table) {
            if (! Schema::hasColumn('conveyance_claims', 'client_name')) {
                $table->string('client_name')->nullable()->default('Office/General');
            }
            if (! Schema::hasColumn('conveyance_claims', 'original_amount')) {
                $table->decimal('original_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('conveyance_claims', 'adjustment_remark')) {
                $table->text('adjustment_remark')->nullable();
            }
            if (! Schema::hasColumn('conveyance_claims', 'is_late')) {
                $table->boolean('is_late')->default(false);
            }
            if (! Schema::hasColumn('conveyance_claims', 'partner_id')) {
                $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('conveyance_claims', 'manager_remarks')) {
                $table->text('manager_remarks')->nullable();
            }
            if (! Schema::hasColumn('conveyance_claims', 'partner_remarks')) {
                $table->text('partner_remarks')->nullable();
            }
            if (! Schema::hasColumn('conveyance_claims', 'attachment_name')) {
                $table->string('attachment_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_policies');
    }
};
