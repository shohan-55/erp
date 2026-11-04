<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConveyanceClaim extends Model
{
    protected static bool $schemaChecked = false;

    protected $fillable = [
        'firm_id',
        'user_id',
        'claim_date',
        'amount',
        'route',
        'purpose',
        'status',
        'reviewed_by',
        'client_name',
        'original_amount',
        'adjustment_remark',
        'is_late',
        'partner_id',
        'manager_remarks',
        'partner_remarks',
        'attachment_name',
        'transport_items',
        'other_expense_description',
        'other_expense_amount',
    ];

    protected $casts = [
        'claim_date'           => 'date',
        'amount'               => 'decimal:2',
        'original_amount'      => 'decimal:2',
        'other_expense_amount' => 'decimal:2',
        'is_late'              => 'boolean',
        'transport_items'      => 'array',
    ];

    public static function resetRuntimeSchemaCheck(): void
    {
        static::$schemaChecked = false;
    }

    public static function ensureRuntimeSchemaColumns(): void
    {
        if (static::$schemaChecked && ! app()->runningUnitTests()) {
            return;
        }

        try {
            \App\Support\LegacySchemaHealer::heal();
            if (! Schema::hasTable('conveyance_claims')) {
                Schema::create('conveyance_claims', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('firm_id')->nullable()->index();
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                    $table->date('claim_date')->nullable();
                    $table->decimal('amount', 10, 2)->default(0);
                    $table->decimal('original_amount', 10, 2)->nullable();
                    $table->text('route')->nullable();
                    $table->text('purpose')->nullable();
                    $table->string('client_name')->nullable();
                    $table->string('attachment_name')->nullable();
                    $table->json('transport_items')->nullable();
                    $table->string('other_expense_description')->nullable();
                    $table->decimal('other_expense_amount', 10, 2)->nullable();
                    $table->boolean('is_late')->default(false);
                    $table->string('status', 60)->default('pending_manager');
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                    $table->unsignedBigInteger('partner_id')->nullable();
                    $table->text('adjustment_remark')->nullable();
                    $table->text('manager_remarks')->nullable();
                    $table->text('partner_remarks')->nullable();
                    $table->timestamps();
                });
                static::$schemaChecked = true;
                return;
            }

            $existing = Schema::getColumnListing('conveyance_claims');
            $has = fn (string $col): bool => in_array($col, $existing, true);

            Schema::table('conveyance_claims', function (Blueprint $table) use ($has) {
                if (! $has('firm_id')) {
                    $table->unsignedBigInteger('firm_id')->nullable()->index();
                }
                if (! $has('user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                }
                if (! $has('claim_date')) {
                    $table->date('claim_date')->nullable();
                }
                if (! $has('amount')) {
                    $table->decimal('amount', 10, 2)->default(0);
                }
                if (! $has('original_amount')) {
                    $table->decimal('original_amount', 10, 2)->nullable();
                }
                if (! $has('route')) {
                    $table->text('route')->nullable();
                }
                if (! $has('purpose')) {
                    $table->text('purpose')->nullable();
                }
                if (! $has('client_name')) {
                    $table->string('client_name')->nullable();
                }
                if (! $has('attachment_name')) {
                    $table->string('attachment_name')->nullable();
                }
                if (! $has('transport_items')) {
                    $table->json('transport_items')->nullable();
                }
                if (! $has('other_expense_description')) {
                    $table->string('other_expense_description')->nullable();
                }
                if (! $has('other_expense_amount')) {
                    $table->decimal('other_expense_amount', 10, 2)->nullable();
                }
                if (! $has('is_late')) {
                    $table->boolean('is_late')->default(false);
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
                if (! $has('adjustment_remark')) {
                    $table->text('adjustment_remark')->nullable();
                }
                if (! $has('manager_remarks')) {
                    $table->text('manager_remarks')->nullable();
                }
                if (! $has('partner_remarks')) {
                    $table->text('partner_remarks')->nullable();
                }
            });

            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement("ALTER TABLE conveyance_claims MODIFY COLUMN route TEXT NULL, MODIFY COLUMN purpose TEXT NULL, MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'pending_manager'");
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            static::$schemaChecked = true;
        } catch (\Throwable $e) {
            // Silently ignore schema check issues
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
