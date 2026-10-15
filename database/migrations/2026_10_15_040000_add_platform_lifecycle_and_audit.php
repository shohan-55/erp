<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });

        Schema::table('memberships', function (Blueprint $table) {
            // Distinguishes a Platform suspension from an unaccepted invitation.
            // Both states are inactive, but only the former can be reactivated
            // by Platform Admin without password/token acceptance.
            $table->timestamp('platform_suspended_at')->nullable()->index();
        });

        Schema::create('platform_control_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_control_events');
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn('platform_suspended_at'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};
