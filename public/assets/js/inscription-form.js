/*
 * Fenêtre « Inscrire au centre » (form[data-inscription-form]) : rendue plus intelligente pour l'accueil.
 *  - téléphone tapé → recherche de la personne déjà connue (compte, test, inscription) : fiche remplie toute seule ;
 *  - total de la formation affiché en direct (formule × mode) ;
 *  - paiement déduit du mode : présentiel → au centre (paiement immédiat coché), en ligne → mobile money ;
 *  - total détaillé par le serveur (promotion, code promo, frais d'inscription) ; montant proposé = le premier
 *    paiement (frais d'inscription + premier mois) ; libellé du bouton adapté (« Inscrire et encaisser » / « Inscrire »).
 * Délégation sur document : les fenêtres sont déplacées dans <body> par crud-ajax.js.
 */
(function () {
    'use strict';

    var money = function (n) { return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'; };
    var timers = new WeakMap();

    function checked(form, name) { return form.querySelector('[name="' + name + '"]:checked'); }

    function quote(form) {
        var select = form.querySelector('[name="formule_id"]');
        var option = select ? select.options[select.selectedIndex] : null;
        var mode = checked(form, 'mode');
        if (!option || !mode) { return null; }
        var unit = parseInt(option.getAttribute('data-price-' + mode.value) || '', 10);
        if (!unit) { return { missing: true }; }
        var months = parseInt(option.getAttribute('data-months'), 10) || 1;
        var monthly = option.getAttribute('data-billing') === 'mois';
        return { unit: unit, months: months, monthly: monthly, total: monthly ? unit * months : unit, online: mode.value === 'en_ligne' };
    }

    function esc(text) { var d = document.createElement('div'); d.textContent = text == null ? '' : String(text); return d.innerHTML; }

    // Tarif détaillé calculé par le serveur (promotion, code promo, frais d'inscription) : affichage et montant du
    // premier paiement. Calcul local en attendant la réponse.
    function fetchTarif(form) {
        var url = form.getAttribute('data-tarif-url');
        var select = form.querySelector('[name="formule_id"]');
        var mode = checked(form, 'mode');
        if (!url || !select || !mode) { return; }
        var promo = form.querySelector('[data-promo-input]');
        var waive = form.querySelector('[data-waive-fee]');
        var params = 'formule_id=' + encodeURIComponent(select.value) + '&mode=' + encodeURIComponent(mode.value)
            + '&promo_code=' + encodeURIComponent(promo ? promo.value : '') + (waive && waive.checked ? '&sans_frais=1' : '');
        fetch(url + '?' + params, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var box = form.querySelector('[data-total]');
                if (!data || !data.ok || !box) { return; }
                form._tarif = data;
                box.classList.remove('alert-warning');
                box.innerHTML = '<table class="w-100 small">' + data.lines.map(function (l) {
                    var cls = l[2] === 'total' ? 'fw-bold fs-6 border-top' : (l[2] === 'promo' ? 'text-success' : '');
                    return '<tr class="' + cls + '"><td>' + esc(l[0]) + '</td><td class="text-end text-nowrap">' + esc(l[1]) + '</td></tr>';
                }).join('') + '</table><div class="mt-1">Premier paiement : <strong>' + esc(data.first_label) + '</strong></div>'
                    + (data.code_error ? '<div class="text-danger small mt-1"><i class="bx bx-error-circle"></i> ' + esc(data.code_error) + '</div>' : '')
                    + (data.code_ok ? '<div class="text-success small mt-1"><i class="bx bx-check-circle"></i> Code promo appliqué.</div>' : '')
                    + (data.suggest && !data.code_error && !data.code_ok ? '<div class="small mt-1"><i class="bx bxs-offer text-warning"></i> Promotion pour cette formule : <b>' + esc(data.suggest.badge) + '</b>, code <b>' + esc(data.suggest.code) + '</b> <button type="button" class="btn btn-warning btn-sm py-0" data-promo-suggest="' + esc(data.suggest.code) + '">Appliquer</button></div>' : '');
                var amount = form.querySelector('[data-pay-amount]');
                if (amount) {
                    amount.max = data.total;
                    if (!amount.dataset.touched) { amount.value = data.first; }
                }
            })
            .catch(function () {});
    }

    function refresh(form, modeChanged) {
        var q = quote(form);
        var box = form.querySelector('[data-total]');
        if (box) {
            box.classList.toggle('alert-warning', !!(q && q.missing));
            box.innerHTML = !q ? '' : q.missing
                ? '<i class="bx bx-error-circle"></i> Cette formule n\'est pas proposée dans ce mode.'
                : 'Total : <strong class="fs-5">' + money(q.total) + '</strong>' + (q.monthly ? ' <span class="text-muted">(' + money(q.unit) + ' par mois × ' + q.months + ' mois)</span>' : '');
        }
        if (!q || q.missing) { return; }
        fetchTarif(form);

        var choice = form.querySelector('input[name="payment_choice"]');
        if (choice) { choice.value = q.online ? 'en_ligne' : 'au_centre'; }

        var payNow = form.querySelector('[data-pay-now]');
        if (payNow && modeChanged) { payNow.checked = !q.online; }
        var paying = payNow ? payNow.checked : false;
        form.querySelectorAll('[data-pay-field]').forEach(function (field) {
            field.hidden = !paying;
            field.querySelectorAll('input, select').forEach(function (input) { input.disabled = !paying; });
        });
        var amount = form.querySelector('[data-pay-amount]');
        if (amount) {
            amount.max = q.total;
            if (!amount.dataset.touched) { amount.value = q.monthly ? q.unit : q.total; }
        }
        var method = form.querySelector('[name="pay_method"]');
        if (method && modeChanged) { method.value = q.online ? 'orange_money' : 'especes'; }
        var hint = form.querySelector('[data-pay-hint]');
        if (hint) {
            hint.textContent = q.online
                ? 'Formation en ligne : l\'apprenant pourra aussi payer plus tard par mobile money, depuis son espace.'
                : 'En présentiel, l\'inscription et le paiement se font ensemble.';
        }
        var label = form.querySelector('[data-submit-label] span');
        if (label) { label.textContent = paying ? 'Inscrire et encaisser' : 'Inscrire'; }
        // Champ de référence : affiché seulement pour le mobile money (règle data-show-when de crud-ajax.js).
        if (method) { method.dispatchEvent(new Event('change', { bubbles: true })); }
    }

    function lookup(form, input) {
        var url = form.getAttribute('data-lookup');
        var box = form.querySelector('[data-lookup-result]');
        if (!url || !box) { return; }
        var digits = input.value.replace(/\D/g, '');
        if (digits.length < 8) { box.hidden = true; return; }
        fetch(url + '?phone=' + encodeURIComponent(input.value), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { found: false }; })
            .then(function (data) {
                if (!data.found) { box.hidden = true; return; }
                Object.keys(data.fields || {}).forEach(function (name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    if (field && !field.value || (field && name === 'niveau_id')) { field.value = data.fields[name]; }
                });
                box.innerHTML = '<div class="alert alert-success small py-2 mb-0"><i class="bx bx-user-check me-1"></i>' + escapeHtml(data.label) + ' Fiche remplie automatiquement.</div>'
                    + (data.warning ? '<div class="alert alert-warning small py-2 mt-2 mb-0"><i class="bx bx-error me-1"></i>' + escapeHtml(data.warning) + '</div>' : '');
                box.hidden = false;
            })
            .catch(function () { box.hidden = true; });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    document.addEventListener('input', function (event) {
        var form = event.target.closest('form[data-inscription-form]');
        if (!form) { return; }
        if (event.target.matches('[data-lookup-phone]')) {
            clearTimeout(timers.get(form));
            timers.set(form, setTimeout(function () { lookup(form, event.target); }, 450));
        }
        if (event.target.matches('[data-pay-amount]')) { event.target.dataset.touched = '1'; }
        if (event.target.matches('[data-promo-input]')) {
            clearTimeout(timers.get(event.target));
            timers.set(event.target, setTimeout(function () { fetchTarif(form); }, 500));
        }
    });
    document.addEventListener('click', function (event) {
        var b = event.target.closest('[data-promo-suggest]');
        var form = b ? b.closest('form[data-inscription-form]') : null;
        var input = form ? form.querySelector('[data-promo-input]') : null;
        if (!input) { return; }
        input.value = b.getAttribute('data-promo-suggest');
        fetchTarif(form);
    });
    document.addEventListener('change', function (event) {
        var form = event.target.closest('form[data-inscription-form]');
        if (!form || event.target.name === 'pay_method') { return; }
        refresh(form, event.target.name === 'mode');
    });
    document.addEventListener('shown.bs.modal', function (event) {
        var form = event.target.querySelector('form[data-inscription-form]');
        if (form) { refresh(form, true); }
    });
})();
