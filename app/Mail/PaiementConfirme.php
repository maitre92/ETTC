<?php

namespace App\Mail;

use App\Models\Paiement;
use App\Services\Paiements\ReceiptService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Paiement confirmé (cahier §3.8 C) : l'apprenant reçoit ses références (inscription, formation, montant payé, reste)
 * et son REÇU EN PDF joint (format A5). Au premier paiement, c'est aussi la confirmation de son inscription.
 */
class PaiementConfirme extends Mailable
{
    public function __construct(public readonly Paiement $paiement) {}

    public function envelope(): Envelope
    {
        $first = $this->paiement->inscription->paiements()->where('status', 'confirme')->count() <= 1;

        return new Envelope(subject: '['.config('app.name').'] '.($first ? 'Inscription confirmée — reçu ' : 'Reçu de paiement ').$this->paiement->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.paiement', with: ['paiement' => $this->paiement, 'kind' => 'recu']);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $receipts = app(ReceiptService::class);

        return [Attachment::fromData(fn () => $receipts->pdf($this->paiement, 'a5')->output(), $receipts->filename($this->paiement, 'a5'))->withMime('application/pdf')];
    }
}
