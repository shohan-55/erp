<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('saas_plans', function (Blueprint $t) {
            $t->unsignedTinyInteger('annual_discount_percent')->default(0);
            $t->unsignedTinyInteger('trial_days')->default(0);
            $t->unsignedInteger('storage_addon_mib')->default(100);
            $t->decimal('storage_addon_monthly_bdt',12,2)->default(0);
        });
        Schema::table('saas_subscriptions', function (Blueprint $t) {
            $t->timestamp('trial_started_at')->nullable();
            $t->date('trial_ends_at')->nullable();
            $t->boolean('auto_invoice')->default(false);
            $t->unsignedTinyInteger('renewal_months')->default(1);
            $t->unsignedInteger('storage_addon_units')->default(0);
            $t->unsignedInteger('pending_storage_addon_units')->nullable();
        });
        Schema::table('saas_invoices', function (Blueprint $t) {
            $t->unsignedTinyInteger('annual_discount_percent_snapshot')->default(0);
            $t->unsignedInteger('storage_addon_units_snapshot')->default(0);
            $t->unsignedInteger('storage_addon_mib_snapshot')->default(0);
            $t->decimal('storage_addon_total_bdt',12,2)->default(0);
            $t->decimal('discount_total_bdt',12,2)->default(0);
        });
        Schema::create('saas_billing_automation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $t->foreignId('subscription_id')->constrained('saas_subscriptions')->restrictOnDelete();
            $t->foreignId('invoice_id')->constrained('saas_invoices')->restrictOnDelete();
            $t->date('period_end_at_check');
            $t->string('event_type',40)->default('renewal_invoice_issued');
            $t->timestamp('created_at');
            $t->unique(['subscription_id','period_end_at_check'],'saas_auto_renew_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_automation_events');
        Schema::table('saas_invoices', function (Blueprint $t) {
            $t->dropColumn(['annual_discount_percent_snapshot','storage_addon_units_snapshot',
                'storage_addon_mib_snapshot','storage_addon_total_bdt','discount_total_bdt']);
        });
        Schema::table('saas_subscriptions', function (Blueprint $t) {
            $t->dropColumn(['trial_started_at','trial_ends_at','auto_invoice','renewal_months',
                'storage_addon_units','pending_storage_addon_units']);
        });
        Schema::table('saas_plans', function (Blueprint $t) {
            $t->dropColumn(['annual_discount_percent','trial_days','storage_addon_mib',
                'storage_addon_monthly_bdt']);
        });
    }
};
