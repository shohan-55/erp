<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalNotification extends Model
{
    protected $fillable = ['company_id','workspace_id','membership_id','title','body','read_at'];
    protected function casts(): array { return ['read_at'=>'immutable_datetime']; }
}
