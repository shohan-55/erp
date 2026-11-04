<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LeaveRequest extends Model
{
    protected static bool $schemaChecked = false;

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
     * Self-healing runtime schema check for cPanel deployments (runs once per request in all environments).
     */
    public static function ensureRuntimeSchemaColumns(): void
    {
        if (self::$schemaChecked && ! app()->runningUnitTests()) {
            return;
        }
        self::$schemaChecked = true;

        try {
            if (! Schema::hasTable('leave_requests')) {
                Schema::create('leave_requests', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('firm_id')->nullable()->index();
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                    $table->unsignedBigInteger('engaged_manager_id')->nullable()->index();
                    $table->string('leave_type', 60)->default('annual');
                    $table->date('start_date')->nullable();
                    $table->date('end_date')->nullable();
                    $table->integer('requested_days')->nullable()->default(1);
                    $table->integer('approved_days')->nullable();
                    $table->string('approved_dates_summary', 255)->nullable();
                    $table->text('reason')->nullable();
                    $table->string('status', 60)->default('pending_manager');
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                    $table->unsignedBigInteger('partner_id')->nullable();
                    $table->text('manager_remarks')->nullable();
                    $table->text('partner_remarks')->nullable();
                    $table->timestamps();
                });
                return;
            }

            $cols = Schema::getColumnListing('leave_requests');
            $has = fn (string $c) => in_array($c, $cols, true);

            $needed = [
                'firm_id', 'user_id', 'engaged_manager_id', 'leave_type',
                'start_date', 'end_date', 'requested_days', 'approved_days',
                'approved_dates_summary', 'reason', 'status', 'reviewed_by',
                'partner_id', 'manager_remarks', 'partner_remarks',
            ];
            $missingAny = false;
            foreach ($needed as $colName) {
                if (! $has($colName)) {
                    $missingAny = true;
                    break;
                }
            }

            if ($missingAny) {
                Schema::table('leave_requests', function (Blueprint $table) use ($has) {
                    if (! $has('firm_id')) {
                        $table->unsignedBigInteger('firm_id')->nullable()->index();
                    }
                    if (! $has('user_id')) {
                        $table->unsignedBigInteger('user_id')->nullable()->index();
                    }
                    if (! $has('engaged_manager_id')) {
                        $table->unsignedBigInteger('engaged_manager_id')->nullable();
                    }
                    if (! $has('leave_type')) {
                        $table->string('leave_type', 60)->default('annual');
                    }
                    if (! $has('start_date')) {
                        $table->date('start_date')->nullable();
                    }
                    if (! $has('end_date')) {
                        $table->date('end_date')->nullable();
                    }
                    if (! $has('requested_days')) {
                        $table->integer('requested_days')->nullable()->default(1);
                    }
                    if (! $has('approved_days')) {
                        $table->integer('approved_days')->nullable();
                    }
                    if (! $has('approved_dates_summary')) {
                        $table->string('approved_dates_summary', 255)->nullable();
                    }
                    if (! $has('reason')) {
                        $table->text('reason')->nullable();
                    }
                    if (! $has('status')) {
                        $table->string('status', 60)->default('pending_manager');
                    }
                    if (! $has('reviewed_by')) {
                        $table->unsignedBigInteger('reviewed_by')->nullable();
                    }
                    if (! $has('partner_id')) {
                        $table->unsignedBigInteger('partner_id')->nullable();
                    }
                    if (! $has('manager_remarks')) {
                        $table->text('manager_remarks')->nullable();
                    }
                    if (! $has('partner_remarks')) {
                        $table->text('partner_remarks')->nullable();
                    }
                });
            }

            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement("ALTER TABLE `leave_requests` MODIFY COLUMN `reason` TEXT NULL, MODIFY COLUMN `status` VARCHAR(60) NOT NULL DEFAULT 'pending_manager', MODIFY COLUMN `leave_type` VARCHAR(60) NOT NULL DEFAULT 'annual'");
                } catch (\Throwable $e) {
                    Log::debug($e->getMessage());
                }
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
