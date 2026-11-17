{{-- Enregistrer un paiement reçu au centre pour une inscription ($inscription, $formId). Envoi AJAX (crud-ajax.js). --}}
@php $field = fn (string $name) => $errors->has($name) ? 'is-invalid' : ''; @endphp
<form method="POST" action="{{ route('paiements.store', $inscription) }}" class="row g-2" data-crud-form data-no-guard id="{{ $formId }}">
    @csrf
    <input type="hidden" name="back" value="{{ $back ?? 'inscription' }}">
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-amount">Montant (FCFA)</label>
        <input class="form-control {{ $field('amount') }}" id="{{ $formId }}-amount" name="amount" type="number" min="1" max="{{ $inscription->balance() }}" step="1" required value="{{ old('amount', $inscription->nextInstallment()) }}">
        <div class="form-text">Reste à payer : {{ \App\Models\Formule::money($inscription->balance()) }}</div>
        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-method">Moyen</label>
        <select class="form-select {{ $field('method') }}" id="{{ $formId }}-method" name="method" required>
            @foreach (\App\Enums\PaymentMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected(old('method', $inscription->mode === \App\Enums\FormationMode::EnLigne ? 'orange_money' : 'especes') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
        @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-reference">Référence de la transaction</label>
        <input class="form-control {{ $field('reference') }}" id="{{ $formId }}-reference" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Obligatoire pour le mobile money">
        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-period">Période réglée</label>
        <input class="form-control {{ $field('period') }}" id="{{ $formId }}-period" name="period" maxlength="100" value="{{ old('period') }}" placeholder="Mois 1, janvier 2027…">
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-date">Date du paiement</label>
        <input class="form-control {{ $field('paid_at') }}" id="{{ $formId }}-date" name="paid_at" type="date" max="{{ now()->format('Y-m-d') }}" value="{{ old('paid_at', now()->format('Y-m-d')) }}">
        @error('paid_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold" for="{{ $formId }}-notes">Remarque</label>
        <input class="form-control" id="{{ $formId }}-notes" name="notes" maxlength="1000" value="{{ old('notes') }}">
    </div>
    <div class="col-12"><button type="submit" class="btn btn-success"><i class='bx bx-check-circle me-1'></i>Enregistrer le paiement et délivrer le reçu</button></div>
</form>
