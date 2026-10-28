<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        try {
            // 1. Clean up dangling firms that have zero associated users (failed provisions)
            if (Schema::hasTable('firms') && Schema::hasTable('users')) {
                $firmIdsWithUsers = DB::table('users')
                    ->whereNotNull('firm_id')
                    ->pluck('firm_id')
                    ->unique()
                    ->all();

                if (! empty($firmIdsWithUsers)) {
                    DB::table('firms')
                        ->whereNotIn('id', $firmIdsWithUsers)
                        ->delete();
                }
            }

            // 2. Clean up orphaned non-platform users whose firm_id no longer exists in firms
            if (Schema::hasTable('users') && Schema::hasTable('firms')) {
                $existingFirmIds = DB::table('firms')->pluck('id')->all();

                DB::table('users')
                    ->where('role', '!=', 'platform_admin')
                    ->whereNotNull('firm_id')
                    ->whereNotIn('firm_id', ! empty($existingFirmIds) ? $existingFirmIds : [0])
                    ->delete();
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op cleanup migration
    }
};
