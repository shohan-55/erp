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
                if (! Schema::hasColumn('firms', 'short_code')) {
                    $table->string('short_code', 60)->nullable()->after('initials');
                }
                if (! Schema::hasColumn('firms', 'app_name')) {
                    $table->string('app_name', 60)->nullable()->after('short_code');
                }
                if (! Schema::hasColumn('firms', 'app_icon_url')) {
                    $table->string('app_icon_url', 255)->nullable()->after('logo_url');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                foreach (['short_code', 'app_name', 'app_icon_url'] as $col) {
                    if (Schema::hasColumn('firms', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
