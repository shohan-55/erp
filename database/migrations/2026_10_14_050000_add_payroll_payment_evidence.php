<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('payment_channel', 40)->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('reconciled_total', 12, 2)->nullable();
            $table->string('payment_evidence_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropColumn(['payment_channel', 'payment_date', 'reconciled_total', 'payment_evidence_path']);
        });
    }
};
