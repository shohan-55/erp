<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->string('type', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewer_membership_id')->nullable()->constrained('company_memberships')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','workspace_id','status']);
            $table->index(['membership_id','starts_on']);
        });

        Schema::create('conveyance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->date('journey_date');
            $table->decimal('amount', 12, 2);
            $table->string('journey_route', 255);
            $table->text('purpose');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewer_membership_id')->nullable()->constrained('company_memberships')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','workspace_id','status']);
            $table->index(['membership_id','journey_date']);
        });

        Schema::create('operational_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('company_memberships')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','membership_id','read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notifications');
        Schema::dropIfExists('conveyance_claims');
        Schema::dropIfExists('leave_requests');
    }
};
