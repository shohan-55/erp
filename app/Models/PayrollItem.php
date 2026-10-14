<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollItem extends Model {
    protected $fillable=['payroll_run_id','company_id','workspace_id','membership_id','base_amount','approved_claim_total','payable_amount','snapshot'];
    protected function casts(): array { return ['base_amount'=>'decimal:2','approved_claim_total'=>'decimal:2','payable_amount'=>'decimal:2','snapshot'=>'array']; }
}
