/*!
 * RideLog — print helpers
 */
(function () {
    'use strict';

    // [data-print] buttons are handled by core.js, which the print layout
    // loads as well; a second listener here opened the dialog twice.

    // Pages that exist only to be printed can open the dialog themselves.
    if (document.body.hasAttribute('data-auto-print')) {
        window.addEventListener('load', function () {
            window.setTimeout(function () { window.print(); }, 350);
        });
    }

    // Label sheets: a guide outline while positioning, off for the real run.
    var guides = document.querySelector('[data-label-guides]');

    if (guides) {
        guides.addEventListener('change', function () {
            var sheet = document.querySelector('.label-sheet');

            if (sheet) {
                sheet.classList.toggle('show-guides', guides.checked);
            }
        });
    }
})();
