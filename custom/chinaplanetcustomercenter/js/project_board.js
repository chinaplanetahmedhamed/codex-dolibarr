'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const board = document.querySelector('[data-project-board-url]');
    if (!board) return;

    const offline = document.querySelector('[data-project-board-offline]');
    let previous;
    let pending = false;

    const refresh = async () => {
        if (document.hidden || pending) return;
        pending = true;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(board.dataset.projectBoardUrl, {
                cache: 'no-store',
                redirect: 'manual',
                signal: controller.signal,
            });
            if (response.status === 401 || response.status === 403 || response.type === 'opaqueredirect') {
                board.replaceChildren();
                previous = undefined;
            }
            if (!response.ok) throw new Error('Board unavailable');
            const result = await response.json();
            if (typeof result.board !== 'string') throw new Error('Board unavailable');
            if (result.board !== previous) board.innerHTML = result.board;
            previous = result.board;
            offline.hidden = true;
        } catch {
            offline.hidden = false;
        } finally {
            window.clearTimeout(timeout);
            pending = false;
        }
    };

    window.setInterval(refresh, 20000);
    document.addEventListener('visibilitychange', refresh);
});
