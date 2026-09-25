/* Pointer based card dragging shared by the project and task Kanban boards. */
(function () {
  'use strict';
  var owner = null;

  window.cpKanbanPointer = function (card, options) {
    var active = null;
    var handle = card.querySelector(options.handle);
    if (!handle) return;

    card.addEventListener('pointerdown', function (event) {
      if (active || owner || !event.isPrimary || event.button !== 0 || options.disabled && options.disabled()) return;
      var onHandle = handle.contains(event.target);
      if (!onHandle && (event.pointerType !== 'mouse' || event.target.closest('a,button,input,select,textarea,label,[contenteditable]'))) return;
      var source = onHandle ? handle : card;
      active = { id: event.pointerId, x: event.clientX, y: event.clientY, started: false, source: source };
      owner = card;
      source.setPointerCapture(event.pointerId);
    });

    card.addEventListener('pointermove', function (event) {
      if (!active || event.pointerId !== active.id) return;
      if (!active.started) {
        if (Math.hypot(event.clientX - active.x, event.clientY - active.y) < 7) return;
        active.started = true;
        card.classList.add('kanban-pointer-dragging');
        document.documentElement.classList.add('kanban-pointer-active');
        if (window.getSelection) window.getSelection().removeAllRanges();
        if (options.start) options.start(event);
      }
      event.preventDefault();
      if (options.move) options.move(event, document.elementFromPoint(event.clientX, event.clientY));
    });

    function finish(event, drop) {
      if (!active || event.pointerId !== active.id) return;
      var state = active;
      active = null;
      owner = null;
      try {
        if (state.started && drop && options.drop) options.drop(event, document.elementFromPoint(event.clientX, event.clientY));
      } finally {
        if (state.started) {
          card.classList.remove('kanban-pointer-dragging');
          document.documentElement.classList.remove('kanban-pointer-active');
          if (options.end) options.end(event);
          card.dataset.kanbanSuppressClick = '1';
          setTimeout(function () { delete card.dataset.kanbanSuppressClick; }, 0);
        }
        if (state.source.hasPointerCapture(event.pointerId)) state.source.releasePointerCapture(event.pointerId);
      }
    }
    card.addEventListener('pointerup', function (event) { finish(event, true); });
    card.addEventListener('pointercancel', function (event) { finish(event, false); });
    card.addEventListener('lostpointercapture', function (event) { finish(event, false); });
    card.addEventListener('click', function (event) {
      if (card.dataset.kanbanSuppressClick === '1') {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);
  };
})();
