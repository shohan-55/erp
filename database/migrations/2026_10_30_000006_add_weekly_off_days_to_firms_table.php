<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The weekly holiday was hardcoded as Friday + Saturday in the attendance
     * calendar. That is the usual CA-firm weekend in Dhaka, but it is not every
     * firm's weekend: some work Saturdays and close on Sunday, some close only
     * on Friday. The pattern now belongs to the firm.
     *
     * Stored as a JSON array of weekday numbers (0 = Sunday … 6 = Saturday).
     * NULL means "never configured", which resolves to Friday + Saturday so
     * every existing tenant keeps exactly the calendar it has today.
     */
    public function up(): void
    {
        if (! Schema::hasTable('firms') || Schema::hasColumn('firms', 'weekly_off_days')) {
            return;
        }

        Schema::table('firms', function (Blueprint $table) {
            $table->json('weekly_off_days')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('firms') && Schema::hasColumn('firms', 'weekly_off_days')) {
            Schema::table('firms', function (Blueprint $table) {
                $table->dropColumn('weekly_off_days');
            });
        }
    }
};
