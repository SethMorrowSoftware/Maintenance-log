/*!
 * RideLog — core client script
 *
 * Vanilla ES2020. No framework, no modules, no build step.
 *
 * Everything here is enhancement. Every form posts normally and every link is
 * a real link, so the application works with JavaScript switched off — which
 * matters when a technician is on a bad connection in a metal shop.
 *
 * Contents
 *   1.  Namespace, config and small utilities
 *   2.  Storage
 *   3.  Formatting
 *   4.  API client
 *   5.  Toasts
 *   6.  Modal and confirm
 *   7.  Theme
 *   8.  Sidebar and navigation
 *   9.  Dropdowns
 *   10. Tables: sort, filter, bulk select
 *   11. Forms: dirty guard, submit lock, validation hints, masks, counters
 *   12. Tabs
 *   13. Autocomplete
 *   14. Global search
 *   15. Notifications
 *   16. File uploads
 *   17. Repeatable rows
 *   18. Meter update
 *   19. Misc behaviours
 *   20. Init
 */

(function (window, document) {
    'use strict';

    /* =====================================================================
       1. Namespace, config and small utilities
       ===================================================================== */

    var RL = window.RL = window.RL || {};

    RL.config = (function () {
        var node = document.getElementById('rl-config');

        if (!node) {
            return { baseUrl: '/', apiUrl: 'api/index.php', csrfToken: '', userId: null };
        }

        try {
            return JSON.parse(node.textContent) || {};
        } catch (e) {
            return { baseUrl: '/', apiUrl: 'api/index.php', csrfToken: '', userId: null };
        }
    })();

    RL.qs = function (selector, root) {
        return (root || document).querySelector(selector);
    };

    RL.qsa = function (selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    };

    /** Delegated listener, so it keeps working for content added later. */
    RL.on = function (selector, eventName, handler, root) {
        (root || document).addEventListener(eventName, function (event) {
            var target = event.target.closest(selector);

            if (target && (root || document).contains(target)) {
                handler.call(target, event, target);
            }
        });
    };

    /** Build an element. Children may be nodes or strings (escaped as text). */
    RL.el = function (tag, attrs, children) {
        var node = document.createElement(tag);

        Object.keys(attrs || {}).forEach(function (key) {
            var value = attrs[key];

            if (value === null || value === undefined || value === false) {
                return;
            }

            if (key === 'class') {
                node.className = value;
            } else if (key === 'text') {
                node.textContent = value;
            } else if (key === 'html') {
                node.innerHTML = value;
            } else if (key.indexOf('on') === 0 && typeof value === 'function') {
                node.addEventListener(key.slice(2).toLowerCase(), value);
            } else if (value === true) {
                node.setAttribute(key, '');
            } else {
                node.setAttribute(key, value);
            }
        });

        (Array.isArray(children) ? children : (children ? [children] : [])).forEach(function (child) {
            if (child === null || child === undefined) {
                return;
            }

            node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
        });

        return node;
    };

    RL.escapeHtml = function (value) {
        var div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    };

    RL.debounce = function (fn, wait) {
        var timer = null;

        return function () {
            var context = this;
            var args = arguments;

            window.clearTimeout(timer);
            timer = window.setTimeout(function () { fn.apply(context, args); }, wait || 200);
        };
    };

    RL.throttle = function (fn, wait) {
        var last = 0;
        var timer = null;

        return function () {
            var context = this;
            var args = arguments;
            var now = Date.now();
            var remaining = (wait || 200) - (now - last);

            if (remaining <= 0) {
                window.clearTimeout(timer);
                timer = null;
                last = now;
                fn.apply(context, args);
            } else if (!timer) {
                timer = window.setTimeout(function () {
                    last = Date.now();
                    timer = null;
                    fn.apply(context, args);
                }, remaining);
            }
        };
    };

    /** Turn a form into a plain object, collecting repeated names into arrays. */
    RL.serialize = function (form) {
        var data = {};

        new FormData(form).forEach(function (value, key) {
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        });

        return data;
    };

    /** Resolve an application-relative path against the install base. */
    RL.url = function (path) {
        var base = RL.config.baseUrl || '/';

        if (/^https?:\/\//i.test(path) || path.charAt(0) === '/') {
            return path;
        }

        return base.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
    };


    /* =====================================================================
       2. Storage — namespaced, and never throws
       ===================================================================== */

    RL.storage = {
        prefix: 'ridelog-',

        get: function (key, fallback) {
            try {
                var raw = window.localStorage.getItem(this.prefix + key);

                return raw === null ? fallback : JSON.parse(raw);
            } catch (e) {
                return fallback;
            }
        },

        set: function (key, value) {
            try {
                window.localStorage.setItem(this.prefix + key, JSON.stringify(value));

                return true;
            } catch (e) {
                return false;
            }
        },

        remove: function (key) {
            try {
                window.localStorage.removeItem(this.prefix + key);
            } catch (e) {
                // Nothing to do.
            }
        }
    };


    /* =====================================================================
       3. Formatting — mirrors the PHP helpers so client and server agree
       ===================================================================== */

    RL.fmt = {
        money: function (value) {
            var amount = parseFloat(value);

            if (isNaN(amount)) {
                amount = 0;
            }

            var symbol = RL.config.currency || '$';
            var sign = amount < 0 ? '-' : '';

            return sign + symbol + Math.abs(amount).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        number: function (value, decimals) {
            var num = parseFloat(value);

            if (isNaN(num)) {
                return '0';
            }

            return num.toLocaleString(undefined, {
                minimumFractionDigits: decimals || 0,
                maximumFractionDigits: decimals === undefined ? 2 : decimals
            });
        },

        date: function (iso) {
            var date = new Date(iso);

            return isNaN(date.getTime()) ? '' : date.toLocaleDateString();
        },

        datetime: function (iso) {
            var date = new Date(iso);

            return isNaN(date.getTime()) ? '' : date.toLocaleString();
        },

        bytes: function (bytes) {
            var units = ['B', 'KB', 'MB', 'GB'];
            var index = 0;
            var value = parseFloat(bytes) || 0;

            while (value >= 1024 && index < units.length - 1) {
                value /= 1024;
                index++;
            }

            return (index === 0 ? value : value.toFixed(1)) + ' ' + units[index];
        }
    };


    /* =====================================================================
       4. API client
       ===================================================================== */

    /**
     * Call the JSON API.
     *
     *   RL.api('assets.list', { params: { q: 'kart' } })
     *   RL.api('assets.update_meter', { method: 'POST', body: { id: 3, reading: 1250 } })
     *
     * Resolves with the envelope's data. Rejects with an Error carrying
     * .code and .errors so a caller can paint field-level messages.
     */
    RL.api = function (route, options) {
        options = options || {};

        var method = (options.method || 'GET').toUpperCase();
        var url = RL.config.apiUrl + (RL.config.apiUrl.indexOf('?') === -1 ? '?' : '&') + 'r=' + encodeURIComponent(route);

        if (options.params) {
            Object.keys(options.params).forEach(function (key) {
                var value = options.params[key];

                if (value !== null && value !== undefined && value !== '') {
                    url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
                }
            });
        }

        var init = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (method !== 'GET' && method !== 'HEAD') {
            init.headers['X-CSRF-Token'] = RL.config.csrfToken || '';

            if (options.body instanceof FormData) {
                init.body = options.body;   // let the browser set the boundary
            } else if (options.body !== undefined) {
                init.headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(options.body);
            }
        }

        return window.fetch(url, init).then(function (response) {
            if (response.status === 401) {
                window.location.href = RL.url('login.php');

                return Promise.reject(makeError('Your session has ended. Please sign in again.', 'unauthenticated'));
            }

            return response.text().then(function (text) {
                var payload = null;

                try {
                    payload = text ? JSON.parse(text) : null;
                } catch (e) {
                    return Promise.reject(makeError(
                        'The server sent a response we could not read. It has been logged.',
                        'bad_response'
                    ));
                }

                if (!payload) {
                    return response.ok ? null : Promise.reject(makeError('Request failed.', 'error'));
                }

                if (payload.ok) {
                    return payload.meta ? { data: payload.data, meta: payload.meta } : payload.data;
                }

                var error = makeError(payload.error || 'Request failed.', payload.code || 'error');
                error.errors = payload.errors || {};
                error.status = response.status;

                return Promise.reject(error);
            });
        }, function () {
            return Promise.reject(makeError(
                'Could not reach the server. Check your connection and try again.',
                'network'
            ));
        });
    };

    function makeError(message, code) {
        var error = new Error(message);
        error.code = code;

        return error;
    }


    /* =====================================================================
       5. Toasts
       ===================================================================== */

    var TOAST_ICONS = {
        success: '<path d="m4.5 12.5 5 5 10-10"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5"/><path d="M12 16h.01"/>',
        warning: '<path d="M10.3 4.3 2.8 17.2A2 2 0 0 0 4.5 20.2h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4"/><path d="M12 17h.01"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 8h.01"/>'
    };

    var MAX_TOASTS = 4;

    RL.toast = function (message, type, duration) {
        var root = document.getElementById('toast-root');

        if (!root || !message) {
            return;
        }

        type = type || 'info';

        while (root.children.length >= MAX_TOASTS) {
            root.removeChild(root.firstChild);
        }

        var icon = RL.el('span', { class: 'toast-icon' });
        icon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + (TOAST_ICONS[type] || TOAST_ICONS.info) + '</svg>';

        var close = RL.el('button', {
            class: 'toast-close',
            type: 'button',
            'aria-label': 'Dismiss'
        });
        close.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            + 'stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';

        var toast = RL.el('div', {
            class: 'toast toast-' + type,
            role: type === 'error' ? 'alert' : 'status'
        }, [
            icon,
            RL.el('div', { class: 'toast-body', text: message }),
            close
        ]);

        var timer = null;

        function dismiss() {
            window.clearTimeout(timer);
            toast.classList.add('is-leaving');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 160);
        }

        function start() {
            // Errors stay until dismissed; people need time to read them.
            if (type === 'error') {
                return;
            }

            timer = window.setTimeout(dismiss, duration || 4500);
        }

        close.addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
        toast.addEventListener('mouseleave', start);

        root.appendChild(toast);
        start();

        return toast;
    };


    /* =====================================================================
       6. Modal and confirm
       ===================================================================== */

    var focusStack = [];

    function trapFocus(container, event) {
        if (event.key !== 'Tab') {
            return;
        }

        var focusable = RL.qsa(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
            container
        ).filter(function (node) {
            return node.offsetParent !== null;
        });

        if (focusable.length === 0) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    RL.modal = {
        current: null,

        open: function (content, options) {
            options = options || {};

            this.close();

            focusStack.push(document.activeElement);

            var dialog = RL.el('div', {
                class: 'modal-dialog' + (options.size ? ' is-' + options.size : ''),
                role: 'dialog',
                'aria-modal': 'true',
                'aria-label': options.title || 'Dialog'
            });

            if (typeof content === 'string') {
                dialog.innerHTML = content;
            } else {
                dialog.appendChild(content);
            }

            var backdrop = RL.el('div', { class: 'modal-backdrop' }, dialog);

            backdrop.addEventListener('mousedown', function (event) {
                if (event.target === backdrop && options.dismissible !== false) {
                    RL.modal.close();
                }
            });

            backdrop.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && options.dismissible !== false) {
                    RL.modal.close();
                } else {
                    trapFocus(dialog, event);
                }
            });

            (document.getElementById('modal-root') || document.body).appendChild(backdrop);
            document.body.style.overflow = 'hidden';

            this.current = backdrop;

            // Focus the first sensible control, or the dialog itself.
            var autofocus = dialog.querySelector('[autofocus]')
                || dialog.querySelector('input:not([type="hidden"]), select, textarea, button');

            if (autofocus) {
                autofocus.focus();
            } else {
                dialog.setAttribute('tabindex', '-1');
                dialog.focus();
            }

            RL.init(dialog);

            return dialog;
        },

        close: function () {
            if (!this.current) {
                return;
            }

            if (this.current.parentNode) {
                this.current.parentNode.removeChild(this.current);
            }

            this.current = null;
            document.body.style.overflow = '';

            var previous = focusStack.pop();

            if (previous && typeof previous.focus === 'function') {
                previous.focus();
            }
        }
    };

    RL.on('[data-modal-close]', 'click', function (event) {
        event.preventDefault();
        RL.modal.close();
    });

    /**
     * A promise-based replacement for window.confirm.
     */
    RL.confirm = function (options) {
        options = typeof options === 'string' ? { message: options } : (options || {});

        return new Promise(function (resolve) {
            var confirmBtn = RL.el('button', {
                type: 'button',
                class: 'btn ' + (options.danger ? 'btn-danger' : 'btn-primary'),
                text: options.confirmText || 'Confirm',
                autofocus: true
            });

            var cancelBtn = RL.el('button', {
                type: 'button',
                class: 'btn btn-secondary',
                text: options.cancelText || 'Cancel'
            });

            var dialog = RL.el('div', {}, [
                RL.el('div', { class: 'modal-header' }, [
                    RL.el('h2', { class: 'modal-title', text: options.title || 'Are you sure?' })
                ]),
                RL.el('div', { class: 'modal-body' }, [
                    RL.el('p', { text: options.message || 'This cannot be undone.' })
                ]),
                RL.el('div', { class: 'modal-footer' }, [cancelBtn, confirmBtn])
            ]);

            var settled = false;

            function finish(result) {
                if (settled) {
                    return;
                }

                settled = true;
                RL.modal.close();
                resolve(result);
            }

            confirmBtn.addEventListener('click', function () { finish(true); });
            cancelBtn.addEventListener('click', function () { finish(false); });

            var backdrop = RL.modal.open(dialog, { title: options.title || 'Confirm' });

            // Closing by Escape or backdrop counts as cancel.
            var observer = new MutationObserver(function () {
                if (!document.body.contains(backdrop)) {
                    observer.disconnect();
                    finish(false);
                }
            });

            observer.observe(document.getElementById('modal-root') || document.body, { childList: true });
        });
    };

    /* [data-confirm] on any link, button or submit control. */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-confirm]');

        if (!trigger || trigger.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        RL.confirm({
            title: trigger.dataset.confirmTitle || 'Are you sure?',
            message: trigger.dataset.confirm,
            confirmText: trigger.dataset.confirmText || (trigger.dataset.confirmDanger ? 'Delete' : 'Confirm'),
            danger: trigger.dataset.confirmDanger === '1'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            trigger.dataset.confirmed = '1';

            if (trigger.form && (trigger.type === 'submit' || trigger.tagName === 'BUTTON')) {
                if (typeof trigger.form.requestSubmit === 'function') {
                    trigger.form.requestSubmit(trigger);
                } else {
                    trigger.form.submit();
                }
            } else {
                trigger.click();
            }
        });
    }, true);


    /* =====================================================================
       7. Theme
       ===================================================================== */

    RL.theme = {
        get: function () {
            return RL.storage.get('theme', RL.config.theme || 'system');
        },

        /** Which theme is actually showing right now. */
        resolved: function () {
            var choice = this.get();

            if (choice === 'light' || choice === 'dark') {
                return choice;
            }

            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';
        },

        set: function (theme, persistToServer) {
            if (theme === 'system') {
                document.documentElement.removeAttribute('data-theme');
            } else {
                document.documentElement.setAttribute('data-theme', theme);
            }

            RL.storage.set('theme', theme);
            this.syncButton();

            // Charts read colours from CSS custom properties, so they must redraw.
            window.dispatchEvent(new CustomEvent('rl:themechange', { detail: { theme: theme } }));

            if (persistToServer !== false && RL.config.userId) {
                RL.api('users.set_theme', { method: 'POST', body: { theme: theme } }).catch(function () {
                    // A preference that fails to save is not worth interrupting anyone.
                });
            }
        },

        toggle: function () {
            this.set(this.resolved() === 'dark' ? 'light' : 'dark');
        },

        syncButton: function () {
            var isDark = this.resolved() === 'dark';
            var light = RL.qs('.theme-icon-light');
            var dark = RL.qs('.theme-icon-dark');

            if (light) { light.hidden = isDark; }
            if (dark) { dark.hidden = !isDark; }
        }
    };


    /* =====================================================================
       8. Sidebar and navigation
       ===================================================================== */

    function initSidebar() {
        var sidebar = document.getElementById('app-sidebar');
        var toggle = document.getElementById('sidebar-toggle');
        var backdrop = document.getElementById('sidebar-backdrop');

        if (!sidebar || !toggle) {
            return;
        }

        function open() {
            sidebar.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');

            if (backdrop) {
                backdrop.hidden = false;
                backdrop.classList.add('is-open');
            }

            document.body.style.overflow = 'hidden';

            var firstLink = sidebar.querySelector('.nav-link');

            if (firstLink) {
                firstLink.focus();
            }
        }

        function close() {
            sidebar.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');

            if (backdrop) {
                backdrop.classList.remove('is-open');
                backdrop.hidden = true;
            }

            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', close);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                close();
                toggle.focus();
            }
        });

        sidebar.addEventListener('keydown', function (event) {
            if (sidebar.classList.contains('is-open')) {
                trapFocus(sidebar, event);
            }
        });

        // A swipe from the left edge opens the drawer on a phone.
        var touchStartX = null;

        document.addEventListener('touchstart', function (event) {
            touchStartX = event.touches[0].clientX;
        }, { passive: true });

        document.addEventListener('touchend', function (event) {
            if (touchStartX === null || window.innerWidth > 1024) {
                return;
            }

            var deltaX = event.changedTouches[0].clientX - touchStartX;

            if (touchStartX < 28 && deltaX > 70) {
                open();
            } else if (sidebar.classList.contains('is-open') && deltaX < -70) {
                close();
            }

            touchStartX = null;
        }, { passive: true });

        // Leaving the mobile breakpoint should not strand an open drawer.
        if (window.matchMedia) {
            window.matchMedia('(min-width: 1025px)').addEventListener('change', function (event) {
                if (event.matches) {
                    close();
                }
            });
        }
    }


    /* =====================================================================
       9. Dropdowns
       ===================================================================== */

    function initDropdowns() {
        var openMenu = null;

        function closeOpen() {
            if (!openMenu) {
                return;
            }

            openMenu.menu.hidden = true;
            openMenu.trigger.setAttribute('aria-expanded', 'false');
            openMenu = null;
        }

        RL.qsa('[aria-haspopup="true"]').forEach(function (trigger) {
            var menu = document.getElementById(trigger.getAttribute('aria-controls'));

            if (!menu) {
                return;
            }

            trigger.addEventListener('click', function (event) {
                event.stopPropagation();

                var wasOpen = !menu.hidden;
                closeOpen();

                if (!wasOpen) {
                    menu.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    openMenu = { menu: menu, trigger: trigger };
                }
            });

            menu.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            menu.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeOpen();
                    trigger.focus();
                } else {
                    trapFocus(menu, event);
                }
            });
        });

        document.addEventListener('click', closeOpen);
    }


    /* =====================================================================
       10. Tables
       ===================================================================== */

    /** Read a cell's sortable value: an explicit data-value, else its text. */
    function cellValue(row, index) {
        var cell = row.children[index];

        if (!cell) {
            return '';
        }

        return cell.dataset.value !== undefined ? cell.dataset.value : cell.textContent.trim();
    }

    function compareValues(a, b) {
        var numA = parseFloat(String(a).replace(/[^0-9.\-]/g, ''));
        var numB = parseFloat(String(b).replace(/[^0-9.\-]/g, ''));
        var bothNumeric = !isNaN(numA) && !isNaN(numB)
            && /[0-9]/.test(String(a)) && /[0-9]/.test(String(b));

        if (bothNumeric) {
            return numA - numB;
        }

        // Empty values always sort last, whichever direction is chosen.
        if (a === '' && b !== '') { return 1; }
        if (b === '' && a !== '') { return -1; }

        return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
    }

    function initSortableTables(root) {
        RL.qsa('.table-sortable', root).forEach(function (table) {
            var headers = RL.qsa('thead th[data-sort]', table);

            headers.forEach(function (header, index) {
                header.style.cursor = 'pointer';

                if (!header.hasAttribute('aria-sort')) {
                    header.setAttribute('aria-sort', 'none');
                }

                header.addEventListener('click', function () {
                    var body = table.tBodies[0];

                    if (!body) {
                        return;
                    }

                    var ascending = header.getAttribute('aria-sort') !== 'ascending';

                    headers.forEach(function (other) {
                        other.setAttribute('aria-sort', 'none');
                    });

                    header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');

                    var rows = Array.prototype.slice.call(body.rows).filter(function (row) {
                        return !row.classList.contains('no-matches');
                    });

                    rows.sort(function (rowA, rowB) {
                        var result = compareValues(cellValue(rowA, index), cellValue(rowB, index));

                        return ascending ? result : -result;
                    });

                    rows.forEach(function (row) { body.appendChild(row); });
                });
            });
        });
    }

    function initTableFilters(root) {
        RL.qsa('[data-filter-target]', root).forEach(function (input) {
            var table = RL.qs(input.dataset.filterTarget);

            if (!table) {
                return;
            }

            var body = table.tBodies[0];

            if (!body) {
                return;
            }

            var countNode = input.dataset.filterCount ? RL.qs(input.dataset.filterCount) : null;
            var emptyRow = null;

            var apply = RL.debounce(function () {
                var query = input.value.trim().toLowerCase();
                var visible = 0;

                Array.prototype.slice.call(body.rows).forEach(function (row) {
                    if (row.classList.contains('no-matches')) {
                        return;
                    }

                    var matches = query === '' || row.textContent.toLowerCase().indexOf(query) !== -1;
                    row.classList.toggle('is-hidden', !matches);

                    if (matches) {
                        visible++;
                    }
                });

                if (countNode) {
                    countNode.textContent = String(visible);
                }

                if (visible === 0 && query !== '') {
                    if (!emptyRow) {
                        var columns = table.tHead ? table.tHead.rows[0].cells.length : 1;
                        emptyRow = RL.el('tr', { class: 'no-matches' }, [
                            RL.el('td', { colspan: columns, text: 'No rows match “' + query + '”.' })
                        ]);
                        body.appendChild(emptyRow);
                    }

                    emptyRow.hidden = false;
                    emptyRow.querySelector('td').textContent = 'No rows match “' + query + '”.';
                } else if (emptyRow) {
                    emptyRow.hidden = true;
                }
            }, 150);

            input.addEventListener('input', apply);

            // Escape clears the box, which is what people expect from a filter.
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    input.value = '';
                    apply();
                }
            });
        });
    }

    function initBulkSelect(root) {
        RL.qsa('[data-bulk-all]', root).forEach(function (master) {
            var scope = master.closest('table') || document;
            var bar = RL.qs(master.dataset.bulkBar || '#bulk-bar');
            var countNode = bar ? bar.querySelector('[data-bulk-count]') : null;

            function boxes() {
                return RL.qsa('[data-bulk-item]', scope);
            }

            function refresh() {
                var all = boxes();
                var checked = all.filter(function (box) { return box.checked; });

                master.checked = all.length > 0 && checked.length === all.length;
                master.indeterminate = checked.length > 0 && checked.length < all.length;

                if (bar) {
                    bar.hidden = checked.length === 0;
                }

                if (countNode) {
                    countNode.textContent = String(checked.length);
                }
            }

            master.addEventListener('change', function () {
                boxes().forEach(function (box) {
                    if (!box.closest('tr') || !box.closest('tr').classList.contains('is-hidden')) {
                        box.checked = master.checked;
                    }
                });

                refresh();
            });

            scope.addEventListener('change', function (event) {
                if (event.target.matches('[data-bulk-item]')) {
                    refresh();
                }
            });

            refresh();
        });
    }

    /** Make a whole row clickable when it carries data-row-href. */
    function initRowLinks(root) {
        RL.on('tr[data-row-href]', 'click', function (event, row) {
            // Never hijack a click on a real control inside the row.
            if (event.target.closest('a, button, input, select, textarea, label, form')) {
                return;
            }

            window.location.href = row.dataset.rowHref;
        }, root === document ? document : root);
    }


    /* =====================================================================
       11. Forms
       ===================================================================== */

    /** Warn before leaving a form with unsaved edits. */
    function initDirtyGuard(root) {
        RL.qsa('form[data-guard]', root).forEach(function (form) {
            var dirty = false;

            form.addEventListener('input', function () { dirty = true; });
            form.addEventListener('change', function () { dirty = true; });
            form.addEventListener('submit', function () { dirty = false; });

            window.addEventListener('beforeunload', function (event) {
                if (!dirty) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });

            // Explicit "cancel" links should not trip the guard.
            RL.qsa('[data-no-guard]', form).forEach(function (node) {
                node.addEventListener('click', function () { dirty = false; });
            });
        });
    }

    /**
     * Stop double submissions.
     *
     * A technician on a slow connection will absolutely tap Save twice, and
     * two identical maintenance logs is a real problem to unpick.
     */
    function initSubmitLock(root) {
        RL.qsa('form', root).forEach(function (form) {
            if (form.dataset.noLock === '1' || form.method.toLowerCase() !== 'post') {
                return;
            }

            form.addEventListener('submit', function () {
                var buttons = RL.qsa('button[type="submit"], input[type="submit"]', form);

                window.setTimeout(function () {
                    buttons.forEach(function (button) {
                        button.disabled = true;
                        button.classList.add('is-loading');
                    });
                }, 0);

                // If the browser restores the page from cache, re-enable them.
                window.setTimeout(function () {
                    buttons.forEach(function (button) {
                        button.disabled = false;
                        button.classList.remove('is-loading');
                    });
                }, 15000);
            });
        });
    }

    /** Inline hints before the server round-trip. The server still validates. */
    function initClientValidation(root) {
        RL.qsa('form[data-validate]', root).forEach(function (form) {
            form.setAttribute('novalidate', 'novalidate');

            form.addEventListener('submit', function (event) {
                var firstInvalid = null;

                RL.qsa('input, select, textarea', form).forEach(function (field) {
                    clearFieldError(field);

                    if (field.type === 'hidden' || field.disabled) {
                        return;
                    }

                    if (!field.checkValidity()) {
                        showFieldError(field, describeValidity(field));

                        if (!firstInvalid) {
                            firstInvalid = field;
                        }
                    }
                });

                if (firstInvalid) {
                    event.preventDefault();
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    RL.toast('Please check the highlighted fields.', 'warning');
                }
            });

            form.addEventListener('input', function (event) {
                if (event.target.matches('input, select, textarea') && event.target.checkValidity()) {
                    clearFieldError(event.target);
                }
            });
        });
    }

    function describeValidity(field) {
        var label = field.labels && field.labels[0]
            ? field.labels[0].textContent.replace('*', '').trim()
            : 'This field';

        if (field.validity.valueMissing) {
            return label + ' is required.';
        }

        if (field.validity.typeMismatch) {
            return field.type === 'email'
                ? 'Enter a valid email address.'
                : 'That is not a valid ' + field.type + '.';
        }

        if (field.validity.rangeUnderflow) {
            return label + ' must be ' + field.min + ' or more.';
        }

        if (field.validity.rangeOverflow) {
            return label + ' must be ' + field.max + ' or less.';
        }

        if (field.validity.tooShort) {
            return label + ' must be at least ' + field.minLength + ' characters.';
        }

        if (field.validity.tooLong) {
            return label + ' must be ' + field.maxLength + ' characters or fewer.';
        }

        if (field.validity.patternMismatch) {
            return field.title || (label + ' is not in the expected format.');
        }

        if (field.validity.stepMismatch) {
            return label + ' is not a valid step.';
        }

        return field.validationMessage || (label + ' is not valid.');
    }

    function showFieldError(field, message) {
        var group = field.closest('.form-group');

        if (!group) {
            return;
        }

        group.classList.add('has-error');
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        var existing = group.querySelector('.form-error.js-error');

        if (existing) {
            existing.querySelector('span').textContent = message;

            return;
        }

        var node = RL.el('div', { class: 'form-error js-error' }, [
            RL.el('span', { text: message })
        ]);

        group.appendChild(node);
    }

    function clearFieldError(field) {
        var group = field.closest('.form-group');

        if (!group) {
            return;
        }

        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');

        var node = group.querySelector('.form-error.js-error');

        if (node) {
            node.parentNode.removeChild(node);
            group.classList.remove('has-error');
        }
    }

    /** Character counters on textareas with data-counter. */
    function initCounters(root) {
        RL.qsa('[data-counter]', root).forEach(function (field) {
            var max = parseInt(field.getAttribute('maxlength') || field.dataset.counter, 10);

            if (!max) {
                return;
            }

            var group = field.closest('.form-group') || field.parentNode;
            var counter = RL.el('div', { class: 'form-hint text-right' });
            group.appendChild(counter);

            function update() {
                var used = field.value.length;
                counter.textContent = used + ' / ' + max;
                counter.style.color = used > max * 0.92 ? 'var(--warn)' : '';
            }

            field.addEventListener('input', update);
            update();
        });
    }

    /** Textareas that grow with their content. */
    function initAutoGrow(root) {
        RL.qsa('textarea[data-autogrow]', root).forEach(function (field) {
            function resize() {
                field.style.height = 'auto';
                field.style.height = (field.scrollHeight + 2) + 'px';
            }

            field.addEventListener('input', resize);
            resize();
        });
    }

    /** Submit a form as soon as a control changes (filter selects). */
    function initAutoSubmit(root) {
        RL.qsa('[data-autosubmit]', root).forEach(function (field) {
            field.addEventListener('change', function () {
                if (field.form) {
                    field.form.submit();
                }
            });
        });
    }

    /** Show/hide a password. */
    function initPasswordToggles(root) {
        RL.qsa('[data-password-toggle]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                var field = button.parentNode.querySelector('input');

                if (!field) {
                    return;
                }

                var showing = field.type === 'text';
                field.type = showing ? 'password' : 'text';
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                button.setAttribute('title', showing ? 'Show password' : 'Hide password');
                field.focus();
            });
        });
    }

    /**
     * Keep dependent totals in step: quantity x unit cost = line total, and
     * the sum of the lines fills the parts cost field.
     */
    function initCostCalculators(root) {
        function recalc(scope) {
            var partsTotal = 0;

            RL.qsa('[data-line-total]', scope).forEach(function (row) {
                var qty = parseFloat((row.querySelector('[data-line-qty]') || {}).value) || 0;
                var cost = parseFloat((row.querySelector('[data-line-cost]') || {}).value) || 0;
                var total = Math.round(qty * cost * 100) / 100;
                var output = row.querySelector('[data-line-out]');

                if (output) {
                    if (output.tagName === 'INPUT') {
                        output.value = total.toFixed(2);
                    } else {
                        output.textContent = RL.fmt.money(total);
                    }
                }

                partsTotal += total;
            });

            var partsField = RL.qs('[data-parts-cost]', scope);

            if (partsField && partsField.dataset.autofill !== '0') {
                partsField.value = partsTotal.toFixed(2);
            }

            var laborHours = parseFloat((RL.qs('[data-labor-hours]', scope) || {}).value) || 0;
            var laborRate = parseFloat((RL.qs('[data-labor-rate]', scope) || {}).value) || 0;
            var laborField = RL.qs('[data-labor-cost]', scope);

            if (laborField && laborRate > 0 && laborField.dataset.autofill !== '0') {
                laborField.value = (Math.round(laborHours * laborRate * 100) / 100).toFixed(2);
            }

            var grand = partsTotal
                + (parseFloat((laborField || {}).value) || 0)
                + (parseFloat((RL.qs('[data-other-cost]', scope) || {}).value) || 0);

            var totalOut = RL.qs('[data-grand-total]', scope);

            if (totalOut) {
                if (totalOut.tagName === 'INPUT') {
                    totalOut.value = grand.toFixed(2);
                } else {
                    totalOut.textContent = RL.fmt.money(grand);
                }
            }
        }

        RL.qsa('[data-cost-scope]', root).forEach(function (scope) {
            scope.addEventListener('input', function (event) {
                if (event.target.matches('[data-line-qty], [data-line-cost], [data-labor-hours], [data-labor-rate], [data-other-cost], [data-parts-cost], [data-labor-cost]')) {
                    // A hand-edited total should stop being overwritten.
                    if (event.target.matches('[data-parts-cost], [data-labor-cost]')) {
                        event.target.dataset.autofill = '0';
                    }

                    recalc(scope);
                }
            });

            scope.addEventListener('rl:repeated', function () { recalc(scope); });
            recalc(scope);
        });
    }


    /* =====================================================================
       12. Tabs
       ===================================================================== */

    function initTabs(root) {
        RL.qsa('[data-toggle="tab"]', root).forEach(function (tab) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();

                var group = tab.closest('.tabs');
                var targetSelector = tab.dataset.target;
                var panel = RL.qs(targetSelector);

                if (!group || !panel) {
                    return;
                }

                RL.qsa('[data-toggle="tab"]', group).forEach(function (other) {
                    other.classList.remove('is-active');
                    other.setAttribute('aria-selected', 'false');

                    var otherPanel = RL.qs(other.dataset.target);

                    if (otherPanel) {
                        otherPanel.hidden = true;
                    }
                });

                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
                panel.hidden = false;

                if (tab.dataset.remember) {
                    RL.storage.set('tab-' + tab.dataset.remember, targetSelector);
                }
            });
        });

        // Restore a remembered tab.
        RL.qsa('[data-toggle="tab"][data-remember]', root).forEach(function (tab) {
            var saved = RL.storage.get('tab-' + tab.dataset.remember, null);

            if (saved && saved === tab.dataset.target && !tab.classList.contains('is-active')) {
                tab.click();
            }
        });
    }


    /* =====================================================================
       13. Autocomplete
       ===================================================================== */

    RL.autocomplete = function (input, options) {
        options = options || {};

        var wrapper = RL.el('div', { class: 'autocomplete' });
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        var list = RL.el('div', {
            class: 'autocomplete-list',
            role: 'listbox',
            hidden: true
        });

        wrapper.appendChild(list);

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('autocomplete', 'off');

        var items = [];
        var activeIndex = -1;

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        }

        function highlight(index) {
            var nodes = RL.qsa('.autocomplete-item', list);

            nodes.forEach(function (node, i) {
                node.classList.toggle('is-active', i === index);

                if (i === index) {
                    node.scrollIntoView({ block: 'nearest' });
                }
            });

            activeIndex = index;
        }

        function render(results) {
            list.innerHTML = '';
            items = results || [];

            if (items.length === 0) {
                list.appendChild(RL.el('div', {
                    class: 'autocomplete-empty',
                    text: options.emptyText || 'No matches'
                }));
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');

                return;
            }

            items.forEach(function (item, index) {
                var node = RL.el('div', {
                    class: 'autocomplete-item',
                    role: 'option',
                    'data-index': index
                });

                if (options.render) {
                    node.innerHTML = options.render(item);
                } else {
                    node.appendChild(RL.el('span', { text: item.label || item.name || '' }));

                    if (item.meta) {
                        node.appendChild(RL.el('span', { class: 'meta', text: item.meta }));
                    }
                }

                node.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(index);
                });

                list.appendChild(node);
            });

            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            highlight(-1);
        }

        function choose(index) {
            var item = items[index];

            if (!item) {
                return;
            }

            if (options.onSelect) {
                options.onSelect(item);
            } else {
                input.value = item.label || item.name || '';
            }

            close();
        }

        var search = RL.debounce(function () {
            var query = input.value.trim();

            if (query.length < (options.minChars || 2)) {
                close();

                return;
            }

            var params = Object.assign({ q: query }, options.params || {});

            RL.api(options.route, { params: params }).then(function (data) {
                render(Array.isArray(data) ? data : (data && data.items) || []);
            }).catch(function () {
                close();
            });
        }, 220);

        input.addEventListener('input', search);
        input.addEventListener('focus', function () {
            if (input.value.trim().length >= (options.minChars || 2)) {
                search();
            }
        });
        input.addEventListener('blur', function () {
            window.setTimeout(close, 150);
        });

        input.addEventListener('keydown', function (event) {
            if (list.hidden) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(Math.min(activeIndex + 1, items.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(activeIndex - 1, 0));
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                choose(activeIndex);
            } else if (event.key === 'Escape') {
                close();
            }
        });

        return { close: close };
    };


    /* =====================================================================
       14. Global search
       ===================================================================== */

    function initGlobalSearch() {
        var input = RL.qs('[data-global-search]');

        if (!input) {
            return;
        }

        function openSpotlight(initialQuery) {
            var searchInput = RL.el('input', {
                type: 'search',
                class: 'spotlight-input',
                placeholder: 'Search assets, logs, work orders and parts…',
                autocomplete: 'off',
                autofocus: true,
                'aria-label': 'Search'
            });

            var results = RL.el('div', { class: 'spotlight-results' });
            var panel = RL.el('div', { class: 'spotlight' }, [searchInput, results]);

            RL.modal.open(panel, { title: 'Search' });

            var flat = [];
            var active = -1;

            function highlight(index) {
                var nodes = RL.qsa('.autocomplete-item', results);
                nodes.forEach(function (node, i) {
                    node.classList.toggle('is-active', i === index);

                    if (i === index) {
                        node.scrollIntoView({ block: 'nearest' });
                    }
                });
                active = index;
            }

            function show(groups) {
                results.innerHTML = '';
                flat = [];

                var total = 0;

                Object.keys(groups || {}).forEach(function (groupName) {
                    var rows = groups[groupName];

                    if (!rows || rows.length === 0) {
                        return;
                    }

                    results.appendChild(RL.el('div', { class: 'spotlight-group-title', text: groupName }));

                    rows.forEach(function (row) {
                        var node = RL.el('div', { class: 'autocomplete-item' }, [
                            RL.el('span', { text: row.label }),
                            row.meta ? RL.el('span', { class: 'meta', text: row.meta }) : null
                        ]);

                        node.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            window.location.href = RL.url(row.url);
                        });

                        results.appendChild(node);
                        flat.push(row);
                        total++;
                    });
                });

                if (total === 0) {
                    results.appendChild(RL.el('div', {
                        class: 'autocomplete-empty',
                        text: 'Nothing found.'
                    }));
                }

                highlight(-1);
            }

            var run = RL.debounce(function () {
                var query = searchInput.value.trim();

                if (query.length < 2) {
                    results.innerHTML = '<div class="autocomplete-empty">Type at least two characters.</div>';

                    return;
                }

                results.innerHTML = '<div class="autocomplete-empty">Searching…</div>';

                RL.api('search.global', { params: { q: query } }).then(show).catch(function (error) {
                    results.innerHTML = '';
                    results.appendChild(RL.el('div', {
                        class: 'autocomplete-empty',
                        text: error.message
                    }));
                });
            }, 220);

            searchInput.addEventListener('input', run);

            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    highlight(Math.min(active + 1, flat.length - 1));
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    highlight(Math.max(active - 1, 0));
                } else if (event.key === 'Enter') {
                    event.preventDefault();

                    if (active >= 0 && flat[active]) {
                        window.location.href = RL.url(flat[active].url);
                    } else if (searchInput.value.trim() !== '') {
                        window.location.href = RL.url('search.php?q=' + encodeURIComponent(searchInput.value.trim()));
                    }
                }
            });

            if (initialQuery) {
                searchInput.value = initialQuery;
                run();
            }
        }

        input.addEventListener('focus', function () {
            input.blur();
            openSpotlight('');
        });

        document.addEventListener('keydown', function (event) {
            var typingHere = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)
                || document.activeElement.isContentEditable;

            if ((event.key === '/' && !typingHere)
                || ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k')) {
                event.preventDefault();
                openSpotlight('');
            }
        });
    }


    /* =====================================================================
       15. Notifications
       ===================================================================== */

    function initNotifications() {
        var badge = document.getElementById('notif-count');

        if (!badge || !RL.config.userId) {
            return;
        }

        function refresh() {
            if (document.hidden) {
                return;
            }

            RL.api('notifications.unread').then(function (data) {
                var count = (data && data.count) || 0;

                badge.hidden = count === 0;
                badge.textContent = count > 99 ? '99+' : String(count);
            }).catch(function () {
                // A failed poll is not worth telling anybody about.
            });
        }

        window.setInterval(refresh, 60000);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refresh();
            }
        });
    }


    /* =====================================================================
       16. File uploads
       ===================================================================== */

    function initDropzones(root) {
        RL.qsa('[data-dropzone]', root).forEach(function (zone) {
            var input = zone.querySelector('[data-dropzone-input]');

            if (!input) {
                return;
            }

            var form = zone.closest('form');
            var preview = form ? form.querySelector('[data-dropzone-preview]') : null;
            var actions = form ? form.querySelector('[data-upload-actions]') : null;
            var maxBytes = (parseFloat(zone.dataset.maxMb) || 8) * 1024 * 1024;

            ['dragenter', 'dragover'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    zone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'drop'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    zone.classList.remove('is-dragover');
                });
            });

            zone.addEventListener('drop', function (event) {
                if (event.dataTransfer && event.dataTransfer.files.length) {
                    input.files = event.dataTransfer.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            input.addEventListener('change', function () {
                if (!preview) {
                    return;
                }

                preview.innerHTML = '';
                var files = Array.prototype.slice.call(input.files);

                if (files.length === 0) {
                    preview.hidden = true;

                    if (actions) {
                        actions.hidden = true;
                    }

                    return;
                }

                var oversized = files.filter(function (file) { return file.size > maxBytes; });

                if (oversized.length) {
                    RL.toast(
                        oversized.length === 1
                            ? '“' + oversized[0].name + '” is larger than the ' + zone.dataset.maxMb + ' MB limit.'
                            : oversized.length + ' files are over the ' + zone.dataset.maxMb + ' MB limit.',
                        'error'
                    );
                }

                var grid = RL.el('div', { class: 'attachment-grid' });

                files.forEach(function (file) {
                    var tile = RL.el('div', { class: 'attachment' });

                    if (/^image\//.test(file.type) && window.URL) {
                        var img = RL.el('img', {
                            class: 'attachment-thumb',
                            src: window.URL.createObjectURL(file),
                            alt: ''
                        });
                        img.addEventListener('load', function () {
                            window.URL.revokeObjectURL(img.src);
                        });
                        tile.appendChild(img);
                    } else {
                        tile.appendChild(RL.el('span', { class: 'attachment-file', html: '📄' }));
                    }

                    tile.appendChild(RL.el('div', { class: 'attachment-meta' }, [
                        RL.el('span', { class: 'attachment-name', text: file.name, title: file.name }),
                        RL.el('span', {
                            class: 'attachment-size',
                            text: RL.fmt.bytes(file.size) + (file.size > maxBytes ? ' — too large' : '')
                        })
                    ]));

                    grid.appendChild(tile);
                });

                preview.appendChild(grid);
                preview.hidden = false;

                if (actions) {
                    actions.hidden = false;
                }
            });
        });
    }


    /* =====================================================================
       17. Repeatable rows
       ===================================================================== */

    RL.repeater = {
        add: function (container) {
            var template = container.querySelector('[data-repeater-template]');

            if (!template) {
                return null;
            }

            var index = parseInt(container.dataset.repeaterIndex || '0', 10);
            var html = template.innerHTML.replace(/__INDEX__/g, String(index));
            var row = RL.el('div', { class: 'repeater-row', 'data-repeater-row': '', html: html });

            var target = container.querySelector('[data-repeater-rows]') || container;
            target.appendChild(row);

            container.dataset.repeaterIndex = String(index + 1);

            RL.init(row);

            var firstField = row.querySelector('input:not([type=hidden]), select, textarea');

            if (firstField) {
                firstField.focus();
            }

            container.dispatchEvent(new CustomEvent('rl:repeated', { bubbles: true }));

            return row;
        },

        remove: function (row) {
            var container = row.closest('[data-repeater]');
            row.parentNode.removeChild(row);

            if (container) {
                container.dispatchEvent(new CustomEvent('rl:repeated', { bubbles: true }));
            }
        }
    };

    /* =====================================================================
       17b. Fields that only matter for one answer
       ===================================================================== */

    /**
     * A select marked [data-reveal="name"] shows and hides the blocks marked
     * [data-reveal-for="name"], each of which lists the values it belongs to in
     * [data-reveal-when="a,b"].
     *
     * The server always renders every block. Without JavaScript the form still
     * works — you just see one or two questions that do not apply, and the
     * server ignores them.
     */
    function initReveals(root) {
        RL.qsa('[data-reveal]', root).forEach(function (select) {
            if (select.dataset.revealBound === '1') {
                return;
            }

            select.dataset.revealBound = '1';

            var name = select.dataset.reveal;
            var scope = select.closest('form') || document;

            var apply = function () {
                RL.qsa('[data-reveal-for="' + name + '"]', scope).forEach(function (block) {
                    var when = (block.dataset.revealWhen || '').split(',');
                    var show = when.indexOf(select.value) !== -1;

                    block.hidden = !show;

                    // A hidden field must not block submission by being required.
                    RL.qsa('[required]', block).forEach(function (field) {
                        field.disabled = !show;
                    });
                });
            };

            select.addEventListener('change', apply);
            apply();
        });
    }


    /**
     * Choosing a part from stock fills in its description and price, so a
     * technician picks one thing instead of typing three.
     */
    function initPartPickers(root) {
        RL.qsa('[data-part-select]', root).forEach(function (select) {
            if (select.dataset.bound === '1') {
                return;
            }

            select.dataset.bound = '1';

            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                var row = select.closest('[data-repeater-row]') || select.closest('.repeater-row');

                if (!row || !option || !option.value) {
                    return;
                }

                var nameField = row.querySelector('[data-part-name]');
                var costField = row.querySelector('[data-line-cost]');

                if (nameField && option.dataset.name) {
                    nameField.value = option.dataset.name;
                }

                if (costField && option.dataset.cost) {
                    costField.value = parseFloat(option.dataset.cost).toFixed(2);
                }

                // Let the cost calculator pick the change up.
                row.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    }

    function initRepeaters(root) {
        RL.qsa('[data-repeater]', root).forEach(function (container) {
            if (!container.dataset.repeaterIndex) {
                container.dataset.repeaterIndex = String(
                    RL.qsa('[data-repeater-row]', container).length
                );
            }

            var addButton = container.querySelector('[data-repeater-add]');

            if (addButton && !addButton.dataset.bound) {
                addButton.dataset.bound = '1';
                addButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    RL.repeater.add(container);
                });
            }
        });

        RL.on('[data-repeater-remove]', 'click', function (event, button) {
            event.preventDefault();

            var row = button.closest('[data-repeater-row]');

            if (row) {
                RL.repeater.remove(row);
            }
        });
    }


    /* =====================================================================
       18. Meter update
       ===================================================================== */

    function initMeterUpdate() {
        RL.on('[data-meter-update]', 'click', function (event, button) {
            event.preventDefault();

            var assetId = button.dataset.meterUpdate;
            var current = button.dataset.meterCurrent || '0';
            var unit = button.dataset.meterUnit || 'hours';
            var assetName = button.dataset.meterAsset || 'this asset';

            var field = RL.el('input', {
                type: 'number',
                step: '0.01',
                min: '0',
                class: 'form-input',
                value: current,
                id: 'meter-reading-input',
                autofocus: true
            });

            var notes = RL.el('input', {
                type: 'text',
                class: 'form-input',
                placeholder: 'Optional note',
                maxlength: '255'
            });

            var save = RL.el('button', { type: 'button', class: 'btn btn-primary', text: 'Save reading' });
            var cancel = RL.el('button', { type: 'button', class: 'btn btn-secondary', text: 'Cancel' });

            var body = RL.el('div', {}, [
                RL.el('div', { class: 'modal-header' }, [
                    RL.el('h2', { class: 'modal-title', text: 'Update meter — ' + assetName })
                ]),
                RL.el('div', { class: 'modal-body' }, [
                    RL.el('div', { class: 'form-group' }, [
                        RL.el('label', {
                            class: 'form-label',
                            for: 'meter-reading-input',
                            text: 'Current reading (' + unit + ')'
                        }),
                        field,
                        RL.el('div', {
                            class: 'form-hint',
                            text: 'Last recorded: ' + current + ' ' + unit
                        })
                    ]),
                    RL.el('div', { class: 'form-group' }, [
                        RL.el('label', { class: 'form-label', text: 'Note' }),
                        notes
                    ])
                ]),
                RL.el('div', { class: 'modal-footer' }, [cancel, save])
            ]);

            RL.modal.open(body, { title: 'Update meter' });

            cancel.addEventListener('click', function () { RL.modal.close(); });

            save.addEventListener('click', function () {
                var reading = parseFloat(field.value);

                if (isNaN(reading) || reading < 0) {
                    RL.toast('Enter a valid meter reading.', 'error');
                    field.focus();

                    return;
                }

                if (reading < parseFloat(current)) {
                    RL.confirm({
                        title: 'Reading went backwards',
                        message: 'The new reading (' + reading + ') is lower than the last one ('
                            + current + '). That usually means a typo, or a replaced meter. Save it anyway?',
                        confirmText: 'Save anyway'
                    }).then(function (ok) {
                        if (ok) {
                            submit(reading);
                        }
                    });

                    return;
                }

                submit(reading);
            });

            function submit(reading) {
                save.classList.add('is-loading');

                RL.api('assets.update_meter', {
                    method: 'POST',
                    body: { id: parseInt(assetId, 10), reading: reading, notes: notes.value }
                }).then(function () {
                    RL.modal.close();
                    RL.toast('Meter updated.', 'success');
                    window.location.reload();
                }).catch(function (error) {
                    save.classList.remove('is-loading');
                    RL.toast(error.message, 'error');
                });
            }
        });
    }


    /* =====================================================================
       18b. Checklist runner
       ===================================================================== */

    /**
     * The inspection screen.
     *
     * Tapping a response button colours the whole item, opens the notes box on
     * a fail, and updates the progress counter at the bottom. All of it is
     * enhancement: the radio inputs and the form work without any of this.
     */
    function initChecklistRunner(root) {
        var items = RL.qsa('[data-checklist-item]', root);

        if (items.length === 0) {
            return;
        }

        function refreshItem(item) {
            var checked = item.querySelector('[data-response-input]:checked');
            var value = checked ? checked.value : '';

            item.classList.remove('is-pass', 'is-fail', 'is-na', 'is-unanswered');

            if (value === 'pass' || value === 'yes') {
                item.classList.add('is-pass');
            } else if (value === 'fail' || value === 'no') {
                item.classList.add('is-fail');
            } else if (value === 'na') {
                item.classList.add('is-na');
            } else {
                item.classList.add('is-unanswered');
            }

            RL.qsa('.response-btn', item).forEach(function (button) {
                var input = button.querySelector('[data-response-input]');
                button.classList.toggle('is-selected', !!(input && input.checked));
            });

            // A failure needs an explanation, so reveal the box and focus it.
            var notes = item.querySelector('[data-fail-notes]');

            if (notes) {
                var shouldShow = value === 'fail' || value === 'no';
                var hasText = notes.querySelector('textarea') && notes.querySelector('textarea').value.trim() !== '';

                notes.hidden = !(shouldShow || hasText);
            }
        }

        function refreshProgress() {
            var answered = 0;
            var passed = 0;
            var failed = 0;

            items.forEach(function (item) {
                var checked = item.querySelector('[data-response-input]:checked');
                var value = checked ? checked.value : '';

                if (value) {
                    answered++;

                    if (value === 'pass' || value === 'yes') {
                        passed++;
                    } else if (value === 'fail' || value === 'no') {
                        failed++;
                    }

                    return;
                }

                // Number, meter and text items count once they have a value.
                var field = item.querySelector('input[type="number"], textarea[name*="value_text"]');

                if (field && field.value.trim() !== '') {
                    answered++;
                    passed++;
                }
            });

            var total = items.length;
            var bar = RL.qs('[data-progress-bar]');
            var pct = total === 0 ? 0 : Math.round((answered / total) * 100);

            if (bar) {
                bar.style.width = pct + '%';
                bar.classList.toggle('tone-danger', failed > 0);
                bar.classList.toggle('tone-ok', failed === 0 && answered === total && total > 0);
            }

            var set = function (selector, value) {
                var node = RL.qs(selector);

                if (node) {
                    node.textContent = String(value);
                }
            };

            set('[data-count-answered]', answered);
            set('[data-count-pass]', passed);
            set('[data-count-fail]', failed);
        }

        items.forEach(function (item) {
            RL.qsa('[data-response-input]', item).forEach(function (input) {
                input.addEventListener('change', function () {
                    refreshItem(item);
                    refreshProgress();

                    var notes = item.querySelector('[data-fail-notes] textarea');

                    if (notes && !item.querySelector('[data-fail-notes]').hidden && notes.value === '') {
                        notes.focus();
                    }
                });
            });

            RL.qsa('input[type="number"], textarea', item).forEach(function (field) {
                field.addEventListener('input', RL.debounce(refreshProgress, 250));
            });

            refreshItem(item);
        });

        refreshProgress();

        // Warn before finishing with unanswered items, rather than letting the
        // server bounce it back after a scroll to the bottom.
        var form = items[0].closest('form');

        if (form) {
            form.addEventListener('submit', function (event) {
                var submitter = event.submitter;

                if (!submitter || submitter.value !== 'complete') {
                    return;
                }

                var unanswered = items.filter(function (item) {
                    if (item.querySelector('[data-response-input]:checked')) {
                        return false;
                    }

                    var field = item.querySelector('input[type="number"], textarea[name*="value_text"]');

                    return !(field && field.value.trim() !== '');
                });

                if (unanswered.length === 0) {
                    return;
                }

                event.preventDefault();

                RL.toast(
                    unanswered.length + ' item' + (unanswered.length === 1 ? '' : 's')
                    + ' still need' + (unanswered.length === 1 ? 's' : '') + ' an answer.',
                    'warning'
                );

                unanswered[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
                unanswered[0].classList.add('is-unanswered');
            });
        }
    }


    /* =====================================================================
       19. Misc behaviours
       ===================================================================== */

    function initMisc(root) {
        // Dismissible alerts
        RL.on('[data-dismiss="alert"]', 'click', function (event, button) {
            var alert = button.closest('.alert');

            if (alert) {
                alert.parentNode.removeChild(alert);
            }
        });

        // Copy to clipboard
        RL.on('[data-copy]', 'click', function (event, button) {
            event.preventDefault();

            var text = button.dataset.copy;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    RL.toast('Copied to clipboard.', 'success', 2000);
                }).catch(function () {
                    RL.toast('Could not copy. Select the text and copy manually.', 'warning');
                });
            } else {
                RL.toast('Copying is not supported in this browser.', 'warning');
            }
        });

        // Print
        RL.on('[data-print]', 'click', function (event) {
            event.preventDefault();
            window.print();
        });

        // Collapsible sections
        RL.qsa('[data-collapse-toggle]', root).forEach(function (button) {
            if (button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';

            button.addEventListener('click', function () {
                var target = RL.qs(button.dataset.collapseToggle);

                if (!target) {
                    return;
                }

                var open = target.hidden;
                target.hidden = !open;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    }

    /** Raise server-side flash messages as toasts, and hide the fallback. */
    function initFlash() {
        var node = document.getElementById('rl-flash');

        if (!node) {
            return;
        }

        var messages = [];

        try {
            messages = JSON.parse(node.textContent) || [];
        } catch (e) {
            return;
        }

        if (messages.length === 0) {
            return;
        }

        // JS is clearly working, so use toasts instead of the inline alerts.
        RL.qsa('.js-flash-fallback').forEach(function (block) {
            block.parentNode.removeChild(block);
        });

        messages.forEach(function (message, index) {
            window.setTimeout(function () {
                RL.toast(message.message, message.type);
            }, index * 120);
        });
    }


    /* =====================================================================
       20. Init
       ===================================================================== */

    /**
     * Wire up a subtree. Called for the document on load, and again for
     * anything injected into a modal or added by the repeater.
     */
    RL.init = function (root) {
        root = root || document;

        initSortableTables(root);
        initTableFilters(root);
        initBulkSelect(root);
        initRowLinks(root === document ? document : root);
        initDirtyGuard(root);
        initSubmitLock(root);
        initClientValidation(root);
        initCounters(root);
        initAutoGrow(root);
        initAutoSubmit(root);
        initPasswordToggles(root);
        initCostCalculators(root);
        initTabs(root);
        initDropzones(root);
        initRepeaters(root);
        initReveals(root);
        initPartPickers(root);
        initChecklistRunner(root);
        initMisc(root);
    };

    function boot() {
        RL.theme.syncButton();

        initSidebar();
        initDropdowns();
        initGlobalSearch();
        initNotifications();
        initMeterUpdate();
        initFlash();

        RL.init(document);

        var themeToggle = document.getElementById('theme-toggle');

        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                RL.theme.toggle();
            });
        }

        // Follow the system when the user has expressed no preference.
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (RL.theme.get() === 'system') {
                    RL.theme.syncButton();
                    window.dispatchEvent(new CustomEvent('rl:themechange', { detail: { theme: 'system' } }));
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})(window, document);
