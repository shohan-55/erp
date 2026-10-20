<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_security_events',function(Blueprint $t){
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            // Global login may occur without company selection. Do not store
            // guessed identity, attempted e-mail, raw IP, user agent or tokens.
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind',48);
            $t->string('severity',12);
            $t->char('daily_ip_fingerprint',64)->nullable();
            $t->timestamp('created_at');
            $t->index(['company_id','created_at','kind'],'erp_security_company_time');
            $t->index(['user_id','created_at'],'erp_security_user_history');
            $t->index(['kind','created_at'],'erp_security_kind_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_security_events');
    }
};
