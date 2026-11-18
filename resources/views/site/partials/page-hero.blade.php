{{-- En-tête d'une page intérieure : fil d'Ariane, titre (h1 unique de la page) et introduction.
     $title, $intro (facultatif), $crumbs : [[libellé, url|null], …] entre « Accueil » et la page courante. --}}
<section class="page-hero">
    <div class="container">
        {{-- Flèche « Retour » (toutes les pages intérieures) : page précédente si l'on vient du site, sinon la page parente. --}}
        <a href="{{ collect($crumbs ?? [])->last()[1] ?? route('site.home') }}" class="page-back" data-back><i class='bx bx-arrow-back' aria-hidden="true"></i> Retour</a>
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('site.home') }}">Accueil</a></li>
                @foreach ($crumbs ?? [] as [$label, $url])
                    <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                @endforeach
                <li class="breadcrumb-item active" aria-current="page">{{ $crumb ?? $title }}</li>
            </ol>
        </nav>
        <h1>{{ $title }}</h1>
        @if (! empty($intro))
            <p>{{ $intro }}</p>
        @endif
    </div>
</section>
