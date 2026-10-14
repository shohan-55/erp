<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollRun extends Model {
    protected $fillable=['company_id','workspace_id','payroll_month','status','created_by_membership_id','reviewed_by_membership_id','approved_by_membership_id','reviewed_at','approved_at','paid_at','locked_at','payment_reference'];
    protected function casts(): array { return ['reviewed_at'=>'immutable_datetime','approved_at'=>'immutable_datetime','paid_at'=>'immutable_datetime','locked_at'=>'immutable_datetime']; }
}
