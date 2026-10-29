<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Practice Holidays & Partner "Office Open on Holiday" Overrides (#2)
        if (! Schema::hasTable('practice_holidays')) {
            Schema::create('practice_holidays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
                $table->string('partner_unit')->nullable(); // null = firm-wide (Govt holiday), or specific Partner Unit
                $table->date('holiday_date');
                $table->string('title');
                $table->string('category', 40)->default('government'); // government, sudden, weekend_override
                $table->boolean('is_office_open_override')->default(false); // When true, Partner declared office OPEN on this holiday/weekend; absence triggers deduction!
                $table->decimal('absent_deduction_days', 5, 2)->default(1.00); // e.g., 1 day salary cut if absent on mandatory open holiday
                $table->string('override_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['firm_id', 'holiday_date']);
            });
        }

        // 2. GPS Geofence Distance & Location Mismatch Columns on attendances & audit_clients (#4)
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'distance_km')) {
                    $table->decimal('distance_km', 8, 2)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'location_mismatch')) {
                    $table->boolean('location_mismatch')->default(false);
                }
            });
        }

        if (Schema::hasTable('audit_clients')) {
            Schema::table('audit_clients', function (Blueprint $table) {
                if (! Schema::hasColumn('audit_clients', 'target_lat')) {
                    $table->decimal('target_lat', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('audit_clients', 'target_lng')) {
                    $table->decimal('target_lng', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('audit_clients', 'allowed_radius_km')) {
                    $table->decimal('allowed_radius_km', 5, 2)->default(1.50);
                }
            });
        }

        // 3. Payment Method & Holiday Absent Deduction on payroll_records (#3 & #2)
        if (Schema::hasTable('payroll_records')) {
            Schema::table('payroll_records', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_records', 'holiday_absent_days')) {
                    $table->integer('holiday_absent_days')->default(0);
                }
                if (! Schema::hasColumn('payroll_records', 'holiday_absent_deduction')) {
                    $table->decimal('holiday_absent_deduction', 10, 2)->default(0.00);
                }
                if (! Schema::hasColumn('payroll_records', 'payment_method')) {
                    $table->string('payment_method', 40)->default('bKash / Bank'); // Cash, bKash, Nagad, Bank Transfer
                }
                if (! Schema::hasColumn('payroll_records', 'voucher_no')) {
                    $table->string('voucher_no', 60)->nullable();
                }
            });
        }

        // 4. DhakaWala Billing & Subscription Columns on firms (#5)
        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                if (! Schema::hasColumn('firms', 'subscription_fee')) {
                    $table->decimal('subscription_fee', 10, 2)->default(5000.00);
                }
                if (! Schema::hasColumn('firms', 'billing_cycle')) {
                    $table->string('billing_cycle', 30)->default('Monthly'); // Monthly, Annual
                }
                if (! Schema::hasColumn('firms', 'payment_status')) {
                    $table->string('payment_status', 30)->default('paid'); // paid, due, overdue
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_holidays');
    }
};
