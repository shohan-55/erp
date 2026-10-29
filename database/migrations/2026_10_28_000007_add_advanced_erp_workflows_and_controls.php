<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. DhakaWala Platform Controls on firms table
        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                if (! Schema::hasColumn('firms', 'organization_type')) {
                    $table->string('organization_type', 40)->default('ca_firm'); // ca_firm, ngo, corporate, consultancy
                }
                if (! Schema::hasColumn('firms', 'shared_attendance_view')) {
                    $table->boolean('shared_attendance_view')->default(true);
                }
                if (! Schema::hasColumn('firms', 'is_suspended')) {
                    $table->boolean('is_suspended')->default(false);
                }
                if (! Schema::hasColumn('firms', 'subscription_expires_at')) {
                    $table->date('subscription_expires_at')->nullable();
                }
                if (! Schema::hasColumn('firms', 'enabled_modules')) {
                    $table->json('enabled_modules')->nullable();
                }
            });
        }

        // 2. Late Penalty Rules & Custom Leave Quotas on partner_policies
        if (Schema::hasTable('partner_policies')) {
            Schema::table('partner_policies', function (Blueprint $table) {
                if (! Schema::hasColumn('partner_policies', 'late_penalty_enabled')) {
                    $table->boolean('late_penalty_enabled')->default(false);
                }
                if (! Schema::hasColumn('partner_policies', 'late_days_per_deduction')) {
                    $table->integer('late_days_per_deduction')->default(3);
                }
                if (! Schema::hasColumn('partner_policies', 'deduction_days_count')) {
                    $table->decimal('deduction_days_count', 5, 2)->default(1.00);
                }
                if (! Schema::hasColumn('partner_policies', 'leave_quotas')) {
                    $table->json('leave_quotas')->nullable();
                }
            });
        }

        // 3. Multi-Segment Movement Logs & Close vs On-Duty state on attendances
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'session_state')) {
                    $table->string('session_state', 30)->default('closed'); // active, on_duty_transit, closed
                }
                if (! Schema::hasColumn('attendances', 'movement_logs')) {
                    $table->json('movement_logs')->nullable();
                }
            });
        }

        // 4. Engaged Manager Mention & Partial Approval on leave_requests
        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('leave_requests', 'engaged_manager_id')) {
                    $table->foreignId('engaged_manager_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('leave_requests', 'requested_days')) {
                    $table->integer('requested_days')->default(1);
                }
                if (! Schema::hasColumn('leave_requests', 'approved_days')) {
                    $table->integer('approved_days')->nullable();
                }
                if (! Schema::hasColumn('leave_requests', 'approved_dates_summary')) {
                    $table->string('approved_dates_summary')->nullable();
                }
            });
        }

        // 5. Multi-Row Transport Items & Other Expenses on conveyance_claims
        if (Schema::hasTable('conveyance_claims')) {
            Schema::table('conveyance_claims', function (Blueprint $table) {
                if (! Schema::hasColumn('conveyance_claims', 'transport_items')) {
                    $table->json('transport_items')->nullable();
                }
                if (! Schema::hasColumn('conveyance_claims', 'other_expense_description')) {
                    $table->string('other_expense_description')->nullable();
                }
                if (! Schema::hasColumn('conveyance_claims', 'other_expense_amount')) {
                    $table->decimal('other_expense_amount', 10, 2)->default(0.00);
                }
            });
        }

        // 6. Targeted Broadcasts & Login Popup Flag on practice_notifications
        if (Schema::hasTable('practice_notifications')) {
            Schema::table('practice_notifications', function (Blueprint $table) {
                if (! Schema::hasColumn('practice_notifications', 'sender_id')) {
                    $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('practice_notifications', 'sender_name')) {
                    $table->string('sender_name')->nullable();
                }
                if (! Schema::hasColumn('practice_notifications', 'recipient_ids')) {
                    $table->json('recipient_ids')->nullable();
                }
                if (! Schema::hasColumn('practice_notifications', 'show_as_popup')) {
                    $table->boolean('show_as_popup')->default(true);
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive rollback for additive columns
    }
};
