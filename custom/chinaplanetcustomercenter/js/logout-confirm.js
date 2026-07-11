(function () {
    'use strict';

    function initializeLogoutConfirmation() {
        var links = document.querySelectorAll('[data-cpcc-logout]');
        var dialog = document.querySelector('[data-cpcc-logout-dialog]');
        var confirmButton = dialog ? dialog.querySelector('[data-cpcc-logout-confirm]') : null;
        var cancelButton = dialog ? dialog.querySelector('[data-cpcc-logout-cancel]') : null;
        var logoutUrl = '';

        links.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                logoutUrl = link.href;

                if (dialog && typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    if (cancelButton) cancelButton.focus();
                    return;
                }

                if (window.confirm('هل تريد حقًا تسجيل الخروج؟\nAre you sure you want to log out?')) {
                    window.location.assign(logoutUrl);
                }
            });
        });

        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                if (logoutUrl) window.location.assign(logoutUrl);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLogoutConfirmation);
    } else {
        initializeLogoutConfirmation();
    }
}());
