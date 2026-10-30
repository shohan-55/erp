<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff exit is a data-retention event, not a delete.
     *
     * Deleting a user cascades to attendances / payroll_records / leave_requests
     * (see the foreign keys added in 2026_10_27_000001 and 2026_10_28_000006),
     * which silently destroyed the financial audit trail. Departed members are
     * now deactivated instead: the account can no longer sign in, but the
     * attendance and payroll history stays intact.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'is_active')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_active')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
