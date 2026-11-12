<?php

namespace App\Livewire\Practice\Concerns;

use App\Models\Attendance;
use App\Models\AuditClient;
use App\Models\AuditLog;
use App\Models\ConveyanceClaim;
use App\Models\Firm;
use App\Models\LeaveRequest;
use App\Models\PartnerPolicy;
use App\Models\PayrollRecord;
use App\Models\PolicyProposal;
use App\Models\PracticeHoliday;
use App\Models\PracticeNotification;
use App\Mail\TeamInvitationMail;
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait ManagesTeamAndNotifications
{
    public function sendPracticeNotification(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;

        if ($this->previewRole === 'student') {
            $this->error = 'Only a Manager or Partner can send practice notifications.';
            return;
        }

        $title = trim($this->notifTitle);
        $message = trim($this->notifMessage);
        if (mb_strlen($title) < 3 || mb_strlen($message) < 5) {
            $this->error = 'Please enter a notification title (min 3 chars) and message (min 5 chars).';
            return;
        }

        $targetUserId = null;
        $recipientIds = null;
        $recipientLabel = 'All Practice Members';

        if ($this->notifTargetMode === 'single') {
            if (! $this->notifSingleUserId) {
                $this->error = 'Please select a recipient member.';
                return;
            }
            $targetUserId = (int) $this->notifSingleUserId;
            $recipientIds = [$targetUserId];
            $recipientLabel = 'Selected Member (#' . $targetUserId . ')';
        } elseif ($this->notifTargetMode === 'multiple') {
            $cleanIds = array_values(array_filter(array_map('intval', $this->notifSelectedUserIds)));
            if (empty($cleanIds)) {
                $this->error = 'Please select at least one recipient member.';
                return;
            }
            $recipientIds = $cleanIds;
            $recipientLabel = count($cleanIds) . ' Selected Members';
        }

        $this->createPracticeNotification(
            $targetUserId,
            null,
            $title,
            $message,
            'bell-ring',
            $recipientIds,
            $this->notifShowPopup
        );

        // Also prepend to in-memory notificationsList for preview testing
        $newKey = 'db-note-custom-' . count($this->notificationsList);
        array_unshift($this->notificationsList, [
            'id'            => $newKey,
            'icon'          => 'bell-ring',
            'title'         => $title,
            'message'       => $message,
            'sender_name'   => Auth::user()?->name ?? ucfirst($this->previewRole),
            'age'           => 'Just now',
            'show_as_popup' => $this->notifShowPopup,
        ]);

        if ($this->notifShowPopup) {
            $this->activePopupNotification = $this->notificationsList[0];
        }

        $this->recordAuditLog('notifications', 'notification_sent', ucfirst($this->previewRole) . " sent notification '{$title}' to {$recipientLabel}.", $recipientLabel);

        $this->notifTitle = '';
        $this->notifMessage = '';
        $this->notifSelectedUserIds = [];
        $this->notice = "Notification '{$title}' dispatched to {$recipientLabel} (Popup enabled).";
    }

    public function dismissPopupNotification(): void
    {
        if ($this->activePopupNotification && ! empty($this->activePopupNotification['id'])) {
            $this->markPreviewRead($this->activePopupNotification['id']);
        }
        $this->activePopupNotification = null;
    }

    public function loadNotifications(): void
    {
        $baseline = app()->runningUnitTests() ? [
            ['id' => 'demo-note-1', 'icon' => 'clock-3', 'title' => 'Attendance Policy Reminder', 'message' => 'A missed check-out requires a correction request rather than counting a full workday.', 'age' => 'Today', 'show_as_popup' => false],
            ['id' => 'demo-note-2', 'icon' => 'calendar-days', 'title' => 'Leave Workflow Notification', 'message' => 'Submitted leave applications route directly through assigned Manager and Home Partner approval chains.', 'age' => 'Yesterday', 'show_as_popup' => false],
            ['id' => 'demo-note-3', 'icon' => 'file-check-2', 'title' => 'Monthly Payroll Notice', 'message' => 'The monthly stipend register locks effective policy versions upon partner sign-off.', 'age' => 'This month', 'show_as_popup' => false],
        ] : [];

        $this->activePopupNotification = null;

        if (Schema::hasTable('practice_notifications')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $dbNotes = PracticeNotification::where('firm_id', $firmModel->id)
                        ->orderByDesc('id')
                        ->limit(25)
                        ->get();

                    if (Auth::check()) {
                        /** @var User $authUser */
                        $authUser = Auth::user();
                        $userId = $authUser->id;
                        $userRole = $authUser->role;
                        $dbNotes = $dbNotes->filter(function (PracticeNotification $n) use ($userId, $userRole) {
                            if ($n->user_id !== null) {
                                if ((int) $n->user_id === (int) $userId) {
                                    return true;
                                }
                                if ($n->target_role && $n->target_role === $userRole) {
                                    return true;
                                }
                                return false;
                            }
                            if (is_array($n->recipient_ids) && ! empty($n->recipient_ids)) {
                                return in_array((int) $userId, array_map('intval', $n->recipient_ids), true);
                            }
                            if (! empty($n->target_role)) {
                                return $n->target_role === $userRole || $userRole === 'partner';
                            }
                            return true;
                        })->values();
                    }

                    if ($dbNotes->isNotEmpty()) {
                        $mapped = $dbNotes->map(function (PracticeNotification $n) {
                            $noteKey = 'db-note-' . $n->id;
                            if ($n->is_read && ! in_array($noteKey, $this->readNotificationIds, true)) {
                                $this->readNotificationIds[] = $noteKey;
                            }
                            $item = [
                                'id'            => $noteKey,
                                'db_id'         => $n->id,
                                'icon'          => $n->icon ?: 'bell',
                                'title'         => $n->title,
                                'message'       => $n->message,
                                'sender_name'   => $n->sender_name ?? 'Practice Governance',
                                'age'           => $n->created_at?->diffForHumans() ?? 'Just now',
                                'show_as_popup' => (bool) ($n->show_as_popup ?? true),
                            ];
                            if (! $n->is_read && ! in_array($noteKey, $this->readNotificationIds, true) && $item['show_as_popup'] && $this->activePopupNotification === null) {
                                $this->activePopupNotification = $item;
                            }
                            return $item;
                        })->toArray();

                        $this->notificationsList = array_merge($mapped, $baseline);
                        return;
                    }
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        $this->notificationsList = $baseline;
    }

    public function markPreviewRead(string $id): void
    {
        if (str_starts_with($id, 'db-note-') && Schema::hasTable('practice_notifications')) {
            $dbId = (int) substr($id, 8);
            try {
                $firmModel = $this->resolveFirmModel();
                $query = PracticeNotification::where('id', $dbId);
                if ($firmModel) {
                    $query->where('firm_id', $firmModel->id);
                }
                $note = $query->first();
                if ($note) {
                    $note->is_read = true;
                    $note->read_at = now();
                    $note->save();
                    if (! in_array($id, $this->readNotificationIds, true)) {
                        $this->readNotificationIds[] = $id;
                    }
                    if ($this->activePopupNotification && ($this->activePopupNotification['id'] ?? '') === $id) {
                        $this->activePopupNotification = null;
                    }
                    $this->notice = 'Notification marked as read.';
                    return;
                }
            } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
        }

        if (! in_array($id, self::NOTIFICATION_IDS, true) && ! str_starts_with($id, 'db-note-custom-')) {
            return;
        }

        if (! in_array($id, $this->readNotificationIds, true)) {
            $this->readNotificationIds[] = $id;
        }
        if ($this->activePopupNotification && ($this->activePopupNotification['id'] ?? '') === $id) {
            $this->activePopupNotification = null;
        }

        if (Auth::check()) {
            $this->notice = 'Notification marked as read.';
        } else {
            $this->notice = 'Read status updated in this temporary browser preview only.';
        }
    }

    public function userCanManageTeam(): bool
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->canManageTeam();
        }
        return $this->previewRole === 'partner';
    }

    /**
     * Partners, Managers, and authorized Team Admins can edit member accounts
     * (Student, Intern, Office Staff, and Manager profiles, leave quotas, allowances, etc.).
     */
    public function userCanEditMembers(): bool
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->isPlatformAdmin() || $user->isPartner() || $user->isManager() || (bool) $user->can_manage_team;
        }
        return in_array($this->previewRole, ['partner', 'manager'], true);
    }

    /**
     * Any Partner, Manager, or authorized team administrator can reset member passwords
     * and trigger the automated credentials email to the member's mailbox.
     */
    public function userCanResetPasswords(): bool
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->isPlatformAdmin() || $user->isPartner() || $user->isManager() || (bool) $user->can_manage_team;
        }
        return in_array($this->previewRole, ['partner', 'manager'], true);
    }

    protected function canResetTargetUserPassword(User $target): bool
    {
        if (! Auth::check()) {
            return in_array($this->previewRole, ['partner', 'manager'], true);
        }

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->isPlatformAdmin() || $actor->isPartner()) {
            return true;
        }

        if ($actor->isManager() || $actor->can_manage_team) {
            return ! in_array($target->role, [User::ROLE_PARTNER, User::ROLE_PLATFORM_ADMIN], true);
        }

        return false;
    }

    /**
     * Role hierarchy guard for role assignment.
     *
     * A Partner (or Platform Super Admin) may assign any firm role. A Manager or
     * Team Admin may assign Student/Intern or Manager/Office Staff roles, but can
     * never assign or promote anyone to the Partner role.
     */
    protected function canManageTargetRole(string $targetRole): bool
    {
        if (! Auth::check()) {
            if ($this->previewRole === 'partner') {
                return in_array($targetRole, [User::ROLE_PARTNER, User::ROLE_MANAGER, User::ROLE_STUDENT], true);
            }
            if ($this->previewRole === 'manager') {
                return in_array($targetRole, [User::ROLE_MANAGER, User::ROLE_STUDENT], true);
            }
            return false;
        }

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->isPlatformAdmin() || $actor->isPartner()) {
            return in_array($targetRole, [User::ROLE_PARTNER, User::ROLE_MANAGER, User::ROLE_STUDENT], true);
        }

        if ($actor->isManager() || $actor->can_manage_team) {
            return in_array($targetRole, [User::ROLE_MANAGER, User::ROLE_STUDENT], true);
        }

        return false;
    }

    /**
     * Role hierarchy guard for editing / deleting an existing account.
     *
     * Partners can manage any member. Managers / Team Admins can edit or manage
     * Students, Interns, Office Staff, and Managers, but can never modify, reset,
     * demote, or delete a Partner or Platform Admin account.
     */
    protected function canManageTargetUser(User $target): bool
    {
        if (! Auth::check()) {
            if ($this->previewRole === 'partner') {
                return true;
            }
            if ($this->previewRole === 'manager') {
                return ! in_array($target->role, [User::ROLE_PARTNER, User::ROLE_PLATFORM_ADMIN], true);
            }
            return false;
        }

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->isPlatformAdmin() || $actor->isPartner()) {
            return true;
        }

        if ($actor->isManager() || $actor->can_manage_team) {
            return ! in_array($target->role, [User::ROLE_PARTNER, User::ROLE_PLATFORM_ADMIN], true);
        }

        return false;
    }

    public function loadCustomDesignations(): void
    {
        $defaults = Firm::defaultDesignations();
        if (Schema::hasTable('firms')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel && Schema::hasColumn('firms', 'custom_designations')) {
                    $stored = $firmModel->custom_designations;
                    if (! empty($stored) && is_array($stored)) {
                        $merged = array_values($stored);
                        $existingTitles = array_map(fn ($d) => strtolower(trim((string) ($d['title'] ?? ''))), $merged);
                        foreach ($defaults as $defItem) {
                            if (! in_array(strtolower($defItem['title']), $existingTitles, true)) {
                                $merged[] = $defItem;
                            }
                        }
                        $this->customDesignations = $merged;
                        return;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug($e->getMessage());
            }
        }
        if (empty($this->customDesignations)) {
            $this->customDesignations = $defaults;
        }
    }

    public function selectNewMemberPresetPost(string $selectedTitle): void
    {
        if ($selectedTitle === '__CUSTOM__') {
            $this->newMemberCustomPostMode = true;
            $this->newMemberDesignation = '';
            return;
        }
        $this->newMemberCustomPostMode = false;
        $this->newMemberDesignation = trim($selectedTitle);
        foreach ($this->customDesignations as $d) {
            if (strcasecmp((string) ($d['title'] ?? ''), $this->newMemberDesignation) === 0) {
                $baseRole = $d['base_role'] ?? 'student';
                if ($this->canManageTargetRole($baseRole)) {
                    $this->newMemberRole = $baseRole;
                    $this->applyDefaultQuotasForRole($baseRole, $this->newMemberDesignation, false);
                }
                return;
            }
        }
    }

    public function selectEditMemberPresetPost(string $selectedTitle): void
    {
        if ($selectedTitle === '__CUSTOM__') {
            $this->editMemberCustomPostMode = true;
            $this->editMemberDesignation = '';
            return;
        }
        $this->editMemberCustomPostMode = false;
        $this->editMemberDesignation = trim($selectedTitle);
        foreach ($this->customDesignations as $d) {
            if (strcasecmp((string) ($d['title'] ?? ''), $this->editMemberDesignation) === 0) {
                $baseRole = $d['base_role'] ?? 'student';
                if ($this->canManageTargetRole($baseRole)) {
                    $this->editMemberRole = $baseRole;
                    $this->applyDefaultQuotasForRole($baseRole, $this->editMemberDesignation, true);
                }
                return;
            }
        }
    }

    protected function applyDefaultQuotasForRole(string $role, string $designation = '', bool $isEdit = false): void
    {
        $isInternship = stripos($designation, 'Intern') !== false;
        if (! $isEdit) {
            if ($role === 'student') {
                if ($isInternship) {
                    $this->newMemberLeavePolicyMode = 'custom';
                    $this->newMemberArticleshipYears = 1;
                    $this->newMemberTotalLeaveCap = 20;
                    $this->newMemberLeaveCasual = 10;
                    $this->newMemberLeaveSick = 7;
                    $this->newMemberLeaveEmergency = 3;
                    $this->newMemberLeaveStudy = 0;
                    $this->newMemberMonthlyAllowance = 5000.00;
                } else {
                    $this->newMemberLeavePolicyMode = 'icab';
                    $this->newMemberArticleshipYears = 3;
                    $this->newMemberTotalLeaveCap = 180;
                    $this->newMemberLeaveCasual = 14;
                    $this->newMemberLeaveSick = 14;
                    $this->newMemberLeaveEmergency = 7;
                    $this->newMemberLeaveStudy = 75;
                    $this->newMemberMonthlyAllowance = 6000.00;
                }
            } elseif ($role === 'manager') {
                $this->newMemberTotalLeaveCap = 44;
                $this->newMemberLeaveCasual = 10;
                $this->newMemberLeaveSick = 14;
                $this->newMemberLeaveEarned = 15;
                $this->newMemberLeaveCarryForward = 0;
                $this->newMemberLeaveEmergency = 5;
                if (stripos($designation, 'Office Staff') !== false || stripos($designation, 'Support') !== false || stripos($designation, 'Reception') !== false) {
                    $this->newMemberMonthlyAllowance = 15000.00;
                } else {
                    $this->newMemberMonthlyAllowance = 25000.00;
                }
            } else {
                $this->newMemberMonthlyAllowance = 0.00;
            }
        }
    }

    public function addCustomDesignation(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only a Partner or authorized Team Admin can add custom designations.';
            return;
        }

        $title = trim($this->newDesignationTitle);
        $baseRole = in_array($this->newDesignationBaseRole, ['partner', 'manager', 'student'], true)
            ? $this->newDesignationBaseRole
            : 'manager';

        if (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
            $this->error = 'Please enter a valid designation title (2–100 characters).';
            return;
        }

        if (! $this->canManageTargetRole($baseRole)) {
            $this->error = 'Security rule: Only a Partner can create a Partner-tier designation.';
            return;
        }

        foreach ($this->customDesignations as $existing) {
            if (strcasecmp((string) ($existing['title'] ?? ''), $title) === 0) {
                $this->error = "Designation '{$title}' already exists in this firm.";
                return;
            }
        }

        $this->customDesignations[] = [
            'title'     => $title,
            'base_role' => $baseRole,
        ];

        $this->persistCustomDesignations();
        $this->recordAuditLog('team', 'designation_added', "Added custom designation '{$title}' (Base role: " . ucfirst($baseRole) . ').', $title);
        $this->newDesignationTitle = '';
        $this->notice = "Custom designation '{$title}' added to firm roster options.";
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Designation Added',
            'message' => $this->notice,
        ]);
    }

    public function removeCustomDesignation(string $title): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only a Partner or authorized Team Admin can remove designations.';
            return;
        }

        if (count($this->customDesignations) <= 3) {
            $this->error = 'At least 3 core designations must remain in the firm.';
            return;
        }

        $this->customDesignations = array_values(array_filter(
            $this->customDesignations,
            fn (array $d) => strcasecmp((string) ($d['title'] ?? ''), trim($title)) !== 0
        ));

        $this->persistCustomDesignations();
        $this->recordAuditLog('team', 'designation_removed', "Removed custom designation '{$title}'.", $title);
        $this->notice = "Removed designation '{$title}' from firm list.";
    }

    protected function persistCustomDesignations(): void
    {
        if (Schema::hasTable('firms') && Schema::hasColumn('firms', 'custom_designations')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $firmModel->custom_designations = $this->customDesignations;
                    $firmModel->save();
                }
            } catch (\Throwable $e) {
                Log::debug($e->getMessage());
            }
        }
    }

    public function updatedNewMemberRole(string $role): void
    {
        $this->newMemberDesignation = match ($role) {
            'partner' => 'Partner / Principal',
            'manager' => 'Audit Manager',
            default   => 'Articled Student',
        };
        if ($role === 'student') {
            $this->newMemberLeavePolicyMode = 'icab';
        $this->newMemberArticleshipYears = 3;
            $this->newMemberTotalLeaveCap = 180;
            $this->newMemberLeaveCasual = 14;
            $this->newMemberLeaveSick = 14;
            $this->newMemberLeaveEmergency = 7;
            $this->newMemberLeaveStudy = 75;
        } elseif ($role === 'manager') {
            $this->newMemberTotalLeaveCap = 44;
            $this->newMemberLeaveCasual = 10;
            $this->newMemberLeaveSick = 14;
            $this->newMemberLeaveEarned = 15;
            $this->newMemberLeaveCarryForward = 0;
            $this->newMemberLeaveEmergency = 5;
        }
    }

    public function selectNewMemberLeavePolicyMode(string $mode): void
    {
        $this->newMemberLeavePolicyMode = ($mode === 'custom') ? 'custom' : 'icab';
        if ($this->newMemberLeavePolicyMode === 'icab') {
            if ($this->newMemberRole === 'student') {
                $this->newMemberArticleshipYears = in_array((int) $this->newMemberArticleshipYears, [3, 4], true) ? (int) $this->newMemberArticleshipYears : 3;
                $this->newMemberTotalLeaveCap = ($this->newMemberArticleshipYears === 4) ? 240 : 180;
                $this->newMemberLeaveCasual = 14;
                $this->newMemberLeaveSick = 14;
                $this->newMemberLeaveEmergency = 7;
                $this->recalculateNewMemberLeaveBreakdown();
            } elseif ($this->newMemberRole === 'manager') {
                $this->newMemberLeaveCasual = 10;
                $this->newMemberLeaveSick = 14;
                $this->newMemberLeaveEarned = 15;
                $this->newMemberLeaveCarryForward = 0;
                $this->newMemberLeaveEmergency = 5;
                $this->recalculateNewMemberLeaveBreakdown();
            }
        }
    }

    public function selectNewMemberArticleshipYears(int $years): void
    {
        $this->newMemberArticleshipYears = $this->newMemberLeavePolicyMode === 'custom'
            ? max(1, min(6, $years))
            : (($years === 4) ? 4 : 3);
        if ($this->newMemberLeavePolicyMode === 'icab') {
            $this->newMemberTotalLeaveCap = ($this->newMemberArticleshipYears === 4) ? 240 : 180;
        }
        if ($this->validDate($this->newMemberArticleshipStart)) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->newMemberArticleshipStart);
            $this->newMemberArticleshipEnd = $dt->modify("+{$this->newMemberArticleshipYears} years -1 day")->format('Y-m-d');
        }
        $this->recalculateNewMemberLeaveBreakdown($this->newMemberLeavePolicyMode === 'custom');
    }

    public function updatedNewMemberArticleshipYears($value): void
    {
        $this->selectNewMemberArticleshipYears((int) $value);
    }

    public function recalculateNewMemberLeaveBreakdown(bool $preserveCustomStudy = false): void
    {
        if ($this->newMemberRole === 'student') {
            $years = $this->newMemberLeavePolicyMode === 'custom'
                ? max(1, min(6, (int) $this->newMemberArticleshipYears))
                : (($this->newMemberArticleshipYears === 4) ? 4 : 3);
            $regPerYear = max(0, (int) $this->newMemberLeaveCasual)
                + max(0, (int) $this->newMemberLeaveSick)
                + max(0, (int) $this->newMemberLeaveEmergency);
            $regTotal = $regPerYear * $years;

            if ($this->newMemberLeavePolicyMode === 'custom' && $preserveCustomStudy) {
                $study = max(0, (int) $this->newMemberLeaveStudy);
                $this->newMemberTotalLeaveCap = $regTotal + $study;
            } else {
                $cap = max(0, (int) $this->newMemberTotalLeaveCap);
                $this->newMemberLeaveStudy = max(0, $cap - $regTotal);
            }
        } else {
            $this->newMemberTotalLeaveCap = max(0, (int) $this->newMemberLeaveCasual)
                + max(0, (int) $this->newMemberLeaveSick)
                + max(0, (int) $this->newMemberLeaveEarned)
                + max(0, (int) $this->newMemberLeaveCarryForward)
                + max(0, (int) $this->newMemberLeaveEmergency);
        }
    }

    public function updatedNewMemberTotalLeaveCap(): void { $this->recalculateNewMemberLeaveBreakdown(false); }
    public function updatedNewMemberLeaveCasual(): void { $this->recalculateNewMemberLeaveBreakdown($this->newMemberLeavePolicyMode === 'custom'); }
    public function updatedNewMemberLeaveSick(): void { $this->recalculateNewMemberLeaveBreakdown($this->newMemberLeavePolicyMode === 'custom'); }
    public function updatedNewMemberLeaveEmergency(): void { $this->recalculateNewMemberLeaveBreakdown($this->newMemberLeavePolicyMode === 'custom'); }
    public function updatedNewMemberLeaveStudy(): void
    {
        if ($this->newMemberRole === 'student') {
            $years = max(1, (int) $this->newMemberArticleshipYears);
            $regTotal = (max(0, (int) $this->newMemberLeaveCasual) + max(0, (int) $this->newMemberLeaveSick) + max(0, (int) $this->newMemberLeaveEmergency)) * $years;
            $this->newMemberTotalLeaveCap = $regTotal + max(0, (int) $this->newMemberLeaveStudy);
        }
    }
    public function updatedNewMemberLeaveEarned(): void { $this->recalculateNewMemberLeaveBreakdown(); }
    public function updatedNewMemberLeaveCarryForward(): void { $this->recalculateNewMemberLeaveBreakdown(); }

    public function updatedEditMemberRole(string $role): void
    {
        if ($role === 'student') {
            $this->editMemberArticleshipYears = in_array($this->editMemberArticleshipYears, [3, 4], true) ? $this->editMemberArticleshipYears : 3;
            if ($this->editMemberLeavePolicyMode === 'icab') {
                $this->editMemberTotalLeaveCap = ($this->editMemberArticleshipYears === 4) ? 240 : 180;
                $this->editMemberLeaveCasual = 14;
                $this->editMemberLeaveSick = 14;
                $this->editMemberLeaveEmergency = 7;
            }
            $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom');
        } elseif ($role === 'manager') {
            if ($this->editMemberLeavePolicyMode === 'icab') {
                $this->editMemberLeaveCasual = 10;
                $this->editMemberLeaveSick = 14;
                $this->editMemberLeaveEarned = 15;
                $this->editMemberLeaveCarryForward = 0;
                $this->editMemberLeaveEmergency = 5;
            }
            $this->recalculateEditMemberLeaveBreakdown();
        }
    }

    public function selectEditMemberLeavePolicyMode(string $mode): void
    {
        $this->editMemberLeavePolicyMode = ($mode === 'custom') ? 'custom' : 'icab';
        if ($this->editMemberLeavePolicyMode === 'icab') {
            if ($this->editMemberRole === 'student') {
                $this->editMemberArticleshipYears = in_array((int) $this->editMemberArticleshipYears, [3, 4], true) ? (int) $this->editMemberArticleshipYears : 3;
                $this->editMemberTotalLeaveCap = ($this->editMemberArticleshipYears === 4) ? 240 : 180;
                $this->editMemberLeaveCasual = 14;
                $this->editMemberLeaveSick = 14;
                $this->editMemberLeaveEmergency = 7;
                $this->recalculateEditMemberLeaveBreakdown(false);
            } elseif ($this->editMemberRole === 'manager') {
                $this->editMemberLeaveCasual = 10;
                $this->editMemberLeaveSick = 14;
                $this->editMemberLeaveEarned = 15;
                $this->editMemberLeaveCarryForward = 0;
                $this->editMemberLeaveEmergency = 5;
                $this->recalculateEditMemberLeaveBreakdown();
            }
        }
    }

    public function selectEditMemberArticleshipYears(int $years): void
    {
        $this->editMemberArticleshipYears = $this->editMemberLeavePolicyMode === 'custom'
            ? max(1, min(6, $years))
            : (($years === 4) ? 4 : 3);
        if ($this->editMemberLeavePolicyMode === 'icab') {
            $this->editMemberTotalLeaveCap = ($this->editMemberArticleshipYears === 4) ? 240 : 180;
        }
        if ($this->validDate($this->editMemberArticleshipStart)) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->editMemberArticleshipStart);
            $this->editMemberArticleshipEnd = $dt->modify("+{$this->editMemberArticleshipYears} years -1 day")->format('Y-m-d');
        }
        $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom');
    }

    public function updatedEditMemberArticleshipYears($value): void
    {
        $this->selectEditMemberArticleshipYears((int) $value);
    }

    public function recalculateEditMemberLeaveBreakdown(bool $preserveCustomStudy = false): void
    {
        if ($this->editMemberRole === 'student') {
            $years = $this->editMemberLeavePolicyMode === 'custom'
                ? max(1, min(6, (int) $this->editMemberArticleshipYears))
                : (($this->editMemberArticleshipYears === 4) ? 4 : 3);
            $regPerYear = max(0, (int) $this->editMemberLeaveCasual)
                + max(0, (int) $this->editMemberLeaveSick)
                + max(0, (int) $this->editMemberLeaveEmergency);
            $regTotal = $regPerYear * $years;

            if ($this->editMemberLeavePolicyMode === 'custom' && $preserveCustomStudy) {
                $study = max(0, (int) $this->editMemberLeaveStudy);
                $this->editMemberTotalLeaveCap = $regTotal + $study;
            } else {
                $cap = max(0, (int) $this->editMemberTotalLeaveCap);
                $this->editMemberLeaveStudy = max(0, $cap - $regTotal);
            }
        } else {
            $this->editMemberTotalLeaveCap = max(0, (int) $this->editMemberLeaveCasual)
                + max(0, (int) $this->editMemberLeaveSick)
                + max(0, (int) $this->editMemberLeaveEarned)
                + max(0, (int) $this->editMemberLeaveCarryForward)
                + max(0, (int) $this->editMemberLeaveEmergency);
        }
    }

    public function updatedEditMemberTotalLeaveCap(): void { $this->recalculateEditMemberLeaveBreakdown(false); }
    public function updatedEditMemberLeaveCasual(): void { $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom'); }
    public function updatedEditMemberLeaveSick(): void { $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom'); }
    public function updatedEditMemberLeaveEmergency(): void { $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom'); }
    public function updatedEditMemberLeaveStudy(): void
    {
        if ($this->editMemberRole === 'student') {
            $years = max(1, (int) $this->editMemberArticleshipYears);
            $regTotal = (max(0, (int) $this->editMemberLeaveCasual) + max(0, (int) $this->editMemberLeaveSick) + max(0, (int) $this->editMemberLeaveEmergency)) * $years;
            $this->editMemberTotalLeaveCap = $regTotal + max(0, (int) $this->editMemberLeaveStudy);
        }
    }
    public function updatedEditMemberLeaveEarned(): void { $this->recalculateEditMemberLeaveBreakdown(); }
    public function updatedEditMemberLeaveCarryForward(): void { $this->recalculateEditMemberLeaveBreakdown(); }

    public function getAvailableManagersProperty(): array
    {
        if (Schema::hasTable('users') && Schema::hasTable('firms')) {
            try {
                $firmModel = $this->resolveFirmModel();
                if ($firmModel) {
                    $dbManagers = User::where('firm_id', $firmModel->id)
                        ->where('role', 'manager')
                        ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                        ->orderBy('name')
                        ->get();
                    if ($dbManagers->isNotEmpty()) {
                        return $dbManagers->map(fn (User $m) => [
                            'id'           => $m->id,
                            'name'         => $m->name,
                            'email'        => $m->email,
                            'role'         => $m->role,
                            'designation'  => $m->displayDesignation(),
                            'partner_unit' => $m->partner_unit ?: $this->partnerUnit,
                        ])->toArray();
                    }
                }
            } catch (\Throwable $e) {
                Log::debug($e->getMessage());
            }
        }

        return array_values(array_filter($this->teamMembers, function ($m) {
            return ($m['role'] ?? '') === 'manager';
        }));
    }

    public function loadTeamMembers(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('firms')) {
            $firmModel = $this->resolveFirmModel();
            if ($firmModel) {
                try {
                    $dbUsers = User::with('manager')
                        ->where('firm_id', $firmModel->id)
                        ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
                        ->when(Auth::check() && ! Auth::user()->isPlatformAdmin(), function ($q) {
                            $actor = Auth::user();
                            $unit = (! $actor->isPartner() && ! empty($actor->partner_unit))
                                ? $actor->partner_unit
                                : $this->partnerUnit;
                            if (! empty($unit)) {
                                $q->where(fn ($sub) => $sub->where('partner_unit', $unit)->orWhereNull('partner_unit'));
                            }
                        })
                        ->orderByRaw("CASE role WHEN 'partner' THEN 1 WHEN 'manager' THEN 2 WHEN 'student' THEN 3 ELSE 4 END")
                        ->orderBy('name')
                        ->get();

                    if ($dbUsers->isNotEmpty()) {
                        $isStudentViewer = Auth::check() && Auth::user()->isStudent();
                        $viewerId = Auth::id();
                        $leavesByUser = Schema::hasTable('leave_requests')
                            ? LeaveRequest::where('firm_id', $firmModel->id)
                                ->whereIn('status', ['pending_manager', 'pending_partner', 'approved', 'Awaiting Manager', 'Awaiting Partner'])
                                ->get()
                                ->groupBy('user_id')
                            : collect();

                        $this->teamMembers = $dbUsers->map(function (User $u) use ($isStudentViewer, $viewerId, $leavesByUser) {
                            $redactSensitive = $isStudentViewer && (int) $u->id !== (int) $viewerId;
                            $ent = $u->resolvedLeaveEntitlement();
                            $uLeaves = $leavesByUser->get($u->id, collect());
                            $usedDays = (int) $uLeaves->where('status', 'approved')->sum(fn ($l) => (int) ($l->approved_days ?: ($l->requested_days ?: 1)));
                            $pendingDays = (int) $uLeaves->whereIn('status', ['pending_manager', 'pending_partner', 'Awaiting Manager', 'Awaiting Partner'])->sum(fn ($l) => (int) ($l->requested_days ?: 1));
                            $availDays = max(0, (int) $ent['total_leave_cap'] - ($usedDays + $pendingDays));
                            $artYears = (int) ($ent['articleship_duration_years'] ?: 3);
                            $defaultEnd = $artYears === 4 ? '2030-03-09' : '2029-03-09';
                            $defaultEndLabel = $artYears === 4 ? '09 Mar 2030' : '09 Mar 2029';

                            return [
                                'id'                   => $u->id,
                                'name'                 => $u->name,
                                'email'                => $redactSensitive ? '—' : $u->email,
                                'role'                 => $u->role,
                                'designation'          => $u->displayDesignation(),
                                'role_label'           => $u->displayDesignation(),
                                'promotion_history'    => $redactSensitive ? [] : (is_array($u->promotion_history) ? $u->promotion_history : []),
                                'avatar_url'             => $u->avatar_url ?? null,
                                'phone'                  => $redactSensitive ? '—' : ($u->phone ?: '—'),
                                'payment_account_mode'   => $redactSensitive ? '—' : ($u->payment_account_mode ?: 'bKash'),
                                'payment_account_no'     => $redactSensitive ? '—' : ($u->payment_account_no ?: '—'),
                                'articleship_duration_years' => $artYears,
                                'total_leave_cap'            => $redactSensitive ? 0 : (int) $ent['total_leave_cap'],
                                'leave_regime_label'         => $redactSensitive ? '—' : $ent['regime_label'],
                                'leave_allocations'          => $redactSensitive ? [] : $ent['allocations'],
                                'leave_used_days'            => $redactSensitive ? 0 : $usedDays,
                                'leave_pending_days'         => $redactSensitive ? 0 : $pendingDays,
                                'leave_available_days'       => $redactSensitive ? 0 : $availDays,
                                'active_articleship_window'  => ($u->role === 'student' && ! $redactSensitive) ? (function () use ($u) {
                                $win = $u->articleshipYearWindowForDate();
                                $win['label'] = 'Year ' . ($win['year_number'] ?? 1) . ' of ' . ($win['total_years'] ?? 3);
                                return $win;
                            })() : null,
                                'articleship_start_date' => $u->articleship_start_date?->format('Y-m-d') ?? ($u->role === 'student' ? '2026-03-10' : null),
                                'articleship_end_date'   => $u->articleship_end_date?->format('Y-m-d') ?? ($u->role === 'student' ? $defaultEnd : null),
                                'articleship_period'     => $u->role === 'student'
                                    ? (($u->articleship_start_date?->format('d M Y') ?? '10 Mar 2026') . ' → ' . ($u->articleship_end_date?->format('d M Y') ?? $defaultEndLabel) . " ({$artYears} Yrs)")
                                    : '—',
                                'student_code'           => $u->student_code ?? ($u->role === 'student' ? ('S-' . $u->id) : null),
                                'partner_unit'         => $u->partner_unit ?? $this->partnerUnit,
                                'manager_id'           => $u->manager_id,
                                'manager_name'         => $u->manager?->name ?? '—',
                                'monthly_allowance'    => $redactSensitive ? 0.0 : (float) ($u->monthly_allowance ?? ($u->role === 'student' ? 6000 : ($u->role === 'manager' ? 25000 : 0))),
                                'can_manage_team'          => (bool) $u->can_manage_team,
                                'can_proxy_attendance'     => (bool) $u->can_proxy_attendance,
                                'can_edit_attendance'      => (bool) $u->can_edit_attendance,
                                'can_approve_leave'        => (bool) $u->can_approve_leave,
                                'can_approve_claims'       => (bool) $u->can_approve_claims,
                                'can_manage_policies'      => (bool) ($u->can_manage_policies ?? false),
                                'can_manage_payroll'       => (bool) ($u->can_manage_payroll ?? false),
                                'can_final_approve_leave'  => (bool) ($u->can_final_approve_leave ?? false),
                                'can_final_approve_claims' => (bool) ($u->can_final_approve_claims ?? false),
                                'created_at'           => $u->created_at?->format('d M Y') ?? 'Recent',
                            ];
                        })->toArray();
                        return;
                    } else {
                        $this->teamMembers = [];
                        return;
                    }
                } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::debug($e->getMessage()); }
            }
        }

        if (! app()->runningUnitTests()) {
            $this->teamMembers = [];
            return;
        }

        $this->teamMembers = [
            [
                'id'                   => 1,
                'name'                 => 'Rahman Anis, FCA',
                'email'                => 'partner@rac.dhakawala.com',
                'role'                 => 'partner',
                'role_label'           => 'Partner / Principal',
                'phone'                => '+880 1711 000001',
                'student_code'         => null,
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => null,
                'manager_name'         => '—',
                'monthly_allowance'    => 0,
                'can_manage_team'      => true,
                'can_proxy_attendance' => true,
                'can_edit_attendance'  => true,
                'can_approve_leave'    => true,
                'can_approve_claims'   => true,
                'created_at'           => '01 Jan 2026',
            ],
            [
                'id'                   => 2,
                'name'                 => 'Sample Manager A',
                'email'                => 'manager.a@rac.dhakawala.com',
                'role'                 => 'manager',
                'role_label'           => 'Audit Manager',
                'phone'                => '+880 1711 000002',
                'student_code'         => null,
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => null,
                'manager_name'         => 'Rahman Anis, FCA',
                'monthly_allowance'    => 28000,
                'can_manage_team'      => true,
                'can_proxy_attendance' => true,
                'can_edit_attendance'  => true,
                'can_approve_leave'    => true,
                'can_approve_claims'   => true,
                'created_at'           => '15 Jan 2026',
            ],
            [
                'id'                   => 3,
                'name'                 => 'Sample Manager B',
                'email'                => 'manager.b@rac.dhakawala.com',
                'role'                 => 'manager',
                'role_label'           => 'Audit Manager',
                'phone'                => '+880 1711 000003',
                'student_code'         => null,
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => null,
                'manager_name'         => 'Rahman Anis, FCA',
                'monthly_allowance'    => 26000,
                'can_manage_team'      => false,
                'can_proxy_attendance' => true,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => true,
                'can_approve_claims'   => false,
                'created_at'           => '01 Feb 2026',
            ],
            [
                'id'                   => 4,
                'name'                 => 'Sample Student 01 (Zubair)',
                'email'                => 'zubair@rac.dhakawala.com',
                'role'                 => 'student',
                'role_label'           => 'Articled Student',
                'phone'                => '+880 1811 000004',
                'student_code'         => 'S-101',
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => 2,
                'manager_name'         => 'Sample Manager A',
                'monthly_allowance'    => 7000,
                'can_manage_team'      => false,
                'can_proxy_attendance' => false,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => false,
                'can_approve_claims'   => false,
                'created_at'           => '10 Mar 2026',
            ],
            [
                'id'                   => 5,
                'name'                 => 'Sample Student 02 (Nusrat)',
                'email'                => 'nusrat@rac.dhakawala.com',
                'role'                 => 'student',
                'role_label'           => 'Articled Student',
                'phone'                => '+880 1811 000005',
                'student_code'         => 'S-102',
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => 2,
                'manager_name'         => 'Sample Manager A',
                'monthly_allowance'    => 6500,
                'can_manage_team'      => false,
                'can_proxy_attendance' => false,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => false,
                'can_approve_claims'   => false,
                'created_at'           => '12 Mar 2026',
            ],
            [
                'id'                   => 6,
                'name'                 => 'Sample Student 03 (Rakibul)',
                'email'                => 'rakibul@rac.dhakawala.com',
                'role'                 => 'student',
                'role_label'           => 'Articled Student',
                'phone'                => '+880 1811 000006',
                'student_code'         => 'S-103',
                'partner_unit'         => 'Partner Unit 01',
                'manager_id'           => 3,
                'manager_name'         => 'Sample Manager B',
                'monthly_allowance'    => 6000,
                'can_manage_team'      => false,
                'can_proxy_attendance' => false,
                'can_edit_attendance'  => false,
                'can_approve_leave'    => false,
                'can_approve_claims'   => false,
                'created_at'           => '15 Mar 2026',
            ],
        ];
    }

    public function openAddMemberModal(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Managers can enroll new team members.';
            return;
        }
        $this->error = null;
        $this->closeDialogs();
        $this->resetNewMemberFields();
        $this->showAddMemberModal = true;
    }

    protected function resetNewMemberFields(): void
    {
        $this->newMemberName = '';
        $this->newMemberEmail = '';
        $this->newMemberPhone = '';
        $this->newMemberPaymentMode = 'bKash';
        $this->newMemberPaymentAccountNo = '';
        $this->newMemberPassword = '';
        $this->newMemberRole = 'student';
        $this->newMemberDesignation = 'Articled Student';
        $this->newMemberCustomPostMode = false;
        $this->newMemberStudentCode = '';
        $this->newMemberManagerId = null;
        $this->newMemberPartnerUnit = $this->partnerUnit;
        $this->newMemberMonthlyAllowance = 6000.00;
        $this->newMemberCanManageTeam = false;
        $this->newMemberCanProxyAttendance = false;
        $this->newMemberCanEditAttendance = false;
        $this->newMemberCanApproveLeave = false;
        $this->newMemberCanApproveClaims = false;
        $this->newMemberCanManagePolicies = false;
        $this->newMemberCanManagePayroll = false;
        $this->newMemberCanFinalApproveLeave = false;
        $this->newMemberCanFinalApproveClaims = false;
        $this->newMemberArticleshipStart = now()->toDateString();
        $this->newMemberArticleshipEnd = now()->addYears(3)->subDay()->toDateString();
        $this->newMemberLeavePolicyMode = 'icab';
        $this->newMemberArticleshipYears = 3;
        $this->newMemberTotalLeaveCap = 180;
        $this->newMemberLeaveCasual = 14;
        $this->newMemberLeaveSick = 14;
        $this->newMemberLeaveEmergency = 7;
        $this->newMemberLeaveStudy = 75;
        $this->newMemberLeaveEarned = 15;
        $this->newMemberLeaveCarryForward = 0;
    }

    public function updatedNewMemberArticleshipStart(string $value): void
    {
        if ($this->validDate($value)) {
            $years = in_array((int) $this->newMemberArticleshipYears, [3, 4], true) ? (int) $this->newMemberArticleshipYears : 3;
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $this->newMemberArticleshipEnd = $dt->modify("+{$years} years -1 day")->format('Y-m-d');
        }
    }

    public function createMember(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized action: Only Partners or authorized Managers can enroll team members.';
            return;
        }

        $this->validate([
            'newMemberName'     => 'required|string|min:2|max:120',
            'newMemberEmail'    => 'required|email|max:150',
            'newMemberRole'     => 'required|in:partner,manager,student',
            'newMemberPassword' => 'nullable|string|min:6|max:120',
        ]);

        $typedNewPassword = trim((string) $this->newMemberPassword);
        if ($typedNewPassword !== '' && strlen($typedNewPassword) < 6) {
            $this->error = 'Custom password must be at least 6 characters long (or leave blank to auto-generate).';
            return;
        }

        if (! $this->canManageTargetRole($this->newMemberRole)) {
            $this->error = 'Security rule: Only a Partner can enroll Manager or Partner accounts.';
            return;
        }

        $firmModel = $this->resolveFirmModel();

        if ($firmModel && Schema::hasTable('users')) {
            // Check seat limit allocated by DhakaWala
            if ($firmModel->seats > 0) {
                $currentCount = User::where('firm_id', $firmModel->id)->count();
                if ($currentCount >= $firmModel->seats) {
                    $this->error = "Allocated seat limit ({$firmModel->seats} seats) reached. Please contact DhakaWala Support to upgrade your seat allocation.";
                    return;
                }
            }

            $existing = User::where('email', strtolower(trim($this->newMemberEmail)))->first();
            if ($existing) {
                $this->error = "A user with email {$this->newMemberEmail} already exists.";
                return;
            }

            $user = new User();
            $user->firm_id = $firmModel->id;
            $user->name = trim($this->newMemberName);
            $user->email = strtolower(trim($this->newMemberEmail));
            $initialSecret = $typedNewPassword !== ''
                ? $typedNewPassword
                : Str::password(10, true, true, false);
            $user->password = Hash::make($initialSecret);
            $user->role = $this->newMemberRole;
            $defaultDesig = match ($this->newMemberRole) {
                'partner' => 'Partner / Principal',
                'manager' => 'Audit Manager',
                default   => 'Articled Student',
            };
            $finalDesig = trim($this->newMemberDesignation) ?: $defaultDesig;
            if (Schema::hasColumn('users', 'designation')) {
                $user->designation = $finalDesig;
            }
            $desigExists = false;
            foreach ($this->customDesignations as $cdItem) {
                if (strcasecmp((string) ($cdItem['title'] ?? ''), $finalDesig) === 0) {
                    $desigExists = true;
                    break;
                }
            }
            if (! $desigExists && $finalDesig !== '') {
                $this->customDesignations[] = ['title' => $finalDesig, 'base_role' => $this->newMemberRole];
                $this->persistCustomDesignations();
            }
            if (Schema::hasColumn('users', 'phone') && trim($this->newMemberPhone) !== '') {
                $user->phone = trim($this->newMemberPhone);
            }
            if (Schema::hasColumn('users', 'payment_account_mode')) {
                $user->payment_account_mode = trim($this->newMemberPaymentMode) ?: 'bKash';
            }
            if (Schema::hasColumn('users', 'payment_account_no') && trim($this->newMemberPaymentAccountNo) !== '') {
                $user->payment_account_no = trim($this->newMemberPaymentAccountNo);
            }
            $user->partner_unit = $this->newMemberPartnerUnit ?: $this->partnerUnit;
            $user->monthly_allowance = (float) $this->newMemberMonthlyAllowance;
            $user->student_code = $this->newMemberRole === 'student' ? ($this->newMemberStudentCode ?: ('S-' . rand(100, 999))) : null;
            $user->manager_id = ($this->newMemberRole === 'student' && !empty($this->newMemberManagerId)) ? (int) $this->newMemberManagerId : null;
            $user->can_manage_team = (bool) $this->newMemberCanManageTeam;
            $user->can_proxy_attendance = (bool) $this->newMemberCanProxyAttendance;
            $user->can_edit_attendance = (bool) $this->newMemberCanEditAttendance;
            $user->can_approve_leave = (bool) $this->newMemberCanApproveLeave;
            $user->can_approve_claims = (bool) $this->newMemberCanApproveClaims;
            if (Schema::hasColumn('users', 'can_manage_policies')) {
                $user->can_manage_policies = (bool) $this->newMemberCanManagePolicies;
                $user->can_manage_payroll = (bool) $this->newMemberCanManagePayroll;
                $user->can_final_approve_leave = (bool) $this->newMemberCanFinalApproveLeave;
                $user->can_final_approve_claims = (bool) $this->newMemberCanFinalApproveClaims;
            }
            $user->email_verified_at = now();
            if (Schema::hasColumn('users', 'is_active')) {
                $user->is_active = true;
            }
            if (Schema::hasColumn('users', 'invitation_token')) {
                $user->invitation_token = null;
                $user->invitation_sent_at = now();
            }
            User::ensureRuntimeSchemaColumns();
            $this->recalculateNewMemberLeaveBreakdown($this->newMemberLeavePolicyMode === 'custom');
            $years = $this->newMemberLeavePolicyMode === 'custom'
                ? max(1, min(6, (int) $this->newMemberArticleshipYears))
                : (in_array((int) $this->newMemberArticleshipYears, [3, 4], true) ? (int) $this->newMemberArticleshipYears : 3);
            if ($this->newMemberRole === 'student' && Schema::hasColumn('users', 'articleship_start_date')) {
                $user->articleship_start_date = $this->validDate($this->newMemberArticleshipStart) ? $this->newMemberArticleshipStart : now()->toDateString();
                $user->articleship_end_date = $this->validDate($this->newMemberArticleshipEnd) ? $this->newMemberArticleshipEnd : now()->addYears($years)->subDay()->toDateString();
            }
            if (Schema::hasColumn('users', 'articleship_duration_years')) {
                $user->articleship_duration_years = $this->newMemberRole === 'student' ? $years : null;
            }
            if (Schema::hasColumn('users', 'total_leave_cap')) {
                $user->total_leave_cap = max(0, (int) $this->newMemberTotalLeaveCap);
            }
            if (Schema::hasColumn('users', 'leave_allocations')) {
                $user->leave_allocations = $this->newMemberRole === 'student'
                    ? [
                        'leave_policy_mode'  => $this->newMemberLeavePolicyMode,
                        'annual_per_year'    => max(0, (int) $this->newMemberLeaveCasual),
                        'sick_per_year'      => max(0, (int) $this->newMemberLeaveSick),
                        'emergency_per_year' => max(0, (int) $this->newMemberLeaveEmergency),
                        'study_per_year'     => (int) round(max(0, (int) $this->newMemberLeaveStudy) / $years),
                        'annual'             => max(0, (int) $this->newMemberLeaveCasual) * $years,
                        'sick'               => max(0, (int) $this->newMemberLeaveSick) * $years,
                        'emergency'          => max(0, (int) $this->newMemberLeaveEmergency) * $years,
                        'icab_study'         => max(0, (int) $this->newMemberLeaveStudy),
                    ]
                    : [
                        'leave_policy_mode'    => $this->newMemberLeavePolicyMode,
                        'annual'               => max(0, (int) $this->newMemberLeaveCasual),
                        'sick'                 => max(0, (int) $this->newMemberLeaveSick),
                        'earned'               => max(0, (int) $this->newMemberLeaveEarned),
                        'carry_forward_earned' => max(0, (int) $this->newMemberLeaveCarryForward),
                        'emergency'            => max(0, (int) $this->newMemberLeaveEmergency),
                    ];
            }
            $user->save();

            // Email direct login link, username, and password to the newly created member.
            $invitationSent = $this->sendInvitationEmail($user, (string) ($firmModel->name ?? 'Practice'), $initialSecret, false, null);

            $this->recordAuditLog('team', 'member_enrolled', "Enrolled {$user->name} ({$user->email}) as " . ucfirst($user->role) . ' and dispatched login credentials.', $user->email);

            $this->notice = $invitationSent
                ? "Successfully enrolled {$user->name} as " . ucfirst($user->role) . ". Login link, username ({$user->email}), and password ({$initialSecret}) have been emailed to {$user->email}."
                : "Enrolled {$user->name} as " . ucfirst($user->role) . " (Login: {$user->email} / Password: {$initialSecret}). Email delivery could not be completed — please share these credentials directly.";
        } else {
            $newId = count($this->teamMembers) + 1;
            $managerName = '—';
            if ($this->newMemberManagerId) {
                foreach ($this->teamMembers as $m) {
                    if ($m['id'] == $this->newMemberManagerId) {
                        $managerName = $m['name'];
                        break;
                    }
                }
            }

            $this->teamMembers[] = [
                'id'                   => $newId,
                'name'                 => trim($this->newMemberName),
                'email'                => strtolower(trim($this->newMemberEmail)),
                'role'                 => $this->newMemberRole,
                'designation'          => trim($this->newMemberDesignation) ?: match ($this->newMemberRole) {
                    'partner' => 'Partner / Principal',
                    'manager' => 'Audit Manager',
                    'student' => 'Articled Student',
                    default   => ucfirst($this->newMemberRole),
                },
                'role_label'           => trim($this->newMemberDesignation) ?: match ($this->newMemberRole) {
                    'partner' => 'Partner / Principal',
                    'manager' => 'Audit Manager',
                    'student' => 'Articled Student',
                    default   => ucfirst($this->newMemberRole),
                },
                'phone'                => '—',
                'student_code'         => $this->newMemberRole === 'student' ? ($this->newMemberStudentCode ?: ('S-' . $newId)) : null,
                'partner_unit'         => $this->newMemberPartnerUnit ?: $this->partnerUnit,
                'manager_id'           => $this->newMemberManagerId ? (int) $this->newMemberManagerId : null,
                'manager_name'         => $managerName,
                'monthly_allowance'    => (float) $this->newMemberMonthlyAllowance,
                'can_manage_team'      => (bool) $this->newMemberCanManageTeam,
                'can_proxy_attendance' => (bool) $this->newMemberCanProxyAttendance,
                'can_edit_attendance'  => (bool) $this->newMemberCanEditAttendance,
                'can_approve_leave'    => (bool) $this->newMemberCanApproveLeave,
                'can_approve_claims'   => (bool) $this->newMemberCanApproveClaims,
                'created_at'           => date('d M Y'),
            ];
            $this->notice = "Successfully added {$this->newMemberName} to the team roster.";
        }

        $this->resetNewMemberFields();
        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
        $this->loadPayrollRows();
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Member Enrolled',
            'message' => $this->notice ?? 'Team member added to practice roster.',
        ]);
    }

    public function openEditMemberModal($id): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanEditMembers()) {
            $this->error = 'Only Partners or Managers can edit member accounts and profiles.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        if (($member['role'] ?? '') === 'partner' && $this->previewRole !== 'partner' && (! Auth::check() || (! Auth::user()->isPartner() && ! Auth::user()->isPlatformAdmin()))) {
            $this->error = 'Security rule: Only a Partner can edit a Partner account.';
            return;
        }

        $this->error = null;
        $this->closeDialogs();
        $this->editMemberId = (int) $member['id'];
        $this->editMemberName = $member['name'];
        $this->editMemberEmail = $member['email'];
        $this->editMemberPhone = ($member['phone'] ?? '') !== '—' ? ($member['phone'] ?? '') : '';
        $this->editMemberPaymentMode = (($member['payment_account_mode'] ?? 'bKash') !== '—' && !empty($member['payment_account_mode'])) ? $member['payment_account_mode'] : 'bKash';
        $this->editMemberPaymentAccountNo = ($member['payment_account_no'] ?? '') !== '—' ? ($member['payment_account_no'] ?? '') : '';
        $this->editMemberNewPassword = '';
        $this->editMemberRole = $member['role'];
        $this->editMemberDesignation = $member['designation'] ?? ($member['role_label'] ?? 'Articled Student');
        $this->editMemberStudentCode = $member['student_code'] ?? '';
        $this->editMemberManagerId = $member['manager_id'] ?? null;
        $this->editMemberPartnerUnit = $member['partner_unit'] ?? $this->partnerUnit;
        $this->editMemberMonthlyAllowance = (float) ($member['monthly_allowance'] ?? 0);
        $this->editMemberCanManageTeam = (bool) ($member['can_manage_team'] ?? false);
        $this->editMemberCanProxyAttendance = (bool) ($member['can_proxy_attendance'] ?? false);
        $this->editMemberCanEditAttendance = (bool) ($member['can_edit_attendance'] ?? false);
        $this->editMemberCanApproveLeave = (bool) ($member['can_approve_leave'] ?? false);
        $this->editMemberCanApproveClaims = (bool) ($member['can_approve_claims'] ?? false);
        $this->editMemberCanManagePolicies = (bool) ($member['can_manage_policies'] ?? false);
        $this->editMemberCanManagePayroll = (bool) ($member['can_manage_payroll'] ?? false);
        $this->editMemberCanFinalApproveLeave = (bool) ($member['can_final_approve_leave'] ?? false);
        $this->editMemberCanApproveClaims = (bool) ($member['can_approve_claims'] ?? false);
        $this->editMemberCanFinalApproveClaims = (bool) ($member['can_final_approve_claims'] ?? false);
        $this->editMemberArticleshipStart = $member['articleship_start_date'] ?? '2026-03-10';
        $this->editMemberArticleshipEnd = $member['articleship_end_date'] ?? '2029-03-09';
        $alloc = is_array($member['leave_allocations'] ?? null) ? $member['leave_allocations'] : [];
        $this->editMemberLeavePolicyMode = (($alloc['leave_policy_mode'] ?? 'icab') === 'custom') ? 'custom' : 'icab';
        if (($member['role'] ?? 'student') === 'student') {
            $this->editMemberArticleshipYears = max(1, min(6, (int) ($member['articleship_duration_years'] ?? 3)));
            $this->editMemberTotalLeaveCap = (int) ($member['total_leave_cap'] ?? ($this->editMemberArticleshipYears === 4 ? 240 : 180));
            $this->editMemberLeaveCasual = (int) ($alloc['annual_per_year'] ?? 14);
            $this->editMemberLeaveSick = (int) ($alloc['sick_per_year'] ?? 14);
            $this->editMemberLeaveEmergency = (int) ($alloc['emergency_per_year'] ?? 7);
            $this->editMemberLeaveStudy = isset($alloc['icab_study']) ? (int) $alloc['icab_study'] : max(0, $this->editMemberTotalLeaveCap - (($this->editMemberLeaveCasual + $this->editMemberLeaveSick + $this->editMemberLeaveEmergency) * $this->editMemberArticleshipYears));
            $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom');
        } else {
            $this->editMemberLeaveCasual = (int) ($alloc['annual'] ?? 10);
            $this->editMemberLeaveSick = (int) ($alloc['sick'] ?? 14);
            $this->editMemberLeaveEarned = (int) ($alloc['earned'] ?? 15);
            $this->editMemberLeaveCarryForward = (int) ($alloc['carry_forward_earned'] ?? 0);
            $this->editMemberLeaveEmergency = (int) ($alloc['emergency'] ?? 5);
            $this->recalculateEditMemberLeaveBreakdown();
        }

        $this->showEditMemberModal = true;
    }

    public function openResetPasswordModal($id): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanResetPasswords()) {
            $this->error = 'Only a Partner or Manager can reset member passwords.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        $this->error = null;
        $this->closeDialogs();
        $this->resetPasswordMemberId = (int) $member['id'];
        $this->resetPasswordMemberName = $member['name'];
        $this->resetPasswordMemberEmail = $member['email'];
        $this->resetPasswordMemberRole = $member['role_label'] ?? ucfirst($member['role']);
        $this->resetPasswordInput = '';
        $this->showResetPasswordModal = true;
    }

    public function resetMemberPassword(?int $memberId = null): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanResetPasswords()) {
            $this->error = 'Unauthorized: Only a Partner or Manager can reset member passwords.';
            return;
        }

        $targetId = $memberId ?: $this->resetPasswordMemberId;
        if (! $targetId) {
            $this->error = 'Please select a member account to reset.';
            return;
        }

        $typedPassword = trim((string) $this->resetPasswordInput);
        if ($typedPassword !== '' && strlen($typedPassword) < 6) {
            $this->error = 'New password must be at least 6 characters long (or leave blank to auto-generate).';
            return;
        }

        $nextSecret = $typedPassword !== ''
            ? $typedPassword
            : Str::password(10, true, true, false);

        $firmModel = $this->resolveFirmModel();

        if ($firmModel && Schema::hasTable('users')) {
            $user = User::where('firm_id', $firmModel->id)->where('id', $targetId)->first();
            if (! $user) {
                $this->error = 'Member account not found in this workspace.';
                return;
            }

            if (! $this->canResetTargetUserPassword($user)) {
                $this->error = 'Security rule: A Manager cannot reset a Partner account password.';
                return;
            }

            $user->password = Hash::make($nextSecret);
            $user->email_verified_at = $user->email_verified_at ?? now();
            if (Schema::hasColumn('users', 'invitation_token')) {
                $user->invitation_token = null;
            }
            $user->save();

            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $emailSent = $this->sendInvitationEmail($user, (string) ($firmModel->name ?? 'Practice'), $nextSecret, true, null);

            $this->recordAuditLog(
                'team',
                'member_password_reset',
                ucfirst($this->previewRole) . " reset password for {$user->name} ({$user->email}) and emailed updated login credentials.",
                $user->email
            );

            $this->notice = $emailSent
                ? "Password updated for {$user->name} ({$user->email}). Login link, username, and new password ({$nextSecret}) have been emailed to {$user->email}."
                : "Password updated for {$user->name} ({$user->email}) to: {$nextSecret} (Email delivery unavailable — please share this password directly).";
        } else {
            $member = collect($this->teamMembers)->firstWhere('id', $targetId);
            $mName = $member['name'] ?? ('Member #' . $targetId);
            $mEmail = $member['email'] ?? 'member@practice.com';
            $this->notice = "Password updated for {$mName} ({$mEmail}). Login credentials have been sent to {$mEmail}.";
        }

        $this->resetPasswordInput = '';
        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Password Reset & Emailed',
            'message' => $this->notice,
        ]);
    }

    public function updateMember(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        if (! $this->userCanEditMembers()) {
            $this->error = 'Unauthorized action: Only Partners or Managers can modify member accounts.';
            return;
        }

        $firmModel = $this->resolveFirmModel();

        if ($firmModel && Schema::hasTable('users')) {
            $user = User::where('firm_id', $firmModel->id)->where('id', $this->editMemberId)->first();
            if ($user) {
                if (! $this->canManageTargetUser($user)) {
                    $this->error = 'Security rule: You cannot modify an account with equal or higher privileges.';
                    return;
                }

                if (! $this->canManageTargetRole($this->editMemberRole)) {
                    $this->error = 'Security rule: Only a Partner can assign the ' . ucfirst($this->editMemberRole) . ' role.';
                    return;
                }

                if (Auth::check() && (int) Auth::id() === (int) $user->id && $user->role !== $this->editMemberRole) {
                    $this->error = 'Security rule: You cannot change your own role.';
                    return;
                }

                $newPlainPassword = trim((string) $this->editMemberNewPassword);
                if ($newPlainPassword !== '' && strlen($newPlainPassword) < 6) {
                    $this->error = 'New password must be at least 6 characters long.';
                    return;
                }

                if (! empty(trim($this->editMemberName))) {
                    $user->name = trim($this->editMemberName);
                }
                if (Schema::hasColumn('users', 'phone')) {
                    $user->phone = trim($this->editMemberPhone) ?: null;
                }
                if (Schema::hasColumn('users', 'payment_account_mode')) {
                    $user->payment_account_mode = trim($this->editMemberPaymentMode) ?: 'bKash';
                }
                if (Schema::hasColumn('users', 'payment_account_no')) {
                    $user->payment_account_no = trim($this->editMemberPaymentAccountNo) ?: null;
                }
                $cleanEmail = strtolower(trim($this->editMemberEmail));
                if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) && $cleanEmail !== $user->email) {
                    if (! User::where('email', $cleanEmail)->where('id', '!=', $user->id)->exists()) {
                        $user->email = $cleanEmail;
                    }
                }
                if ($newPlainPassword !== '') {
                    $user->password = Hash::make($newPlainPassword);
                    $user->email_verified_at = $user->email_verified_at ?? now();
                    if (Schema::hasColumn('users', 'invitation_token')) {
                        $user->invitation_token = null;
                    }
                }
                $user->role = $this->editMemberRole;
                if (Schema::hasColumn('users', 'designation')) {
                    $user->designation = trim($this->editMemberDesignation) ?: match ($this->editMemberRole) {
                        'partner' => 'Partner / Principal',
                        'manager' => 'Audit Manager',
                        default   => 'Articled Student',
                    };
                }
                $user->partner_unit = $this->editMemberPartnerUnit;
                $user->monthly_allowance = (float) $this->editMemberMonthlyAllowance;
                if ($this->editMemberRole === 'student') {
                    $user->student_code = $this->editMemberStudentCode ?: $user->student_code;
                    $user->manager_id = !empty($this->editMemberManagerId) ? (int) $this->editMemberManagerId : null;
                    if (Schema::hasColumn('users', 'articleship_start_date')) {
                        if ($this->validDate($this->editMemberArticleshipStart)) {
                            $user->articleship_start_date = $this->editMemberArticleshipStart;
                        }
                        if ($this->validDate($this->editMemberArticleshipEnd)) {
                            $user->articleship_end_date = $this->editMemberArticleshipEnd;
                        }
                    }
                } else {
                    $user->manager_id = null;
                }
                $user->can_manage_team = (bool) $this->editMemberCanManageTeam;
                $user->can_proxy_attendance = (bool) $this->editMemberCanProxyAttendance;
                $user->can_edit_attendance = (bool) $this->editMemberCanEditAttendance;
                $user->can_approve_leave = (bool) $this->editMemberCanApproveLeave;
                $user->can_approve_claims = (bool) $this->editMemberCanApproveClaims;
                if (Schema::hasColumn('users', 'can_manage_policies')) {
                    $user->can_manage_policies = (bool) $this->editMemberCanManagePolicies;
                    $user->can_manage_payroll = (bool) $this->editMemberCanManagePayroll;
                    $user->can_final_approve_leave = (bool) $this->editMemberCanFinalApproveLeave;
                    $user->can_final_approve_claims = (bool) $this->editMemberCanFinalApproveClaims;
                }
                User::ensureRuntimeSchemaColumns();
                $this->recalculateEditMemberLeaveBreakdown($this->editMemberLeavePolicyMode === 'custom');
                $editYears = $this->editMemberLeavePolicyMode === 'custom'
                    ? max(1, min(6, (int) $this->editMemberArticleshipYears))
                    : (in_array((int) $this->editMemberArticleshipYears, [3, 4], true) ? (int) $this->editMemberArticleshipYears : 3);
                if (Schema::hasColumn('users', 'articleship_duration_years')) {
                    $user->articleship_duration_years = $this->editMemberRole === 'student' ? $editYears : null;
                }
                if (Schema::hasColumn('users', 'total_leave_cap')) {
                    $user->total_leave_cap = max(0, (int) $this->editMemberTotalLeaveCap);
                }
                if (Schema::hasColumn('users', 'leave_allocations')) {
                    $user->leave_allocations = $this->editMemberRole === 'student'
                        ? [
                            'leave_policy_mode'  => $this->editMemberLeavePolicyMode,
                            'annual_per_year'    => max(0, (int) $this->editMemberLeaveCasual),
                            'sick_per_year'      => max(0, (int) $this->editMemberLeaveSick),
                            'emergency_per_year' => max(0, (int) $this->editMemberLeaveEmergency),
                            'study_per_year'     => (int) round(max(0, (int) $this->editMemberLeaveStudy) / $editYears),
                            'annual'             => max(0, (int) $this->editMemberLeaveCasual) * $editYears,
                            'sick'               => max(0, (int) $this->editMemberLeaveSick) * $editYears,
                            'emergency'          => max(0, (int) $this->editMemberLeaveEmergency) * $editYears,
                            'icab_study'         => max(0, (int) $this->editMemberLeaveStudy),
                        ]
                        : [
                            'leave_policy_mode'    => $this->editMemberLeavePolicyMode,
                            'annual'               => max(0, (int) $this->editMemberLeaveCasual),
                            'sick'                 => max(0, (int) $this->editMemberLeaveSick),
                            'earned'               => max(0, (int) $this->editMemberLeaveEarned),
                            'carry_forward_earned' => max(0, (int) $this->editMemberLeaveCarryForward),
                            'emergency'            => max(0, (int) $this->editMemberLeaveEmergency),
                        ];
                }
                $user->save();

                $passwordResetSuffix = '.';
                if ($newPlainPassword !== '') {
                    if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                    }
                    $emailSent = $this->sendInvitationEmail($user, (string) ($firmModel->name ?? 'Practice'), $newPlainPassword, true);
                    $passwordResetSuffix = $emailSent
                        ? " and emailed the new password ({$newPlainPassword}) to {$user->email}."
                        : " and reset password to {$newPlainPassword} (email could not be sent).";
                }

                $this->recordAuditLog(
                    'team',
                    $newPlainPassword !== '' ? 'member_password_reset' : 'member_updated',
                    "Updated profile and permissions for {$user->name} ({$user->role})" . ($newPlainPassword !== '' ? ' and reset password.' : '.'),
                    $user->email
                );
                $this->notice = "Updated permissions and profile for {$user->name}{$passwordResetSuffix}";
            }
        } else {
            foreach ($this->teamMembers as &$m) {
                if ($m['id'] == $this->editMemberId) {
                    $m['name'] = trim($this->editMemberName) ?: $m['name'];
                    $m['email'] = strtolower(trim($this->editMemberEmail)) ?: $m['email'];
                    $m['phone'] = trim($this->editMemberPhone) ?: '—';
                    $m['payment_account_mode'] = trim($this->editMemberPaymentMode) ?: 'bKash';
                    $m['payment_account_no'] = trim($this->editMemberPaymentAccountNo) ?: '—';
                    $m['role'] = $this->editMemberRole;
                    $m['designation'] = trim($this->editMemberDesignation) ?: ($m['designation'] ?? 'Articled Student');
                    $m['role_label'] = match ($this->editMemberRole) {
                        'partner' => 'Partner / Principal',
                        'manager' => 'Audit Manager',
                        'student' => 'Articled Student',
                        default   => ucfirst($this->editMemberRole),
                    };
                    $m['partner_unit'] = $this->editMemberPartnerUnit;
                    $m['monthly_allowance'] = (float) $this->editMemberMonthlyAllowance;
                    if ($this->editMemberRole === 'student') {
                        $m['student_code'] = $this->editMemberStudentCode;
                        $m['manager_id'] = $this->editMemberManagerId ? (int) $this->editMemberManagerId : null;
                        if ($this->editMemberManagerId) {
                            foreach ($this->teamMembers as $mgr) {
                                if ($mgr['id'] == $this->editMemberManagerId) {
                                    $m['manager_name'] = $mgr['name'];
                                    break;
                                }
                            }
                        } else {
                            $m['manager_name'] = '—';
                        }
                    }
                    $m['can_manage_team'] = (bool) $this->editMemberCanManageTeam;
                    $m['can_proxy_attendance'] = (bool) $this->editMemberCanProxyAttendance;
                    $m['can_edit_attendance'] = (bool) $this->editMemberCanEditAttendance;
                    $m['can_approve_leave'] = (bool) $this->editMemberCanApproveLeave;
                    $m['can_approve_claims'] = (bool) $this->editMemberCanApproveClaims;
                    break;
                }
            }
            unset($m);
            $this->notice = "Updated permissions and profile for {$this->editMemberName}.";
        }

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
        $this->loadPayrollRows();
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Member Updated',
            'message' => $this->notice ?? 'Member profile and permissions updated.',
        ]);
    }

    public function openPromoteDemoteModal($id): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Team Admins can promote or demote practice members.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        $this->closeDialogs();
        $this->promoteMemberId = (int) $member['id'];
        $this->promoteMemberName = $member['name'];
        $this->promoteMemberCurrentRole = $member['role'];
        $this->promoteMemberCurrentDesignation = $member['designation'] ?? ($member['role_label'] ?? 'Articled Student');
        $this->promoteMemberCurrentAllowance = (float) ($member['monthly_allowance'] ?? 0);
        $this->promoteActionType = 'promote';
        $this->promoteNewRole = $member['role'];
        $this->promoteNewDesignation = $this->promoteMemberCurrentDesignation;
        $this->promoteNewAllowance = $this->promoteMemberCurrentAllowance;
        $this->promoteEffectiveDate = now()->toDateString();
        $this->promoteRemarks = '';
        $this->showPromoteDemoteModal = true;
    }

    public function selectPresetDesignationForPromotion(string $title, string $baseRole): void
    {
        $this->promoteNewDesignation = $title;
        if (in_array($baseRole, ['partner', 'manager', 'student'], true) && $this->canManageTargetRole($baseRole)) {
            $this->promoteNewRole = $baseRole;
        }
    }

    public function submitPromotionOrDemotion(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;

        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized: Only Partners or authorized Team Admins can record promotions or demotions.';
            return;
        }

        if (! $this->promoteMemberId) {
            $this->error = 'Please select a member to promote or demote.';
            return;
        }

        $newDesig = trim($this->promoteNewDesignation);
        if (mb_strlen($newDesig) < 2 || mb_strlen($newDesig) > 120) {
            $this->error = 'Please enter or select a valid new designation (2–120 characters).';
            return;
        }

        if (! in_array($this->promoteNewRole, ['partner', 'manager', 'student'], true)) {
            $this->error = 'Select a valid base access tier.';
            return;
        }

        if (! $this->canManageTargetRole($this->promoteNewRole)) {
            $this->error = 'Security rule: Only a Partner can promote or assign a member to the ' . ucfirst($this->promoteNewRole) . ' tier.';
            return;
        }

        $actionType = in_array($this->promoteActionType, ['promote', 'demote', 'transfer'], true)
            ? $this->promoteActionType
            : 'promote';
        $actionVerb = match ($actionType) {
            'demote'   => 'Demoted',
            'transfer' => 'Reassigned',
            default    => 'Promoted',
        };
        $effectiveDate = $this->validDate($this->promoteEffectiveDate) ? $this->promoteEffectiveDate : now()->toDateString();
        $remarks = trim($this->promoteRemarks) ?: "{$actionVerb} to {$newDesig} effective {$effectiveDate}.";

        // Ensure custom designation is also in the firm's list if newly typed
        $existsInList = false;
        foreach ($this->customDesignations as $cd) {
            if (strcasecmp((string) ($cd['title'] ?? ''), $newDesig) === 0) {
                $existsInList = true;
                break;
            }
        }
        if (! $existsInList) {
            $this->customDesignations[] = [
                'title'     => $newDesig,
                'base_role' => $this->promoteNewRole,
            ];
            $this->persistCustomDesignations();
        }

        $firmModel = $this->resolveFirmModel();
        if ($firmModel && Schema::hasTable('users')) {
            $user = User::where('firm_id', $firmModel->id)->where('id', $this->promoteMemberId)->first();
            if (! $user) {
                $this->error = 'Member account not found in this workspace.';
                return;
            }

            if (! $this->canManageTargetUser($user)) {
                $this->error = 'Security rule: You cannot promote or demote an account with equal or higher privileges.';
                return;
            }

            if (Auth::check() && (int) Auth::id() === (int) $user->id && $user->role !== $this->promoteNewRole) {
                $this->error = 'Security rule: You cannot change your own base access role.';
                return;
            }

            $oldRole = $user->role;
            $oldDesig = $user->displayDesignation();
            $oldAllowance = (float) ($user->monthly_allowance ?? 0);
            $newAllowance = max(0.0, (float) $this->promoteNewAllowance);

            $user->role = $this->promoteNewRole;
            if (Schema::hasColumn('users', 'designation')) {
                $user->designation = $newDesig;
            }
            $user->monthly_allowance = $newAllowance;
            if ($this->promoteNewRole !== 'student') {
                $user->manager_id = null;
            }

            $historyEntry = [
                'date'             => $effectiveDate,
                'action'           => $actionType,
                'action_label'     => $actionVerb,
                'from_role'        => $oldRole,
                'to_role'          => $this->promoteNewRole,
                'from_designation' => $oldDesig,
                'to_designation'   => $newDesig,
                'from_allowance'   => $oldAllowance,
                'to_allowance'     => $newAllowance,
                'remarks'          => $remarks,
                'actor'            => Auth::user()?->name ?? ucfirst($this->previewRole),
            ];

            if (Schema::hasColumn('users', 'promotion_history')) {
                $hist = is_array($user->promotion_history) ? $user->promotion_history : [];
                array_unshift($hist, $historyEntry);
                $user->promotion_history = $hist;
            }
            $user->save();

            $this->recordAuditLog(
                'team',
                'member_' . $actionType,
                "{$actionVerb} {$user->name} from {$oldDesig} (" . ucfirst($oldRole) . ", ৳{$oldAllowance}) to {$newDesig} (" . ucfirst($this->promoteNewRole) . ", ৳{$newAllowance}). Effective: {$effectiveDate}. Remarks: {$remarks}",
                $user->email
            );

            $notifTitle = $actionType === 'promote'
                ? "Congratulations! Promoted to {$newDesig}"
                : "Official Designation Update: {$newDesig}";
            $notifMsg = "{$user->name}: Your practice designation has been updated from {$oldDesig} to {$newDesig} (effective {$effectiveDate}, monthly allowance/pay: ৳" . number_format($newAllowance, 0) . "). Note: {$remarks}";
            $this->createPracticeNotification($user->id, $user->role, $notifTitle, $notifMsg, 'award');

            $this->notice = "{$actionVerb} {$user->name} from {$oldDesig} to {$newDesig} (৳" . number_format($newAllowance, 0) . "/month).";
        } else {
            foreach ($this->teamMembers as &$m) {
                if ((int) $m['id'] === (int) $this->promoteMemberId) {
                    $m['role'] = $this->promoteNewRole;
                    $m['designation'] = $newDesig;
                    $m['role_label'] = $newDesig;
                    $m['monthly_allowance'] = max(0.0, (float) $this->promoteNewAllowance);
                    break;
                }
            }
            unset($m);
            $this->notice = "{$actionVerb} {$this->promoteMemberName} to {$newDesig}.";
        }

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
        $this->loadPayrollRows();
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => "{$actionVerb}: {$newDesig}",
            'message' => $this->notice,
        ]);
    }

    public function confirmDeleteMember($id): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Managers can remove practice members.';
            return;
        }

        if (Auth::check() && Auth::id() == $id) {
            $this->error = 'Security rule: You cannot delete your own active account.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        $this->error = null;
        $this->closeDialogs();
        $this->selectedMemberId = (int) $member['id'];
        $this->selectedMemberName = $member['name'];
        $this->deleteConfirmationInput = '';
        $this->showDeleteMemberModal = true;
    }

    public function deleteMember(): void
    {
        $this->enforceAuthenticatedRoleLock();
        $this->error = null;
        $this->notice = null;
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized: Only Partners or authorized Managers can remove team members.';
            return;
        }

        $typed = strtoupper(trim($this->deleteConfirmationInput));
        if ($typed !== 'DELETE' && $typed !== 'CONFIRM') {
            $this->error = 'Security confirmation failed: You must type "DELETE" or "CONFIRM" exactly to proceed.';
            return;
        }

        if (Auth::check() && Auth::id() == $this->selectedMemberId) {
            $this->error = 'Security rule: You cannot delete your own active account.';
            return;
        }

        $memberName = $this->selectedMemberName;
        $memberId = $this->selectedMemberId;

        $firmModel = $this->resolveFirmModel();

        if ($firmModel && Schema::hasTable('users')) {
            $user = User::where('firm_id', $firmModel->id)->where('id', $memberId)->first();
            if ($user) {
                if (! $this->canManageTargetUser($user)) {
                    $this->error = 'Security rule: You cannot remove an account with equal or higher privileges.';
                    return;
                }

                $userEmail = $user->email;

                // DATA RETENTION: a staff exit must not erase payroll or attendance.
                // Those records are the firm's financial audit trail, and deleting
                // the user row used to cascade them away. Members who already have
                // history are deactivated: they can no longer sign in, but every
                // record they produced stays intact. Members without history are
                // still removed completely.
                if ($this->memberHasFinancialHistory($user)) {
                    $this->deactivateMember($user);
                    $this->recordAuditLog('team', 'member_deactivated', "Deactivated practice member {$memberName} ({$userEmail}); financial records retained.", $userEmail);
                    $this->notice = "{$memberName} has attendance or payroll history, so the account was deactivated and all records were retained.";
                    $this->closeDialogs();
                    $this->loadTeamMembers();
                    $this->dispatch('toast', [
                        'type'    => 'info',
                        'title'   => 'Member Deactivated',
                        'message' => $this->notice,
                    ]);

                    return;
                }

                User::where('firm_id', $firmModel->id)->where('manager_id', $user->id)->update(['manager_id' => null]);
                $user->delete();
                $this->recordAuditLog('team', 'member_deleted', "Permanently removed practice member {$memberName} ({$userEmail}).", $userEmail);
                $this->notice = "Member {$memberName} has been permanently removed from the practice.";
            }
        } else {
            $this->teamMembers = array_values(array_filter($this->teamMembers, fn ($m) => $m['id'] != $memberId));
            $this->notice = "Member {$memberName} has been permanently removed.";
        }

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
        $this->loadPayrollRows();
        $this->dispatch('toast', [
            'type'    => 'success',
            'title'   => 'Member Removed',
            'message' => $this->notice ?? 'Team member removed from practice.',
        ]);
    }

    /**
     * Does this member own attendance / payroll / leave / claim records?
     *
     * Every one of those tables carries a cascading foreign key to users, so a
     * hard delete silently destroys the firm's financial audit trail.
     */
    protected function memberHasFinancialHistory(User $user): bool
    {
        $sources = [
            ['attendances', 'user_id'],
            ['payroll_records', 'user_id'],
            ['leave_requests', 'user_id'],
            ['conveyance_claims', 'user_id'],
        ];

        foreach ($sources as [$table, $column]) {
            try {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    if (DB::table($table)->where($column, $user->id)->exists()) {
                        return true;
                    }
                }
            } catch (\Throwable) {
                // A missing/renamed table must never block a legitimate removal,
                // but it also must never silently authorise a destructive delete.
                return true;
            }
        }

        return false;
    }

    /**
     * Disable sign-in for a departed member without deleting their history.
     */
    protected function deactivateMember(User $user): void
    {
        $user->password = bcrypt(Str::password(40));
        $user->remember_token = null;
        if (Schema::hasColumn('users', 'invitation_token')) {
            $user->invitation_token = null;
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $user->is_active = false;
        }
        $user->save();

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Email login credentials (login link, username, and password) to a newly
     * enrolled member or a member whose password was reset by a Manager/Partner.
     *
     * Returns false when delivery fails so the caller can fall back to showing
     * the password on screen.
     */
    protected function sendInvitationEmail(User $user, string $firmName, ?string $plainPassword = null, bool $isPasswordReset = false, ?string $invitationToken = null): bool
    {
        try {
            Mail::to($user->email)->send(new TeamInvitationMail(
                member: $user,
                firmName: $firmName,
                firmSlug: $this->firmSlug,
                plainPassword: $plainPassword,
                isPasswordReset: $isPasswordReset,
                invitationToken: null,
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Credentials email failed for ' . $user->email . ': ' . $e->getMessage());

            return false;
        }
    }
}
