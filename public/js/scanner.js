/* GTAV-M gate scanner: camera QR scanning plus manual search by name or ticket code. */
(function () {
    'use strict';

    var root = document.getElementById('scanner');
    if (!root) return;

    var config = {
        eventId: root.dataset.eventId,
        scanUrl: root.dataset.scanUrl,
        searchUrl: root.dataset.searchUrl,
    };
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var result = document.getElementById('result');
    var recent = document.getElementById('recent');
    var note = document.getElementById('camera-note');
    var matches = document.getElementById('matches');
    var icons = { ok: '\u2713', warn: '!', bad: '\u00D7' };
    var busy = false;
    var lastCode = null;
    var lastTime = 0;
    var holdUntil = 0;
    /** The camera reads a held-up code many times a second: the same ticket is ignored for this long. */
    var SAME_CODE_PAUSE = 10000;
    /** After any camera result, new reads wait this long so staff can read the screen. */
    var RESULT_HOLD = 2500;

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function clock(date) {
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function show(tone, instruction, who, message) {
        result.className = 'scan-result ' + tone;
        var verdict = el('div', 'verdict');
        if (icons[tone]) verdict.appendChild(el('span', 'verdict-icon', icons[tone]));
        verdict.appendChild(document.createTextNode(instruction));
        result.replaceChildren(verdict);
        if (who) result.appendChild(el('div', 'who', who));
        if (message) result.appendChild(el('div', 'detail', message));
    }

    /** One line per ticket: a repeat scan updates that ticket's line and moves it to the top. */
    function updateActivity(row) {
        var empty = document.getElementById('recent-empty');
        if (empty) empty.remove();

        var item = el('li', 'activity-row activity-' + row.status);
        item.dataset.key = row.key;
        var person = el('div', 'activity-person');
        person.appendChild(el('strong', '', row.name));
        if (row.company) person.appendChild(el('span', '', row.company));
        var status = el('div', 'activity-status');
        status.appendChild(el('span', 'badge badge-' + row.status, row.status_label));
        status.appendChild(el('span', 'activity-detail', row.detail));
        if (row.repeat_note) status.appendChild(el('span', 'activity-repeat', row.repeat_note));
        item.appendChild(person);
        item.appendChild(status);

        var existing = recent.querySelector('[data-key="' + row.key + '"]');
        if (existing) existing.remove();
        recent.prepend(item);
        while (recent.children.length > 8) recent.lastElementChild.remove();
    }

    function check(code, fromCamera) {
        code = (code || '').trim();
        if (!code || busy) return;

        var now = Date.now();
        if (fromCamera) {
            if (now < holdUntil) return;
            if (code === lastCode && now - lastTime < SAME_CODE_PAUSE) return;
        }
        lastCode = code;
        lastTime = now;
        busy = true;

        fetch(config.scanUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ event_id: config.eventId, code: code }),
        })
            .then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            })
            .then(function (res) {
                var data = res.data;
                if (!res.ok || !data.result) {
                    show('bad', 'NOT CHECKED', null, data.message || 'The ticket could not be checked. Scan it again.');
                    return;
                }
                var who = data.attendee
                    ? data.attendee.name + (data.attendee.company ? ' · ' + data.attendee.company : '')
                    : null;
                show(data.tone || 'bad', data.instruction || 'DO NOT LET IN', who, data.message);
                if (data.activity) updateActivity(data.activity);
                if (fromCamera) holdUntil = Date.now() + RESULT_HOLD;
                document.getElementById('count-in').textContent = data.event.checked_in;
                document.getElementById('count-left').textContent = data.event.remaining;
                if (navigator.vibrate && (!navigator.userActivation || navigator.userActivation.hasBeenActive)) {
                    navigator.vibrate(data.authorized ? 80 : [120, 60, 120]);
                }
            })
            .catch(function () {
                show('bad', 'NO CONNECTION', null, 'The ticket was not checked. Check the Wi-Fi, then scan again.');
                lastCode = null;
            })
            .then(function () { busy = false; });
    }

    function search(term) {
        matches.replaceChildren(el('p', 'muted small', 'Searching…'));
        var query = '?event_id=' + encodeURIComponent(config.eventId) + '&q=' + encodeURIComponent(term);

        fetch(config.searchUrl + query, { headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    matches.replaceChildren(el('p', 'error', res.data.message || 'Search failed. Try again.'));
                    return;
                }
                if (!res.data.data.length) {
                    matches.replaceChildren(el('p', 'muted small', 'No ticket holder for this event matches "' + term + '".'));
                    return;
                }
                var list = el('ul', 'plain');
                res.data.data.forEach(function (person) {
                    var item = el('li');
                    var info = el('div');
                    info.appendChild(el('strong', '', person.name));
                    info.appendChild(el('div', 'muted small', (person.company ? person.company + ' · ' : '') + person.reference_code));
                    item.appendChild(info);
                    if (person.checked_in_at) {
                        item.appendChild(el('span', 'badge badge-inside', 'Inside since ' + clock(new Date(person.checked_in_at))));
                    } else {
                        var button = el('button', 'btn btn-sm btn-primary', 'Let in');
                        button.type = 'button';
                        button.addEventListener('click', function () {
                            lastCode = null;
                            matches.replaceChildren();
                            check(person.reference_code);
                        });
                        item.appendChild(button);
                    }
                    list.appendChild(item);
                });
                matches.replaceChildren(list);
            })
            .catch(function () {
                matches.replaceChildren(el('p', 'error', 'No connection to the server.'));
            });
    }

    document.getElementById('manual-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var input = document.getElementById('manual-code');
        var value = input.value.trim();
        if (!value) return;

        // A full ticket code is checked straight away; anything else is a name search.
        if (/^EX-[A-Z0-9]{8}$/i.test(value)) {
            lastCode = null;
            matches.replaceChildren();
            check(value);
            input.value = '';
        } else if (value.length >= 2) {
            search(value);
        } else {
            matches.replaceChildren(el('p', 'muted small', 'Type at least 2 characters.'));
        }
    });

    if (window.Html5Qrcode) {
        var reader = new window.Html5Qrcode('reader');
        reader.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, function (decoded) { check(decoded, true); })
            .then(function () { note.textContent = 'Camera on. Hold the QR code inside the square.'; })
            .catch(function () {
                note.textContent = 'Camera unavailable. Allow camera access, or open this page over HTTPS or on localhost. You can still search by name or ticket code below.';
            });
    } else {
        note.textContent = 'The camera scanner could not be loaded (no internet). Search by name or ticket code below instead.';
    }
})();
