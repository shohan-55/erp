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
                if (! Schema::hasColumn('users', 'designation')) {
                    $table->string('designation', 120)->nullable()->after('role');
                }
                if (! Schema::hasColumn('users', 'promotion_history')) {
                    $table->json('promotion_history')->nullable()->after('articleship_end_date');
                }
            });
        }

        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                if (! Schema::hasColumn('firms', 'custom_designations')) {
                    $table->json('custom_designations')->nullable()->after('enabled_modules');
                }
            });
        }

        if (Schema::hasTable('partner_policies')) {
            Schema::table('partner_policies', function (Blueprint $table) {
                if (! Schema::hasColumn('partner_policies', 'geofence_radius_km')) {
                    $table->decimal('geofence_radius_km', 6, 2)->default(1.50)->after('conveyance_late_days');
                }
                if (! Schema::hasColumn('partner_policies', 'policy_timezone')) {
                    $table->string('policy_timezone', 60)->default('Asia/Dhaka')->after('geofence_radius_km');
                }
                if (! Schema::hasColumn('partner_policies', 'governance_rules')) {
                    $table->json('governance_rules')->nullable()->after('manager_permissions');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $cols = array_filter(['designation', 'promotion_history'], fn ($c) => Schema::hasColumn('users', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('firms') && Schema::hasColumn('firms', 'custom_designations')) {
            Schema::table('firms', function (Blueprint $table) {
                $table->dropColumn('custom_designations');
            });
        }

        if (Schema::hasTable('partner_policies')) {
            Schema::table('partner_policies', function (Blueprint $table) {
                $cols = array_filter(['geofence_radius_km', 'policy_timezone', 'governance_rules'], fn ($c) => Schema::hasColumn('partner_policies', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
