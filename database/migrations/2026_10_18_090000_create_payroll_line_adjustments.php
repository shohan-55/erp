<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_line_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $t->foreignId('membership_id')->constrained('memberships')->restrictOnDelete();
            $t->string('kind', 20); // bonus, deduction
            $t->decimal('amount', 12, 2); // positive amount; kind decides sign
            $t->text('reason');
            $t->foreignId('authorized_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamp('voided_at')->nullable();
            $t->foreignId('voided_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'payroll_run_id', 'membership_id'], 'payroll_adj_scoped_run_member');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payroll_line_adjustments');
    }
};
