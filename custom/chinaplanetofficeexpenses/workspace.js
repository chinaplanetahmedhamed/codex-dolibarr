'use strict';
document.addEventListener('DOMContentLoaded', () => {
  // Reuse Dolibarr's loaded Select2: its popup stays inside the scaled web page.
  // Keep the native select and its name/options as the server-side form contract.
  const app = document.querySelector('.office-expenses-app');
  const jq = window.jQuery;
  if (app && typeof jq === 'function' && typeof jq.fn.select2 === 'function') {
    const ar = app.getAttribute('dir') === 'rtl';
    app.querySelectorAll('select').forEach(select => {
      if (select.multiple || jq(select).data('select2')) return;
      jq(select).select2({
        width: '100%',
        dir: ar ? 'rtl' : 'ltr',
        dropdownParent: jq(select.closest('dialog') || app),
        minimumResultsForSearch: select.id === 'native-staff' || select.options.length > 7 ? 0 : Infinity,
        language: {
          noResults: () => ar ? 'لا توجد نتائج مطابقة' : 'No matching results',
          searching: () => ar ? 'جارٍ البحث…' : 'Searching…'
        }
      });
      const label = select.labels && select.labels[0];
      if (label && select.id) {
        if (!label.id) label.id = select.id + '-label';
        const selection = jq(select).next('.select2').find('.select2-selection');
        selection.attr('aria-labelledby', label.id + ' ' + selection.attr('aria-labelledby'));
      }
      // Select2 emits jQuery change; existing module code uses native listeners.
      jq(select).on('select2:select select2:unselect', () => {
        select.dispatchEvent(new Event('change', {bubbles: true}));
        // Branch changes can update dependent native values (currency/type).
        // Refresh only Select2 listeners after those synchronous form rules run.
        if (select.form) select.form.querySelectorAll('select').forEach(field => {
          if (jq(field).data('select2')) jq(field).trigger('change.select2');
        });
      });
      select.addEventListener('invalid', () => {
        if (!select.disabled) jq(select).select2('open');
      });
    });
  }
  const root = document.querySelector('.workspace-app');
  if (!root) return;
  root.querySelectorAll('[data-workspace-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });
  [['receipt', '#receipt-editor'], ['workspace', '#workspace-editor']].forEach(([kind, selector]) => {
    const editor = root.querySelector(selector);
    if (!editor) return;
    const open = () => { if (!editor.open) editor.showModal(); };
    root.querySelectorAll(`[data-${kind}-open]`).forEach(button => {
      button.addEventListener('click', event => { event.preventDefault(); open(); });
    });
    editor.querySelectorAll(`[data-${kind}-close]`).forEach(button => {
      button.addEventListener('click', () => editor.close());
    });
    if (editor.hasAttribute(`data-${kind}-auto-open`) || location.hash === '#workspace-form') open();
  });
  const month = root.querySelector('[data-report-month]');
  if (month) month.addEventListener('change', () => {
    if (!/^\d{4}-\d{2}$/.test(month.value)) return;
    const [year, number] = month.value.split('-').map(Number);
    if (number < 1 || number > 12) return;
    const last = new Date(Date.UTC(year, number, 0)).getUTCDate();
    root.querySelector('#report-from').value = `${month.value}-01`;
    root.querySelector('#report-to').value = `${month.value}-${last}`;
  });
  root.querySelectorAll('[data-report-chart]').forEach(chart => {
    const tooltip = chart.querySelector('.chart-tooltip');
    chart.querySelectorAll('[data-chart-label]').forEach(mark => {
      const show = () => { tooltip.textContent = mark.dataset.chartLabel; tooltip.hidden = false; };
      const hide = () => { tooltip.hidden = true; };
      mark.addEventListener('pointerenter', show);
      mark.addEventListener('pointerleave', hide);
      mark.addEventListener('focus', show);
      mark.addEventListener('blur', hide);
    });
  });
});
