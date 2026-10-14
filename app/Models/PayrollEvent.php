<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['payroll_run_id','company_id','workspace_id','actor_membership_id','action','previous_status','new_status','metadata'];
    protected function casts(): array { return ['metadata'=>'array','created_at'=>'immutable_datetime']; }
}
