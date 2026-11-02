'use strict';
document.addEventListener('DOMContentLoaded', () => {
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
