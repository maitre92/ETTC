<?php

namespace App\Mail;

use App\Models\GroupeMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Message du formateur à son groupe, envoyé à un apprenant (variable `$groupeMessage` : `$message` est réservé). */
class MessageGroupe extends Mailable
{
    public function __construct(public readonly GroupeMessage $groupeMessage, public readonly string $learnerName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] Message de '.$this->groupeMessage->authorName().' — '.$this->groupeMessage->groupe->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.message-groupe', with: ['groupeMessage' => $this->groupeMessage, 'learnerName' => $this->learnerName]);
    }
}
