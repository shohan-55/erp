/* Dashboard attendance: no optimistic writes. Only the server flash result may celebrate success. */
(() => {
  function initAttendance() {
    const scope = document.querySelector('[data-attendance-experience]');
    const dialog = document.querySelector('[data-attendance-dialog]');
    if (!scope || !dialog || scope.dataset.attendanceInitialized === 'yes') return;
    scope.dataset.attendanceInitialized = 'yes';

    const form = scope.querySelector('[data-attendance-confirm-form]');
    const primary = scope.querySelector('[data-attendance-primary]');
    const confirm = dialog.querySelector('[data-attendance-confirm]');
    const title = dialog.querySelector('[data-attendance-dialog-title]');
    const description = dialog.querySelector('[data-attendance-dialog-description]');
    const panel = dialog.querySelector('[role="dialog"]');
    const mode = form.querySelector('[data-attendance-mode]');
    const client = form.querySelector('[data-attendance-client]');

    // Monotonic display anchored in authenticated server time. The server alone
    // determines saved lateness and payroll-related review eligibility.
    const clock = scope.querySelector('[data-attendance-clock]');
    const configEl = scope.querySelector('[data-attendance-clock-config]');
    if (clock && configEl) {
      const cfg = JSON.parse(configEl.textContent);
      let schedule = cfg.schedule;
      let active = cfg.active;
      let anchorTime = performance.now();
      let anchorMs = schedule.server_ms;
      let lastSync = anchorTime;
      const clockTime = clock.querySelector('[data-attendance-clock-time]');
      const clockPeriod = clock.querySelector('[data-attendance-clock-period]');
      const clockStatus = clock.querySelector('[data-attendance-clock-status]');
      const clockContext = clock.querySelector('[data-attendance-clock-context]');
      const clockProgress = clock.querySelector('[data-attendance-progress]');
      const clockFill = clock.querySelector('[data-attendance-fill]');
      const count = clock.querySelector('[data-attendance-qualifying-count]');
      const syncLabel = clock.querySelector('[data-attendance-sync-label]');
      const clamp = (n) => Math.max(0, Math.min(1, Number.isFinite(n) ? n : 0));
      const phaseClasses = ['attendance-clock--safe','attendance-clock--grace','attendance-clock--late','attendance-clock--half','attendance-clock--active','attendance-clock--holiday'];
      const formatParts = new Intl.DateTimeFormat('en-US', {
        timeZone: schedule.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
      });

      const render = () => {
        const nowMs = anchorMs + performance.now() - anchorTime;
        const fields = Object.fromEntries(formatParts.formatToParts(new Date(nowMs)).map(part => [part.type,part.value]));
        clockTime.textContent = fields.hour+':'+fields.minute+':'+fields.second;
        clockPeriod.textContent = fields.dayPeriod;
        let stage = 'safe', progress = 0, accent = [34,197,94];
        let message = 'Before office start · On-time window';
        let context = 'Office begins at '+schedule.office_start+'.';
        if (active) {
          stage = 'active';
          accent = [61,227,171];
          message = 'Session active · Attendance recorded';
          context = 'Your recorded check-in is preserved on the server.';
          progress = 1;
        } else if (schedule.holiday) {
          stage = 'holiday';
          accent = [166,174,190];
          message = 'Scheduled day off';
          context = 'Your approved calendar marks today as a holiday.';
        } else if (nowMs > schedule.start_ms && nowMs <= schedule.grace_end_ms) {
          stage = 'grace';
          progress = clamp((nowMs - schedule.start_ms) / Math.max(1000, schedule.grace_end_ms - schedule.start_ms));
          accent = [Math.round(34 + 200*progress), Math.round(197 - 18*progress), Math.round(94 - 86*progress)];
          message = 'Late · Within grace';
          context = 'Late is visible now, but this arrival is excluded from the 3-day review count.';
        } else if (nowMs > schedule.grace_end_ms) {
          const end = schedule.half_day_ms ?? schedule.office_end_ms;
          progress = clamp((nowMs - schedule.grace_end_ms) / Math.max(1000, end - schedule.grace_end_ms));
          stage = schedule.half_day_ms !== null && nowMs >= schedule.half_day_ms ? 'half' : 'late';
          accent = stage === 'half' ? [239,68,68] : [Math.round(234+5*progress), Math.round(179-111*progress), Math.round(8+60*progress)];
          message = stage === 'half' ? 'Half-day warning threshold reached' : 'Late · Grace period ended';
          context = stage === 'half'
            ? 'Visual alert only. Half-day leave or payroll action needs an approved decision.'
            : 'After-grace arrival may count toward the 3-day review; salary is never deducted automatically.';
        }
        clock.classList.remove(...phaseClasses);
        clock.classList.add('attendance-clock--'+stage);
        clock.dataset.attendanceStage = stage;
        clock.style.setProperty('--clock-accent','rgb('+accent.join(',')+')');
        clock.style.setProperty('--clock-glow','rgba('+accent.join(',')+',.3)');
        clockStatus.textContent = message;
        clockContext.textContent = context;
        const pct = Math.round(progress * 100);
        clockFill.style.width = pct+'%';
        clockProgress.setAttribute('aria-valuenow',String(pct));
        const stale = performance.now() - lastSync > 120000;
        clock.classList.toggle('attendance-clock--stale', stale);
        syncLabel.textContent = stale ? '· Sync paused (display estimate)' : '· Synced';
      };

      const sync = async () => {
        try {
          const response = await fetch(clock.dataset.attendanceClockUrl, {
            credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
          });
          if (!response.ok) throw new Error('Clock synchronization unavailable');
          const payload = await response.json();
          if (!payload.clock || typeof payload.clock.server_ms !== 'number') throw new Error('Invalid clock response');
          schedule = payload.clock;
          active = Boolean(payload.active);
          anchorMs = schedule.server_ms;
          anchorTime = performance.now();
          lastSync = anchorTime;
          count.textContent = String(payload.qualifying_late_days);
          render();
        } catch (_) {
          // Offline display may be approximate; attendance stays server-based.
          render();
        }
      };
      render();
      window.setInterval(render,1000);
      window.setInterval(sync,60000);
      document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
      sync();
    }

    let previousFocus = null;

    const modalVisible = () => !dialog.hidden;
    const closeModal = () => {
      if (!modalVisible() || form.dataset.attendanceConfirmed === 'yes') return;
      dialog.hidden = true;
      document.body.style.overflow = '';
      if (previousFocus && previousFocus.isConnected) previousFocus.focus();
    };

    const openModal = () => {
      if (modalVisible()) return;
      previousFocus = document.activeElement;
      const checkingOut = form.dataset.attendanceKind === 'out';
      title.textContent = checkingOut ? 'Finish your work session?' : 'Ready to check in?';
      description.textContent = checkingOut
        ? 'Confirm your check-out. The server will record your actual finishing time.'
        : 'Confirm your check-in. The server will determine your arrival status using your approved schedule.';
      confirm.textContent = checkingOut ? 'OK, Check Out →' : 'OK, Check In →';
      dialog.hidden = false;
      document.body.style.overflow = 'hidden';
      confirm.focus();
    };

    if (mode && client) {
      const syncMode = () => {
        client.required = mode.value === 'client';
        client.setAttribute('aria-required', client.required ? 'true' : 'false');
      };
      mode.addEventListener('change', syncMode);
      syncMode();
    }

    // Capturing prevents the optional GPS submit listener from running on an
    // unconfirmed form. After confirmation, it remains free to collect consented GPS.
    form.addEventListener('submit', (event) => {
      if (form.dataset.attendanceConfirmed === 'yes') return;
      event.preventDefault();
      event.stopImmediatePropagation();
      if (!form.reportValidity()) return;
      openModal();
    }, true);

    dialog.querySelectorAll('[data-attendance-cancel]').forEach((button) => {
      button.addEventListener('click', closeModal);
    });

    confirm.addEventListener('click', () => {
      if (form.dataset.attendanceConfirmed === 'yes') return;
      if (!form.reportValidity()) {
        closeModal();
        return;
      }
      form.dataset.attendanceConfirmed = 'yes';
      confirm.disabled = true;
      dialog.hidden = true;
      document.body.style.overflow = '';
      primary.disabled = true;
      primary.setAttribute('aria-busy', 'true');
      const label = primary.querySelector('span:nth-child(2)');
      if (label) label.textContent = 'Recording securely…';
      // Exactly one user confirmation; server handles duplicate open sessions.
      // Browser GPS (when consented) remains in the existing submit pipeline.
      form.requestSubmit();
    });

    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeModal();
      }
      if (event.key !== 'Tab') return;
      const controls = [...dialog.querySelectorAll('button:not([disabled])')];
      if (!controls.length) return;
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    const result = document.querySelector('[data-attendance-result]');
    if (result) {
      const done = result.querySelector('[data-attendance-done]');
      const dismiss = () => {
        result.hidden = true;
        document.body.style.overflow = '';
        if (primary.isConnected) primary.focus();
      };
      document.body.style.overflow = 'hidden';
      done.addEventListener('click', dismiss);
      done.focus();
      result.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') dismiss();
        if (event.key === 'Tab') {
          event.preventDefault();
          done.focus();
        }
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAttendance, { once: true });
  } else {
    initAttendance();
  }
})();