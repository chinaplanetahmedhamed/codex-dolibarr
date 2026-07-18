(function () {
    'use strict';

    function setupNavigation() {
        var toggle = document.querySelector('.cpcc-portal-menu-toggle');
        var menu = document.getElementById('cpcc-portal-menu');
        if (!toggle || !menu) return;

        function setOpen(open) {
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        menu.addEventListener('click', function (event) {
            if (event.target.closest('a') && window.matchMedia('(max-width: 900px)').matches) setOpen(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    function setupResponsiveTables() {
        var tables = document.querySelectorAll('.cpcc-table, .cpcc-export-table, table.liste, table.noborder.centpercent');
        tables.forEach(function (table) {
            var headerRow = table.querySelector('thead tr') || table.querySelector('tr.liste_titre');
            var headerCells = headerRow ? headerRow.querySelectorAll('th') : [];
            var headers = Array.prototype.map.call(headerCells, function (header) {
                return header.textContent.trim();
            });
            if (!headers.length || !table.tBodies.length) return;

            table.classList.add('cpcc-mobile-cards');
            if (headerRow && !headerRow.closest('thead')) headerRow.classList.add('cpcc-mobile-header-row');
            Array.prototype.forEach.call(table.tBodies, function (tbody) {
                Array.prototype.forEach.call(tbody.rows, function (row) {
                    if (row === headerRow) return;
                    if (row.cells.length === 1 && Number(row.cells[0].getAttribute('colspan') || 1) > 1) {
                        row.cells[0].setAttribute('data-cpcc-empty-row', '1');
                        return;
                    }
                    Array.prototype.forEach.call(row.cells, function (cell, index) {
                        cell.setAttribute('data-label', headers[index] || '');
                    });
                });
            });
        });
    }

    function init() {
        document.documentElement.classList.add('cpcc-customer-portal');
        setupNavigation();
        setupResponsiveTables();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
