<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location collection is opt-in and disabled for all existing workspaces.
 * Raw coordinates are never placed in attendance_sessions or audit details.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('attendance_location_enabled')->default(false);
            $table->unsignedSmallInteger('attendance_location_retention_days')->default(30);
        });

        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('event', 20); // check_in / check_out
            $table->text('latitude_encrypted');
            $table->text('longitude_encrypted');
            $table->decimal('accuracy_meters', 9, 2)->nullable();
            $table->timestamp('captured_at'); // server time, NOT untrusted browser clock
            $table->timestamps();
            $table->unique(['attendance_session_id', 'event']);
            $table->index(['company_id', 'workspace_id', 'captured_at'], 'attendance_locations_scope_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_locations');
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['attendance_location_enabled', 'attendance_location_retention_days']);
        });
    }
};
