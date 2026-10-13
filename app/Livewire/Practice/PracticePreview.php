<?php

namespace App\Livewire\Practice;

use App\Services\FirmRegistry;
use Livewire\Component;

class PracticePreview extends Component
{
    public string $firmSlug = 'rac';
    public ?array $firm = null;
    public string $previewRole = 'partner';
    public string $section = 'dashboard';
    public string $search = '';
    public string $period = '2026-10';
    public string $filter = 'all';
    public bool $sidebarOpen = false;
    public bool $showPolicyModal = false;
    public bool $showCorrectionModal = false;
    public bool $showClaimReviewModal = false;
    public bool $showLeaveReviewModal = false;
    public ?string $notice = null;
    public ?string $error = null;

    // Design-prototype-only fields. Nothing persists to a database.
    public string $workMode = 'office';
    public bool $previewSessionActive = false;
    public string $clientSite = 'Sample Client Site';
    public string $leaveType = 'annual';
    public string $leaveStart = '';
    public string $leaveEnd = '';
    public string $leaveReason = '';
    public string $claimDate = '';
    public string $claimAmount = '';
    public string $claimRoute = '';
    public string $claimPurpose = '';
    public array $readNotificationIds = [];

    private const ROLES = ['partner', 'manager', 'student'];
    private const SECTIONS = [
        'dashboard', 'attendance', 'leave', 'conveyance', 'payroll',
        'notifications', 'policies', 'settings',
    ];
    private const NOTIFICATION_IDS = ['demo-note-1', 'demo-note-2', 'demo-note-3'];

    public function mount(string $firmSlug, string $previewRole = 'partner', string $section = 'dashboard'): void
    {
        $this->firmSlug = strtolower(trim($firmSlug));
        $this->firm = FirmRegistry::get($this->firmSlug);
        $this->previewRole = in_array($previewRole, self::ROLES, true) ? $previewRole : 'partner';
        $this->section = in_array($section, self::SECTIONS, true) ? $section : 'dashboard';
    }

    public function setSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return;
        }

        $this->section = $section;
        $this->sidebarOpen = false;
        $this->search = '';
        $this->filter = 'all';
        $this->closeDialogs();
        $this->error = null;
        $this->notice = null;
    }

    public function setPreviewRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            return;
        }

        // Role switching is intentionally a PUBLIC visual demo, not impersonation.
        $this->previewRole = $role;
        $this->section = 'dashboard';
        $this->sidebarOpen = false;
        $this->search = '';
        $this->filter = 'all';
        $this->notice = 'Design preview role changed. No authenticated user, permission, or tenant data was changed.';
        $this->error = null;
        $this->closeDialogs();
        $this->previewSessionActive = false;
    }

    public function openDialog(string $kind): void
    {
        $this->closeDialogs();

        match ($kind) {
            'policy' => $this->showPolicyModal = $this->previewRole === 'partner',
            'correction' => $this->showCorrectionModal = $this->previewRole !== 'student',
            'claim' => $this->showClaimReviewModal = $this->previewRole !== 'student',
            'leave' => $this->showLeaveReviewModal = $this->previewRole !== 'student',
            default => null,
        };
    }

    public function closeDialogs(): void
    {
        $this->showPolicyModal = false;
        $this->showCorrectionModal = false;
        $this->showClaimReviewModal = false;
        $this->showLeaveReviewModal = false;
    }

    public function acknowledgeDialog(string $kind): void
    {
        $allowed = match ($kind) {
            'policy' => $this->showPolicyModal,
            'correction' => $this->showCorrectionModal,
            'claim' => $this->showClaimReviewModal,
            'leave' => $this->showLeaveReviewModal,
            default => false,
        };

        if (! $allowed) {
            return;
        }

        $this->closeDialogs();
        $this->notice = match ($kind) {
            'policy' => 'Policy proposal illustrated only. No partner votes, approvals or policy revisions were saved.',
            'correction' => 'Attendance correction workflow illustrated only. No attendance record was changed.',
            'claim' => 'Claim review illustrated only. No payment or expense approval was recorded.',
            'leave' => 'Leave review illustrated only. No leave request, entitlement or approval was changed.',
            default => 'This is a design-only demonstration.',
        };
    }

    public function togglePreviewSession(): void
    {
        $this->error = null;
        if ($this->previewRole !== 'student') {
            $this->notice = 'Attendance self check-in preview is available only in the Student design role.';
            return;
        }

        if (! in_array($this->workMode, ['office', 'client'], true)) {
            $this->error = 'Select a valid attendance mode.';
            return;
        }

        if (! $this->previewSessionActive && $this->workMode === 'client' && trim($this->clientSite) === '') {
            $this->error = 'Choose a demo client site before checking in.';
            return;
        }

        $this->previewSessionActive = ! $this->previewSessionActive;
        $this->notice = $this->previewSessionActive
            ? 'Check-in appearance simulated. No timestamp, geolocation or attendance record was saved.'
            : 'Check-out appearance simulated. No attendance record, hours or payroll was updated.';
    }

    public function submitLeaveDemo(): void
    {
        $this->error = null;
        $this->notice = null;
        if ($this->previewRole !== 'student') {
            $this->error = 'Only the Student design role can preview a new leave application.';
            return;
        }

        if (! in_array($this->leaveType, ['annual', 'sick', 'emergency'], true)
            || ! $this->validDate($this->leaveStart)
            || ! $this->validDate($this->leaveEnd)
            || $this->leaveEnd < $this->leaveStart
            || mb_strlen(trim($this->leaveReason)) < 5
        ) {
            $this->error = 'Enter a leave type, valid start and end dates, and a reason of at least five characters.';
            return;
        }

        $this->notice = 'Leave request preview validated. No request was submitted and no leave balance was changed.';
    }

    public function submitClaimDemo(): void
    {
        $this->error = null;
        $this->notice = null;
        if ($this->previewRole !== 'student') {
            $this->error = 'Only the Student design role can preview a new conveyance claim.';
            return;
        }

        $amount = filter_var($this->claimAmount, FILTER_VALIDATE_FLOAT);
        if (! $this->validDate($this->claimDate)
            || $amount === false || $amount <= 0 || $amount > 50000
            || mb_strlen(trim($this->claimRoute)) < 3
            || mb_strlen(trim($this->claimPurpose)) < 5
        ) {
            $this->error = 'Enter a valid date, amount (1–50,000), journey, and a purpose of at least five characters.';
            return;
        }

        $this->notice = 'Conveyance claim preview validated. No invoice, receipt, reimbursement or payment was recorded.';
    }

    public function markPreviewRead(string $id): void
    {
        if (! in_array($id, self::NOTIFICATION_IDS, true)) {
            return;
        }

        if (! in_array($id, $this->readNotificationIds, true)) {
            $this->readNotificationIds[] = $id;
        }

        $this->notice = 'Read status updated in this temporary browser preview only.';
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
        $this->error = null;
    }

    private function validDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public function render()
    {
        $studentRows = [
            ['id' => 'S-101', 'name' => 'Sample Student 01', 'manager' => 'Sample Manager A', 'partner' => 'Partner Unit 01', 'status' => 'On time', 'mode' => 'Office', 'day' => 'Mon', 'checkin' => '10:05', 'checkout' => '18:00'],
            ['id' => 'S-102', 'name' => 'Sample Student 02', 'manager' => 'Sample Manager A', 'partner' => 'Partner Unit 01', 'status' => 'Late', 'mode' => 'Client', 'day' => 'Tue', 'checkin' => '10:14', 'checkout' => '18:15'],
            ['id' => 'S-103', 'name' => 'Sample Student 03', 'manager' => 'Sample Manager B', 'partner' => 'Partner Unit 01', 'status' => 'Incomplete', 'mode' => 'Office', 'day' => 'Wed', 'checkin' => '10:08', 'checkout' => '—'],
            ['id' => 'S-104', 'name' => 'Sample Student 04', 'manager' => 'Sample Manager B', 'partner' => 'Partner Unit 01', 'status' => 'On time', 'mode' => 'Office', 'day' => 'Thu', 'checkin' => '10:01', 'checkout' => '18:10'],
        ];

        // Design-only scope example. Manager A cannot see Manager B's students;
        // a student sees only their own record. This is NOT authorization.
        $rows = match ($this->previewRole) {
            'manager' => array_values(array_filter(
                $studentRows, fn (array $row) => $row['manager'] === 'Sample Manager A'
            )),
            'student' => array_values(array_filter(
                $studentRows, fn (array $row) => $row['id'] === 'S-101'
            )),
            default => $studentRows,
        };

        if (trim($this->search) !== '') {
            $query = mb_strtolower(trim($this->search));
            $rows = array_values(array_filter(
                $rows,
                fn (array $row) => str_contains(mb_strtolower($row['name'].' '.$row['id'].' '.$row['mode']), $query)
            ));
        }

        if ($this->filter !== 'all') {
            $rows = array_values(array_filter(
                $rows, fn (array $row) => strtolower(str_replace(' ', '-', $row['status'])) === $this->filter
            ));
        }

        return view('livewire.practice.preview', [
            'rows' => $rows,
            'allRows' => $studentRows,
            'roleLabel' => match ($this->previewRole) {
                'manager' => 'Audit Manager',
                'student' => 'Articled Student',
                default => 'Partner / Principal',
            },
            'sectionLabel' => [
                'dashboard' => 'Overview',
                'attendance' => 'Attendance',
                'leave' => 'Leave Management',
                'conveyance' => 'Conveyance Claims',
                'payroll' => 'Monthly Allowance & Payroll',
                'notifications' => 'Notifications',
                'policies' => 'Policy & Governance',
                'settings' => 'Workspace Settings',
            ][$this->section],
        ])->layout('layouts.practice', ['title' => 'RA&C ERP — '.ucfirst($this->section).' Design Preview']);
    }
}
