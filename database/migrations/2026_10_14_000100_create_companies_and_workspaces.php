<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('timezone')->default('Asia/Dhaka');
            $table->timestamps();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('partner');
            $table->timestamps();
            $table->unique(['company_id', 'id']);
        });

        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->nullable();
            $table->foreignId('manager_membership_id')->nullable();
            $table->enum('role', ['partner', 'manager', 'student']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
            $table->unique(['company_id', 'id']);
            $table->foreign(['company_id', 'workspace_id'])->references(['company_id', 'id'])->on('workspaces')->restrictOnDelete();
            $table->foreign(['company_id', 'manager_membership_id'])->references(['company_id', 'id'])->on('company_memberships')->restrictOnDelete();
            $table->index(['company_id', 'workspace_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('companies');
    }
};
