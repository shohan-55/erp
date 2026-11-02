<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LeaveRequest extends Model
{
    protected $fillable = [
        'firm_id',
        'user_id',
        'engaged_manager_id',
        'leave_type',
        'start_date',
        'end_date',
        'requested_days',
        'approved_days',
        'approved_dates_summary',
        'reason',
        'status',
        'reviewed_by',
        'partner_id',
        'manager_remarks',
        'partner_remarks',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'requested_days' => 'integer',
        'approved_days'  => 'integer',
    ];

    /**
     * Self-healing runtime schema check for cPanel deployments where new columns
     * on leave_requests may not have been migrated via CLI yet.
     */
    public static function ensureRuntimeSchemaColumns(): void
    {
        if (! app()->environment('local', 'testing') && ! app()->runningUnitTests()) {
            return;
        }

        try {
            if (! Schema::hasTable('leave_requests')) {
                return;
            }
            $missingEngaged = ! Schema::hasColumn('leave_requests', 'engaged_manager_id');
            $missingReqDays = ! Schema::hasColumn('leave_requests', 'requested_days');
            $missingAppDays = ! Schema::hasColumn('leave_requests', 'approved_days');
            $missingSummary = ! Schema::hasColumn('leave_requests', 'approved_dates_summary');
            $missingMgrRem  = ! Schema::hasColumn('leave_requests', 'manager_remarks');
            $missingPtrRem  = ! Schema::hasColumn('leave_requests', 'partner_remarks');

            if ($missingEngaged || $missingReqDays || $missingAppDays || $missingSummary || $missingMgrRem || $missingPtrRem) {
                Schema::table('leave_requests', function (Blueprint $table) use ($missingEngaged, $missingReqDays, $missingAppDays, $missingSummary, $missingMgrRem, $missingPtrRem) {
                    if ($missingEngaged) {
                        $table->unsignedBigInteger('engaged_manager_id')->nullable()->after('user_id');
                    }
                    if ($missingReqDays) {
                        $table->integer('requested_days')->nullable()->default(1);
                    }
                    if ($missingAppDays) {
                        $table->integer('approved_days')->nullable();
                    }
                    if ($missingSummary) {
                        $table->string('approved_dates_summary', 255)->nullable();
                    }
                    if ($missingMgrRem) {
                        $table->text('manager_remarks')->nullable();
                    }
                    if ($missingPtrRem) {
                        $table->text('partner_remarks')->nullable();
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::debug('LeaveRequest schema check skipped: ' . $e->getMessage());
        }
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function engagedManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engaged_manager_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
