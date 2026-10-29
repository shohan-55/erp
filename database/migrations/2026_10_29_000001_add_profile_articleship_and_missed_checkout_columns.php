<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Self-Service Profile (Phone, bKash/Bank Account) & ICAB Articleship Dates on users (#1 & #4)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone', 40)->nullable();
                }
                if (! Schema::hasColumn('users', 'payment_account_mode')) {
                    $table->string('payment_account_mode', 30)->default('bKash'); // bKash, Nagad, Bank A/C, Cash
                }
                if (! Schema::hasColumn('users', 'payment_account_no')) {
                    $table->string('payment_account_no', 80)->nullable();
                }
                if (! Schema::hasColumn('users', 'articleship_start_date')) {
                    $table->date('articleship_start_date')->nullable();
                }
                if (! Schema::hasColumn('users', 'articleship_end_date')) {
                    $table->date('articleship_end_date')->nullable();
                }
            });
        }

        // 2. Student Missed Check-out Correction Request columns on attendances (#3)
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'correction_requested')) {
                    $table->boolean('correction_requested')->default(false);
                }
                if (! Schema::hasColumn('attendances', 'requested_out_time')) {
                    $table->string('requested_out_time', 10)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'correction_reason')) {
                    $table->text('correction_reason')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'correction_status')) {
                    $table->string('correction_status', 30)->nullable(); // pending, approved, rejected
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive additive columns
    }
};
