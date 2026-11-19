<?php

namespace App\Mail;

use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Nouvelle inscription reçue du site, pour l'équipe (« Répondre » écrit au candidat s'il a laissé son e-mail). */
class InscriptionRecue extends Mailable
{
    public function __construct(public readonly Inscription $inscription, public readonly ?Paiement $paiement = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.($this->paiement ? 'Paiement à vérifier — ' : 'Nouvelle inscription — ').$this->inscription->name,
            replyTo: $this->inscription->email ? [new Address($this->inscription->email, $this->inscription->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.inscription', with: ['inscription' => $this->inscription, 'kind' => 'equipe', 'declared' => $this->paiement]);
    }
}
