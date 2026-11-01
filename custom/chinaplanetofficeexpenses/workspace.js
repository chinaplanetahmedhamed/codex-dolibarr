'use strict';
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.workspace-app [data-workspace-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });
});
