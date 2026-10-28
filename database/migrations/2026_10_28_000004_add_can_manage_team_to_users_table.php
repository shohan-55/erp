<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'can_manage_team')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_manage_team')->default(false)->after('can_approve_claims');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'can_manage_team')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('can_manage_team');
            });
        }
    }
};
