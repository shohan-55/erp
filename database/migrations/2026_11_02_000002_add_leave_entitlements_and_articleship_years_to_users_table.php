<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'articleship_duration_years')) {
                    $table->unsignedTinyInteger('articleship_duration_years')->nullable()->default(3)->after('articleship_end_date');
                }
                if (! Schema::hasColumn('users', 'total_leave_cap')) {
                    $table->unsignedSmallInteger('total_leave_cap')->nullable()->default(180)->after('articleship_duration_years');
                }
                if (! Schema::hasColumn('users', 'leave_allocations')) {
                    $table->json('leave_allocations')->nullable()->after('total_leave_cap');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['leave_allocations', 'total_leave_cap', 'articleship_duration_years'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
