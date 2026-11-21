'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-board-publication]');
    if (!form) return;

    const all = form.querySelector('[data-publication-all]');
    const rows = Array.from(form.querySelectorAll('[data-publication-task]'), row => ({
        task: Number(row.dataset.publicationTask),
        version: Number(row.dataset.version),
        internal: row.querySelector('th bdi'),
        checkbox: row.querySelector('[data-publication-visible]'),
        title: row.querySelector('[data-publication-title]'),
    }));
    for (const row of rows) row.initialTitle = row.title.value;

    const update = () => {
        const selected = rows.filter(row => row.checkbox.checked).length;
        all.checked = selected === rows.length;
        all.indeterminate = selected > 0 && selected < rows.length;
        for (const row of rows) row.title.required = row.checkbox.checked;
    };

    all.addEventListener('change', () => {
        for (const row of rows) row.checkbox.checked = all.checked;
        update();
    });

    for (const row of rows) row.checkbox.addEventListener('change', update);

    form.querySelector('[data-publication-copy]').addEventListener('click', () => {
        for (const row of rows) {
            if (row.checkbox.checked && !row.title.value.trim()) {
                row.title.value = row.internal.textContent.trim().slice(0, row.title.maxLength);
            }
        }
    });

    form.addEventListener('submit', () => {
        const changes = rows.map(row => ({
            task: row.task,
            version: row.version,
            published: row.checkbox.checked,
            title: row.checkbox.checked ? row.title.value.trim() : '',
        })).filter((change, index) => change.title !== rows[index].initialTitle || (change.published && !change.title));
        form.elements.selection.value = JSON.stringify(changes);
    });

    update();
    form.querySelector('[data-publication-submit]').disabled = false;
});
