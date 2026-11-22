/*
 * Comportements propres à l'accueil d'ETTC (maquette du 2026-11-17).
 *  - Carrousel des témoignages ([data-ettc-carousel]) : flèches, points, défilement automatique toutes les 7 s,
 *    en pause au survol ou au focus et coupé si le visiteur réduit les animations. Sans JavaScript, le premier
 *    témoignage reste affiché.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Montants en un clic (paiements/_amount-chips) : « Prochaine échéance », « Tout le reste » → champ du montant.
    if (!window.__fillAmountReady) {
        window.__fillAmountReady = true;
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-fill-amount]');
            if (!button) { return; }
            var input = document.getElementById(button.getAttribute('data-target'));
            if (!input) { return; }
            input.value = button.getAttribute('data-fill-amount');
            input.dataset.touched = '1';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });
    }

    /*
     * Menu « Formations » : ouvert au survol à la souris (ordinateur, menu déplié à partir de 992 px), avec un court
     * délai à la sortie pour glisser du bouton vers la liste ; au doigt et au clavier, le clic l'ouvre comme avant.
     * Bootstrap 5.0 : Dropdown.getInstance(el) || new Dropdown(el).
     */
    var desktop = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 992px)');
    document.querySelectorAll('.ettc-menu .dropdown').forEach(function (item) {
        var toggle = item.querySelector('[data-bs-toggle="dropdown"]');
        if (!toggle || !window.bootstrap || !bootstrap.Dropdown) { return; }
        var dropdown = function () { return bootstrap.Dropdown.getInstance(toggle) || new bootstrap.Dropdown(toggle); };
        var closeTimer = null;
        item.addEventListener('mouseenter', function () {
            if (!desktop.matches) { return; }
            clearTimeout(closeTimer);
            if (!item.querySelector('.dropdown-menu.show')) { dropdown().show(); }
        });
        item.addEventListener('mouseleave', function () {
            if (!desktop.matches) { return; }
            closeTimer = setTimeout(function () { dropdown().hide(); }, 220);
        });
        // À la souris, le menu est déjà ouvert par le survol : un clic sur « Formations » le laisse ouvert au lieu de
        // le refermer. Intercepté sur window en phase de capture : Bootstrap 5.0 écoute les clics délégués sur
        // document, lui aussi en capture, et les traiterait avant tout écouteur placé dans la page.
        window.addEventListener('click', function (e) {
            if (desktop.matches && toggle.contains(e.target) && item.querySelector('.dropdown-menu.show')) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    });

    /*
     * Assistant d'inscription ([data-ettc-wizard], cahier §3.5) : une étape à la fois (glissement), contrôle des champs
     * obligatoires avant de passer à la suivante, tarif calculé en direct (prix du mode choisi × durée, ou paiement
     * unique), récapitulatif avant confirmation. Si le serveur signale une erreur (site.js l'affiche sous le champ),
     * l'assistant revient sur l'étape concernée. Sans JavaScript, toutes les étapes sont visibles.
     */
    var money = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'; };
    document.querySelectorAll('[data-ettc-wizard]').forEach(function (form) {
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
        var marks = Array.prototype.slice.call(form.querySelectorAll('.ettc-wizard-steps li'));
        var nav = form.querySelector('[data-wizard-nav]');
        var prev = form.querySelector('[data-wizard-prev]');
        var next = form.querySelector('[data-wizard-next]');
        var live = form.querySelector('[data-price-live]');
        var recap = form.querySelector('[data-recap]');
        var current = 0;
        if (steps.length < 2) { return; }
        form.classList.add('is-wizard');
        nav.hidden = false;

        var checked = function (name) { return form.querySelector('input[name="' + name + '"]:checked'); };
        function quote() {
            var formule = checked('formule_id');
            var mode = checked('mode');
            if (!formule || !mode) { return null; }
            var unit = formule.getAttribute('data-price-' + mode.value);
            if (!unit) { return { missing: true, formule: formule, mode: mode }; }
            unit = parseInt(unit, 10);
            var months = parseInt(formule.getAttribute('data-months'), 10);
            var monthly = formule.getAttribute('data-billing') === 'mois';
            return { formule: formule, mode: mode, unit: unit, months: months, monthly: monthly, total: monthly ? unit * months : unit };
        }
        // Tarif détaillé calculé par le serveur (frais de formation, promotion, code promo, frais d'inscription) : le
        // même calcul que l'enregistrement. En attendant la réponse (ou sans réseau), calcul local sans promotion.
        var tarif = null;
        var tarifUrl = form.getAttribute('data-tarif-url');
        var promoInput = form.querySelector('[data-promo-input]');
        var promoFeedback = form.querySelector('[data-promo-feedback]');
        var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };
        function fetchTarif(showCode) {
            var q = quote();
            if (!tarifUrl || !q || q.missing) { tarif = null; renderPrice(); return; }
            var params = 'formule_id=' + encodeURIComponent(q.formule.value) + '&mode=' + encodeURIComponent(q.mode.value)
                + '&promo_code=' + encodeURIComponent(promoInput ? promoInput.value : '');
            fetch(tarifUrl + '?' + params, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    tarif = data && data.ok ? data : null;
                    if (promoFeedback && tarif && (showCode || tarif.code_error)) {
                        promoFeedback.innerHTML = tarif.code_error
                            ? '<span class="text-danger">' + esc(tarif.code_error) + '</span>'
                            : (tarif.code_ok ? '<span class="text-success"><i class="bx bx-check-circle"></i> Code appliqué : ' + esc(tarif.promo.name) + ' (' + esc(tarif.promo.badge) + ')</span>'
                                : (tarif.promo ? '<span class="text-muted">La promotion en cours est plus avantageuse : elle est gardée.</span>' : ''));
                    }
                    renderPrice();
                    if (current === steps.length - 1) { renderRecap(); }
                })
                .catch(function () { tarif = null; renderPrice(); });
        }
        function renderPrice() {
            var q = quote();
            if (!q) { live.hidden = true; return; }
            live.hidden = false;
            live.classList.toggle('is-warning', !!q.missing);
            if (q.missing) {
                live.innerHTML = '<i class="bx bx-error-circle" aria-hidden="true"></i> Cette formule n\'est pas proposée ' + q.mode.getAttribute('data-label').toLowerCase() + ' : choisissez l\'autre mode ou une autre formule.';
            } else if (tarif) {
                live.innerHTML = '<table class="ettc-price-lines">' + tarif.lines.map(function (l) {
                    return '<tr class="' + esc(l[2]) + '"><th scope="row">' + esc(l[0]) + '</th><td>' + esc(l[1]) + '</td></tr>';
                }).join('') + '</table><p class="ettc-price-first">Premier paiement : <b>' + esc(tarif.first_label) + '</b></p>';
            } else {
                live.innerHTML = '<span>' + money(q.unit) + (q.monthly ? ' / mois × ' + q.months + ' mois' : ' (paiement unique)') + '</span><strong>Total : ' + money(q.total) + '</strong>';
            }
            live.classList.remove('is-pop'); void live.offsetWidth; live.classList.add('is-pop');
            renderOnlinePay();
        }
        // « Payer en ligne » : bloc des numéros et QR ouvert, montant du premier paiement à jour.
        function renderOnlinePay() {
            var block = form.querySelector('[data-online-pay]');
            if (!block) { return; }
            var pay = checked('payment_choice');
            block.hidden = !(pay && pay.value === 'en_ligne');
            var q = quote();
            var first = tarif ? tarif.first : (q && !q.missing ? (q.monthly ? q.unit : q.total) : null);
            var amount = block.querySelector('[data-online-amount]');
            if (amount) { amount.textContent = tarif ? tarif.first_label : (first ? money(first) : '—'); }
            // Montant envoyé prérempli (premier paiement), sauf si l'apprenant l'a déjà changé.
            var input = block.querySelector('[data-online-amount-input]');
            if (input && first && !input.dataset.touched) { input.value = first; }
            if (input && !input.dataset.watch) { input.dataset.watch = '1'; input.addEventListener('input', function () { input.dataset.touched = '1'; }); }
            // Champs du paiement déclaré ignorés quand on choisit « Payer au centre ».
            block.querySelectorAll('input, select').forEach(function (field) { field.disabled = block.hidden; });
        }
        function renderRecap() {
            renderOnlinePay();
            var q = quote();
            var niveau = checked('niveau_id');
            var pay = checked('payment_choice');
            var rows = [
                ['Niveau', niveau ? niveau.getAttribute('data-label') : '—'],
                ['Formule', q ? q.formule.getAttribute('data-label') : '—'],
                ['Mode', q ? q.mode.getAttribute('data-label') : '—'],
            ];
            if (tarif) {
                tarif.lines.forEach(function (l) { rows.push([l[0], l[1]]); });
                rows.push(['Premier paiement', tarif.first_label]);
            } else {
                rows.push(['Tarif', q && !q.missing ? money(q.unit) + (q.monthly ? ' / mois' : ' (paiement unique)') : '—']);
                rows.push(['Total', q && !q.missing ? money(q.total) : '—']);
            }
            rows.push(['Paiement', pay ? pay.getAttribute('data-label') : '—']);
            rows.push(['Nom', (form.querySelector('[name="name"]') || {}).value || '—']);
            var dl = recap.querySelector('dl');
            dl.innerHTML = '';
            rows.forEach(function (row) {
                var dt = document.createElement('dt'); dt.textContent = row[0];
                var dd = document.createElement('dd'); dd.textContent = row[1];
                dl.appendChild(dt); dl.appendChild(dd);
            });
            recap.hidden = false;
        }
        function valid(step) {
            var ok = true;
            var groups = {};
            step.querySelectorAll('input[required][type="radio"]').forEach(function (r) { groups[r.name] = true; });
            Object.keys(groups).forEach(function (name) {
                if (ok && !checked(name)) {
                    ok = false;
                    var first = step.querySelector('input[name="' + name + '"]');
                    first.focus();
                    if (window.SiteUI) { SiteUI.toast('Faites un choix pour continuer.', 'error'); }
                }
            });
            if (!ok) { return false; }
            var fields = step.querySelectorAll('input:not([type="radio"]):not([type="hidden"]), select, textarea');
            for (var i = 0; i < fields.length; i++) {
                if (!fields[i].checkValidity()) { fields[i].reportValidity(); return false; }
            }
            var q = quote();
            if (step.contains(form.querySelector('input[name="mode"]')) && q && q.missing) {
                if (window.SiteUI) { SiteUI.toast('Cette formule n\'est pas proposée dans ce mode.', 'error'); }
                return false;
            }
            return true;
        }
        function show(index) {
            current = Math.max(0, Math.min(steps.length - 1, index));
            steps.forEach(function (s, k) {
                s.classList.toggle('is-active', k === current);
                s.hidden = k !== current;
            });
            marks.forEach(function (m, k) {
                m.classList.toggle('is-current', k === current);
                m.classList.toggle('is-done', k < current);
            });
            prev.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            if (current === steps.length - 1) { renderRecap(); }
            var top = form.getBoundingClientRect().top + window.scrollY - 140;
            if (window.scrollY > top) { window.scrollTo({ top: top, behavior: reduceMotion ? 'auto' : 'smooth' }); }
        }

        next.addEventListener('click', function () { if (valid(steps[current])) { show(current + 1); } });
        prev.addEventListener('click', function () { show(current - 1); });
        marks.forEach(function (m, k) { m.addEventListener('click', function () { if (k < current) { show(k); } }); });
        form.addEventListener('change', function (e) {
            if (e.target.name === 'formule_id' || e.target.name === 'mode') { renderPrice(); fetchTarif(false); }
            if (e.target.name === 'payment_choice') { renderOnlinePay(); }
            // Paiement présélectionné d'après le mode : présentiel → au centre, en ligne → mobile money (modifiable).
            if (e.target.name === 'mode') {
                var pay = form.querySelector('input[name="payment_choice"][data-payment-for="' + e.target.value + '"]');
                if (pay) { pay.checked = true; }
            }
            if (current === steps.length - 1) { renderRecap(); }
        });
        // Erreur renvoyée par le serveur : aller à l'étape du premier champ signalé.
        new MutationObserver(function () {
            var bad = form.querySelector('.is-invalid');
            if (!bad) { return; }
            var step = bad.closest('[data-step]');
            var index = steps.indexOf(step);
            if (index !== -1 && index !== current) { show(index); }
        }).observe(form, { subtree: true, attributes: true, attributeFilter: ['class'] });

        // Code promo : bouton « Appliquer » ou touche Entrée (sans envoyer le formulaire).
        var promoApply = form.querySelector('[data-promo-apply]');
        if (promoApply) { promoApply.addEventListener('click', function () { fetchTarif(true); }); }
        if (promoInput) {
            promoInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); fetchTarif(true); } });
        }

        renderPrice();
        fetchTarif(!!(promoInput && promoInput.value));
        show(0);
    });

    /*
     * Promotions : diaporama ([data-promo-slider] : flèches, points, barre de progression, toutes les 6 s, glissement au
     * doigt, pause au survol ou au focus) et bandeau ([data-promo-ticker] : fermé pour la visite, sessionStorage).
     */
    document.querySelectorAll('[data-promo-slider]').forEach(function (root) {
        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-promo-slide]'));
        if (slides.length < 2) { return; }
        var index = 0, timer = null;
        var progress = document.createElement('span'); progress.className = 'promo-progress'; root.appendChild(progress);
        var dots = document.createElement('div'); dots.className = 'promo-dots'; root.appendChild(dots);
        slides.forEach(function (s, k) {
            var d = document.createElement('button'); d.type = 'button'; d.setAttribute('aria-label', 'Promotion ' + (k + 1));
            d.addEventListener('click', function () { show(k); start(); });
            dots.appendChild(d);
        });
        [['prev', 'bx-chevron-left', 'Promotion précédente', -1], ['next', 'bx-chevron-right', 'Promotion suivante', 1]].forEach(function (a) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'promo-arrow ' + a[0]; b.setAttribute('aria-label', a[2]);
            b.innerHTML = '<i class="bx ' + a[1] + '" aria-hidden="true"></i>';
            b.addEventListener('click', function () { show(index + a[3]); start(); });
            root.appendChild(b);
        });
        function show(k) {
            index = (k + slides.length) % slides.length;
            slides.forEach(function (s, j) { s.hidden = j !== index; });
            Array.prototype.forEach.call(dots.children, function (d, j) { d.classList.toggle('is-active', j === index); d.setAttribute('aria-current', j === index ? 'true' : 'false'); });
        }
        function start() {
            stop();
            if (reduceMotion) { return; }
            progress.classList.remove('is-running'); void progress.offsetWidth; progress.classList.add('is-running');
            timer = setInterval(function () { show(index + 1); progress.classList.remove('is-running'); void progress.offsetWidth; progress.classList.add('is-running'); }, 6000);
        }
        function stop() { clearInterval(timer); timer = null; progress.classList.remove('is-running'); }
        var x0 = null;
        root.addEventListener('pointerdown', function (e) { x0 = e.clientX; });
        root.addEventListener('pointerup', function (e) {
            if (x0 !== null && Math.abs(e.clientX - x0) > 50) { show(index + (e.clientX < x0 ? 1 : -1)); start(); }
            x0 = null;
        });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);
        show(0);
        start();
    });
    document.querySelectorAll('[data-promo-ticker]').forEach(function (bar) {
        try { if (sessionStorage.getItem('promoTickerClosed') === '1') { bar.hidden = true; return; } } catch (e) {}
        var close = bar.querySelector('[data-promo-ticker-close]');
        if (close) {
            close.addEventListener('click', function () {
                bar.hidden = true;
                try { sessionStorage.setItem('promoTickerClosed', '1'); } catch (e) {}
            });
        }
    });

    document.querySelectorAll('[data-ettc-carousel]').forEach(function (root) {
        var slides = Array.prototype.slice.call(root.querySelectorAll('.ettc-testimonial'));
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-dot]'));
        if (slides.length < 2) {
            root.querySelectorAll('[data-prev], [data-next]').forEach(function (b) { b.hidden = true; });
            return;
        }
        var index = 0;
        var timer = null;

        function show(i) {
            index = (i + slides.length) % slides.length;
            slides.forEach(function (s, k) {
                s.hidden = k !== index;
                s.classList.toggle('is-active', k === index);
            });
            dots.forEach(function (d, k) {
                d.classList.toggle('is-active', k === index);
                d.setAttribute('aria-current', k === index ? 'true' : 'false');
            });
        }
        function start() {
            if (reduceMotion) return;
            stop();
            timer = setInterval(function () { show(index + 1); }, 7000);
        }
        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        root.querySelector('[data-prev]').addEventListener('click', function () { show(index - 1); start(); });
        root.querySelector('[data-next]').addEventListener('click', function () { show(index + 1); start(); });
        dots.forEach(function (d) {
            d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-dot'), 10)); start(); });
        });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);
        start();
    });
})();
