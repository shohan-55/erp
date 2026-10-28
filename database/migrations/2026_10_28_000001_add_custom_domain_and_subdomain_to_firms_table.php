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
                if (! Schema::hasColumn('firms', 'custom_domain')) {
                    $table->string('custom_domain')->nullable()->unique()->after('region');
                }
                if (! Schema::hasColumn('firms', 'custom_subdomain')) {
                    $table->string('custom_subdomain')->nullable()->unique()->after('custom_domain');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                if (Schema::hasColumn('firms', 'custom_domain')) {
                    $table->dropColumn('custom_domain');
                }
                if (Schema::hasColumn('firms', 'custom_subdomain')) {
                    $table->dropColumn('custom_subdomain');
                }
            });
        }
    }
};
