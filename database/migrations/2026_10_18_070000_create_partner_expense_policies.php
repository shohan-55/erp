<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_expense_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('expense_year');
            $t->decimal('max_single_claim',12,2)->default(50000);
            $t->decimal('monthly_student_limit',12,2)->default(100000);
            $t->unsignedSmallInteger('submission_window_days')->default(90);
            $t->foreignId('set_by')->nullable()->constrained('memberships')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id','workspace_id','expense_year'],'expense_policy_partner_year_unique');
        });
        Schema::table('conveyance_claims', function (Blueprint $t) {
            $t->string('expense_category',24)->default('transport');
            $t->string('receipt_sha256',64)->nullable();
            $t->index(['company_id','workspace_id','membership_id','journey_date','expense_category'],'expense_claim_member_category_date');
        });
    }
    public function down(): void
    {
        Schema::table('conveyance_claims',function(Blueprint $t) {
            $t->dropIndex('expense_claim_member_category_date');
            $t->dropColumn(['expense_category','receipt_sha256']);
        });
        Schema::dropIfExists('workspace_expense_policies');
    }
};
