(function () {
  'use strict';
  // Native per-user cells route every ID to ActionComm. Projected task IDs are negative.
  document.addEventListener('click', function (event) {
    var cell = event.target.closest('td[ref]');
    if (!cell) return;
    var parts = cell.getAttribute('ref').split('_');
    var ids = (parts[7] || '').split(',').map(function (id) { return id.trim(); });
    var tasks = ids.filter(function (id) { return /^-[1-9][0-9]*$/.test(id); });
    if (!tasks.length) return;
    event.preventDefault(); event.stopImmediatePropagation();
    var config = document.querySelector('[data-cpo-agenda-root]');
    if (!config) return;
    var root = config.dataset.cpoAgendaRoot;
    if (ids.length === 1) window.location.href = root + '/custom/chinaplanetoperations/tasks/card.php?id=' + String(-Number(tasks[0]));
    else window.location.href = root + '/comm/action/index.php?mode=show_day&year=' + Number(parts[2]) + '&month=' + Number(parts[3]) + '&day=' + Number(parts[4]) + '&search_filtert=' + Number(parts[1]);
  }, true);
}());
