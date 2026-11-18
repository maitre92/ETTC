<?php

namespace App\Mail;

use App\Models\Annonce;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Annonce du centre envoyée à un apprenant. */
class AnnonceCentre extends Mailable
{
    public function __construct(public readonly Annonce $annonce, public readonly string $learnerName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.$this->annonce->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.annonce', with: ['annonce' => $this->annonce, 'learnerName' => $this->learnerName]);
    }
}
