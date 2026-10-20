<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $t) {
            $t->string('proxy_status', 16)->nullable()->index(); // null=ordinary, pending/verified/rejected
            $t->foreignId('proxy_started_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->foreignId('proxy_ended_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->text('proxy_start_reason')->nullable();
            $t->text('proxy_end_reason')->nullable();
            $t->foreignId('proxy_verified_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamp('proxy_verified_at')->nullable();
            $t->text('proxy_verification_note')->nullable();
            $t->index(['company_id','workspace_id','proxy_status'], 'attendance_proxy_review_scope');
        });
    }
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $t) {
            $t->dropIndex('attendance_proxy_review_scope');
            foreach (['proxy_started_by','proxy_ended_by','proxy_verified_by'] as $f) $t->dropConstrainedForeignId($f);
            $t->dropIndex(['proxy_status']);
            $t->dropColumn(['proxy_status','proxy_start_reason','proxy_end_reason','proxy_verified_at','proxy_verification_note']);
        });
    }
};
