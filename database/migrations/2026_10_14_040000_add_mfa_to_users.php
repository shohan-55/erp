<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Encrypt TOTP secrets with Laravel's APP_KEY. Recovery codes are
            // high-entropy random strings, stored as one-way SHA-256 digests.
            $table->text('mfa_secret')->nullable();
            $table->json('mfa_recovery_codes')->nullable();
            $table->unsignedBigInteger('mfa_last_step')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'mfa_secret', 'mfa_recovery_codes', 'mfa_last_step', 'mfa_confirmed_at',
            ]);
        });
    }
};
