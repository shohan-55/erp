<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConveyanceClaim extends Model
{
    protected $fillable = ['company_id','workspace_id','membership_id','journey_date','amount','journey_route','purpose','status','reviewer_membership_id','reviewed_at','paid_at'];
    protected function casts(): array { return ['reviewed_at'=>'immutable_datetime','paid_at'=>'immutable_datetime','amount'=>'decimal:2']; }
}
