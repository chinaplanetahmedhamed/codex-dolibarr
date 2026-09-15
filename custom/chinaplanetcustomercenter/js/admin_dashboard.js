(function () {
    'use strict';

    function initDashboardCustomization() {
    var shell = document.querySelector('.cpad-shell');
    var grid = document.getElementById('cpad-widget-grid');
    var dataNode = document.getElementById('cpad-layout-data');
    if (!shell || !grid || !dataNode) return;

    var config;
    try { config = JSON.parse(dataNode.textContent || '{}'); } catch (e) { return; }
    var layout = Array.isArray(config.layout) ? config.layout : [];
    var labels = config.labels || {};
    var ui = config.ui || {};
    var byKey = {};
    layout.forEach(function (row) { if (row && row.key) byKey[row.key] = row; });

    var widgets = Array.prototype.slice.call(document.querySelectorAll('[data-cpad-widget]'));
    widgets.forEach(function (widget, index) {
        var key = widget.getAttribute('data-cpad-widget');
        var saved = byKey[key] || {};
        var size = saved.size || widget.getAttribute('data-default-size') || 'medium';
        widget.setAttribute('data-size', size);
        widget.setAttribute('data-visible', saved.visible === 0 ? '0' : '1');
        widget.setAttribute('data-default-order', String(index));
        grid.appendChild(widget);
    });

    if (!layout.length) {
        // Manager-first default. Persisted per-user order and visibility always take precedence.
        ['attention_center', 'customer_finance', 'shipment_control', 'documents_center'].reverse().forEach(function (key) {
            var priority = grid.querySelector('[data-cpad-widget="' + key + '"]');
            if (priority) grid.insertBefore(priority, grid.firstChild);
        });
    }
    if (layout.length) {
        layout.slice().sort(function (a, b) { return (a.position || 0) - (b.position || 0); }).forEach(function (row) {
            var widget = grid.querySelector('[data-cpad-widget="' + row.key + '"]');
            if (widget) grid.appendChild(widget);
        });
    }

    document.querySelectorAll('.cpad-kpis, .cpad-grid').forEach(function (container) {
        container.hidden = true;
    });

    var editButton = document.getElementById('cpad-customize');
    var saveButton = document.getElementById('cpad-save-layout');
    var cancelButton = document.getElementById('cpad-cancel-layout');
    var resetButton = document.getElementById('cpad-reset-layout');
    var libraryButton = document.getElementById('cpad-widget-library-button');
    var library = document.getElementById('cpad-widget-library');
    var libraryList = document.getElementById('cpad-widget-library-list');
    var closeLibrary = document.getElementById('cpad-widget-library-close');
    var message = document.getElementById('cpad-layout-message');
    var dragged = null;

    function widgetControls(widget) {
        var controls = document.createElement('div');
        controls.className = 'cpad-widget-controls';
        controls.innerHTML = '<button type="button" class="cpad-drag-handle" title="Drag or use arrow keys / اسحب أو استخدم الأسهم" aria-label="Move widget">↕</button>' +
            '<button type="button" class="cpad-resize-widget" title="Resize / تغيير الحجم" aria-label="Resize widget">↔</button>' +
            '<button type="button" class="cpad-hide-widget" title="Hide / إخفاء" aria-label="Hide widget">×</button>';
        ['move','resize','hide'].forEach(function(key,index){
            var button=controls.children[index];button.setAttribute('aria-label',ui[key] || button.getAttribute('aria-label'));button.title=ui[key] || button.title;
        });
        widget.appendChild(controls);
        controls.querySelector('.cpad-resize-widget').addEventListener('click', function () {
            var sizes = ['small','medium','large','full'];
            var current = widget.getAttribute('data-size') || 'medium';
            widget.setAttribute('data-size', sizes[(sizes.indexOf(current) + 1) % sizes.length]);
            updateLibrary();
        });
        controls.querySelector('.cpad-hide-widget').addEventListener('click', function () {
            widget.setAttribute('data-visible', '0');
            updateLibrary();
        });
        var handle = controls.querySelector('.cpad-drag-handle');
        handle.addEventListener('keydown', function (event) {
            if (!shell.classList.contains('is-customizing')) return;
            var previous = widget.previousElementSibling;
            var next = widget.nextElementSibling;
            if ((event.key === 'ArrowLeft' || event.key === 'ArrowUp') && previous) {
                event.preventDefault();
                grid.insertBefore(widget, previous);
            } else if ((event.key === 'ArrowRight' || event.key === 'ArrowDown') && next) {
                event.preventDefault();
                grid.insertBefore(widget, next.nextSibling);
            }
        });
        handle.addEventListener('pointerdown', function (event) {
            if (!shell.classList.contains('is-customizing')) return;
            event.preventDefault();
            dragged = widget;
            widget.classList.add('is-dragging');
            handle.setPointerCapture(event.pointerId);

            function move(pointerEvent) {
                var target = document.elementFromPoint(pointerEvent.clientX, pointerEvent.clientY);
                target = target ? target.closest('[data-cpad-widget]') : null;
                if (!target || target === dragged || target.parentElement !== grid) return;
                var box = target.getBoundingClientRect();
                var before = pointerEvent.clientY < box.top + box.height / 2 ||
                    (Math.abs(pointerEvent.clientY - (box.top + box.height / 2)) < box.height / 4 && pointerEvent.clientX < box.left + box.width / 2);
                grid.insertBefore(dragged, before ? target : target.nextSibling);
            }

            function stop() {
                handle.removeEventListener('pointermove', move);
                widget.classList.remove('is-dragging');
                dragged = null;
            }

            handle.addEventListener('pointermove', move);
            handle.addEventListener('pointerup', stop, {once:true});
            handle.addEventListener('pointercancel', stop, {once:true});
        });
    }

    widgets.forEach(function (widget) {
        widgetControls(widget);
        widget.addEventListener('dragstart', function (event) {
            if (!shell.classList.contains('is-customizing')) { event.preventDefault(); return; }
            dragged = widget;
            widget.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', widget.getAttribute('data-cpad-widget'));
        });
        widget.addEventListener('dragend', function () {
            widget.classList.remove('is-dragging');
            dragged = null;
        });
        widget.addEventListener('dragover', function (event) {
            if (!dragged || dragged === widget) return;
            event.preventDefault();
            var box = widget.getBoundingClientRect();
            var before = event.clientY < box.top + box.height / 2 ||
                (Math.abs(event.clientY - (box.top + box.height / 2)) < box.height / 4 && event.clientX < box.left + box.width / 2);
            grid.insertBefore(dragged, before ? widget : widget.nextSibling);
        });
    });

    function updateLibrary() {
        libraryList.innerHTML = '';
        widgets.forEach(function (widget) {
            var key = widget.getAttribute('data-cpad-widget');
            var visible = widget.getAttribute('data-visible') !== '0';
            widget.hidden = !visible;
            var row = document.createElement('button');
            row.type = 'button';
            row.dataset.widgetKey = key;
            row.className = 'cpad-library-row' + (visible ? ' is-visible' : '');
            row.innerHTML = '<span>' + (visible ? '✓' : '+') + '</span><b>' + (labels[key] || key) + '</b><small>' + (ui[widget.getAttribute('data-size') || 'medium'] || widget.getAttribute('data-size') || 'medium') + '</small>';
            row.addEventListener('click', function () {
                widget.setAttribute('data-visible', visible ? '0' : '1');
                updateLibrary();
                libraryList.children[widgets.indexOf(widget)].focus();
            });
            row.setAttribute('aria-pressed', visible ? 'true' : 'false');
            libraryList.appendChild(row);
        });
    }

    function setCustomizing(enabled) {
        shell.classList.toggle('is-customizing', enabled);
        widgets.forEach(function (widget) { widget.draggable = enabled; });
        editButton.hidden = enabled;
        saveButton.hidden = !enabled;
        cancelButton.hidden = !enabled;
        resetButton.hidden = !enabled;
        libraryButton.hidden = !enabled;
        if (!enabled && library.open) library.close();
        updateLibrary();
    }

    function showMessage(text, error) {
        message.textContent = text;
        message.classList.toggle('is-error', !!error);
        message.hidden = false;
    }

    function post(action, payload) {
        var body = new URLSearchParams();
        body.set('token', config.token || '');
        body.set('action', action);
        if (payload) body.set('layout', JSON.stringify(payload));
        return fetch(config.endpoint, {
            method:'POST',
            credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
            body:body.toString()
        }).then(function (response) { return response.json(); });
    }

    editButton.addEventListener('click', function () { setCustomizing(true); });
    cancelButton.addEventListener('click', function () { window.location.reload(); });
    libraryButton.addEventListener('click', function () { library.showModal(); closeLibrary.focus(); });
    closeLibrary.addEventListener('click', function () { library.close(); });
    library.addEventListener('close', function () { libraryButton.focus(); });
    saveButton.addEventListener('click', function () {
        var payload = Array.prototype.slice.call(grid.querySelectorAll('[data-cpad-widget]')).map(function (widget) {
            return {
                key:widget.getAttribute('data-cpad-widget'),
                size:widget.getAttribute('data-size') || 'medium',
                visible:widget.getAttribute('data-visible') !== '0'
            };
        });
        post('save', payload).then(function (result) {
            if (!result.success) throw new Error(result.message || 'save_failed');
            showMessage(ui.saved || 'Layout saved', false);
            setCustomizing(false);
        }).catch(function (error) { showMessage(error.message || 'Unable to save layout', true); });
    });
    resetButton.addEventListener('click', function () {
        post('reset').then(function (result) {
            if (!result.success) throw new Error(result.message || 'reset_failed');
            window.location.reload();
        }).catch(function (error) { showMessage(error.message || 'Unable to reset layout', true); });
    });

    updateLibrary();
    setCustomizing(false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardCustomization);
    } else {
        initDashboardCustomization();
    }
}());
