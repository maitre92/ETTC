{{-- Juste après un encaissement : le reçu à imprimer, sans le chercher (session « receipt_id »). À placer DANS la zone
     [data-crud] pour qu'il apparaisse aussi après un envoi AJAX. --}}
@php $lastReceipt = session('receipt_id') ? \App\Models\Paiement::find(session('receipt_id')) : null; @endphp
@if ($lastReceipt && $lastReceipt->status->hasReceipt())
    <div class="alert alert-success d-flex flex-wrap align-items-center gap-2" role="status">
        <i class='bx bx-printer fs-3'></i>
        <span class="flex-grow-1"><strong>Reçu {{ $lastReceipt->number }}</strong> — {{ $lastReceipt->money() }} encaissés pour {{ $lastReceipt->inscription->name }}. Imprimez-le pour l'apprenant :</span>
        @include('paiements._receipt-buttons', ['receiptRoute' => 'paiements.receipt', 'paiement' => $lastReceipt, 'btn' => 'btn btn-success'])
    </div>
@endif
