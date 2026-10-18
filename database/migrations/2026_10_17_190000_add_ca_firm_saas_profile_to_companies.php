<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Existing firms remain active CA-firm tenants. No identity or private
        // financial/attendance records are rewritten by this additive migration.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('organization_type', 30)->default('ca_firm');
            $table->string('brand_name', 100)->nullable();
            $table->string('brand_logo_asset', 180)->nullable();
            $table->string('brand_accent_color', 7)->default('#FCDF02');
            $table->unsignedSmallInteger('min_active_partners_for_company_policy')->default(1);
        });
        // One-time migration of the existing pilot's legacy visual asset.
        // No application behavior is conditional on a firm slug.
        DB::table('companies')->where('slug', 'rac')->update([
            'brand_logo_asset' => 'images/rac_logo.svg',
            'min_active_partners_for_company_policy' => 3,
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'organization_type',
                'brand_name',
                'brand_logo_asset',
                'brand_accent_color',
                'min_active_partners_for_company_policy',
            ]);
        });
    }
};
