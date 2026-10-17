<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned, prospective Partner-workspace day exceptions.
 * All changes append a fresh approved record; historical entries remain.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('day_type', 12); // working / holiday
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('memberships')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'workspace_id', 'work_date'], 'workspace_day_overrides_scope_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_day_overrides');
    }
};
