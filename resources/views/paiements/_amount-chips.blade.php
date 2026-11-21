{{-- Montants en un clic (reliquat) : « Prochaine échéance » et « Tout le reste » remplissent le champ du montant.
     $inscription, $target (id du champ), $payable (ce qui reste à encaisser), $btn (classe des boutons, facultatif). --}}
@php
    $next = min($inscription->nextInstallment(), $payable);
    $chips = collect([
        ['Prochaine échéance', $next],
        ['Tout le reste', $payable],
    ])->unique(fn ($chip) => $chip[1])->filter(fn ($chip) => $chip[1] > 0);
@endphp
@if ($chips->count() > 1)
    <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="Montant à payer">
        @foreach ($chips as [$chipLabel, $chipAmount])
            <button type="button" class="{{ $btn ?? 'btn btn-sm btn-outline-primary' }}" data-fill-amount="{{ $chipAmount }}" data-target="{{ $target }}" aria-controls="{{ $target }}">
                {{ $chipLabel }} : <strong>{{ \App\Models\Formule::money($chipAmount) }}</strong>
            </button>
        @endforeach
    </div>
@endif
