<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure leave_requests table and its columns exist
        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->nullable()->constrained('firms')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('leave_type')->default('annual');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('Awaiting Manager');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('manager_remarks')->nullable();
                $table->text('partner_remarks')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('leave_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('leave_requests', 'firm_id')) {
                    $table->foreignId('firm_id')->nullable()->after('id')->constrained('firms')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('leave_requests', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('firm_id')->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('leave_requests', 'leave_type')) {
                    $table->string('leave_type')->default('annual');
                }
                if (! Schema::hasColumn('leave_requests', 'start_date')) {
                    $table->date('start_date')->nullable();
                }
                if (! Schema::hasColumn('leave_requests', 'end_date')) {
                    $table->date('end_date')->nullable();
                }
                if (! Schema::hasColumn('leave_requests', 'reason')) {
                    $table->text('reason')->nullable();
                }
                if (! Schema::hasColumn('leave_requests', 'status')) {
                    $table->string('status')->default('Awaiting Manager');
                }
                if (! Schema::hasColumn('leave_requests', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }
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
        }

        // 2. Ensure conveyance_claims table and its columns exist
        if (! Schema::hasTable('conveyance_claims')) {
            Schema::create('conveyance_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->nullable()->constrained('firms')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->date('claim_date')->nullable();
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('route')->nullable();
                $table->text('purpose')->nullable();
                $table->string('status')->default('Manager Review');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('client_name')->nullable()->default('Office/General');
                $table->decimal('original_amount', 10, 2)->nullable();
                $table->text('adjustment_remark')->nullable();
                $table->boolean('is_late')->default(false);
                $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('manager_remarks')->nullable();
                $table->text('partner_remarks')->nullable();
                $table->string('attachment_name')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('conveyance_claims', function (Blueprint $table) {
                if (! Schema::hasColumn('conveyance_claims', 'firm_id')) {
                    $table->foreignId('firm_id')->nullable()->after('id')->constrained('firms')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('conveyance_claims', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('firm_id')->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('conveyance_claims', 'claim_date')) {
                    $table->date('claim_date')->nullable();
                }
                if (! Schema::hasColumn('conveyance_claims', 'amount')) {
                    $table->decimal('amount', 10, 2)->default(0.00);
                }
                if (! Schema::hasColumn('conveyance_claims', 'route')) {
                    $table->string('route')->nullable();
                }
                if (! Schema::hasColumn('conveyance_claims', 'purpose')) {
                    $table->text('purpose')->nullable();
                }
                if (! Schema::hasColumn('conveyance_claims', 'status')) {
                    $table->string('status')->default('Manager Review');
                }
                if (! Schema::hasColumn('conveyance_claims', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }
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

        // 3. Ensure attendances table has firm_id
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'firm_id')) {
                    $table->foreignId('firm_id')->nullable()->after('id')->constrained('firms')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('attendances', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('firm_id')->constrained('users')->cascadeOnDelete();
                }
            });
        }

        // 4. Ensure partner_policies table has firm_id
        if (Schema::hasTable('partner_policies')) {
            Schema::table('partner_policies', function (Blueprint $table) {
                if (! Schema::hasColumn('partner_policies', 'firm_id')) {
                    $table->foreignId('firm_id')->nullable()->after('id')->constrained('firms')->cascadeOnDelete();
                }
            });
        }

        // 5. Backfill firm_id from users table if rows are missing firm_id
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'firm_id')) {
                $userFirms = DB::table('users')->whereNotNull('firm_id')->pluck('firm_id', 'id')->all();

                if (! empty($userFirms)) {
                    if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'firm_id') && Schema::hasColumn('leave_requests', 'user_id')) {
                        foreach ($userFirms as $userId => $firmId) {
                            DB::table('leave_requests')->where('user_id', $userId)->whereNull('firm_id')->update(['firm_id' => $firmId]);
                        }
                    }

                    if (Schema::hasTable('conveyance_claims') && Schema::hasColumn('conveyance_claims', 'firm_id') && Schema::hasColumn('conveyance_claims', 'user_id')) {
                        foreach ($userFirms as $userId => $firmId) {
                            DB::table('conveyance_claims')->where('user_id', $userId)->whereNull('firm_id')->update(['firm_id' => $firmId]);
                        }
                    }

                    if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'firm_id') && Schema::hasColumn('attendances', 'user_id')) {
                        foreach ($userFirms as $userId => $firmId) {
                            DB::table('attendances')->where('user_id', $userId)->whereNull('firm_id')->update(['firm_id' => $firmId]);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Silently continue if tables are empty or already synced
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op or nullable drops
    }
};
