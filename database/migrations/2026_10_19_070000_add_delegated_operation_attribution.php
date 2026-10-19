<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_sessions',function(Blueprint $table) {
            $table->foreignId('proxy_checked_in_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('proxy_checked_out_by')->nullable()->constrained('memberships')->nullOnDelete();
        });
        Schema::table('leave_requests',function(Blueprint $table) {
            $table->foreignId('initiated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('first_reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
        });
        Schema::table('conveyance_claims',function(Blueprint $table) {
            $table->foreignId('initiated_by')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('first_reviewed_by')->nullable()->constrained('memberships')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('conveyance_claims',function(Blueprint $table) {
            $table->dropConstrainedForeignId('first_reviewed_by');
            $table->dropConstrainedForeignId('initiated_by');
        });
        Schema::table('leave_requests',function(Blueprint $table) {
            $table->dropConstrainedForeignId('first_reviewed_by');
            $table->dropConstrainedForeignId('initiated_by');
        });
        Schema::table('attendance_sessions',function(Blueprint $table) {
            $table->dropConstrainedForeignId('proxy_checked_out_by');
            $table->dropConstrainedForeignId('proxy_checked_in_by');
        });
    }
};
