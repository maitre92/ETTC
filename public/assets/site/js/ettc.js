/*
 * Comportements propres à l'accueil d'ETTC (maquette du 2026-11-17).
 *  - Carrousel des témoignages ([data-ettc-carousel]) : flèches, points, défilement automatique toutes les 7 s,
 *    en pause au survol ou au focus et coupé si le visiteur réduit les animations. Sans JavaScript, le premier
 *    témoignage reste affiché.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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
