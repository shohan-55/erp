# Working Day Exceptions — one date, different hours

**Read this before touching `classifyArrivalStatus()`, `effectiveOfficeHoursFor()`
or anything that counts late days.**

Last updated: 31 October 2026.

---

## 1. The problem this solves

A CA firm in Dhaka does not have one fixed set of hours. Real weeks look like
this:

| Day | What actually happens |
|---|---|
| Hartal called overnight | the office opens at 12:00, nobody should be marked late for a strike they cannot control |
| Torrential rain / flood | same: staff arrive at 11:40, that is not their fault |
| Eid half-day | office closes at 16:00 instead of 18:00 |
| Budget night | office opens at 09:00 instead of 10:00 |
| Political unrest | the firm stays open but late arrivals are simply not counted |

Editing **Workspace Settings → policy** for one of these is wrong, and
dangerous: the policy is *retroactive*. Changing `office_start` from 10:00 to
12:00 re-derives every past day under the new rule, so the late-day count for a
month that was already approved and paid changes silently.

So the change must be attached to **one date**, not to the policy.

---

## 2. The model

A working day exception is a row in `practice_holidays` with
`category = 'exception'`:

| Column | Meaning |
|---|---|
| `holiday_date` | the one date it applies to |
| `partner_unit` | `NULL` = whole firm, otherwise one Partner Unit |
| `title` | "Hartal — office opens at 12:00" |
| `office_start_override` | `"12:00"` or `NULL` (keep the policy's start) |
| `office_end_override` | `"16:00"` or `NULL` |
| `grace_minutes_override` | `60` or `NULL` |
| `late_exempt` | `true` = lateness on this date is never counted |
| `override_reason` | free text for the audit trail |

It is **not** a holiday. The calendar must never paint it red, because people
are expected to attend.

### The resolution order

```
effective hours for a date =
    the date's Partner-Unit exception (if any)
    else the date's firm-wide exception (if any)
    else the standing policy from partner_policies
        (office_start / office_end / grace_minutes)
```

Every lateness decision goes through `effectiveOfficeHoursFor($date)`. There is
exactly one place in the codebase that decides whether somebody is late.

---

## 3. The statuses

| Status | Meaning | Counted by payroll? |
|---|---|---|
| `Right Time` | arrived at or before the start time | no (it is good) |
| `Late` | inside the grace window past the start | **no** — grace is not a penalty |
| `Late Present` | beyond the grace window | **yes** |
| `Late (Excused)` | beyond the grace window, but the firm declared the date exempt | **no** |

`Late (Excused)` exists so the register stays honest. It would be easy to write
`Right Time` and hide the fact that somebody arrived at 11:40, but then the
evidence that the exception was needed disappears. Instead the record says what
happened *and* says it does not count.

The payroll ledger filters `whereIn('status', ['Late', 'Late Present'])`, so
`Late (Excused)` can never reach a salary deduction. The punctuality leaderboard
scores an excused day at full marks, and the calendar counts it in a separate
teal chip rather than as a late day.

---

## 4. What a Partner actually does

**Workspace Settings → Working Day Exception**

1. pick the date,
2. optionally type a start / end / grace (blank = keep the policy's value),
3. optionally tick **"Lateness is not counted on this date"**,
4. name it, add a reason,
5. press **Preview impact** — this reports *"N attendance records on this date,
   M would hold a different status"* and lists each `Late → On time` change,
6. press **Save**.

Nothing is written until Save. Saving:

- writes the exception row,
- **re-derives the status of every attendance row on that date from its stored
  `in_time`** — the arrival time itself is never touched, and a row without a
  check-in is skipped,
- writes an audit entry (`working_day_exception_saved`) listing how many rows
  changed,
- notifies the firm if any row changed,
- warns when that month's payroll is already reviewed/approved/paid.

Only a **Partner** can do this. It changes what the firm counts as working time,
which is the same class of decision as the weekly off days — a Manager has the
policy-proposal workflow instead.

---

## 5. What it deliberately does NOT do

- **It does not delete or create attendance rows.** A member with no check-in
  on that date stays absent; an exception excuses lateness, it does not excuse
  absence.
- **It does not rewrite payroll records.** Payroll is regenerated from the
  attendance table; if the month is already approved, reopen the period first.
- **It does not repeat.** One row = one date. A week-long flood means seven
  rows (or a holiday for the whole week, which is the right answer if the firm
  is actually closed).
- **It does not apply retroactively to future days.** Tomorrow keeps the policy.
- **It does not cover a per-member exception.** If one articled student was
  stuck, that is a manual attendance edit with a remark, not a firm policy.

---

## 6. Tests that pin this behaviour

`tests/Feature/WorkingDayExceptionTest.php` (16 tests):

| Test | Guarantees |
|---|---|
| `test_an_ordinary_day_uses_the_standing_policy` | no exception ⇒ policy hours |
| `test_an_exception_shifts_the_start_time_for_that_date_only` | 11:40 on time today, still late tomorrow |
| `test_a_declared_exception_excuses_lateness` | `Late (Excused)` |
| `test_excused_lateness_is_never_counted_by_payroll` | the exact payroll filter returns 0 |
| `test_saving_an_exception_reclassifies_the_days_attendance` | stored `in_time` re-derived |
| `test_the_preview_reports_the_impact_without_writing_anything` | dry run writes nothing |
| `test_the_standing_policy_is_never_modified_by_an_exception` | `partner_policies` untouched |
| `test_an_exception_needs_at_least_one_effect` | validation |
| `test_the_end_time_must_be_after_the_start_time` | validation |
| `test_a_malformed_time_is_rejected` | 24-hour validation |
| `test_a_manager_cannot_declare_a_working_day_exception` | role guard |
| `test_a_unit_specific_exception_wins_over_the_firm_wide_one` | precedence |
| `test_an_exception_day_is_shown_as_a_shift_not_a_holiday` | never labelled a holiday |
| `test_the_dashboard_shows_todays_exception_as_a_banner` | staff see it before checking in |
| `test_removing_the_exception_stops_the_excuse` | delete restores the policy |
| `test_a_locked_payroll_period_is_flagged_after_saving` | payroll warning |

---

## 7. Where the code lives

| Concern | File |
|---|---|
| storage + `shiftWindowLabel()` / `effectLabel()` | `app/Models/PracticeHoliday.php` |
| hours resolution, classification, preview, save, re-classify | `app/Livewire/Practice/Concerns/ManagesPoliciesHolidaysAndSettings.php` |
| calendar counts, leaderboard weighting, badge colours | `app/Livewire/Practice/Concerns/ManagesAttendanceCalendarAndRadar.php` |
| the form and the declared-exceptions list | `partials/settings.blade.php` |
| the "Today's Office Timing" banner | `preview.blade.php` |
| calendar chip / selected-day panel | `partials/dashboard.blade.php` |

**Migration:** `2026_10_31_000001_add_working_day_exceptions_to_practice_holidays_table.php`
(adds four nullable columns; safe on existing data, but `php artisan migrate --force`
must run on deploy).

---

## 8. Honest limits

- **Nobody is notified at the moment the hartal is declared** — the banner
  appears when each member next opens the app. WhatsApp is still how the firm
  actually tells people.
- **A mid-day declaration cannot re-open a closed session.** If somebody already
  checked out, the exception changes their *status*, not their hours.
- **The re-classification is not reversible by deleting the row.** Deleting an
  exception stops future classification but leaves the statuses it already
  wrote; the notice says so, and a manual edit is the way back.
- **Time zones:** hours are compared as `Asia/Dhaka` wall-clock strings. A firm
  operating outside Bangladesh would need the zone to be configurable too.
