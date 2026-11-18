{{-- Déclaration d'un paiement mobile money envoyé au numéro du centre (vérifié par l'équipe). $inscription, $payable. --}}
@use('App\Enums\PaymentMethod')
@php $money = fn (int $amount) => \App\Models\Formule::money($amount); @endphp
{{-- Moyens du centre : numéro et/ou QR marchand à scanner avec l'application de l'opérateur (touché = agrandi). --}}
<div class="d-flex flex-wrap gap-3 my-2">
    @foreach ($numbers as $method => $number)
        <div class="border rounded-3 p-2 text-center" style="min-width: 150px">
            <div class="fw-bold">{{ PaymentMethod::from($method)->label() }}</div>
            @if ($number !== '')<div class="small">{{ $number }}</div>@endif
            @if (isset($qrCodes[$method]))
                <a href="{{ $qrCodes[$method] }}" target="_blank" rel="noopener" title="Agrandir le QR {{ PaymentMethod::from($method)->label() }}">
                    <img src="{{ $qrCodes[$method] }}" alt="QR marchand {{ PaymentMethod::from($method)->label() }} à scanner" width="140" height="140" style="object-fit:contain" class="bg-white mt-1">
                </a>
                <div class="small text-muted">Scannez avec votre application</div>
            @endif
        </div>
    @endforeach
</div>
<p class="small text-muted">{{ $instructions }}</p>
<form method="POST" action="{{ route('client.paiements.store') }}" class="row g-3" data-ajax-form novalidate>
    @csrf
    <input type="hidden" name="inscription_id" value="{{ $inscription->id }}">
    <div class="col-md-6">@include('site.demandes._field', ['name' => 'payment_method', 'label' => 'Moyen utilisé', 'type' => 'select', 'required' => true, 'options' => collect($numbers)->mapWithKeys(fn ($n, $m) => [$m => PaymentMethod::from($m)->label()])->all(), 'value' => count($numbers) === 1 ? array_key_first($numbers) : null])</div>
    <div class="col-md-6">
        <label for="f-amount-{{ $inscription->id }}" class="form-label required">Montant envoyé (FCFA)</label>
        <input type="number" class="form-control" id="f-amount-{{ $inscription->id }}" name="amount" min="1" max="{{ $payable }}" step="1" required value="{{ min($inscription->nextInstallment(), $payable) }}">
        <div class="form-text">Reste à payer : {{ $money($payable) }}</div>
    </div>
    <div class="col-md-6">@include('site.demandes._field', ['name' => 'reference', 'label' => 'Référence de la transaction', 'required' => true, 'max' => 100, 'placeholder' => 'Ex. MP241118.1530.A12345', 'help' => 'Elle figure dans le SMS de confirmation de votre opérateur.'])</div>
    <div class="col-md-6">@include('site.demandes._field', ['name' => 'payer_phone', 'label' => 'Numéro qui a payé', 'type' => 'tel', 'max' => 30, 'value' => $inscription->phone])</div>
    <div class="col-12">@include('site.demandes._field', ['name' => 'period', 'label' => 'Période réglée (facultatif)', 'max' => 100, 'placeholder' => 'Mois 1, janvier 2027…'])</div>
    <div class="col-12"><button type="submit" class="btn btn-accent"><i class='bx bx-send' aria-hidden="true"></i> Déclarer mon paiement</button></div>
</form>
