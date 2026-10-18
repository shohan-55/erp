<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Legacy late=1 rows were outside grace under the former policy;
            // default false preserves their eligibility without rewriting history.
            $table->boolean('late_within_grace')->default(false)->after('late');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            // Optional visual threshold only: NEVER auto-create leave or deductions.
            $table->time('half_day_warning_time')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $t) => $t->dropColumn('half_day_warning_time'));
        Schema::table('attendance_sessions', fn (Blueprint $t) => $t->dropColumn('late_within_grace'));
    }
};
