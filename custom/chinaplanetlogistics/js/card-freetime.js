(function () {
    'use strict';

    function element(id) {
        return document.getElementById(id);
    }

    function readDate(prefix) {
        var year = parseInt(element(prefix + 'year').value, 10);
        var month = parseInt(element(prefix + 'month').value, 10);
        var day = parseInt(element(prefix + 'day').value, 10);

        if (!year || !month || !day) return null;
        return new Date(year, month - 1, day, 12, 0, 0);
    }

    function writeDate(prefix, date) {
        var input = element(prefix);
        var day = element(prefix + 'day');
        var month = element(prefix + 'month');
        var year = element(prefix + 'year');
        var formatted = '';

        if (!input || !day || !month || !year) return;

        if (date) {
            day.value = date.getDate();
            month.value = date.getMonth() + 1;
            year.value = date.getFullYear();

            if (window.jQuery && window.jQuery.datepicker) {
                var sourceInput = element('ata_');
                var format = 'mm/dd/yy';
                if (sourceInput && window.jQuery(sourceInput).hasClass('hasDatepicker')) {
                    format = window.jQuery(sourceInput).datepicker('option', 'dateFormat') || format;
                }
                formatted = window.jQuery.datepicker.formatDate(format, date);
            } else {
                formatted = String(date.getFullYear()) + '-'
                    + String(date.getMonth() + 1).padStart(2, '0') + '-'
                    + String(date.getDate()).padStart(2, '0');
            }
        } else {
            day.value = '';
            month.value = '';
            year.value = '';
        }

        input.value = formatted;
    }

    function synchronizeFreeTime() {
        var ata = readDate('ata_');
        var daysInput = element('freetime_days');
        var days = daysInput ? parseInt(daysInput.value, 10) : 0;

        if (!ata || !days || days < 1) {
            writeDate('fts_', null);
            writeDate('fte_', null);
            return;
        }

        var end = new Date(ata.getTime());
        end.setDate(end.getDate() + days);
        writeDate('fts_', ata);
        writeDate('fte_', end);
    }

    function scheduleSynchronization() {
        window.setTimeout(synchronizeFreeTime, 0);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var ataInput = element('ata_');
        var daysInput = element('freetime_days');

        if (!ataInput || !daysInput) return;
        ataInput.addEventListener('change', scheduleSynchronization);
        ataInput.addEventListener('input', scheduleSynchronization);
        daysInput.addEventListener('change', scheduleSynchronization);
        daysInput.addEventListener('input', scheduleSynchronization);
        synchronizeFreeTime();
    });
}());
