<?php

namespace App\Mail;

use App\Models\TestNiveau;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Nouvelle demande de test de niveau, pour l'équipe (« Répondre » écrit au candidat s'il a laissé son e-mail). */
class TestNiveauDemande extends Mailable
{
    public function __construct(public readonly TestNiveau $test) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] Test de niveau demandé — '.$this->test->name,
            replyTo: $this->test->email ? [new Address($this->test->email, $this->test->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.test-niveau', with: ['test' => $this->test, 'kind' => 'equipe']);
    }
}
