/* aGo Harden Admin JS */
(function () {
    'use strict';

    var $ = document.querySelector.bind(document);
    var $$ = document.querySelectorAll.bind(document);

    var saveBtn = $('#ago-save-btn');
    var saveStatus = $('#ago-save-status');

    if (!saveBtn) return;

    /* ─── Score gauge helpers ─── */
    var CIRCUMFERENCE = 326.73;

    var WEIGHTS = {
        custom_login_url: 15,
        limit_login_attempts: 15,
        disable_file_edit: 10,
        hide_wp_version: 5,
        block_author_enum: 5,
        security_headers: 15,
        disable_xmlrpc: 10,
        block_php_uploads: 10,
        disable_directory_listing: 5,
        force_logout_hours: 5,
        hide_login_errors: 5
    };

    function calcScore() {
        var score = 0;
        Object.keys(WEIGHTS).forEach(function (key) {
            var el = document.querySelector('[name="' + key + '"]');
            if (!el) return;
            if (el.type === 'checkbox') {
                if (el.checked) score += WEIGHTS[key];
            } else if (el.type === 'text') {
                if (el.value.trim() !== '') score += WEIGHTS[key];
            } else if (el.type === 'number') {
                if (parseInt(el.value, 10) > 0) score += WEIGHTS[key];
            }
        });
        return Math.min(100, score);
    }

    function scoreColor(s) {
        if (s >= 90) return '#00a32a';
        if (s >= 70) return '#72aee6';
        if (s >= 40) return '#dba617';
        return '#d63638';
    }

    function scoreLabel(s) {
        if (s >= 90) return agoHarden.i18n ? agoHarden.i18n.excellent : 'Excellent';
        if (s >= 70) return agoHarden.i18n ? agoHarden.i18n.good : 'Good';
        if (s >= 40) return agoHarden.i18n ? agoHarden.i18n.fair : 'Fair';
        return agoHarden.i18n ? agoHarden.i18n.weak : 'Weak';
    }

    function updateGauge(score) {
        var circle = $('#ago-score-circle');
        var number = $('#ago-score-number');
        var label  = $('#ago-score-label');
        var color  = scoreColor(score);

        if (circle) {
            circle.setAttribute('stroke-dashoffset', CIRCUMFERENCE - (CIRCUMFERENCE * score / 100));
            circle.setAttribute('stroke', color);
        }
        if (number) {
            number.textContent = score;
            number.setAttribute('fill', color);
        }
        if (label) {
            label.textContent = scoreLabel(score);
            label.style.color = color;
        }
    }

    /* ─── Live score update on toggle change ─── */
    $$('.ago-toggle-header input[type="checkbox"], .ago-text-input, .ago-number-input').forEach(function (el) {
        var evt = (el.type === 'checkbox') ? 'change' : 'input';
        el.addEventListener(evt, function () {
            updateGauge(calcScore());
        });
    });

    /* ─── Save settings ─── */
    saveBtn.addEventListener('click', function () {
        var data = {};

        Object.keys(WEIGHTS).forEach(function (key) {
            var el = document.querySelector('[name="' + key + '"]');
            if (!el) return;
            if (el.type === 'checkbox') {
                data[key] = el.checked;
            } else if (el.type === 'number') {
                data[key] = parseInt(el.value, 10) || 0;
            } else {
                data[key] = el.value.trim();
            }
        });

        saveBtn.classList.add('saving');
        saveBtn.textContent = agoHarden.i18n ? agoHarden.i18n.saving : 'Saving…';
        saveStatus.textContent = '';
        saveStatus.className = 'ago-save-status';

        fetch(agoHarden.restUrl + '/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': agoHarden.nonce
            },
            body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (resp.saved) {
                saveStatus.className = 'ago-save-status ok';
                saveStatus.textContent = agoHarden.i18n ? agoHarden.i18n.saved : 'Saved!';
                if (resp.settings && typeof resp.settings.security_score !== 'undefined') {
                    updateGauge(resp.settings.security_score);
                }
            } else {
                saveStatus.className = 'ago-save-status fail';
                saveStatus.textContent = agoHarden.i18n ? agoHarden.i18n.error : 'Error saving.';
            }
        })
        .catch(function (err) {
            saveStatus.className = 'ago-save-status fail';
            saveStatus.textContent = 'Error: ' + err.message;
        })
        .finally(function () {
            saveBtn.classList.remove('saving');
            saveBtn.textContent = agoHarden.i18n ? agoHarden.i18n.save : 'Save Settings';
            // Auto-clear status after 3s
            setTimeout(function () {
                saveStatus.textContent = '';
                saveStatus.className = 'ago-save-status';
            }, 3000);
        });
    });
})();
