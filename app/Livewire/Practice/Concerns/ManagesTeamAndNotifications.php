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
use App\Models\User;
use App\Services\FirmRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
            } catch (\Throwable) {
            }
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
            } catch (\Throwable) {
            }
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
     * Role hierarchy guard for role assignment.
     *
     * A Partner (or Platform Super Admin) may assign any firm role. A delegated
     * Manager may only ever assign the Articled Student role — otherwise they
     * could mint themselves (or an accomplice) a Partner account.
     */
    protected function canManageTargetRole(string $targetRole): bool
    {
        if (! Auth::check()) {
            return $this->previewRole === 'partner';
        }

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->isPlatformAdmin() || $actor->isPartner()) {
            return in_array($targetRole, [User::ROLE_PARTNER, User::ROLE_MANAGER, User::ROLE_STUDENT], true);
        }

        if (! $actor->can_manage_team) {
            return false;
        }

        return $targetRole === User::ROLE_STUDENT;
    }

    /**
     * Role hierarchy guard for editing / deleting an existing account.
     *
     * A delegated Manager may only touch Articled Students. Partners and other
     * Managers are out of scope, so a Manager can never reset a Partner password,
     * demote a Partner, or delete an account above their own level.
     */
    protected function canManageTargetUser(User $target): bool
    {
        if (! Auth::check()) {
            return $this->previewRole === 'partner';
        }

        /** @var User $actor */
        $actor = Auth::user();

        if ($actor->isPlatformAdmin() || $actor->isPartner()) {
            return true;
        }

        if (! $actor->can_manage_team) {
            return false;
        }

        return $target->role === User::ROLE_STUDENT;
    }

    public function getAvailableManagersProperty(): array
    {
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
                        ->orderByRaw("CASE role WHEN 'partner' THEN 1 WHEN 'manager' THEN 2 WHEN 'student' THEN 3 ELSE 4 END")
                        ->orderBy('name')
                        ->get();

                    if ($dbUsers->isNotEmpty()) {
                        $this->teamMembers = $dbUsers->map(function (User $u) {
                            return [
                                'id'                   => $u->id,
                                'name'                 => $u->name,
                                'email'                => $u->email,
                                'role'                 => $u->role,
                                'role_label'           => match ($u->role) {
                                    'partner' => 'Partner / Principal',
                                    'manager' => 'Audit Manager',
                                    'student' => 'Articled Student',
                                    default   => ucfirst($u->role),
                                },
                                'avatar_url'             => $u->avatar_url ?? null,
                                'phone'                  => $u->phone ?: '—',
                                'payment_account_mode'   => $u->payment_account_mode ?: 'bKash',
                                'payment_account_no'     => $u->payment_account_no ?: '—',
                                'articleship_start_date' => $u->articleship_start_date?->format('Y-m-d') ?? ($u->role === 'student' ? '2026-03-10' : null),
                                'articleship_end_date'   => $u->articleship_end_date?->format('Y-m-d') ?? ($u->role === 'student' ? '2029-03-09' : null),
                                'articleship_period'     => $u->role === 'student'
                                    ? (($u->articleship_start_date?->format('d M Y') ?? '10 Mar 2026') . ' → ' . ($u->articleship_end_date?->format('d M Y') ?? '09 Mar 2029'))
                                    : '—',
                                'student_code'           => $u->student_code ?? ($u->role === 'student' ? ('S-' . $u->id) : null),
                                'partner_unit'         => $u->partner_unit ?? $this->partnerUnit,
                                'manager_id'           => $u->manager_id,
                                'manager_name'         => $u->manager?->name ?? '—',
                                'monthly_allowance'    => (float) ($u->monthly_allowance ?? ($u->role === 'student' ? 6000 : ($u->role === 'manager' ? 25000 : 0))),
                                'can_manage_team'      => (bool) $u->can_manage_team,
                                'can_proxy_attendance' => (bool) $u->can_proxy_attendance,
                                'can_edit_attendance'  => (bool) $u->can_edit_attendance,
                                'can_approve_leave'    => (bool) $u->can_approve_leave,
                                'can_approve_claims'   => (bool) $u->can_approve_claims,
                                'created_at'           => $u->created_at?->format('d M Y') ?? 'Recent',
                            ];
                        })->toArray();
                        return;
                    } else {
                        $this->teamMembers = [];
                        return;
                    }
                } catch (\Throwable) {}
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
        $this->closeDialogs();
        $this->resetNewMemberFields();
        $this->showAddMemberModal = true;
    }

    protected function resetNewMemberFields(): void
    {
        $this->newMemberName = '';
        $this->newMemberEmail = '';
        $this->newMemberPassword = '';
        $this->newMemberRole = 'student';
        $this->newMemberStudentCode = '';
        $this->newMemberManagerId = null;
        $this->newMemberPartnerUnit = $this->partnerUnit;
        $this->newMemberMonthlyAllowance = 6000.00;
        $this->newMemberCanManageTeam = false;
        $this->newMemberCanProxyAttendance = false;
        $this->newMemberCanEditAttendance = false;
        $this->newMemberCanApproveLeave = false;
        $this->newMemberCanApproveClaims = false;
        $this->newMemberArticleshipStart = now()->toDateString();
        $this->newMemberArticleshipEnd = now()->addYears(3)->subDay()->toDateString();
    }

    public function updatedNewMemberArticleshipStart(string $value): void
    {
        if ($this->validDate($value)) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $this->newMemberArticleshipEnd = $dt->modify('+3 years -1 day')->format('Y-m-d');
        }
    }

    public function createMember(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized action: Only Partners or authorized Managers can enroll team members.';
            return;
        }

        $this->validate([
            'newMemberName'  => 'required|string|min:2|max:120',
            'newMemberEmail' => 'required|email|max:150',
            'newMemberRole'  => 'required|in:partner,manager,student',
        ]);

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
            $plainPassword = trim((string) $this->newMemberPassword) !== ''
                ? trim($this->newMemberPassword)
                : Str::password(14);
            $user->password = bcrypt($plainPassword);
            $user->role = $this->newMemberRole;
            $user->partner_unit = $this->newMemberPartnerUnit ?: $this->partnerUnit;
            $user->monthly_allowance = (float) $this->newMemberMonthlyAllowance;
            $user->student_code = $this->newMemberRole === 'student' ? ($this->newMemberStudentCode ?: ('S-' . rand(100, 999))) : null;
            $user->manager_id = ($this->newMemberRole === 'student' && !empty($this->newMemberManagerId)) ? (int) $this->newMemberManagerId : null;
            $user->can_manage_team = (bool) $this->newMemberCanManageTeam;
            $user->can_proxy_attendance = (bool) $this->newMemberCanProxyAttendance;
            $user->can_edit_attendance = (bool) $this->newMemberCanEditAttendance;
            $user->can_approve_leave = (bool) $this->newMemberCanApproveLeave;
            $user->can_approve_claims = (bool) $this->newMemberCanApproveClaims;
            if (Schema::hasColumn('users', 'invitation_token')) {
                $user->invitation_token = Str::random(40);
                $user->invitation_sent_at = now();
            }
            if ($this->newMemberRole === 'student' && Schema::hasColumn('users', 'articleship_start_date')) {
                $user->articleship_start_date = $this->validDate($this->newMemberArticleshipStart) ? $this->newMemberArticleshipStart : now()->toDateString();
                $user->articleship_end_date = $this->validDate($this->newMemberArticleshipEnd) ? $this->newMemberArticleshipEnd : now()->addYears(3)->subDay()->toDateString();
            }
            $user->save();

            $this->recordAuditLog('team', 'member_enrolled', "Enrolled {$user->name} ({$user->email}) as " . ucfirst($user->role) . ".", $user->email);
            $this->notice = "Successfully enrolled {$user->name} as " . ucfirst($user->role) . " (Temporary password: {$plainPassword}).";
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
                'role_label'           => match ($this->newMemberRole) {
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

        $this->closeDialogs();
        $this->loadTeamMembers();
        $this->loadAttendanceRows();
        $this->loadPayrollRows();
    }

    public function openEditMemberModal($id): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManageTeam()) {
            $this->error = 'Only Partners or authorized Managers can edit member profiles and permissions.';
            return;
        }

        $member = collect($this->teamMembers)->firstWhere('id', $id);
        if (! $member) {
            $this->error = 'Member not found.';
            return;
        }

        $this->closeDialogs();
        $this->editMemberId = (int) $member['id'];
        $this->editMemberName = $member['name'];
        $this->editMemberEmail = $member['email'];
        $this->editMemberPhone = ($member['phone'] ?? '') !== '—' ? ($member['phone'] ?? '') : '';
        $this->editMemberNewPassword = '';
        $this->editMemberRole = $member['role'];
        $this->editMemberStudentCode = $member['student_code'] ?? '';
        $this->editMemberManagerId = $member['manager_id'] ?? null;
        $this->editMemberPartnerUnit = $member['partner_unit'] ?? $this->partnerUnit;
        $this->editMemberMonthlyAllowance = (float) ($member['monthly_allowance'] ?? 0);
        $this->editMemberCanManageTeam = (bool) ($member['can_manage_team'] ?? false);
        $this->editMemberCanProxyAttendance = (bool) ($member['can_proxy_attendance'] ?? false);
        $this->editMemberCanEditAttendance = (bool) ($member['can_edit_attendance'] ?? false);
        $this->editMemberCanApproveLeave = (bool) ($member['can_approve_leave'] ?? false);
        $this->editMemberCanApproveClaims = (bool) ($member['can_approve_claims'] ?? false);
        $this->editMemberArticleshipStart = $member['articleship_start_date'] ?? '2026-03-10';
        $this->editMemberArticleshipEnd = $member['articleship_end_date'] ?? '2029-03-09';

        $this->showEditMemberModal = true;
    }

    public function updateMember(): void
    {
        $this->enforceAuthenticatedRoleLock();
        if (! $this->userCanManageTeam()) {
            $this->error = 'Unauthorized action: Only Partners or authorized Managers can modify permissions.';
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

                if (! empty(trim($this->editMemberName))) {
                    $user->name = trim($this->editMemberName);
                }
                if (Schema::hasColumn('users', 'phone')) {
                    $user->phone = trim($this->editMemberPhone) ?: null;
                }
                $cleanEmail = strtolower(trim($this->editMemberEmail));
                if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) && $cleanEmail !== $user->email) {
                    if (! User::where('email', $cleanEmail)->where('id', '!=', $user->id)->exists()) {
                        $user->email = $cleanEmail;
                    }
                }
                if (! empty(trim($this->editMemberNewPassword)) && strlen(trim($this->editMemberNewPassword)) >= 8) {
                    $user->password = bcrypt(trim($this->editMemberNewPassword));
                }
                $user->role = $this->editMemberRole;
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
                $user->save();

                $this->recordAuditLog('team', 'member_updated', "Updated profile and permissions for {$user->name} ({$user->role}).", $user->email);
                $this->notice = "Updated permissions and profile for {$user->name}.";
            }
        } else {
            foreach ($this->teamMembers as &$m) {
                if ($m['id'] == $this->editMemberId) {
                    $m['role'] = $this->editMemberRole;
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

        $this->closeDialogs();
        $this->selectedMemberId = (int) $member['id'];
        $this->selectedMemberName = $member['name'];
        $this->deleteConfirmationInput = '';
        $this->showDeleteMemberModal = true;
    }

    public function deleteMember(): void
    {
        $this->enforceAuthenticatedRoleLock();
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

                User::where('firm_id', $firmModel->id)->where('manager_id', $user->id)->update(['manager_id' => null]);
                $userEmail = $user->email;
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
    }

}
