<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollClaimAllocation extends Model {
    protected $fillable=['payroll_item_id','conveyance_claim_id','amount'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
}
