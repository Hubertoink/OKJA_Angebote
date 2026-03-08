(function () {
    'use strict';

    var data = window.jhhEventModalData || {};
    if (!data.ajaxUrl) {
        return;
    }

    var root = null;
    var content = null;
    var closeButton = null;
    var lastFocused = null;
    var savedScrollY = 0;

    function ensureModal() {
        if (root) {
            return;
        }

        root = document.createElement('div');
        root.className = 'jhh-event-modal-root';
        root.hidden = true;
        root.innerHTML = '' +
            '<div class="jhh-event-modal-backdrop" data-jhh-event-modal-close></div>' +
            '<div class="jhh-event-modal-spinner"></div>' +
            '<div class="jhh-event-modal-dialog" role="dialog" aria-modal="true" aria-label="Event-Details">' +
                '<button type="button" class="jhh-event-modal-close" data-jhh-event-modal-close aria-label="' + escapeHtml((data.labels && data.labels.close) || 'Schließen') + '">&times;</button>' +
                '<div class="jhh-event-modal-scroll">' +
                    '<div class="jhh-event-modal-content"></div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(root);
        content = root.querySelector('.jhh-event-modal-content');
        closeButton = root.querySelector('.jhh-event-modal-close');

        root.addEventListener('click', function (event) {
            if (event.target && event.target.hasAttribute('data-jhh-event-modal-close')) {
                closeModal();
            }
        });
    }

    function openModal() {
        ensureModal();
        savedScrollY = window.scrollY;
        root.hidden = false;
        document.body.style.top = '-' + savedScrollY + 'px';
        document.body.classList.add('jhh-event-modal-open');
        lastFocused = document.activeElement;
        if (closeButton) {
            closeButton.focus();
        }
    }

    var isClosing = false;

    function closeModal() {
        if (!root || isClosing) {
            return;
        }

        isClosing = true;
        root.classList.add('is-closing');

        setTimeout(function () {
            root.hidden = true;
            root.classList.remove('is-closing');
            document.body.classList.remove('jhh-event-modal-open');
            document.body.style.top = '';
            window.scrollTo(0, savedScrollY);
            if (content) {
                content.innerHTML = '';
            }
            isClosing = false;
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }, 280);
    }

    function setLoading() {
        ensureModal();
        content.innerHTML = '';
        root.classList.add('is-loading');
        openModal();
    }

    function setError(message, href) {
        var html = '<div class="jhh-event-modal-error"><p>' + escapeHtml(message || ((data.labels && data.labels.error) || 'Das Event konnte gerade nicht geladen werden.')) + '</p>';
        if (href) {
            html += '<p><a class="jhh-event-modal-permalink" href="' + escapeAttribute(href) + '">' + escapeHtml((data.labels && data.labels.open) || 'Event als Seite öffnen') + '</a></p>';
        }
        html += '</div>';
        content.innerHTML = html;
        root.classList.remove('is-loading');
    }

    function fetchEvent(eventId, href) {
        setLoading();

        var body = new URLSearchParams();
        body.set('action', 'jhh_pb_get_event_modal');
        body.set('nonce', data.nonce || '');
        body.set('event_id', String(eventId));

        fetch(data.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (!response || !response.success || !response.data || !response.data.html) {
                setError((response && response.data && response.data.message) || '', href);
                return;
            }

            content.innerHTML = response.data.html;
            root.classList.remove('is-loading');
        }).catch(function () {
            setError('', href);
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-jhh-event-modal="1"][data-jhh-event-id]');
        if (!link) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        fetchEvent(link.getAttribute('data-jhh-event-id'), link.getAttribute('href') || '');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && root && !root.hidden) {
            closeModal();
        }
    });

    /* --- Calendar dropdown toggle (delegated, works inside AJAX-loaded modal content) --- */
    function closeAllCalMenus() {
        var menus = document.querySelectorAll('.jhh-event-cal-menu');
        for (var i = 0; i < menus.length; i++) {
            menus[i].hidden = true;
            var btn = menus[i].previousElementSibling;
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.jhh-event-cal-trigger');
        if (trigger) {
            e.preventDefault();
            var menu = trigger.nextElementSibling;
            if (!menu) return;
            var isOpen = !menu.hidden;
            closeAllCalMenus();
            if (!isOpen) {
                menu.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
            }
            return;
        }
        if (!e.target.closest('.jhh-event-cal-menu')) {
            closeAllCalMenus();
        }
    });

    window.__jhhCalDrop = true;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/\n/g, '&#10;');
    }
})();