<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void
    {
        Schema::create('saas_plans',function(Blueprint $t){
            $t->id();
            $t->string('code',50)->unique();
            $t->string('name',120);
            $t->decimal('monthly_bdt',12,2);
            $t->unsignedSmallInteger('max_partner_workspaces')->default(50);
            $t->unsignedInteger('max_members')->default(500);
            $t->boolean('custom_domain_enabled')->default(false);
            $t->boolean('available')->default(true);
            $t->timestamps();
        });
        Schema::create('saas_subscriptions',function(Blueprint $t){
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete()->unique();
            $t->foreignId('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $t->string('status',20)->default('pending'); // pending,active,grace,overdue
            $t->date('current_period_start')->nullable();
            $t->date('current_period_end')->nullable(); // exclusive
            $t->unsignedSmallInteger('grace_days')->default(7);
            $t->timestamps();
            $t->index(['status','current_period_end']);
        });
        Schema::create('saas_invoices',function(Blueprint $t){
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->constrained('saas_subscriptions')->restrictOnDelete();
            $t->foreignId('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $t->string('invoice_number',32)->unique();
            $t->string('plan_name_snapshot',120);
            $t->string('currency',3)->default('BDT');
            $t->unsignedSmallInteger('months');
            $t->date('period_start');
            $t->date('period_end'); // exclusive
            $t->decimal('total_amount',12,2);
            $t->decimal('paid_amount',12,2)->default(0);
            $t->string('status',15)->default('pending'); // pending,partial,paid
            $t->date('due_on');
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
            $t->unique(['subscription_id','period_start'],'invoice_subscription_period_start_unique');
            $t->index(['company_id','status','due_on'],'invoice_company_due_index');
        });
        Schema::create('saas_payments',function(Blueprint $t){
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained('saas_invoices')->restrictOnDelete();
            $t->string('method',24); // bank,bkash,nagad
            $t->string('reference',120);
            $t->decimal('amount',12,2);
            $t->string('status',16)->default('pending'); // pending,approved,rejected
            $t->text('operator_note')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['method','reference'],'saas_payment_method_reference_unique');
            $t->index(['company_id','invoice_id','status'],'saas_payment_invoice_status');
        });
    }

    public function down():void
    {
        Schema::dropIfExists('saas_payments');
        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_plans');
    }
};
