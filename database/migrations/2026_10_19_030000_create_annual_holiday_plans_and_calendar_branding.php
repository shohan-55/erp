<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annual_holiday_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('calendar_year');
            $table->foreignId('submitted_by')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('holiday_dates');
            $table->text('source_note');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','workspace_id','calendar_year','status'], 'annual_holiday_scope');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('calendar_watermark_mode', 10)->default('name');
            $table->unsignedTinyInteger('calendar_watermark_opacity')->default(6);
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn([
            'calendar_watermark_mode', 'calendar_watermark_opacity',
        ]));
        Schema::dropIfExists('annual_holiday_plans');
    }
};
