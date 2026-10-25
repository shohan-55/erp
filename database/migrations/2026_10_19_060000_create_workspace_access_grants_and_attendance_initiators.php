<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('access_role', 24); // company_admin, moderator
            $table->json('permissions');
            $table->foreignId('granted_by')->constrained('memberships')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id','workspace_id','membership_id'],'access_grant_unique_subject');
            $table->index(['company_id','workspace_id','revoked_at'],'access_grant_active_scope');
        });
        Schema::table('attendance_review_requests', function (Blueprint $table) {
            $table->foreignId('initiated_by')->nullable()->after('membership_id')->constrained('memberships')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_review_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('initiated_by'));
        Schema::dropIfExists('workspace_access_grants');
    }
};
