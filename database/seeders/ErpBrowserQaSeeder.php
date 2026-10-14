<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ErpBrowserQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            throw new \RuntimeException('Browser QA fixtures cannot be seeded outside a local/test environment.');
        }

        $password = getenv('ERP_E2E_PASSWORD');
        if (! is_string($password) || strlen($password) < 16) {
            throw new \RuntimeException('ERP_E2E_PASSWORD must be set to a temporary test-only secret.');
        }

        if (DB::table('companies')->where('slug', 'rac')->exists()) {
            throw new \RuntimeException('Browser QA seed refused: firm already exists.');
        }

        DB::transaction(function () use ($password) {
            $companyId = DB::table('companies')->insertGetId([
                'slug'=>'rac', 'name'=>'Rahman Anis & Co.',
                'timezone'=>'Asia/Dhaka', 'office_start'=>'10:00:00',
                'office_end'=>'18:00:00', 'grace_minutes'=>10,
                'weekly_holidays'=>'[5,6]', 'active'=>true,
                'created_at'=>now(), 'updated_at'=>now(),
            ]);

            $managerWorkspace = null;
            $firstPartnerId = null;
            for ($i = 1; $i <= 3; $i++) {
                $workspaceId = DB::table('workspaces')->insertGetId([
                    'company_id'=>$companyId,'slug'=>"partner-0$i",
                    'name'=>"Partner Unit 0$i", 'active'=>true,
                    'created_at'=>now(), 'updated_at'=>now(),
                ]);
                if ($i === 1) $managerWorkspace = $workspaceId;

                $user = User::create([
                    'name'=>"QA Partner $i",
                    'email'=>$i === 1 ? 'e2e.partner@example.test' : "e2e.partner$i@example.test",
                    'password'=>Hash::make($password),
                ]);
                $memberId = DB::table('memberships')->insertGetId([
                    'company_id'=>$companyId,'workspace_id'=>$workspaceId,
                    'user_id'=>$user->id,'role'=>'partner',
                    'active'=>true,'monthly_allowance'=>'0.00',
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                if ($i === 1) $firstPartnerId=$memberId;
            }

            $manager = User::create([
                'name'=>'QA Manager',
                'email'=>'e2e.manager@example.test',
                'password'=>Hash::make($password),
            ]);
            $managerId = DB::table('memberships')->insertGetId([
                'company_id'=>$companyId,'workspace_id'=>$managerWorkspace,
                'user_id'=>$manager->id,'role'=>'manager','active'=>true,
                'monthly_allowance'=>'0.00','created_at'=>now(),'updated_at'=>now(),
            ]);

            $student = User::create([
                'name'=>'QA Student',
                'email'=>'e2e.student@example.test',
                'password'=>Hash::make($password),
            ]);
            DB::table('memberships')->insert([
                'company_id'=>$companyId,'workspace_id'=>$managerWorkspace,
                'user_id'=>$student->id,'role'=>'student',
                'manager_membership_id'=>$managerId,
                'active'=>true,'monthly_allowance'=>'8000.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);

            $platform = User::create([
                'name'=>'QA Platform Operator',
                'email'=>'e2e.platform@example.test',
                'password'=>Hash::make($password),
            ]);
            $platform->forceFill(['is_platform_admin'=>true])->save();
        }, 3);
    }
}