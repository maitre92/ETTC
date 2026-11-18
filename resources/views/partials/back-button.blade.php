{{-- Flèche « Retour » sur toutes les pages de l'administration sauf le tableau de bord (demande du centre) : posée devant
     le fil d'Ariane. Clic : page précédente si l'on vient du site ; sinon la page parente du fil d'Ariane (ou l'accueil). --}}
<script>
    (function () {
        var home = @json(route('home'));
        var crumb = document.querySelector('.page-breadcrumb');
        if (!crumb || location.href.replace(/[?#].*$/, '').replace(/\/$/, '') === home.replace(/\/$/, '')) return;
        var links = crumb.querySelectorAll('.breadcrumb-item a');
        var back = document.createElement('a');
        back.href = links.length ? links[links.length - 1].href : home;
        back.className = 'btn btn-light btn-sm d-inline-flex align-items-center gap-1 me-2 nv-back';
        back.innerHTML = "<i class='bx bx-arrow-back'></i><span>Retour</span>";
        back.setAttribute('title', 'Revenir à la page précédente');
        back.addEventListener('click', function (event) {
            var sameSite = document.referrer && document.referrer.indexOf(location.origin) === 0 && document.referrer !== location.href;
            if (sameSite && history.length > 1) { event.preventDefault(); history.back(); }
        });
        crumb.insertBefore(back, crumb.firstChild);
    })();
</script>
