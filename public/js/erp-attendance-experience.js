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