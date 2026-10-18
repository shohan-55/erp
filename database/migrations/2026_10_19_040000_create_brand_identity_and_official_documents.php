<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_brand_assets',function(Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('memberships')->restrictOnDelete();
            $table->string('kind',30);
            $table->string('storage_path',300);
            $table->string('original_name',180);
            $table->string('mime',100);
            $table->string('sha256',64);
            $table->unsignedInteger('byte_size');
            $table->timestamps();
            $table->index(['company_id','workspace_id','kind']);
        });
        Schema::create('workspace_brand_profiles',function(Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('public_name',160)->nullable();
            $table->string('office_address',400)->nullable();
            $table->string('email',180)->nullable();
            $table->string('phone',60)->nullable();
            $table->string('website',200)->nullable();
            $table->string('accent_color',7)->default('#FCDF02');
            $table->string('sidebar_color',7)->default('#17191F');
            $table->string('signatory_name',160)->nullable();
            $table->string('signatory_title',160)->nullable();
            $table->foreignId('logo_asset_id')->nullable()->constrained('workspace_brand_assets')->nullOnDelete();
            $table->foreignId('letterhead_asset_id')->nullable()->constrained('workspace_brand_assets')->nullOnDelete();
            $table->foreignId('seal_asset_id')->nullable()->constrained('workspace_brand_assets')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id','workspace_id']);
        });
        Schema::create('official_document_issues',function(Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('document_type',45);
            $table->unsignedBigInteger('source_id');
            $table->string('reference',60)->unique();
            $table->foreignId('issued_by')->constrained('memberships')->restrictOnDelete();
            $table->json('approved_snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['company_id','workspace_id','document_type','source_id'],'official_doc_source_unique');
        });
    }

    public function down():void
    {
        Schema::dropIfExists('official_document_issues');
        Schema::dropIfExists('workspace_brand_profiles');
        Schema::dropIfExists('workspace_brand_assets');
    }
};
