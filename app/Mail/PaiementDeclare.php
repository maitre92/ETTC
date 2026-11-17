<?php

namespace App\Mail;

use App\Models\Paiement;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Paiement mobile money déclaré par un apprenant, pour l'équipe : à vérifier sur le compte marchand. */
class PaiementDeclare extends Mailable
{
    public function __construct(public readonly Paiement $paiement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] Paiement à vérifier — '.$this->paiement->inscription->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.paiement', with: ['paiement' => $this->paiement, 'kind' => 'equipe']);
    }
}
