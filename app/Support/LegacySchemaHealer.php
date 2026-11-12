<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacySchemaHealer
{
    protected static bool $healed = false;

    public static function heal(): void
    {
        if (static::$healed && ! app()->runningUnitTests()) {
            return;
        }

        $flagFile = storage_path('framework/cache/erp_schema_healed_v9.flag');
        if (! app()->runningUnitTests() && is_file($flagFile)) {
            static::$healed = true;
            return;
        }

        try {
            if (DB::getDriverName() !== 'mysql') {
                static::$healed = true;
                return;
            }

            $erpTables = [
                'leave_requests',
                'conveyance_claims',
                'attendances',
                'users',
                'firms',
                'partner_policies',
                'payroll_records',
                'practice_notifications',
                'policy_proposals',
                'audit_logs',
                'audit_clients',
                'practice_holidays',
            ];

            // 1. Discover and drop any foreign key constraints referencing `workspaces` or `workspace_id`
            try {
                $fks = DB::select("
                    SELECT TABLE_NAME, CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                      AND (COLUMN_NAME = 'workspace_id' OR REFERENCED_TABLE_NAME = 'workspaces')
                ");
                foreach ($fks as $fk) {
                    try {
                        DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    } catch (\Throwable $e) {
                        // Ignore if already dropped
                    }
                }
            } catch (\Throwable $e) {
                // Fallback if information_schema is restricted
            }

            // 2. Explicitly neutralize and drop `workspace_id` across all ERP tables
            foreach ($erpTables as $table) {
                try {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workspace_id')) {
                        continue;
                    }
                    try {
                        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_workspace_id_foreign`");
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                    try {
                        DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `workspace_id` BIGINT UNSIGNED NULL");
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                    try {
                        DB::statement('SET SESSION foreign_key_checks = 0');
                        Schema::table($table, function (Blueprint $blueprint) {
                            $blueprint->dropColumn('workspace_id');
                        });
                        DB::statement('SET SESSION foreign_key_checks = 1');
                    } catch (\Throwable $e) {
                        try {
                            DB::statement('SET SESSION foreign_key_checks = 1');
                        } catch (\Throwable $e2) {}
                    }
                } catch (\Throwable $e) {
                    // Continue healing remaining tables
                }
            }

            // 3. Make any legacy non-PK columns that have IS_NULLABLE='NO' and no default value nullable on workflow tables
            try {
                $workflowTables = [
                    'leave_requests',
                    'conveyance_claims',
                    'attendances',
                    'partner_policies',
                    'payroll_records',
                    'practice_notifications',
                    'policy_proposals',
                    'audit_logs',
                    'audit_clients',
                    'practice_holidays',
                ];
                $inList = "'" . implode("','", $workflowTables) . "'";
                $strictCols = DB::select("
                    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME IN ({$inList})
                      AND COLUMN_NAME != 'id'
                      AND IS_NULLABLE = 'NO'
                      AND COLUMN_DEFAULT IS NULL
                      AND EXTRA NOT LIKE '%auto_increment%'
                ");
                foreach ($strictCols as $col) {
                    try {
                        DB::statement("ALTER TABLE `{$col->TABLE_NAME}` MODIFY COLUMN `{$col->COLUMN_NAME}` {$col->COLUMN_TYPE} NULL");
                    } catch (\Throwable $e) {
                        // Ignore individual column modify errors
                    }
                }
            } catch (\Throwable $e) {
                // Ignore information_schema query errors
            }

            static::$healed = true;
            if (! app()->runningUnitTests()) {
                @file_put_contents($flagFile, (string) time());
            }
        } catch (\Throwable $e) {
            // Never block request execution
        }
    }
}
