# Phase 10 — Advanced Attendance and Proxy Operations

Development base: `419fc8434cd53299e0788d69053c0227e9cb09a1`. No production actions.

## Implemented
- **Explicit, revocable Manager/Moderator permission:** `attendance.proxy` is a separate server-enforced operation. Merely being a Manager, having a custom title, or receiving an old Full Workspace grant does not enable proxy access. Partner grants it from the Phase 06 Access Control Studio or Phase 05 Custom Roles. All activity remains restricted to the Manager's *currently assigned* students within the selected Partner workspace.
- **Server-authored check-in/check-out:** authorized Manager supplies a specific student, work mode/client and a mandatory 15–1000 character reason. Actual timestamps use the server, never form-supplied times. Employee GPS is NOT impersonated: the proxy endpoints reject location fields, store no GPS, and visibly mark the source.
- **Independent Partner verification:** all proxy sessions start `pending`; the student and Partner are notified. Until reviewed, pending sessions are excluded from monthly Attendance Reports, Daily Reconciliation and late-day totals. A Partner can verify a completed session or reject an inaccurate session. Rejecting an open proxy closes it at genuine server time so a mistaken entry cannot leave a student stuck.
- **Immutable provenance:** separate proxy creator, proxy checkout actor, start/end reason, Partner verification actor/time/reason, original server timestamps and the existing append-only tenant audit events. The recorded original is never rewritten to appear as a student self-punch.
- **Concurrency/anti-abuse:** DB transactions lock the Partner workspace and target membership, prevent duplicate open sessions and cross-company/student selection, validate live grant revocation again *inside* the lock, reject adjustments in frozen payroll months, and prevent repeated final verification.
- **Existing correction process retained:** inaccurate historical check-ins, late times and missed punches continue through Student/Manager-initiated reasoned correction requests with final Partner approval. No bypass of the already-shipped safeguards.
- **Manager UX and Partner UX:** attendance page includes a conditional assigned-student proxy form and a Pending/Verified/Rejected marker with Partner decision controls. Nondelegated Managers never see proxy forms.

## Boundaries and follow-up
- Browser GPS is self-reported, opt-in for a student's own real punch only, encrypted privately with retention; it is not a substitute for identity or fraud-proof physical location.
- This phase does not create automatic payroll deductions or unlock finalized payroll snapshots.
- A rejected session is kept for audit but excluded from compliance counts; pending attendance is not evidence of physical presence.
- Manager cannot arbitrarily alter historical timestamps; proposals to adjust earlier dates still require the separate correction workflow.

## Acceptance gates
- [x] Additive migration, explicit deny-unless-authorized permission, zero GPS spoofing
- [x] Protected proxy endpoints and Partner verification
- [x] Pending/rejected session exclusion from monthly report, daily reconciliation and late reviews
- [x] UI, traceability and regression tests
- [ ] Latest head PHPUnit/Vite and browser QA green
- [ ] MySQL8 integration and cPanel package green
- [ ] Reviewed development-only PR merge, then exact merge SHA CI success
- [ ] Staging restore drill and owner-approved go-live (separate)
