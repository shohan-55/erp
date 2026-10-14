<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Workspace;
use Illuminate\Console\Command;

class AddPartnerWorkspace extends Command
{
    protected $signature = 'erp:add-workspace {companySlug} {name}';
    protected $description = 'Create a partner-owned workspace under an existing active company';

    public function handle(): int
    {
        $company = Company::query()->where('slug', $this->argument('companySlug'))->where('is_active', true)->first();
        if (! $company) {
            $this->error('Company not found or inactive.');
            return self::FAILURE;
        }

        $name = trim($this->argument('name'));
        if ($name === '' || mb_strlen($name) > 150) {
            $this->error('Workspace name must be 1–150 characters.');
            return self::FAILURE;
        }

        if (Workspace::query()->where('company_id', $company->id)->where('name', $name)->exists()) {
            $this->error('Workspace name already exists in this company.');
            return self::FAILURE;
        }

        $workspace = Workspace::create(['company_id' => $company->id, 'name' => $name, 'type' => 'partner']);
        $this->info("Workspace created with ID {$workspace->id}. Add users with erp:add-member.");
        return self::SUCCESS;
    }
}
