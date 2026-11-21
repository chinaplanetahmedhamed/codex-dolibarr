(() => {
    'use strict';

    const form = document.querySelector('[data-cpl-loading-media]');
    if (!form) return;

    const input = form.elements.loading_media;
    const token = form.elements.token;
    const publish = form.elements.publish_media;
    const button = form.querySelector('button');
    const progress = form.querySelector('progress');
    const status = form.querySelector('[role="status"]');
    const refresh = form.querySelector('a');
    const results = form.parentElement.querySelector('[data-cpl-loading-media-results]');
    let pending = [];

    input.addEventListener('change', () => {
        pending = Array.from(input.files);
        results.replaceChildren();
        status.textContent = '';
        button.disabled = !pending.length;
    });

    function upload(file, share) {
        return new Promise(resolve => {
            const request = new XMLHttpRequest();
            const body = new FormData();
            body.append('token', token.value);
            body.append('loading_media', file, file.name);
            if (share) body.append('publish_media', '1');

            request.open('POST', form.action);
            request.responseType = 'json';
            request.upload.addEventListener('progress', event => {
                if (event.lengthComputable) progress.value = Math.floor(100 * event.loaded / event.total);
            });
            request.addEventListener('load', () => {
                const result = request.response;
                if (result && typeof result.token === 'string' && typeof result.message === 'string') {
                    const previousToken = token.value;
                    document.querySelectorAll('input[type="hidden"][name="token"]').forEach(field => {
                        if (field.value === previousToken) field.value = result.token;
                    });
                    resolve(result);
                } else if (request.status === 413) {
                    resolve({message: form.dataset.serverLimit});
                } else {
                    resolve({message: form.dataset.networkError, uncertain: true});
                }
            });
            request.addEventListener('error', () => resolve({message: form.dataset.networkError, uncertain: true}));
            request.send(body);
        });
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!pending.length || input.disabled) return;

        const batch = pending;
        const share = publish && publish.checked;
        pending = [];
        let interruption = '';
        input.disabled = true;
        if (publish) publish.disabled = true;
        button.disabled = true;
        refresh.hidden = true;

        for (const [index, file] of batch.entries()) {
            progress.hidden = false;
            progress.value = 0;
            status.textContent = `${index + 1}/${batch.length} · ${file.name}`;
            const result = file.size > Number(form.dataset.limit)
                ? {message: form.dataset.sizeError}
                : await upload(file, share);
            const row = document.createElement('li');
            row.textContent = `${file.name} · ${result.message}`;
            if (!result.ok || result.warning) row.className = 'warning';
            results.append(row);

            if (result.uncertain) {
                interruption = result.message;
                break;
            }
        }

        input.disabled = false;
        input.value = '';
        if (publish) publish.disabled = false;
        progress.hidden = true;
        refresh.hidden = false;
        button.disabled = true;
        status.textContent = interruption || form.dataset.complete;
    });

    form.hidden = false;
})();
