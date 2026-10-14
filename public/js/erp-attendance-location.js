/**
 * Voluntary browser GPS for attendance forms.
 * No continuous tracking, no CDN and no third-party location request.
 * Declining browser location still permits attendance to be recorded.
 */
(() => {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-erp-attendance-form]').forEach((form) => {
      const consent = form.querySelector('[data-erp-location-consent]');
      const status = form.querySelector('[data-erp-location-status]');
      if (!consent || !status) return;

      form.addEventListener('submit', (event) => {
        if (!consent.checked || form.dataset.locationAttempted === 'yes') return;
        event.preventDefault();
        form.dataset.locationAttempted = 'yes';

        const button = event.submitter;
        if (button) button.disabled = true;

        const complete = () => {
          if (button) button.disabled = false;
          form.requestSubmit();
        };
        if (!navigator.geolocation) {
          status.textContent = 'Location unavailable; attendance will be saved without GPS.';
          complete();
          return;
        }

        status.textContent = 'Requesting location permission…';
        navigator.geolocation.getCurrentPosition((position) => {
          const values = {
            location_latitude: position.coords.latitude,
            location_longitude: position.coords.longitude,
            location_accuracy: position.coords.accuracy,
            location_consent: '1'
          };
          for (const [name, value] of Object.entries(values)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value);
            form.append(input);
          }
          status.textContent = 'Location captured in this browser for this attendance event.';
          complete();
        }, () => {
          status.textContent = 'Location was declined or unavailable. Attendance continues without GPS.';
          complete();
        }, {enableHighAccuracy: true, timeout: 12000, maximumAge: 0});
      });
    });
  });
})();
