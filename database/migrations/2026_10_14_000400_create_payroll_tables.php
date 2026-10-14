<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('allowance_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->char('effective_month', 7);
            $table->decimal('base_amount', 12, 2);
            $table->timestamps();
            $table->unique(['membership_id', 'effective_month']);
            $table->index(['company_id', 'workspace_id', 'effective_month']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->char('payroll_month', 7);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->foreignId('reviewed_by_membership_id')->nullable()->constrained('company_memberships')->restrictOnDelete();
            $table->foreignId('approved_by_membership_id')->nullable()->constrained('company_memberships')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('payment_reference', 150)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'workspace_id', 'payroll_month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->decimal('approved_claim_total', 12, 2)->default(0);
            $table->decimal('payable_amount', 12, 2);
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'membership_id']);
        });

        Schema::create('payroll_claim_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('conveyance_claim_id')->unique()->constrained('conveyance_claims')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_claim_allocations');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('allowance_rates');
    }
};
