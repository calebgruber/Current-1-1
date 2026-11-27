/* StageTrack front-end helpers: loader, theme, scan sounds, scanning, confirmations, previews. */
(function () {
    'use strict';

    var body = document.body;
    var base = (body.getAttribute('data-base') || '/').replace(/\/$/, '');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var loader = document.getElementById('st-loader');
    var loaderTimer = null;

    /* ---------- Loader ---------- */
    function showLoader() {
        if (!loader) { return; }
        clearTimeout(loaderTimer);
        loaderTimer = setTimeout(function () { loader.classList.add('show'); }, 120);
    }
    function hideLoader() {
        clearTimeout(loaderTimer);
        if (loader) { loader.classList.remove('show'); }
    }
    window.StageTrack = { showLoader: showLoader, hideLoader: hideLoader };
    window.addEventListener('pageshow', hideLoader);

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-no-loader')) { return; }
        if (a.origin && a.origin !== window.location.origin) { return; }
        showLoader();
    });

    /* Clickable table rows */
    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row || e.target.closest('a, button, input, select, textarea, form, label')) { return; }
        showLoader();
        window.location.href = row.getAttribute('data-href');
    });

    /* Confirm + loader on submit */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
        if (form.hasAttribute('data-ajax')) { return; }
        var btn = form.querySelector('button[type="submit"], button:not([type])');
        if (btn) { setTimeout(function () { btn.disabled = true; }, 0); }
        showLoader();
    });

    /* ---------- Theme ---------- */
    function applyTheme(pref) {
        var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    }
    document.querySelectorAll('[data-toggle-theme]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('st-theme', next);
            applyTheme(next);
            savePreference({ theme: next });
        });
    });

    /* ---------- Sounds ---------- */
    var audioCtx = null;
    function muted() { return body.getAttribute('data-muted') === '1'; }
    function tone(freq, start, duration, type) {
        var osc = audioCtx.createOscillator();
        var gain = audioCtx.createGain();
        osc.type = type || 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, audioCtx.currentTime + start);
        gain.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + start + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + start + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(audioCtx.currentTime + start);
        osc.stop(audioCtx.currentTime + start + duration + 0.02);
    }
    function beep(ok) {
        if (muted()) { return; }
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (ok) { tone(880, 0, 0.09); tone(1320, 0.09, 0.12); }
            else { tone(220, 0, 0.18, 'square'); tone(180, 0.2, 0.25, 'square'); }
        } catch (err) { /* audio not available */ }
    }
    window.StageTrack.beep = beep;
    document.querySelectorAll('[data-toggle-sound]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nowMuted = !muted();
            body.setAttribute('data-muted', nowMuted ? '1' : '0');
            savePreference({ sound_muted: nowMuted ? '1' : '0' });
            if (!nowMuted) { beep(true); }
        });
    });
    /* Play a sound for the result of a non-AJAX scan form (set by the server) */
    var scanResult = document.querySelector('[data-scan-sound]');
    if (scanResult) { beep(scanResult.getAttribute('data-scan-sound') === 'ok'); }

    function savePreference(data) {
        if (!csrf) { return; }
        var form = new FormData();
        form.append('_token', csrf);
        Object.keys(data).forEach(function (k) { form.append(k, data[k]); });
        fetch(base + '/account/preferences', { method: 'POST', body: form, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).catch(function () {});
    }

    /* ---------- Mobile menu ---------- */
    document.querySelectorAll('[data-toggle-menu]').forEach(function (btn) {
        btn.addEventListener('click', function () { body.classList.toggle('menu-open'); });
    });

    /* ---------- Keyboard: "/" focuses search, scanners type fast and end with Enter ---------- */
    var globalScan = document.querySelector('[data-global-scan]');
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && globalScan && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
            e.preventDefault();
            globalScan.focus();
            globalScan.select();
        }
    });

    /* Global search box: exact barcode jumps straight to the record */
    document.querySelectorAll('[data-scan-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var input = form.querySelector('input[name="q"]');
            var code = (input.value || '').trim();
            if (!code) { e.preventDefault(); hideLoader(); return; }
            e.preventDefault();
            lookup(code).then(function (res) {
                if (res && res.found) {
                    beep(true);
                    window.location.href = res.url;
                } else {
                    window.location.href = base + '/search?q=' + encodeURIComponent(code);
                }
            }).catch(function () {
                window.location.href = base + '/search?q=' + encodeURIComponent(code);
            });
        });
    });

    function lookup(code) {
        return fetch(base + '/api/scan?code=' + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }
    window.StageTrack.lookup = lookup;

    /* ---------- Scan forms that post via fetch (prep/return/station) ---------- */
    document.querySelectorAll('form[data-ajax-scan]').forEach(function (form) {
        var input = form.querySelector('[data-scan-input]');
        var out = document.querySelector(form.getAttribute('data-ajax-scan'));
        form.setAttribute('data-ajax', '1');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!input.value.trim()) { return; }
            var data = new FormData(form);
            input.value = '';
            fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    beep(!!res.ok);
                    showResult(out, res.ok, res.message);
                    if (res.ok && form.hasAttribute('data-reload')) {
                        refreshRegion(form.getAttribute('data-reload'));
                    }
                    if (res.container_id) {
                        var select = form.querySelector('select[name="container_id"]');
                        if (select) { ensureOption(select, res.container_id, res.container_name); }
                    }
                })
                .catch(function () { beep(false); showResult(out, false, 'Could not reach the server. Try again.'); })
                .finally(function () { input.focus(); });
        });
    });

    function ensureOption(select, id, name) {
        var exists = Array.prototype.some.call(select.options, function (o) { return o.value === String(id); });
        if (!exists && name) {
            var opt = document.createElement('option');
            opt.value = id; opt.textContent = name;
            select.appendChild(opt);
        }
        select.value = String(id);
    }

    function showResult(el, ok, message) {
        if (!el) { return; }
        var row = document.createElement('div');
        row.className = 'st-scan-result ' + (ok ? 'ok st-flash-ok' : 'fail st-flash-fail');
        var icon = document.createElement('i');
        icon.className = 'ti ' + (ok ? 'ti-circle-check' : 'ti-alert-triangle');
        var text = document.createElement('div');
        text.textContent = message;
        row.appendChild(icon); row.appendChild(text);
        if (el.hasAttribute('data-log')) {
            el.insertBefore(row, el.firstChild);
            while (el.children.length > 25) { el.removeChild(el.lastChild); }
        } else {
            el.innerHTML = '';
            el.appendChild(row);
        }
    }
    window.StageTrack.showResult = showResult;

    /* Re-fetch the current page and swap a region (keeps focus in the scan box) */
    function refreshRegion(selector) {
        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                document.querySelectorAll(selector).forEach(function (target, i) {
                    var fresh = doc.querySelectorAll(selector)[i];
                    if (fresh) { target.innerHTML = fresh.innerHTML; }
                });
            });
    }

    /* ---------- Fullscreen ---------- */
    document.querySelectorAll('[data-fullscreen]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                (document.documentElement.requestFullscreen || function () {}).call(document.documentElement);
            } else if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        });
    });

    /* Keep scan inputs focused on the station */
    var stationInput = document.querySelector('[data-keep-focus]');
    if (stationInput) {
        stationInput.focus();
        document.addEventListener('click', function (e) {
            if (!e.target.closest('input, select, textarea, button, a, label')) { stationInput.focus(); }
        });
    }

    /* ---------- Template live preview ---------- */
    var editor = document.querySelector('[data-template-editor]');
    if (editor) {
        var frame = document.querySelector(editor.getAttribute('data-preview'));
        var showSelect = document.querySelector('[data-preview-show]');
        var status = document.querySelector('[data-preview-status]');
        var timer = null;
        var render = function () {
            var data = new FormData();
            data.append('_token', csrf);
            data.append('body', editor.value);
            if (showSelect) { data.append('show_id', showSelect.value); }
            if (status) { status.textContent = 'Updating…'; }
            fetch(editor.getAttribute('data-preview-url'), { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    frame.srcdoc = res.html || '';
                    if (status) {
                        status.textContent = res.errors && res.errors.length ? res.errors.join(' ') : 'Preview up to date';
                        status.className = res.errors && res.errors.length ? 'text-danger small' : 'text-success small';
                    }
                });
        };
        editor.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 400); });
        if (showSelect) { showSelect.addEventListener('change', render); }
        editor.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                var s = editor.selectionStart;
                editor.setRangeText('  ', s, editor.selectionEnd, 'end');
            }
        });
        render();
    }

    /* Print a sandboxed document frame */
    document.querySelectorAll('[data-print-frame]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.open(btn.getAttribute('data-print-frame'), '_blank', 'noopener');
        });
    });

    /* Auto-focus the first scan input on warehouse pages */
    var autoScan = document.querySelector('[data-scan-input][autofocus]');
    if (autoScan) { autoScan.focus(); }
})();
