/* Keep open Kanban boards in sync across browser tabs and devices. */
(function () {
  'use strict';
  function init() {
    var board = document.querySelector('[data-kanban-revision][data-kanban-revision-url]');
    if (!board) return;
    var revision = board.dataset.kanbanRevision;
    var endpoint = new URL(board.dataset.kanbanRevisionUrl, window.location.href);
    endpoint.search = window.location.search;
    var storageKey = 'kanban-scroll:' + window.location.pathname + window.location.search;
    try {
      var saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
      if (saved) {
        window.scrollTo(saved.pageX || 0, saved.pageY || 0);
        var horizontal = board.querySelector('.cpk-board,.cpo-task-kanban');
        if (horizontal) horizontal.scrollLeft = saved.boardX || 0;
        sessionStorage.removeItem(storageKey);
      }
    } catch (ignoreStorage) {}
    var checking = false, changed = false;
    function editing() {
      var active = document.activeElement;
      return document.querySelector('dialog[open]') ||
        document.documentElement.classList.contains('kanban-pointer-active') ||
        active && active.matches && active.matches('input,textarea,select,[contenteditable="true"]');
    }
    function refreshIfReady() {
      if (!changed || document.visibilityState !== 'visible' || editing()) return;
      var horizontal = board.querySelector('.cpk-board,.cpo-task-kanban');
      try { sessionStorage.setItem(storageKey, JSON.stringify({pageX:window.scrollX,pageY:window.scrollY,boardX:horizontal ? horizontal.scrollLeft : 0})); } catch (ignoreStorage) {}
      window.location.reload();
    }
    function check() {
      if (checking || document.visibilityState !== 'visible') return;
      if (changed) { refreshIfReady(); return; }
      checking = true;
      fetch(endpoint.href, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}})
        .then(function (response) { if (!response.ok) throw new Error('revision unavailable'); return response.json(); })
        .then(function (result) { if (result && typeof result.revision === 'string' && result.revision !== revision) { changed = true; refreshIfReady(); } })
        .catch(function () {})
        .finally(function () { checking = false; });
    }
    setInterval(check, 8000);
    document.addEventListener('visibilitychange', check);
    document.addEventListener('focusin', refreshIfReady);
    document.addEventListener('click', function () { setTimeout(refreshIfReady, 0); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
