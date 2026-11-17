<?php

namespace App\Mail;

use App\Models\Inscription;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Récapitulatif de l'inscription envoyé au candidat (cahier §3.5 « réception d'un récapitulatif »). */
class InscriptionRecapitulatif extends Mailable
{
    public function __construct(public readonly Inscription $inscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre inscription — '.$this->inscription->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.inscription', with: ['inscription' => $this->inscription, 'kind' => 'recapitulatif']);
    }
}
