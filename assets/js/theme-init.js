/*!
 * RideLog — theme bootstrap
 *
 * Loaded synchronously in <head>, before any styles paint. Its only job is to
 * stamp data-theme on <html> so a dark-theme user never sees a white flash.
 * Everything else lives in core.js.
 */
(function () {
    'use strict';

    var stored = null;

    try {
        stored = window.localStorage.getItem('ridelog-theme');
    } catch (e) {
        // Private browsing, or site data blocked. Fall through to system.
    }

    if (stored === 'light' || stored === 'dark') {
        document.documentElement.setAttribute('data-theme', stored);
    }
    // "system" or nothing: leave the attribute off and let the media query decide.
})();
