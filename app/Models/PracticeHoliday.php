<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeHoliday extends Model
{
    protected $fillable = [
        'firm_id',
        'partner_unit',
        'holiday_date',
        'title',
        'category',
        'is_office_open_override',
        'absent_deduction_days',
        'override_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date'            => 'date',
            'is_office_open_override' => 'boolean',
            'absent_deduction_days'   => 'decimal:2',
        ];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Standard Bangladesh Government Public Holidays preset for a given year.
     */
    public static function defaultGovernmentHolidays(int $year = 2026): array
    {
        return [
            ['holiday_date' => "{$year}-02-21", 'title' => 'Shaheed Day & International Mother Language Day', 'category' => 'government'],
            ['holiday_date' => "{$year}-03-20", 'title' => 'Eid-ul-Fitr (Govt. Holiday)', 'category' => 'government'],
            ['holiday_date' => "{$year}-03-21", 'title' => 'Eid-ul-Fitr (Day 2)', 'category' => 'government'],
            ['holiday_date' => "{$year}-03-26", 'title' => 'Independence & National Day', 'category' => 'government'],
            ['holiday_date' => "{$year}-04-14", 'title' => 'Pohela Boishakh (Bangla Nababarsha)', 'category' => 'government'],
            ['holiday_date' => "{$year}-05-01", 'title' => 'May Day (International Workers Day)', 'category' => 'government'],
            ['holiday_date' => "{$year}-05-27", 'title' => 'Eid-ul-Adha (Govt. Holiday)', 'category' => 'government'],
            ['holiday_date' => "{$year}-05-28", 'title' => 'Eid-ul-Adha (Day 2)', 'category' => 'government'],
            ['holiday_date' => "{$year}-10-21", 'title' => 'Durga Puja (Bijoya Dashami)', 'category' => 'government'],
            ['holiday_date' => "{$year}-12-16", 'title' => 'Victory Day of Bangladesh', 'category' => 'government'],
            ['holiday_date' => "{$year}-12-25", 'title' => 'Christmas Day', 'category' => 'government'],
        ];
    }
}
