/* GTAV-M: small progressive enhancements. The site works without this file. */
(function () {
    'use strict';

    var progress = document.getElementById('progress');
    var progressTimer = null;

    function startProgress() {
        if (!progress) return;
        progress.classList.add('is-active');
        clearTimeout(progressTimer);
        // Safety net: never leave the bar running (for example after a file download).
        progressTimer = setTimeout(stopProgress, 8000);
    }

    function stopProgress() {
        if (progress) progress.classList.remove('is-active');
        document.querySelectorAll('.btn.is-loading').forEach(function (button) {
            button.classList.remove('is-loading');
            button.removeAttribute('aria-busy');
            button.disabled = false;
        });
    }

    // Coming back with the browser's Back button restores a clean page.
    window.addEventListener('pageshow', stopProgress);

    /* ---- Mobile menu ---- */
    var toggle = document.getElementById('nav-toggle');
    var links = document.getElementById('nav-links');
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            var open = links.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ---- Filters that apply as soon as a choice is made ---- */
    document.querySelectorAll('[data-autosubmit]').forEach(function (field) {
        field.addEventListener('change', function () {
            if (field.form) field.form.submit();
        });
    });

    /* ---- Confirmation dialog for destructive actions ---- */
    var dialog = document.getElementById('confirm-dialog');
    var pendingForm = null;

    function closeDialog() {
        pendingForm = null;
        if (dialog && dialog.open) dialog.close();
    }

    if (dialog) {
        document.getElementById('confirm-cancel').addEventListener('click', closeDialog);
        document.getElementById('confirm-ok').addEventListener('click', function () {
            var form = pendingForm;
            closeDialog();
            if (form) {
                form.dataset.confirmed = 'yes';
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            }
        });
        // Clicking the dark backdrop closes the dialog.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) closeDialog();
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        var message = form.getAttribute('data-confirm');
        if (message && form.dataset.confirmed !== 'yes') {
            event.preventDefault();
            if (dialog && typeof dialog.showModal === 'function') {
                pendingForm = form;
                document.getElementById('confirm-message').textContent = message;
                dialog.showModal();
            } else if (window.confirm(message)) {
                form.dataset.confirmed = 'yes';
                form.submit();
            }
            return;
        }

        if (event.defaultPrevented || form.hasAttribute('data-no-loading')) return;

        /* ---- Loading state: shows the action was received and blocks double submission ---- */
        var button = event.submitter || form.querySelector('button[type="submit"]');
        var isDownload = form.hasAttribute('data-download');
        startProgress();
        if (button && button.classList.contains('btn')) {
            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');
            // Disable on the next tick so the button's own value is still sent.
            setTimeout(function () { button.disabled = true; }, 0);
        }
        if (isDownload) setTimeout(stopProgress, 2500);
    });

    /* ---- Progress bar while the next page loads ---- */
    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || event.defaultPrevented) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target === '_blank' || link.hasAttribute('data-download') || link.hasAttribute('download')) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || link.origin !== window.location.origin) return;
        startProgress();
    });
})();
