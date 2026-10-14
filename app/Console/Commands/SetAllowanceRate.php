<?php

namespace App\Console\Commands;

use App\Models\AllowanceRate;
use App\Models\Company;
use App\Models\CompanyMembership;
use Illuminate\Console\Command;

class SetAllowanceRate extends Command
{
    protected $signature = 'erp:set-allowance {companySlug} {membershipId} {effectiveMonth} {amount}';
    protected $description = 'Record an explicitly approved, effective-month student allowance (server operator only)';

    public function handle(): int
    {
        $company = Company::query()->where('slug',$this->argument('companySlug'))->where('is_active',true)->first();
        $member = $company ? CompanyMembership::query()->where('company_id',$company->id)
            ->where('role','student')->where('is_active',true)->whereKey($this->argument('membershipId'))->first() : null;

        if (! $company || ! $member
            || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$this->argument('effectiveMonth'))
            || ! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/',$this->argument('amount'))
            || (float)$this->argument('amount') > 1000000
        ) {
            $this->error('Invalid active student, month, or allowance (must be 0–1,000,000 BDT).');
            return self::FAILURE;
        }

        if (AllowanceRate::query()->where('membership_id',$member->id)
            ->where('effective_month',$this->argument('effectiveMonth'))->exists()) {
            $this->error('An allowance rate already exists for this student/effective month. Historical rates must not be silently overwritten.');
            return self::FAILURE;
        }

        if (! $this->confirm('Confirm you have verified an authorized allowance policy for '.$this->argument('effectiveMonth').' and membership #'.$member->id.'?',false)) {
            $this->warn('No changes made.');
            return self::FAILURE;
        }

        AllowanceRate::create([
            'company_id'=>$company->id,'workspace_id'=>$member->workspace_id,
            'membership_id'=>$member->id,'effective_month'=>$this->argument('effectiveMonth'),
            'base_amount'=>$this->argument('amount'),
        ]);
        $this->info('Approved allowance version recorded. Existing finalized payroll snapshots remain unchanged.');
        return self::SUCCESS;
    }
}
