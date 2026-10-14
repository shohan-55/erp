<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDay extends Model
{
    protected $fillable = ['company_id', 'workspace_id', 'membership_id', 'work_date', 'status'];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }
}
