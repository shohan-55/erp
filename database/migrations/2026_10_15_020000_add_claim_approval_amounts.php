<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conveyance_claims', function (Blueprint $table) {
            // Employee's original amount is immutable in the existing "amount".
            // Manager recommendation and Partner-approved reimbursement are
            // separately recorded and fed to payroll without rewriting history.
            $table->decimal('manager_recommended_amount', 12, 2)->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conveyance_claims', function (Blueprint $table) {
            $table->dropColumn(['manager_recommended_amount', 'approved_amount']);
        });
    }
};
