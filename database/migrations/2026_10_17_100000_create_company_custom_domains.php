<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete()->unique();
            $table->string('hostname', 253)->unique();
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('tls_approved_at')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_custom_domains');
    }
};
