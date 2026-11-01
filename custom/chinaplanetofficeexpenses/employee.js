/* Finens drawer/quick-entry interactions. No requests, storage, tokens or accounting actions. */
'use strict';
(() => {
  let opener = null;
  const sync = () => {
    const form = document.querySelector('[data-draft-form]');
    if (!form) return;
    const selectedOffice = form.elements.office.selectedOptions[0];
    if (!selectedOffice) return;
    const kinds = selectedOffice.dataset.kinds?.split(',');
    if (kinds) {
      for (const option of form.elements.kind.options) option.disabled = !kinds.includes(option.value);
      if (!kinds.includes(form.elements.kind.value)) form.elements.kind.value = kinds[0];
    }
    const kind = form.elements.kind.value;
    const settlement = form.querySelector('.settlement-field');
    settlement.hidden = kind !== 'advance_return';
    form.elements.settles_ref.required = kind === 'advance_return';
    // Never erase retained error values. The server still rejects irrelevant references.
    form.elements.settles_ref.disabled = kind !== 'advance_return';
    const options = selectedOffice.dataset.currencies.split(',');
    for (const option of form.elements.currency.options) option.disabled = !options.includes(option.value);
    if (!options.includes(form.elements.currency.value)) form.elements.currency.value = options[0];
    const foreign = form.elements.currency.value !== form.elements.currency.dataset.base;
    if (foreign) form.querySelector('.fx-fields').open = true;
    for (const field of ['quote_rate', 'quote_date', 'quote_source']) form.elements[field].required = foreign;
  };
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-open]');
    if (trigger) {
      const dialog = document.getElementById(trigger.dataset.open);
      if (!dialog) return;
      opener = trigger;
      dialog.showModal();
      const first = dialog.querySelector('select, input:not([type=hidden]), a');
      if (first) first.focus();
    }
    if (event.target.closest('[data-close]')) event.target.closest('dialog').close();
    if (event.target instanceof HTMLDialogElement) {
      const box = event.target.getBoundingClientRect();
      if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) event.target.close();
    }
  });
  for (const dialog of document.querySelectorAll('dialog')) {
    dialog.addEventListener('close', () => { if (opener) opener.focus(); });
    dialog.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      const controls = [...dialog.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]')]
        .filter((element) => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
      const first = controls[0], last = controls[controls.length - 1];
      if ((event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
        event.preventDefault(); (event.shiftKey ? last : first).focus();
      }
    });
    if (dialog.hasAttribute('data-auto-open')) { dialog.removeAttribute('open'); dialog.showModal(); }
  }
  document.querySelector('[data-draft-form]')?.addEventListener('change', sync);
  for (const region of document.querySelectorAll('[data-table-scroll]')) {
    const tools = region.parentElement.querySelector('[data-scroll-tools]');
    if (!tools) continue;
    const before = tools.querySelector('[data-table-step="-1"]');
    const after = tools.querySelector('[data-table-step="1"]');
    const direction = () => getComputedStyle(region).direction === 'rtl' ? -1 : 1;
    const update = () => {
      const money = region.querySelector('.amount-cell');
      if (money) {
        region.style.setProperty('--table-viewport', region.getBoundingClientRect().width + 'px');
        region.style.setProperty('--money-width', money.getBoundingClientRect().width + 'px');
        region.dataset.pinnedMoney = 'true';
      }
      const maximum = Math.max(0, region.scrollWidth - region.clientWidth);
      const position = Math.max(0, Math.min(maximum, region.scrollLeft * direction()));
      tools.hidden = maximum <= 1;
      // scrollWidth/clientWidth round fractional pixels at browser zoom levels.
      before.disabled = position <= 2;
      after.disabled = position >= maximum - 2;
    };
    tools.addEventListener('click', (event) => {
      const button = event.target.closest('[data-table-step]');
      if (!button || !['-1', '1'].includes(button.dataset.tableStep)) return;
      region.scrollBy({left: Number(button.dataset.tableStep) * direction() * Math.max(100, region.clientWidth * 0.65), behavior: 'instant'});
      update();
    });
    region.addEventListener('scroll', update, {passive: true});
    window.addEventListener('resize', update);
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(update).observe(region);
    document.fonts?.ready.then(update);
    update();
  }
  sync();
})();
