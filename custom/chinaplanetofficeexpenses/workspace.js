'use strict';
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.workspace-app [data-workspace-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });
  const editor = document.querySelector('.receipts-app #receipt-editor');
  if (!editor) return;
  const open = () => { if (!editor.open) editor.showModal(); };
  document.querySelectorAll('.receipts-app [data-receipt-open]').forEach(button => {
    button.addEventListener('click', event => { event.preventDefault(); open(); });
  });
  editor.querySelectorAll('[data-receipt-close]').forEach(button => {
    button.addEventListener('click', () => editor.close());
  });
  if (editor.hasAttribute('data-receipt-auto-open') || location.hash === '#workspace-form') open();
});
