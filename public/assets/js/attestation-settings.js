/*
 * Réglages de l'attestation (attestations/edit) :
 *  - signature tracée à l'écran (canvas, souris / doigt / stylet, trait lissé), « Effacer », « Voir sur l'attestation » ;
 *  - image déposée (signature ou sceau) : traitée par le serveur, montrée avant / après et SUR l'attestation ;
 *  - sceau : couleur et nettoyage renforcé refont l'aperçu aussitôt ;
 * l'image traitée reste en attente côté serveur jusqu'à « Enregistrer ».
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-attestation-settings]');
    if (!root) { return; }
    var url = root.getAttribute('data-preview-url');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var zoom = root.querySelector('[data-preview-zoom]');
    var full = root.querySelector('[data-preview-full]');
    var note = root.querySelector('[data-preview-note]');

    function preview(card, body, originalSrc) {
        var pending = card.querySelector('[data-pending]');
        var error = card.querySelector('[data-error]');
        error.hidden = true;
        body.append('kind', card.getAttribute('data-asset'));
        note.textContent = 'Traitement en cours…';
        fetch(url, { method: 'POST', body: body, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (!res.ok) {
                    var errors = res.data.errors || {};
                    var first = Object.keys(errors)[0];
                    error.textContent = first ? errors[first][0] : (res.data.message || 'Traitement impossible.');
                    error.hidden = false;
                    note.textContent = 'Attestation avec les réglages enregistrés.';
                    return;
                }
                card.querySelector('[data-original]').src = originalSrc;
                card.querySelector('[data-processed]').src = res.data.processed;
                pending.hidden = false;
                zoom.src = res.data.zoom;
                full.src = res.data.full;
                note.textContent = 'Aperçu avec ' + (card.getAttribute('data-asset') === 'stamp' ? 'le nouveau sceau' : 'la nouvelle signature') + ' (pas encore enregistré).';
            })
            .catch(function () { error.textContent = 'Connexion impossible : réessayez.'; error.hidden = false; });
    }

    // Image déposée (signature ou sceau) ; pour le sceau, couleur et nettoyage refont l'aperçu.
    root.querySelectorAll('[data-asset]').forEach(function (card) {
        var input = card.querySelector('[data-file]');
        var ink = card.querySelector('[data-ink]');
        var strong = card.querySelector('[data-strong]');
        function send() {
            if (!input || !input.files.length) { return; }
            var body = new FormData();
            body.append('file', input.files[0]);
            if (ink) { body.append('ink', ink.value); }
            if (strong && strong.checked) { body.append('strong', '1'); }
            preview(card, body, URL.createObjectURL(input.files[0]));
        }
        if (input) { input.addEventListener('change', send); }
        if (ink) { ink.addEventListener('change', send); }
        if (strong) { strong.addEventListener('change', send); }
    });

    // Signature tracée à l'écran.
    var pad = root.querySelector('[data-pad]');
    if (pad) {
        var ctx = pad.getContext('2d');
        var drawing = false, last = null, dirty = false;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1b3fa0';
        function point(e) {
            var rect = pad.getBoundingClientRect();
            return { x: (e.clientX - rect.left) * pad.width / rect.width, y: (e.clientY - rect.top) * pad.height / rect.height, p: e.pressure || 0.5 };
        }
        pad.addEventListener('pointerdown', function (e) { drawing = true; last = point(e); pad.setPointerCapture(e.pointerId); e.preventDefault(); });
        pad.addEventListener('pointermove', function (e) {
            if (!drawing) { return; }
            var p = point(e);
            ctx.lineWidth = 3 + p.p * 3;
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.quadraticCurveTo(last.x, last.y, (last.x + p.x) / 2, (last.y + p.y) / 2);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            last = p;
            dirty = true;
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (type) { pad.addEventListener(type, function () { drawing = false; }); });
        root.querySelector('[data-pad-clear]').addEventListener('click', function () { ctx.clearRect(0, 0, pad.width, pad.height); dirty = false; });
        root.querySelector('[data-pad-use]').addEventListener('click', function () {
            var card = pad.closest('[data-asset]');
            if (!dirty) {
                var error = card.querySelector('[data-error]');
                error.textContent = 'Signez d\'abord dans le cadre.';
                error.hidden = false;
                return;
            }
            var data = pad.toDataURL('image/png');
            var body = new FormData();
            body.append('drawing', data);
            preview(card, body, data);
        });
    }

    // Retour au modèle : confirmation.
    root.querySelectorAll('form[data-confirm-reset]').forEach(function (form) {
        form.addEventListener('submit', function (e) { if (!window.confirm(form.getAttribute('data-confirm-reset'))) { e.preventDefault(); } });
    });
})();
