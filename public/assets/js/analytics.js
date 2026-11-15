/**
 * Google Analytics, loaded without an inline script.
 *
 * The site's Content-Security-Policy allows neither inline handlers nor
 * inline scripts, so the usual gtag snippet would be blocked and silently do
 * nothing. The measurement ID travels on this tag's own data attribute
 * instead, and gtag.js is pulled in from here.
 *
 *   <script src="/assets/js/analytics.js" data-ga-id="G-XXXXXXX"></script>
 */
(function () {
    'use strict';

    var tag = document.currentScript;
    var id = tag && tag.dataset ? tag.dataset.gaId : null;

    if (!id) {
        return;
    }

    window.dataLayer = window.dataLayer || [];

    function gtag() {
        window.dataLayer.push(arguments);
    }

    window.gtag = gtag;

    gtag('js', new Date());
    gtag('config', id, {
        // Nothing here identifies a person to Google beyond what the page
        // already shows; order tokens stay out of the URL anyway.
        anonymize_ip: true,
    });

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(script);
})();
