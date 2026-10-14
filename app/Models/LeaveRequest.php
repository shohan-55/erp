<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = ['company_id','workspace_id','membership_id','type','starts_on','ends_on','reason','status','reviewer_membership_id','reviewed_at'];
    protected function casts(): array { return ['reviewed_at'=>'immutable_datetime']; }
}
