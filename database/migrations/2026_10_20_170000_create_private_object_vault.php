<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_vault_quotas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('limit_bytes');
            $t->unsignedBigInteger('used_bytes')->default(0);
            $t->unsignedBigInteger('reserved_bytes')->default(0);
            $t->timestamps();
            $t->unique(['company_id','workspace_id'], 'vault_quota_scope');
        });

        Schema::create('erp_vault_uploads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('memberships')->restrictOnDelete();
            $t->string('title',180);
            $t->string('filename',180);
            $t->string('visibility',12)->default('partner');
            $t->foreignId('subject_membership_id')->nullable()->constrained('memberships')->restrictOnDelete();
            $t->unsignedBigInteger('expected_bytes');
            $t->unsignedInteger('expected_parts');
            $t->string('status',20)->default('pending');
            $t->timestamp('expires_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['company_id','workspace_id','status','expires_at'], 'vault_upload_cleanup_scope');
        });

        Schema::create('erp_vault_parts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('upload_id')->constrained('erp_vault_uploads')->restrictOnDelete();
            $t->unsignedInteger('part_number');
            $t->unsignedInteger('size_bytes');
            $t->char('sha256',64);
            $t->string('private_path',320);
            $t->timestamp('created_at');
            $t->unique(['upload_id','part_number'], 'vault_part_unique');
        });

        Schema::create('erp_vault_objects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignId('upload_id')->unique()->constrained('erp_vault_uploads')->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('subject_membership_id')->nullable()->constrained('memberships')->restrictOnDelete();
            $t->string('title',180);
            $t->string('original_filename',180);
            $t->string('visibility',12);
            $t->string('disk',32);
            $t->string('object_key',320);
            $t->string('mime',100);
            $t->unsignedBigInteger('byte_size');
            $t->char('sha256',64);
            $t->timestamp('archived_at')->nullable();
            $t->foreignId('archived_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->boolean('legal_hold')->default(false);
            $t->timestamps();
            $t->unique(['disk','object_key'], 'vault_storage_key_unique');
            $t->index(['company_id','workspace_id','archived_at','id'], 'vault_visible_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_vault_objects');
        Schema::dropIfExists('erp_vault_parts');
        Schema::dropIfExists('erp_vault_uploads');
        Schema::dropIfExists('erp_vault_quotas');
    }
};
