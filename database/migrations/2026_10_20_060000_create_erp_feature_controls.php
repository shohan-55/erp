<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_feature_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Exact, non-null, globally scoped identifier within a company.
            // Avoid nullable-column MySQL UNIQUE semantics for polymorphic scope.
            $table->string('scope_key', 100);
            $table->string('feature_key', 60);
            $table->boolean('enabled');
            $table->text('reason');
            $table->foreignId('updated_by')->constrained('memberships')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'scope_key', 'feature_key'], 'erp_feature_controls_exact_scope');
            $table->index(['company_id', 'scope_key'], 'erp_feature_controls_scope_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_feature_controls');
    }
};
