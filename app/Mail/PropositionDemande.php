<?php

namespace App\Mail;

use App\Models\Demande;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Proposition chiffrée envoyée au demandeur ($kind = offre), ou alerte à l'équipe quand il l'accepte ($kind = acceptee). */
class PropositionDemande extends Mailable
{
    public function __construct(public readonly Demande $demande, public readonly string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.($this->kind === 'acceptee'
            ? 'Proposition acceptée — '.$this->demande->name
            : 'Notre proposition — '.($this->demande->need_type?->label() ?? 'votre demande')));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.proposition', with: ['demande' => $this->demande, 'kind' => $this->kind]);
    }
}
