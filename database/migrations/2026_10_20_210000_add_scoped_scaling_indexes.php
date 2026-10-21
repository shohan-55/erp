<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, non-destructive hot-path indexes. No customer rows are rewritten.
 * On large production tables DBA must schedule online index-building separately.
 */
return new class extends Migration {
    private const INDEXES = [
        'attendance_sessions'=>[
            'p21_attendance_member_month'=>['company_id','workspace_id','membership_id','work_date'],
        ],
        'leave_requests'=>[
            'p21_leave_scope_status_dates'=>['company_id','workspace_id','status','date_from','date_to','membership_id'],
        ],
        'conveyance_claims'=>[
            'p21_claim_scope_status_month'=>['company_id','workspace_id','status','journey_date','membership_id'],
        ],
        'audit_timesheets'=>[
            'p21_timesheet_scope_month_status'=>['company_id','workspace_id','status','work_date','membership_id'],
        ],
        'tenant_notifications'=>[
            'p21_notification_unread_lookup'=>['company_id','workspace_id','recipient_membership_id','read_at','id'],
        ],
        'erp_vault_uploads'=>[
            'p21_vault_expiration_lookup'=>['status','expires_at','id'],
        ],
        'saas_subscriptions'=>[
            'p21_subscription_renewal_lookup'=>['auto_invoice','current_period_end','id'],
        ],
        'tenant_exit_cases'=>[
            'p21_exit_case_queue_lookup'=>['state','id'],
        ],
    ];

    public function up(): void
    {
        foreach(self::INDEXES as $table=>$indexes){
            Schema::table($table,function(Blueprint $t)use($indexes){
                foreach($indexes as $name=>$columns)$t->index($columns,$name);
            });
        }
    }

    public function down(): void
    {
        foreach(array_reverse(self::INDEXES,true) as $table=>$indexes){
            Schema::table($table,function(Blueprint $t)use($indexes){
                foreach($indexes as $name=>$columns)$t->dropIndex($name);
            });
        }
    }
};
