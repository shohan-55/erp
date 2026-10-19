/* Accessible date details: no writable attendance actions on calendar. */
(() => {
  const init = () => {
    const modal = document.querySelector('[data-calendar-dialog]');
    const calendar = document.querySelector('.erp-month-calendar');
    if (!modal || !calendar || calendar.dataset.calendarInitialized) return;
    calendar.dataset.calendarInitialized = 'yes';
    calendar.querySelectorAll('[data-calendar-day]').forEach(day => {
      day.addEventListener('click', () => {
        modal.querySelector('[data-calendar-modal-date]').textContent = day.dataset.date;
        modal.querySelector('[data-calendar-modal-status]').textContent = day.dataset.title;
        modal.querySelector('[data-calendar-modal-detail]').textContent = day.dataset.detail;
        // Pre-rendered Blade template is role-scoped on the server. Clone real
        // DOM nodes; never parse untrusted JSON/HTML from a data attribute.
        const details = modal.querySelector('[data-calendar-modal-leaves]');
        const template = document.getElementById(day.dataset.leaveTemplate);
        if (details) details.replaceChildren(...(template ? template.content.cloneNode(true).childNodes : []));

        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open','');
      });
    });
    modal.querySelectorAll('[data-calendar-close]').forEach(button =>
      button.addEventListener('click', () => {
        if (typeof modal.close === 'function') modal.close();
        else modal.removeAttribute('open');
      })
    );
    modal.addEventListener('click', event => {
      if (event.target === modal && typeof modal.close === 'function') modal.close();
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
