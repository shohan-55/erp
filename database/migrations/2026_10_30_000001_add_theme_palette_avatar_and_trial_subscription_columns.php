<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                if (! Schema::hasColumn('firms', 'theme_palette')) {
                    $table->string('theme_palette', 40)->default('sea_green');
                }
                if (! Schema::hasColumn('firms', 'primary_color')) {
                    $table->string('primary_color', 20)->default('#0D9488');
                }
                if (! Schema::hasColumn('firms', 'subscription_type')) {
                    $table->string('subscription_type', 20)->default('paid'); // 'trial' or 'paid'
                }
                if (! Schema::hasColumn('firms', 'per_seat_rate')) {
                    $table->decimal('per_seat_rate', 10, 2)->default(500.00);
                }
                if (! Schema::hasColumn('firms', 'trial_days')) {
                    $table->integer('trial_days')->nullable()->default(14);
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone', 40)->nullable();
                }
                if (! Schema::hasColumn('users', 'avatar_url')) {
                    $table->string('avatar_url')->nullable();
                }
                if (! Schema::hasColumn('users', 'payment_account_mode')) {
                    $table->string('payment_account_mode', 30)->default('bKash');
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
    }

    public function down(): void
    {
        // Additive production migration
    }
};
