{{-- Onglets du module Formations et tarifs. --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('formations.formules.*') ? 'active' : '' }}" href="{{ route('formations.formules.index') }}"><i class='bx bx-purchase-tag me-1'></i>Formules et tarifs</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('formations.niveaux.*') ? 'active' : '' }}" href="{{ route('formations.niveaux.index') }}"><i class='bx bx-book-open me-1'></i>Niveaux</a>
    </li>
    @if (Route::has('site.formations.index'))
        <li class="nav-item ms-auto">
            <a class="nav-link" href="{{ route('site.formations.index') }}" target="_blank" rel="noopener"><i class='bx bx-link-external me-1'></i>Voir sur le site</a>
        </li>
    @endif
</ul>
