{{-- Paiement mobile money d'une inscription en ligne ($payment : PaiementService::onlinePaymentInfo()) : affiché sur la
     confirmation quand le navigateur n'exécute pas JavaScript (sinon site.js construit le même bloc). --}}
<div class="pay-now">
    <h3 class="h5">Payer maintenant par mobile money</h3>
    <p class="mb-2">{{ $payment['instructions'] }}</p>
    <div class="pay-now-methods">
        @foreach ($payment['methods'] as $method)
            <div class="pay-now-method">
                <strong>{{ $method['label'] }}</strong>
                @if ($method['number'] !== '')<div class="pay-now-number">{{ $method['number'] }}</div>@endif
                @if ($method['qr'])
                    <img src="{{ $method['qr'] }}" alt="QR marchand {{ $method['label'] }} à scanner" width="150" height="150">
                    <small class="text-muted d-block">Scannez avec votre application</small>
                @endif
            </div>
        @endforeach
    </div>
    @foreach ($payment['methods'] as $method)
        @if (! empty($method['ussd']))@include('paiements._ussd-steps', ['label' => $method['label'], 'ussd' => $method['ussd']])@endif
    @endforeach
    <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
        @if ($payment['whatsapp'])<a href="{{ $payment['whatsapp'] }}" class="btn btn-whatsapp" target="_blank" rel="noopener">J'ai payé : envoyer la référence sur WhatsApp</a>@endif
        @if ($payment['account'])<a href="{{ $payment['account'] }}" class="btn btn-outline-primary">Créer mon espace apprenant</a>@endif
    </div>
</div>
