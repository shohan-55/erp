# Attendance & Duty Movement — the rules

**Read this before changing anything in
`app/Livewire/Practice/Concerns/ManagesAttendanceCalendarAndRadar.php`,
`resources/views/livewire/practice/partials/attendance*.blade.php` or
`app/Console/Commands/CloseMissedCheckouts.php`.**

Last updated: 30 October 2026.

---

## 1. The day is a series of legs, not a single in/out pair

A member of an audit firm does not sit in one place. A normal day is:

```
09:58  Office HQ          check-in
11:20  Office HQ          out → ON DUTY  (travelling)
12:10  Client A           check-in
14:05  Client A           out → ON DUTY  (travelling)
14:40  Client B           check-in
18:30  Client B           out → CLOSE    (day finished)
```

Each leg is stored in `attendances.movement_logs` (JSON array) as
`{seq, mode, site, in_time, in_location, out_time, out_location, out_action}`.
`attendances.session_state` tracks where the day stands:

| `session_state` | Meaning | Can check in? | Can close? |
|---|---|---|---|
| `inactive` | nothing recorded yet today | yes | no |
| `active` | on site, working | no (already in) | yes (via review) |
| `on_duty_transit` | left a site, travelling | **yes** (arrives at next site) | yes (via review) |
| `closed` | day finished | no | no |

> **Lateness is not fixed at check-in time.** A declared *working day
> exception* (hartal, storm, half-day) can change that date's hours and even
> excuse lateness outright; see
> [`docs/WORKING_DAY_EXCEPTIONS.md`](WORKING_DAY_EXCEPTIONS.md). The status can
> be re-derived from the stored `in_time`, but the arrival time itself never
> moves.

The **last leg is always the current one**; every earlier leg keeps its own
`out_time`/`out_action` forever.

---

## 1a. The weekly holiday belongs to the firm

The weekend is **not** hardcoded. `firms.weekly_off_days` holds a JSON list of
weekday numbers (0 = Sunday … 6 = Saturday); `NULL` means the platform default,
**Friday + Saturday** — the usual Dhaka CA-firm weekend.

| Stored value | Meaning |
|---|---|
| `NULL` | never configured → Friday + Saturday (every pre-existing tenant) |
| `[5]` | closes Friday only |
| `[0]` | works Saturday, closes Sunday |
| `[]` | genuinely open all seven days |

Where to change it: **Workspace Settings → Weekly Off Days** (Partner only;
Managers can submit a policy proposal instead). It is deliberately *not* per
partner unit: the calendar, the notification copy and the policy summary show one
firm-wide pattern.

Never write `in_array($dow, [5, 6])` again. Use the component helper:

```php
$isWeekend = $this->isWeeklyOffDay($dow);   // reads $this->weeklyOffDays
```

The model normalises everything through `Firm::normaliseWeeklyOffDays()` and
formats it with `Firm::describeWeeklyOffDays()` / `shortWeeklyOffDays()`.

**Not covered:** a per-partner-unit weekend, and a rotary/weekly-alternating
weekend (some firms close the 2nd and 4th Saturday). Add those as new fields,
not as more `if` statements.

---

## 2. What the user sees

- **Out** (dashboard dock or attendance page) opens a chooser:
  - **B. On Duty (Moving to Client / Office)** — records the departure and
    keeps the day open. The member then checks in at the next site.
  - **A. Close (End Today's Duty)** — *does not close immediately*.
- Choosing Close opens the **Final Check-out Review**:
  - every leg of today with in/out times,
  - first check-in, total duty time, current time and location,
  - a warning if they are currently `on_duty_transit`,
  - a mandatory checkbox: *"I have finished today's duty…"*,
  - **Confirm Final Check-out** (disabled until the box is ticked),
  - **Not finished — Keep On Duty** as an escape hatch.

Only after that does the day become `closed`.

### Why the extra step exists

A student on their way from client A to client B tapped "Close" by mistake and
their attendance day ended at 11:00. Payroll, the Partner dashboard and the
ICAB register all believed they went home at 11. The review panel makes that
mistake impossible to perform with one click.

---

## 3. The server-side gate (do not remove)

`confirmCheckoutWithAction('close')` refuses to close unless
`$finalCloseConfirmed` is true:

```php
if ($action === 'close') {
    if (! $this->previewSessionActive && $this->sessionState !== 'on_duty_transit') {
        // already closed — no second check-out is written
        return;
    }
    if (! $this->finalCloseConfirmed) {
        $this->openFinalCheckoutReview();   // show the review, write nothing
        return;
    }
}
```

The gate lives in the **component**, not the Blade view, because a double
click, a stale browser tab, a replayed Livewire request or a crafted POST all
reach the same method. `confirmFinalCheckout()` is the public entry point used
by the button; it re-checks the flag itself.

Closing twice is idempotent: the second attempt keeps the original `out_time`
and sets a notice.

---

## 4. A new leg clears the previous departure

When the member checks in again (arriving at client A after leaving the
office), the database row must stop claiming they checked out at 11:20:

```php
$att->out_time = null;
$att->out_lat = null;
$att->out_lng = null;
$att->out_location_name = null;
```

`in_time` is **not** touched — the first check-in of the day is what decides
Right Time / Grace / Late Present. `classifyArrivalStatus()` only runs when
`in_time` was empty, so arriving at the second client never re-classifies the
day as Late.

Regression pinned by
`FinalCheckoutGuardTest::test_checking_in_again_clears_the_previous_departure_time`.

---

## 5. The nightly sweep

`php artisan erp:flag-missed-checkouts [--days=7]` (scheduled) flags any row
from a previous day that was never closed:

```
out_time IS NULL  OR  session_state != 'closed'
```

The second half matters: somebody who tapped **On Duty** at 11:00 and never
came back *has* an `out_time` (the departure), so an `out_time IS NULL` sweep
called that day complete and payroll paid for it. Flagged rows get
`correction_requested = true` plus a notification to the member and their
manager. The command never invents an out-time — that would falsify a legal
record; the member submits a correction request instead.

---

## 6. Where the dialogs live

`partials/attendance-modals.blade.php` holds the checkout chooser, the final
review and the movement timeline, and is included **once** from
`preview.blade.php`. It used to sit inside `partials/attendance.blade.php`,
which meant the dashboard's own "Out" button set the flag but rendered nothing
at all (only one section is rendered per request). Do not move them back into
a section partial.

---

## 7. Tests that pin all of this

`tests/Feature/FinalCheckoutGuardTest.php` (10 tests):

| Test | Guarantees |
|---|---|
| `test_a_single_click_cannot_end_the_duty_day` | review opens, nothing written |
| `test_the_review_panel_shows_every_leg_of_the_day` | 3 legs, first-in, elapsed, location |
| `test_confirming_without_ticking_the_box_does_nothing` | server gate |
| `test_ticking_the_box_and_confirming_closes_the_day_once` | close + idempotency |
| `test_the_review_panel_offers_keep_on_duty_as_the_way_out` | escape hatch |
| `test_checking_in_again_clears_the_previous_departure_time` | no stale out_time |
| `test_the_sweep_catches_an_on_duty_move_that_was_never_closed` | sweep P1 |
| `test_the_sweep_still_catches_a_day_with_no_checkout_at_all` | sweep regression |
| `test_the_sweep_leaves_a_properly_closed_day_alone` | no false positives |
| `test_the_dashboard_can_open_the_checkout_dialog` | dialog reachable from the dock |

`ProductionWorkspaceTest::test_student_can_record_on_duty_movement_and_close_duty_with_movement_timeline`
covers the end-to-end three-leg flow through the same two-step close.

Run them with the no-CI harness — see `docs/RUNNING_TESTS_WITHOUT_CI.md`:

```bash
php artisan test --filter=FinalCheckoutGuardTest
```

---

## 8. Known limits (be honest with the firm)

- **GPS is captured, not enforced.** `location_mismatch` and `distance_km` are
  recorded and shown as a warning; check-in is not blocked outside the radius.
- **A member can still close from the wrong place** — the review shows the
  location, it cannot prove they are standing there.
- **No offline support**: the check-in buttons need a live connection.
- **On Duty has no expected-arrival timer** — if somebody goes on duty and
  never arrives, the nightly sweep only catches it the next morning.
