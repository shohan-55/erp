<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = ['attendance_day_id', 'company_id', 'membership_id', 'mode', 'client_site', 'checked_in_at', 'checked_out_at', 'is_open'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'immutable_datetime', 'checked_out_at' => 'immutable_datetime', 'is_open' => 'boolean'];
    }
}
