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
                if (! Schema::hasColumn('users', 'can_manage_policies')) {
                    $table->boolean('can_manage_policies')->default(false)->after('can_manage_team');
                }
                if (! Schema::hasColumn('users', 'can_manage_payroll')) {
                    $table->boolean('can_manage_payroll')->default(false)->after('can_manage_policies');
                }
                if (! Schema::hasColumn('users', 'can_final_approve_leave')) {
                    $table->boolean('can_final_approve_leave')->default(false)->after('can_manage_payroll');
                }
                if (! Schema::hasColumn('users', 'can_final_approve_claims')) {
                    $table->boolean('can_final_approve_claims')->default(false)->after('can_final_approve_leave');
                }
            });
        }

        if (Schema::hasTable('firms')) {
            Schema::table('firms', function (Blueprint $table) {
                if (! Schema::hasColumn('firms', 'logo_url')) {
                    $table->string('logo_url', 255)->nullable()->after('initials');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $cols = array_filter(
                    ['can_manage_policies', 'can_manage_payroll', 'can_final_approve_leave', 'can_final_approve_claims'],
                    fn ($c) => Schema::hasColumn('users', $c)
                );
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('firms') && Schema::hasColumn('firms', 'logo_url')) {
            Schema::table('firms', function (Blueprint $table) {
                $table->dropColumn('logo_url');
            });
        }
    }
};
