/*
 * Fenêtre « Inscrire au centre » (form[data-inscription-form]) : rendue plus intelligente pour l'accueil.
 *  - téléphone tapé → recherche de la personne déjà connue (compte, test, inscription) : fiche remplie toute seule ;
 *  - total de la formation affiché en direct (formule × mode) ;
 *  - paiement déduit du mode : présentiel → au centre (paiement immédiat coché), en ligne → mobile money ;
 *  - montant proposé = une mensualité ; libellé du bouton adapté (« Inscrire et encaisser » / « Inscrire »).
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
