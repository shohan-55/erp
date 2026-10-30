<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "working day exception" is a single date whose office hours differ from
     * the firm's standing policy: a hartal that delays opening, a flood that
     * makes lateness meaningless, an Eid half-day that ends at 16:00.
     *
     * It lives on `practice_holidays` (category = "exception") because that table
     * already models "this one date is special". Changing the *policy* instead
     * would rewrite every past day and silently alter already-approved payroll.
     */
    public function up(): void
    {
        if (! Schema::hasTable('practice_holidays')) {
            return;
        }

        Schema::table('practice_holidays', function (Blueprint $table) {
            if (! Schema::hasColumn('practice_holidays', 'office_start_override')) {
                $table->string('office_start_override', 5)->nullable(); // "12:00"
            }
            if (! Schema::hasColumn('practice_holidays', 'office_end_override')) {
                $table->string('office_end_override', 5)->nullable(); // "16:00"
            }
            if (! Schema::hasColumn('practice_holidays', 'grace_minutes_override')) {
                $table->unsignedSmallInteger('grace_minutes_override')->nullable(); // 60
            }
            if (! Schema::hasColumn('practice_holidays', 'late_exempt')) {
                // True = attendance is still recorded, lateness is recorded
                // honestly as "Late (Excused)", and payroll never counts it.
                $table->boolean('late_exempt')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('practice_holidays')) {
            return;
        }

        Schema::table('practice_holidays', function (Blueprint $table) {
            foreach (['office_start_override', 'office_end_override', 'grace_minutes_override', 'late_exempt'] as $column) {
                if (Schema::hasColumn('practice_holidays', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
