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
        'office_start_override',
        'office_end_override',
        'grace_minutes_override',
        'late_exempt',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date'            => 'date',
            'is_office_open_override' => 'boolean',
            'absent_deduction_days'   => 'decimal:2',
            'grace_minutes_override'  => 'integer',
            'late_exempt'             => 'boolean',
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
     * A working day exception is a date whose hours differ from the standing
     * policy — a hartal, a flood, a half-day before Eid. It is a *working* day,
     * not a holiday, so the calendar must never paint it as one.
     */
    public const CATEGORY_EXCEPTION = 'exception';

    public function isWorkingDayException(): bool
    {
        return $this->category === self::CATEGORY_EXCEPTION;
    }

    /** "12:00 – 16:00", "from 12:00", "until 16:00" or null when no hours were shifted. */
    public function shiftWindowLabel(): ?string
    {
        $start = $this->office_start_override ?: null;
        $end = $this->office_end_override ?: null;

        if ($start && $end) {
            return "{$start} – {$end}";
        }
        if ($start) {
            return "from {$start}";
        }
        if ($end) {
            return "until {$end}";
        }

        return null;
    }

    /**
     * One line an operator (or an auditor) can read a year later:
     * "12:00 – 16:00 · grace 60 min · late not counted".
     */
    public function effectLabel(): string
    {
        $parts = [];

        if ($window = $this->shiftWindowLabel()) {
            $parts[] = $window;
        }
        if ($this->grace_minutes_override !== null) {
            $parts[] = 'grace ' . (int) $this->grace_minutes_override . ' min';
        }
        if ($this->late_exempt) {
            $parts[] = 'late not counted';
        }

        return $parts === [] ? 'No timing change declared' : implode(' · ', $parts);
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
