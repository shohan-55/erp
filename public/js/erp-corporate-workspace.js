/* Corporate workdesk: progressive enhancement. No privileged data is cached. */
(() => {
  const init = () => {
    const actionCenter = document.querySelector('[data-corporate-action-center]');
    if (actionCenter && !actionCenter.dataset.initialized) {
      actionCenter.dataset.initialized = '1';
      const cards = Array.from(actionCenter.querySelectorAll('[data-corporate-action]'));
      const search = actionCenter.querySelector('[data-corporate-action-search]');
      const buttons = Array.from(actionCenter.querySelectorAll('[data-corporate-filter]'));
      const count = actionCenter.querySelector('[data-corporate-action-count]');
      const empty = actionCenter.querySelector('[data-corporate-action-empty]');
      const storeKey = 'erp-action-pins-v1:' + (actionCenter.dataset.scope || 'local');
      let saved = [];
      try { const value = JSON.parse(localStorage.getItem(storeKey) || '[]'); saved = Array.isArray(value) ? value.filter(x => typeof x === 'string').slice(0, 20) : []; } catch (_) {}
      const activeIds = new Set(cards.map(card => card.dataset.actionId));
      let pins = new Set(saved.filter(id => activeIds.has(id)));
      let filter = 'all';
      const persist = () => { try {localStorage.setItem(storeKey, JSON.stringify([...pins]));}catch(_){} };
      const update = () => {
        const term = (search?.value || '').trim().toLocaleLowerCase();
        let visible = 0;
        cards.forEach(card => {
          const id = card.dataset.actionId;
          const pinned = pins.has(id);
          const pin = card.querySelector('[data-corporate-pin]');
          pin.dataset.pinned = pinned ? 'true' : 'false';
          pin.setAttribute('aria-pressed', pinned ? 'true' : 'false');
          pin.setAttribute('aria-label', (pinned ? 'Unpin ' : 'Pin ') + card.dataset.actionTitle);
          const matchesFilter = filter === 'all' || (filter === 'pinned' ? pinned : card.dataset.actionCategory === filter);
          const matchesText = (card.dataset.search || '').toLocaleLowerCase().includes(term);
          card.hidden = !(matchesFilter && matchesText);
          if (!card.hidden) visible++;
        });
        buttons.forEach(btn => btn.setAttribute('aria-pressed', btn.dataset.corporateFilter === filter ? 'true' : 'false'));
        if (count) count.textContent = visible + ' of ' + cards.length + ' actions shown';
        if (empty) empty.hidden = visible !== 0;
      };
      search?.addEventListener('input', update);
      buttons.forEach(btn => btn.addEventListener('click', () => {
        filter = btn.dataset.corporateFilter;
        update();
      }));
      cards.forEach(card => card.querySelector('[data-corporate-pin]')?.addEventListener('click', () => {
        const id = card.dataset.actionId;
        if (pins.has(id)) pins.delete(id); else if (pins.size < 8) pins.add(id);
        persist();
        update();
      }));
      document.addEventListener('keydown', event => {
        if (!search || event.ctrlKey || event.metaKey || event.altKey) return;
        const tag = event.target?.tagName?.toLowerCase();
        const editing = ['input','textarea','select'].includes(tag) || event.target?.isContentEditable;
        if (event.key === '/' && !editing && !event.defaultPrevented) {
          event.preventDefault(); search.focus();
        } else if (event.key === 'Escape' && document.activeElement === search) {
          search.value=''; filter='all'; update();search.blur();
        }
      });
      update();
    }


    // Search/filter only already server-authorized records rendered on this page.
    document.querySelectorAll('[data-corporate-history-toolbar]').forEach(toolbar => {
      if (toolbar.dataset.initialized) return;
      toolbar.dataset.initialized = '1';
      const key = toolbar.dataset.historyTarget;
      const group = [...document.querySelectorAll('[data-corporate-history-group]')]
        .find(element => element.dataset.corporateHistoryGroup === key);
      if (!group) return;
      const items = [...group.querySelectorAll('[data-corporate-history-item]')];
      const search = toolbar.querySelector('[data-history-search]');
      const status = toolbar.querySelector('[data-history-status]');
      const count = toolbar.querySelector('[data-history-count]');
      const reset = toolbar.querySelector('[data-history-reset]');
      const apply = () => {
        const text = (search?.value || '').trim().toLocaleLowerCase();
        const stage = status?.value || 'all';
        let shown=0;
        items.forEach(item => {
          const match = (stage === 'all' || stage === item.dataset.historyStatus)
            && item.textContent.toLocaleLowerCase().includes(text);
          item.hidden = !match;
          if (match) shown++;
        });
        if (count) count.textContent = shown + ' / ' + items.length + ' loaded records';
      };
      search?.addEventListener('input',apply);
      status?.addEventListener('change',apply);
      reset?.addEventListener('click',()=>{if(search)search.value='';if(status)status.value='all';apply();});
      apply();
    });

    // Extra confirmation for explicitly tagged approval/financial actions;
    // native attendance's existing confirmation remains separate.
    const dialog = document.querySelector('[data-corporate-confirm-dialog]');
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = '1';
    let pending = null;
    let submitter = null;
    const label = dialog.querySelector('[data-corporate-confirm-label]');
    const go = dialog.querySelector('[data-corporate-confirm-go]');
    const close = () => { if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open'); pending=null; submitter=null; };
    document.querySelectorAll('form[data-corporate-confirm]').forEach(form => {
      form.addEventListener('submit', ev => {
        if (form.dataset.confirmed === 'true') {
          form.dataset.confirmed = 'false'; return;
        }
        ev.preventDefault();
        if (pending || form.dataset.submitting === 'true') return;
        pending=form; submitter=ev.submitter || null;
        label.textContent=form.dataset.corporateConfirm || 'Confirm this change before saving.';
        if(typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open','');
        go.focus();
      });
    });
    go.addEventListener('click', () => {
      if (!pending) return;
      const form=pending, button=submitter;
      close();
      form.dataset.confirmed = 'true';
      if (typeof form.requestSubmit === 'function') form.requestSubmit(button || undefined);
      else form.submit();
    });
    dialog.querySelectorAll('[data-corporate-cancel]').forEach(btn=>btn.addEventListener('click',close));
    dialog.addEventListener('close',()=>{pending=null;submitter=null;});
    dialog.addEventListener('click',e=>{if(e.target===dialog) close();});
  };
  if(document.readyState === 'loading')document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
