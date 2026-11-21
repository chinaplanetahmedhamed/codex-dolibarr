(() => {
    'use strict';

    const customer = document.getElementById('fk_soc');
    const project = document.getElementById('fk_project');
    const projectStatus = document.getElementById('cpl-project-status');

    if (customer && project && projectStatus) {
        let lookup = 0;

        function setProjects(options, message) {
            project.replaceChildren(new Option('', '0'));
            for (const item of options) {
                const option = new Option(item.label, item.id);
                option.disabled = item.disabled;
                project.add(option);
            }
            project.disabled = !options.some(item => !item.disabled);
            projectStatus.textContent = message;
            if (window.jQuery) {
                window.jQuery(project).trigger('change.select2');
            }
        }

        async function loadProjects() {
            const request = ++lookup;
            const selected = customer.value;
            if (Number(selected) <= 0 || !selected) {
                setProjects([], projectStatus.dataset.choose);
                return;
            }

            setProjects([], projectStatus.dataset.loading);
            try {
                const url = new URL(projectStatus.dataset.url, window.location.href);
                url.searchParams.set('customer', selected);
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('Project lookup failed');
                }
                const options = await response.json();
                if (!Array.isArray(options)) {
                    throw new Error('Unexpected project response');
                }
                if (request === lookup && selected === customer.value) {
                    setProjects(options, options.length ? '' : projectStatus.dataset.empty);
                }
            } catch (error) {
                if (request === lookup && selected === customer.value) {
                    setProjects([], projectStatus.dataset.failed);
                }
            }
        }

        if (window.jQuery) {
            window.jQuery(customer).on('change', loadProjects);
        } else {
            customer.addEventListener('change', loadProjects);
        }
        project.form.addEventListener('submit', event => {
            if (projectStatus.textContent === projectStatus.dataset.loading) {
                event.preventDefault();
            }
        });
    }

    const rows = document.getElementById('cpl-container-rows');
    const template = document.getElementById('cpl-container-template');
    const add = document.getElementById('cpl-container-add');
    const total = document.getElementById('cpl-container-total');
    if (!rows || !template || !add || !total) return;

    function updateContainers() {
        const multiple = rows.children.length > 1;
        let count = 0;
        for (const row of rows.children) {
            row.querySelector('[data-remove-container]').hidden = !multiple;
            row.querySelector('select').required = multiple;
            const quantity = row.querySelector('input').valueAsNumber;
            count += Number.isInteger(quantity) && quantity > 0 ? quantity : 0;
        }
        total.value = count;
        add.disabled = rows.children.length >= Number(rows.dataset.limit);
    }

    add.hidden = false;
    add.addEventListener('click', () => {
        rows.append(template.content.cloneNode(true));
        updateContainers();
        rows.lastElementChild.querySelector('select').focus();
    });
    rows.addEventListener('input', updateContainers);
    rows.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-container]');
        if (!remove || rows.children.length < 2) return;
        const row = remove.closest('.cpl-container-row');
        const next = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        updateContainers();
        next.querySelector('select').focus();
    });
    updateContainers();
})();
