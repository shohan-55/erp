<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Never automatically relabel existing real customers as demo tenants.
            $table->string('environment_kind', 16)->default('customer')->index();
            // Soft removal is NOT physical deletion. All operational and billing
            // records remain intact until an independently verified exit workflow.
            $table->timestamp('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deleted_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['environment_kind', 'deleted_at', 'deleted_reason']);
        });
    }
};
