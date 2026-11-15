/**
 * The invoice document's only script: wire the print button.
 *
 * It lives in a file rather than an onclick attribute because the site's
 * Content-Security-Policy allows neither inline handlers nor inline scripts —
 * an onclick here would simply do nothing when a staff member clicked it.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.querySelector('[data-print]');

        if (button) {
            button.addEventListener('click', function () {
                window.print();
            });
        }
    });
})();
