/*
 * Test de niveau en ligne ([data-online-test]) :
 *  - une question à la fois (Précédente / Suivante, progression), « Envoyer » à la dernière ;
 *  - compte à rebours jusqu'à data-deadline : à zéro, les réponses partent toutes seules ;
 *  - expression orale : enregistrement au micro (MediaRecorder), réécoute, nouvel essai ; le fichier est glissé dans le
 *    champ du formulaire (sinon le candidat dépose un enregistrement fait avec son téléphone) ;
 *  - question laissée sans réponse : confirmation avant l'envoi.
 * Sans JavaScript, toutes les questions s'affichent et le formulaire s'envoie normalement.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-online-test]');
    if (!form) { return; }
    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-q]'));
    var prev = form.querySelector('[data-prev]');
    var next = form.querySelector('[data-next]');
    var submit = form.querySelector('[data-submit]');
    var progress = form.querySelector('[data-progress]');
    var timer = form.querySelector('[data-timer]');
    var current = 0;
    var sending = false;

    function show(i) {
        current = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach(function (s, k) { s.hidden = k !== current; });
        prev.hidden = current === 0;
        next.hidden = current === steps.length - 1;
        submit.hidden = current !== steps.length - 1;
        progress.textContent = 'Question ' + (current + 1) + ' / ' + steps.length;
        window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 120 });
    }
    if (steps.length > 1) {
        form.classList.add('is-stepped');
        prev.addEventListener('click', function () { show(current - 1); });
        next.addEventListener('click', function () { show(current + 1); });
        show(0);
    }

    // Compte à rebours ; à zéro, envoi automatique (le serveur garde aussi la limite).
    var deadline = new Date(form.getAttribute('data-deadline')).getTime();
    function tick() {
        var left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        var m = Math.floor(left / 60), s = left % 60;
        timer.innerHTML = '<i class="bx bx-time-five" aria-hidden="true"></i> ' + m + ':' + (s < 10 ? '0' : '') + s;
        timer.classList.toggle('is-low', left <= 120);
        if (left === 0 && !sending) { sending = true; form.submit(); }
    }
    tick();
    setInterval(tick, 1000);

    form.addEventListener('submit', function (e) {
        if (sending) { return; }
        var empty = steps.filter(function (s) {
            return s.querySelector('input[type="radio"]') && !s.querySelector('input[type="radio"]:checked');
        }).length;
        if (empty && !window.confirm(empty + ' question(s) sans réponse. Envoyer quand même ?')) { e.preventDefault(); return; }
        sending = true;
    });

    // Enregistrement de l'expression orale.
    form.querySelectorAll('[data-recorder]').forEach(function (box) {
        var start = box.querySelector('[data-rec-start]');
        var stop = box.querySelector('[data-rec-stop]');
        var status = box.querySelector('[data-rec-status]');
        var audio = box.querySelector('[data-rec-audio]');
        var input = box.querySelector('[data-rec-input]');
        if (!window.MediaRecorder || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.DataTransfer) { return; }
        start.hidden = false;
        var recorder = null, chunks = [], started = 0, clock = null;
        start.addEventListener('click', function () {
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                chunks = [];
                recorder = new MediaRecorder(stream);
                recorder.ondataavailable = function (ev) { if (ev.data.size) { chunks.push(ev.data); } };
                recorder.onstop = function () {
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    clearInterval(clock);
                    var type = recorder.mimeType || 'audio/webm';
                    var blob = new Blob(chunks, { type: type });
                    var file = new File([blob], 'oral.' + (type.indexOf('mp4') !== -1 ? 'm4a' : (type.indexOf('ogg') !== -1 ? 'ogg' : 'webm')), { type: type });
                    var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
                    audio.src = URL.createObjectURL(blob); audio.hidden = false;
                    status.textContent = 'Enregistré (' + Math.round((Date.now() - started) / 1000) + ' s). Réécoutez, ou enregistrez de nouveau.';
                    start.hidden = false; stop.hidden = true;
                    start.innerHTML = '<i class="bx bx-revision" aria-hidden="true"></i> Recommencer';
                };
                recorder.start();
                started = Date.now();
                start.hidden = true; stop.hidden = false;
                clock = setInterval(function () {
                    var sec = Math.round((Date.now() - started) / 1000);
                    status.textContent = '● Enregistrement… ' + sec + ' s';
                    if (sec >= 180) { recorder.stop(); }
                }, 500);
            }).catch(function () {
                status.textContent = 'Micro inaccessible : autorisez-le dans le navigateur, ou déposez un enregistrement ci-dessous.';
            });
        });
        stop.addEventListener('click', function () { if (recorder && recorder.state === 'recording') { recorder.stop(); } });
    });
})();
