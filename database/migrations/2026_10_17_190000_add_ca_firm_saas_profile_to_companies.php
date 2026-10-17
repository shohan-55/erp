<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing firms remain active CA-firm tenants. No identity or private
        // financial/attendance records are rewritten by this additive migration.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('organization_type', 30)->default('ca_firm');
            $table->string('brand_name', 100)->nullable();
            $table->string('brand_accent_color', 7)->default('#FCDF02');
            $table->unsignedSmallInteger('min_active_partners_for_company_policy')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'organization_type',
                'brand_name',
                'brand_accent_color',
                'min_active_partners_for_company_policy',
            ]);
        });
    }
};
