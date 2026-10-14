<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AllowanceRate extends Model {
    protected $fillable=['company_id','workspace_id','membership_id','effective_month','base_amount'];
    protected function casts(): array { return ['base_amount'=>'decimal:2']; }
}
