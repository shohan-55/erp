<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform authority is now granted exclusively through the `role` column.
     *
     * Earlier builds treated a hard-coded email address as a super-admin flag,
     * which meant a firm member could gain platform control simply by changing
     * their own email. This migration keeps the legitimate operator accounts
     * working by promoting them once, and never adds the fallback back.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $legacyAdminEmails = ['infobd.sohan@gmail.com', 'admin@dhakawala.com'];

        try {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(email)'), $legacyAdminEmails)
                ->where(function ($query) {
                    $query->where('role', '!=', 'platform_admin')->orWhereNull('role');
                })
                ->update(['role' => 'platform_admin', 'updated_at' => now()]);
        } catch (\Throwable) {
            // Never block a deployment on this promotion step.
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: revoking administrator role automatically
        // could lock the operator out of the platform.
    }
};
