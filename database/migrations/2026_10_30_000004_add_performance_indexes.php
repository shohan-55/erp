<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the hot register queries (audit P1-3.8).
     *
     * Every attendance / payroll read filters on (firm_id, user_id, date|period)
     * and every dashboard render re-reads a whole month, but the schema only had
     * single-column foreign-key indexes. On a 200-person firm with three years of
     * history that is a full scan per student per page.
     *
     * Index creation is wrapped per-table so a partially migrated database still
     * boots, and `hasIndex` guards keep the migration idempotent.
     */
    public function up(): void
    {
        $this->addIndex('attendances', ['firm_id', 'user_id', 'date'], 'attendances_firm_user_date_index');
        $this->addIndex('attendances', ['firm_id', 'date'], 'attendances_firm_date_index');
        $this->addIndex('payroll_records', ['firm_id', 'period'], 'payroll_firm_period_index');
        $this->addIndex('leave_requests', ['user_id', 'leave_type', 'start_date'], 'leave_user_type_start_index');
        $this->addIndex('leave_requests', ['firm_id', 'status'], 'leave_firm_status_index');
        $this->addIndex('conveyance_claims', ['firm_id', 'user_id', 'claim_date'], 'claims_firm_user_date_index');
        $this->addIndex('conveyance_claims', ['firm_id', 'status'], 'claims_firm_status_index');
        $this->addIndex('audit_logs', ['firm_id', 'created_at'], 'audit_firm_created_index');
        $this->addIndex('users', ['firm_id', 'is_active'], 'users_firm_active_index');
    }

    public function down(): void
    {
        $this->dropIndex('attendances', 'attendances_firm_user_date_index');
        $this->dropIndex('attendances', 'attendances_firm_date_index');
        $this->dropIndex('payroll_records', 'payroll_firm_period_index');
        $this->dropIndex('leave_requests', 'leave_user_type_start_index');
        $this->dropIndex('leave_requests', 'leave_firm_status_index');
        $this->dropIndex('conveyance_claims', 'claims_firm_user_date_index');
        $this->dropIndex('conveyance_claims', 'claims_firm_status_index');
        $this->dropIndex('audit_logs', 'audit_firm_created_index');
        $this->dropIndex('users', 'users_firm_active_index');
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function addIndex(string $table, array $columns, string $name): void
    {
        try {
            if (! Schema::hasTable($table)) {
                return;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return;
                }
            }

            if ($this->indexExists($table, $name)) {
                return;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        } catch (\Throwable) {
            // Never block a deployment on a missing optimisation; the app is
            // correct without it, only slower.
        }
    }

    protected function dropIndex(string $table, string $name): void
    {
        try {
            if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        } catch (\Throwable) {
        }
    }

    protected function indexExists(string $table, string $name): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['name'] ?? null) === $name) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
};
