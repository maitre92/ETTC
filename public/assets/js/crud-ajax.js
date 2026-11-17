/*
 * Écrans d'administration au gabarit admin/_crud, en application : sans rechargement de page.
 *  - Tableau DataTables, pastilles de filtre, listes d'icônes (Select2) : CrudAjax.init(root).
 *  - Formulaires [data-crud-form] (modales de création / modification, suppressions confirmées, petits réglages) :
 *    envoi en arrière-plan (fetch), bouton en chargement, double envoi impossible ;
 *      · 422 : erreurs sous chaque champ, la modale reste ouverte, la saisie est conservée ;
 *      · succès : la modale se ferme, la zone [data-crud] est remplacée par la version à jour (recherche et filtre
 *        conservés), une notification confirme l'opération ;
 *      · coupure réseau ou erreur serveur : message clair, rien n'est perdu.
 *  - Sans JavaScript, les formulaires s'envoient normalement (le serveur redirige avec le même message).
 * Bootstrap local 5.0 : Modal.getInstance(el) || new Modal(el) (pas de getOrCreateInstance).
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Animation discrète de la liste actualisée et du formulaire en cours d'envoi (sans toucher au thème du socle).
    if (!document.getElementById('crud-ajax-style')) {
        var style = document.createElement('style');
        style.id = 'crud-ajax-style';
        style.textContent = '@keyframes crudIn{from{opacity:.35;transform:translateY(6px)}to{opacity:1;transform:none}}'
            + '[data-crud].crud-refreshed .card{animation:crudIn .35s ease-out}'
            + 'form.is-busy{cursor:progress}form.is-busy .modal-body{opacity:.7;transition:opacity .2s}'
            + '@media (prefers-reduced-motion:reduce){[data-crud].crud-refreshed .card{animation:none}}';
        document.head.appendChild(style);
    }

    function toast(icon, text) {
        if (!window.Swal) { return; }
        Swal.fire({ toast: true, position: 'top-end', icon: icon, title: text, showConfirmButton: false, timer: icon === 'success' ? 3200 : 6000, timerProgressBar: true });
    }

    function modalOf(el) {
        var node = el.closest('.modal');
        if (!node) { return null; }
        return bootstrap.Modal.getInstance(node) || new bootstrap.Modal(node);
    }

    function setBusy(form, busy) {
        form.classList.toggle('is-busy', busy);
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            btn.disabled = busy;
            if (busy && !btn.dataset.label) {
                btn.dataset.label = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (btn.title ? '' : 'Enregistrement…');
            } else if (!busy && btn.dataset.label) {
                btn.innerHTML = btn.dataset.label;
                delete btn.dataset.label;
            }
        });
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('[data-ajax-error]').forEach(function (el) { el.remove(); });
    }

    function showErrors(form, errors) {
        var first = null;
        Object.keys(errors).forEach(function (name) {
            var field = form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + name + '[]"]');
            var message = errors[name][0];
            if (!field) { toast('error', message); return; }
            field.classList.add('is-invalid');
            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.setAttribute('data-ajax-error', '');
            feedback.textContent = message;
            var anchor = field.closest('.input-group') || (field.classList.contains('select2-hidden-accessible') ? field.nextElementSibling : field);
            anchor.insertAdjacentElement('afterend', feedback);
            first = first || field;
        });
        if (first) { first.focus({ preventScroll: false }); }
    }

    /** Remplace la zone [data-crud] par celle de la page reçue, en gardant la recherche et le filtre en cours. */
    function refresh(root, html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = root ? doc.querySelector('[data-crud="' + root.dataset.crud + '"]') : null;
        var statusEl = doc.querySelector('[data-crud-status]');
        var status = statusEl ? statusEl.textContent.trim() : '';
        if (!root || !fresh) { return status; }

        var state = root._crudState ? root._crudState() : null;
        if (root._crudDestroy) { root._crudDestroy(); }
        root.innerHTML = fresh.innerHTML;
        init(root, state);
        if (!reduceMotion) {
            root.classList.remove('crud-refreshed');
            void root.offsetWidth;
            root.classList.add('crud-refreshed');
        }
        return status;
    }

    function send(form) {
        if (form.classList.contains('is-busy')) { return; }
        var owner = form.closest('[data-crud-owner]');
        var root = form.closest('[data-crud]')
            || (owner ? document.querySelector('[data-crud="' + owner.getAttribute('data-crud-owner') + '"]') : null)
            || document.querySelector('[data-crud]');
        clearErrors(form);
        setBusy(form, true);

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, text/html;q=0.9' },
        }).then(function (response) {
            if (response.status === 422) {
                return response.json().then(function (data) {
                    setBusy(form, false);
                    showErrors(form, data.errors || {});
                    toast('error', 'Vérifiez les champs signalés en rouge.');
                });
            }
            if (response.status === 419) {
                setBusy(form, false);
                toast('warning', 'Votre session a expiré : la page va se recharger.');
                setTimeout(function () { window.location.reload(); }, 1800);
                return null;
            }
            if (!response.ok) {
                var failure = new Error('http');
                failure.userMessage = response.status === 403
                    ? 'Vous n\'avez pas l\'autorisation de faire cette opération.'
                    : 'Le serveur n\'a pas pu enregistrer (erreur ' + response.status + '). Réessayez.';
                throw failure;
            }
            return response.text().then(function (html) {
                var modal = modalOf(form);
                var done = function () {
                    setBusy(form, false);
                    var status = refresh(root, html);
                    toast('success', status || 'Enregistré.');
                };
                if (modal && form.closest('.modal').classList.contains('show')) {
                    form.closest('.modal').addEventListener('hidden.bs.modal', done, { once: true });
                    modal.hide();
                } else {
                    done();
                }
            });
        }).catch(function (error) {
            setBusy(form, false);
            toast('error', (error && error.userMessage) || 'Connexion interrompue : rien n\'a été perdu, réessayez dans un instant.');
        });
    }

    /** Tableau, filtres et listes d'icônes d'une zone [data-crud] ; state = { search, chip } à restaurer. */
    function init(root, state) {
        if (!root) { return; }
        var id = root.dataset.crud;
        var statusColumn = parseInt(root.dataset.crudColumns, 10);
        var tableEl = root.querySelector('#' + id + '-table');
        var table = tableEl ? $(tableEl).DataTable({ scrollX: false, order: [] }) : null;
        var chips = root.querySelectorAll('#' + id + '-filter .nv-chip');

        function applyChip(chip) {
            chips.forEach(function (other) { other.classList.toggle('active', other === chip); });
            if (table) { table.column(statusColumn).search(chip.dataset.token || '', false, true).draw(); }
        }
        chips.forEach(function (chip) { chip.addEventListener('click', function () { applyChip(chip); }); });

        if (state && table) {
            if (state.search) { table.search(state.search).draw(); }
            var chip = Array.prototype.find.call(chips, function (c) { return (c.dataset.token || '') === state.chip; });
            if (chip && state.chip) { applyChip(chip); }
        }

        $(root).find('.icon-select').each(function () {
            var modal = $(this).closest('.modal');
            var render = function (option) { return option.id ? $('<span><i class="bx ' + option.id + ' me-2"></i>' + $('<span>').text(option.text).html() + '</span>') : option.text; };
            $(this).select2({ theme: 'bootstrap4', dropdownParent: modal.length ? modal : $(document.body), templateResult: render, templateSelection: render });
        });

        // Validation instantanée : l'erreur d'un champ disparaît dès qu'il est corrigé.
        root.querySelectorAll('.modal [data-crud-form]').forEach(function (form) {
            form.addEventListener('input', function (e) {
                if (e.target.classList.contains('is-invalid') && e.target.checkValidity()) {
                    e.target.classList.remove('is-invalid');
                    var next = e.target.parentElement.querySelector('[data-ajax-error]');
                    if (next) { next.remove(); }
                }
            });
        });

        root._crudState = function () {
            var active = root.querySelector('#' + id + '-filter .nv-chip.active');
            return { search: table ? table.search() : '', chip: active ? (active.dataset.token || '') : '' };
        };
        root._crudDestroy = function () {
            if (table) { table.destroy(); }
            $(root).find('.icon-select.select2-hidden-accessible').select2('destroy');
        };

        // Modales déplacées sous <body> : à l'intérieur de la page, le fond grisé de Bootstrap (ajouté à <body>)
        // passait par-dessus la modale et rendait « Enregistrer » inaccessible. Elles restent rattachées à leur zone.
        var modals = Array.prototype.slice.call(root.querySelectorAll('.modal'));
        modals.forEach(function (modal) {
            modal.setAttribute('data-crud-owner', id);
            document.body.appendChild(modal);
        });
        var destroyTable = root._crudDestroy;
        root._crudDestroy = function () {
            destroyTable();
            modals.forEach(function (modal) {
                var instance = bootstrap.Modal.getInstance(modal);
                if (instance) { instance.dispose(); }
                modal.remove();
            });
        };

        var reopen = root.querySelector('[data-crud-reopen]');
        if (reopen && !state) {
            var target = document.getElementById(reopen.getAttribute('data-crud-reopen'));
            if (target) { (bootstrap.Modal.getInstance(target) || new bootstrap.Modal(target)).show(); }
        }
    }

    // Envois interceptés (les suppressions passent d'abord par la confirmation : partials/confirm-delete).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-crud-form') || form.classList.contains('js-confirm-delete')) { return; }
        if (!window.fetch || !window.FormData) { return; }
        event.preventDefault();
        send(form);
    });

    window.CrudAjax = { init: init, send: send, toast: toast };
})();
