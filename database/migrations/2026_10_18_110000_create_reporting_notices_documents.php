<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('practice_notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_membership_id')->constrained('memberships')->restrictOnDelete();
            $t->string('title', 160);
            $t->text('body');
            $t->string('audience', 24); // everyone, managers, students
            $t->unsignedInteger('recipients_count')->default(0);
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'created_at'], 'notice_partner_date');
        });
        Schema::table('tenant_notifications', function (Blueprint $t) {
            $t->foreignId('practice_notice_id')->nullable()
                ->constrained('practice_notices')->nullOnDelete();
            $t->index(['company_id', 'workspace_id', 'practice_notice_id'], 'notification_notice_scope');
        });
        Schema::create('practice_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('memberships')->restrictOnDelete();
            $t->foreignId('subject_membership_id')->nullable()->constrained('memberships')->restrictOnDelete();
            $t->string('title', 180);
            $t->string('category', 30); // policy, audit, hr, notice, other
            $t->string('visibility', 24); // partner, subject, team
            $t->string('original_filename', 255);
            $t->string('storage_path');
            $t->string('sha256', 64);
            $t->unsignedBigInteger('byte_size');
            $t->timestamp('archived_at')->nullable();
            $t->foreignId('archived_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'workspace_id', 'subject_membership_id'], 'practice_doc_member_scope');
            $t->index(['company_id', 'workspace_id', 'archived_at'], 'practice_doc_active_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_documents');
        Schema::table('tenant_notifications', function (Blueprint $t) {
            $t->dropIndex('notification_notice_scope');
            $t->dropConstrainedForeignId('practice_notice_id');
        });
        Schema::dropIfExists('practice_notices');
    }
};
