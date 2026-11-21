{{-- Encaisser un paiement reçu au centre ($inscription, $formId, $back). Deux champs seulement : le montant (une
     mensualité proposée) et le moyen ; la référence n'apparaît que pour le mobile money ; le reste est facultatif. --}}
@php $field = fn (string $name) => $errors->has($name) ? 'is-invalid' : ''; @endphp
<form method="POST" action="{{ route('paiements.store', $inscription) }}" class="row g-3" data-crud-form data-no-guard id="{{ $formId }}">
    @csrf
    <input type="hidden" name="back" value="{{ $back ?? 'inscription' }}">
    <div class="col-12">
        <p class="mb-0 small">Total {{ \App\Models\Formule::money($inscription->total_amount) }} · déjà payé {{ \App\Models\Formule::money($inscription->amount_paid) }}@if ($inscription->pendingAmount() > 0) · en vérification {{ \App\Models\Formule::money($inscription->pendingAmount()) }}@endif · <strong>à encaisser {{ \App\Models\Formule::money($inscription->payableNow()) }}</strong></p>
        @if ($inscription->pendingAmount() > 0)
            <div class="alert alert-warning small py-2 mt-2 mb-0"><i class='bx bx-info-circle'></i> {{ \App\Models\Formule::money($inscription->pendingAmount()) }} déjà payé en ligne par l'apprenant, en cours de vérification : ne l'encaissez pas une seconde fois. Seul le reliquat est proposé.</div>
        @endif
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold" for="{{ $formId }}-amount">Montant reçu (FCFA)</label>
        @include('paiements._amount-chips', ['inscription' => $inscription, 'target' => $formId.'-amount', 'payable' => $inscription->payableNow()])
        <input class="form-control form-control-lg {{ $field('amount') }}" id="{{ $formId }}-amount" name="amount" type="number" min="1" max="{{ $inscription->payableNow() }}" step="1" required value="{{ old('amount', $inscription->nextInstallment()) }}">
        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-method">Payé en</label>
        @include('paiements._method-select', ['id' => $formId.'-method', 'name' => 'method', 'class' => 'form-select form-select-lg '.$field('method'), 'selected' => old('method', $inscription->mode === \App\Enums\FormationMode::EnLigne ? 'orange_money' : 'especes')])
        @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12" data-show-when="method:orange_money,wave,moov_money,banque">
        <label class="form-label fw-semibold" for="{{ $formId }}-reference">Référence (SMS mobile money, ou n° de virement / bordereau / chèque)</label>
        <input class="form-control {{ $field('reference') }}" id="{{ $formId }}-reference" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Ex. MP241118.1530.A12345 ou CHQ 0012345">
        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <details @if ($errors->hasAny(['period', 'paid_at', 'notes'])) open @endif>
            <summary class="small text-primary" style="cursor:pointer">Plus d'options (période, date, remarque)</summary>
            <div class="row g-2 mt-1">
                <div class="col-sm-4"><label class="form-label small" for="{{ $formId }}-period">Période</label><input class="form-control form-control-sm" id="{{ $formId }}-period" name="period" maxlength="100" value="{{ old('period') }}" placeholder="Mois 1…"></div>
                <div class="col-sm-4"><label class="form-label small" for="{{ $formId }}-date">Date du paiement</label><input class="form-control form-control-sm {{ $field('paid_at') }}" id="{{ $formId }}-date" name="paid_at" type="date" max="{{ now()->format('Y-m-d') }}" value="{{ old('paid_at', now()->format('Y-m-d')) }}">@error('paid_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-sm-4"><label class="form-label small" for="{{ $formId }}-notes">Remarque</label><input class="form-control form-control-sm" id="{{ $formId }}-notes" name="notes" maxlength="1000" value="{{ old('notes') }}"></div>
            </div>
        </details>
    </div>
    <div class="col-12"><button type="submit" class="btn btn-success btn-lg w-100"><i class='bx bx-check-circle me-1'></i>Encaisser et créer le reçu</button></div>
</form>
