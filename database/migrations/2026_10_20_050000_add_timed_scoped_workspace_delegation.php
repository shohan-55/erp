<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workspace_access_grants', function (Blueprint $table) {
            // NULL time bound preserves existing approved grants; future grants
            // use explicit start/expiry and are checked synchronously.
            $table->string('delegation_mode', 16)->default('limited');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('parent_grant_id')->nullable()
                ->constrained('workspace_access_grants')->nullOnDelete();
            $table->text('grant_reason')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('workspace_access_grants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_grant_id');
            $table->dropColumn(['delegation_mode', 'starts_at', 'expires_at', 'grant_reason']);
        });
    }
};
