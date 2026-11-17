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
    var verdicts = {
        admitted: ['ok', 'ADMIT'],
        duplicate: ['warn', 'ALREADY IN'],
        invalid: ['bad', 'STOP'],
        wrong_event: ['bad', 'WRONG EVENT'],
        cancelled: ['bad', 'CANCELLED'],
    };
    var busy = false;
    var lastCode = null;
    var lastTime = 0;

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function clock(date) {
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function show(kind, verdict, who, message) {
        result.className = 'scan-result ' + kind;
        result.replaceChildren(el('div', 'verdict', verdict));
        if (who) result.appendChild(el('div', 'who', who));
        if (message) result.appendChild(el('div', '', message));
    }

    function addRecent(data) {
        var empty = document.getElementById('recent-empty');
        if (empty) empty.remove();
        var row = el('tr');
        row.appendChild(el('td', 'nowrap', clock(new Date())));
        row.appendChild(el('td', '', data.attendee ? data.attendee.name : 'Unknown pass'));
        var cell = el('td', 'right');
        cell.appendChild(el('span', 'badge badge-' + data.result, data.result.replace('_', ' ')));
        row.appendChild(cell);
        recent.prepend(row);
        while (recent.children.length > 8) recent.lastElementChild.remove();
    }

    function check(code) {
        code = (code || '').trim();
        if (!code || busy) return;

        // Ignore the same code being read again while it is still in front of the camera.
        var now = Date.now();
        if (code === lastCode && now - lastTime < 4000) return;
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
                    show('bad', 'ERROR', null, data.message || 'The scan could not be checked. Try again.');
                    return;
                }
                var verdict = verdicts[data.result] || ['bad', 'STOP'];
                var who = data.attendee
                    ? data.attendee.name + (data.attendee.company ? ' · ' + data.attendee.company : '')
                    : null;
                show(verdict[0], verdict[1], who, data.message);
                addRecent(data);
                document.getElementById('count-in').textContent = data.event.checked_in;
                document.getElementById('count-left').textContent = data.event.remaining;
                if (navigator.vibrate && (!navigator.userActivation || navigator.userActivation.hasBeenActive)) {
                    navigator.vibrate(data.authorized ? 80 : [120, 60, 120]);
                }
            })
            .catch(function () {
                show('bad', 'OFFLINE', null, 'No connection to the server. Check the network and scan again.');
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
                        item.appendChild(el('span', 'badge badge-duplicate', 'In since ' + clock(new Date(person.checked_in_at))));
                    } else {
                        var button = el('button', 'btn btn-sm btn-primary', 'Check in');
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
        reader.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, check)
            .then(function () { note.textContent = 'Camera on. Hold the QR code inside the square.'; })
            .catch(function () {
                note.textContent = 'Camera unavailable. Allow camera access, or open this page over HTTPS or on localhost. You can still search by name or ticket code below.';
            });
    } else {
        note.textContent = 'The camera scanner could not be loaded (no internet). Search by name or ticket code below instead.';
    }
})();
