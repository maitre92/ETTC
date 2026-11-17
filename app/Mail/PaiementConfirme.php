<?php

namespace App\Mail;

use App\Models\Paiement;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Reçu envoyé à l'apprenant quand son paiement est confirmé (cahier §3.8 C). */
class PaiementConfirme extends Mailable
{
    public function __construct(public readonly Paiement $paiement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] Reçu de paiement '.$this->paiement->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.paiement', with: ['paiement' => $this->paiement, 'kind' => 'recu']);
    }
}
