<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\PartnerPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Platform Super Admins
        User::updateOrCreate(
            ['email' => 'infobd.sohan@gmail.com'],
            [
                'name' => 'Sohan Hossain',
                'password' => Hash::make('password'),
                'role' => 'platform_admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@dhakawala.com'],
            [
                'name' => 'DhakaWala Platform Admin',
                'password' => Hash::make('password'),
                'role' => 'platform_admin',
            ]
        );

        // 2. Create Firms
        $rac = Firm::updateOrCreate(
            ['slug' => 'rac'],
            [
                'name' => 'Rahman Anis & Co.',
                'kind' => 'Chartered Accountants',
                'email' => 'support@rahmananis.com',
                'status' => 'active',
                'workspaces' => 3,
                'seats' => 12,
                'plan' => 'Practice',
                'region' => 'Dhaka',
                'initials' => 'RA',
            ]
        );

        Firm::updateOrCreate(
            ['slug' => 'sample-alpha'],
            [
                'name' => 'Sample Practice Alpha',
                'kind' => 'Chartered Accountants',
                'email' => 'info@samplealpha.com',
                'status' => 'active',
                'workspaces' => 2,
                'seats' => 8,
                'plan' => 'Starter',
                'region' => 'Dhaka',
                'initials' => 'SA',
            ]
        );

        // 3. Create Multi-Partner Architecture for RA&C
        // Partner 1: Partner Unit 01 (Senior Executive Practice)
        $partner1 = User::updateOrCreate(
            ['email' => 'mohitul.haq@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'name' => 'A.K.M. Mohitul Haq, FCA',
                'password' => Hash::make('password'),
                'role' => 'partner',
                'partner_unit' => 'Partner Unit 01',
                'can_proxy_attendance' => true,
                'can_edit_attendance' => true,
                'can_approve_leave' => true,
                'can_approve_claims' => true,
                'monthly_allowance' => 0,
            ]
        );

        // Partner Policy for Unit 01
        PartnerPolicy::updateOrCreate(
            ['partner_id' => $partner1->id],
            [
                'firm_id' => $rac->id,
                'partner_unit' => 'Partner Unit 01',
                'office_start' => '10:00',
                'office_end' => '18:00',
                'grace_minutes' => 10,
                'manager_conveyance_limit' => 300.00,
                'conveyance_late_days' => 3,
                'student_grids' => PartnerPolicy::defaultStudentGrids(),
                'manager_permissions' => PartnerPolicy::defaultManagerPermissions(),
            ]
        );

        // Partner 2: Partner Unit 02 (Audit & Assurance Unit)
        $partner2 = User::updateOrCreate(
            ['email' => 'anwar.partner@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'name' => 'Anwarul Kabir, FCA',
                'password' => Hash::make('password'),
                'role' => 'partner',
                'partner_unit' => 'Partner Unit 02',
                'can_proxy_attendance' => true,
                'can_edit_attendance' => true,
                'can_approve_leave' => true,
                'can_approve_claims' => true,
                'monthly_allowance' => 0,
            ]
        );

        // Partner Policy for Unit 02 (Independent custom policy)
        PartnerPolicy::updateOrCreate(
            ['partner_id' => $partner2->id],
            [
                'firm_id' => $rac->id,
                'partner_unit' => 'Partner Unit 02',
                'office_start' => '09:30',
                'office_end' => '17:30',
                'grace_minutes' => 15,
                'manager_conveyance_limit' => 500.00,
                'conveyance_late_days' => 5,
                'student_grids' => PartnerPolicy::defaultStudentGrids(),
                'manager_permissions' => PartnerPolicy::defaultManagerPermissions(),
            ]
        );

        // Managers
        $managerA = User::updateOrCreate(
            ['email' => 'tanvir.ahmed@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'name' => 'Khandaker Tanvir Ahmed, ACA',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'partner_unit' => 'Partner Unit 01',
                'can_proxy_attendance' => true,
                'can_edit_attendance' => true,
                'can_approve_leave' => true,
                'can_approve_claims' => true,
                'monthly_allowance' => 0,
            ]
        );

        $managerB = User::updateOrCreate(
            ['email' => 'farhana.manager@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'name' => 'Farhana Rahman, ACA',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'partner_unit' => 'Partner Unit 01',
                'can_proxy_attendance' => false,
                'can_edit_attendance' => false,
                'can_approve_leave' => true,
                'can_approve_claims' => false,
                'monthly_allowance' => 0,
            ]
        );

        // Students under Partner Unit 01
        $s1 = User::updateOrCreate(
            ['email' => 'zubair.student@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'student_code' => 'S-101',
                'name' => 'Zubair Hossain Siddique',
                'password' => Hash::make('password'),
                'role' => 'student',
                'manager_id' => $managerA->id,
                'partner_unit' => 'Partner Unit 01',
                'monthly_allowance' => 8000,
            ]
        );

        $s2 = User::updateOrCreate(
            ['email' => 'nusrat.student@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'student_code' => 'S-102',
                'name' => 'Nusrat Jahan',
                'password' => Hash::make('password'),
                'role' => 'student',
                'manager_id' => $managerA->id,
                'partner_unit' => 'Partner Unit 01',
                'monthly_allowance' => 8000,
            ]
        );

        $s3 = User::updateOrCreate(
            ['email' => 'rakibul.student@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'student_code' => 'S-103',
                'name' => 'Rakibul Hasan',
                'password' => Hash::make('password'),
                'role' => 'student',
                'manager_id' => $managerB->id,
                'partner_unit' => 'Partner Unit 01',
                'monthly_allowance' => 8000,
            ]
        );

        // Student under Partner Unit 02
        $s4 = User::updateOrCreate(
            ['email' => 'tania.student@rahmananis.com'],
            [
                'firm_id' => $rac->id,
                'student_code' => 'S-104',
                'name' => 'Tania Akter',
                'password' => Hash::make('password'),
                'role' => 'student',
                'manager_id' => $managerA->id,
                'partner_unit' => 'Partner Unit 02',
                'monthly_allowance' => 8500,
            ]
        );

        $today = now()->toDateString();

        // 4. Attendances
        Attendance::updateOrCreate(
            ['user_id' => $s1->id, 'date' => $today],
            [
                'firm_id' => $rac->id,
                'work_mode' => 'office',
                'in_time' => now()->setTime(9, 58),
                'in_lat' => 23.7516000,
                'in_lng' => 90.3943000,
                'in_location_name' => 'EDB Trade Centre, Karwan Bazar, Dhaka',
                'out_time' => now()->setTime(18, 05),
                'out_lat' => 23.7516000,
                'out_lng' => 90.3943000,
                'out_location_name' => 'EDB Trade Centre, Karwan Bazar, Dhaka',
                'status' => 'On time',
            ]
        );

        Attendance::updateOrCreate(
            ['user_id' => $s2->id, 'date' => $today],
            [
                'firm_id' => $rac->id,
                'work_mode' => 'client',
                'client_site' => 'Square Pharmaceuticals Audit Site',
                'in_time' => now()->setTime(10, 18),
                'in_lat' => 23.7279000,
                'in_lng' => 90.4178000,
                'in_location_name' => 'Motijheel C/A, Dhaka',
                'out_time' => now()->setTime(18, 20),
                'out_lat' => 23.7279000,
                'out_lng' => 90.4178000,
                'out_location_name' => 'Motijheel C/A, Dhaka',
                'status' => 'Late',
            ]
        );

        // 5. Multi-Tier Leave Requests
        LeaveRequest::updateOrCreate(
            ['user_id' => $s1->id, 'start_date' => now()->addDays(2)->toDateString()],
            [
                'firm_id' => $rac->id,
                'leave_type' => 'annual',
                'end_date' => now()->addDays(4)->toDateString(),
                'reason' => 'Family visit outside Dhaka',
                'status' => 'pending_manager',
                'reviewed_by' => null,
            ]
        );

        LeaveRequest::updateOrCreate(
            ['user_id' => $s2->id, 'start_date' => now()->addDays(10)->toDateString()],
            [
                'firm_id' => $rac->id,
                'leave_type' => 'icab_exam',
                'end_date' => now()->addDays(15)->toDateString(),
                'reason' => 'ICAB Certificate Level Final Exam Preparation',
                'status' => 'pending_partner',
                'reviewed_by' => $managerA->id,
                'manager_remarks' => 'Verified with ICAB exam admit card. Recommended for Partner approval.',
                'partner_id' => $partner1->id,
            ]
        );

        // 6. Multi-Tier Conveyance Claims with Client Tagging & Threshold Rules
        ConveyanceClaim::updateOrCreate(
            ['user_id' => $s1->id, 'claim_date' => $today],
            [
                'firm_id' => $rac->id,
                'client_name' => 'BEXIMCO Textiles Audit',
                'route' => 'Karwan Bazar HQ → BEXIMCO Head Office, Dhanmondi → Return',
                'purpose' => 'Physical inventory count and audit working paper submission',
                'original_amount' => 220.00,
                'amount' => 220.00,
                'is_late' => false,
                'status' => 'approved',
                'reviewed_by' => $managerA->id,
                'manager_remarks' => 'Standard local fare. Approved within manager limit (<= ৳300).',
            ]
        );

        ConveyanceClaim::updateOrCreate(
            ['user_id' => $s2->id, 'claim_date' => now()->subDay()->toDateString()],
            [
                'firm_id' => $rac->id,
                'client_name' => 'Square Pharmaceuticals Audit',
                'route' => 'Karwan Bazar HQ → Kaliakair Plant, Gazipur → Return',
                'purpose' => 'External audit field inspection and fixed asset verification',
                'original_amount' => 650.00,
                'amount' => 550.00,
                'adjustment_remark' => 'Deducted non-approved AC cab premium; approved Express bus + local CNG fare.',
                'is_late' => false,
                'status' => 'pending_partner',
                'reviewed_by' => $managerA->id,
                'partner_id' => $partner1->id,
                'manager_remarks' => 'Amount exceeds ৳300 Manager limit. Adjusted to ৳550 and escalated to Partner.',
            ]
        );

        ConveyanceClaim::updateOrCreate(
            ['user_id' => $s3->id, 'claim_date' => now()->subDays(6)->toDateString()],
            [
                'firm_id' => $rac->id,
                'client_name' => 'Brac Bank Motijheel Branch',
                'route' => 'Karwan Bazar → Motijheel Commercial Branch → Karwan Bazar',
                'purpose' => 'Bank branch audit and loan document inspection',
                'original_amount' => 180.00,
                'amount' => 180.00,
                'is_late' => true,
                'status' => 'pending_partner',
                'reviewed_by' => $managerB->id,
                'partner_id' => $partner1->id,
                'manager_remarks' => 'Submitted 6 days late (> 3 days policy). Escalated to Partner for late exception approval.',
            ]
        );
    }
}
