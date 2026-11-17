<?php

namespace App\Mail;

use App\Models\TestNiveau;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Accusé de réception d'une demande de test de niveau, pour le candidat. */
class TestNiveauAccuse extends Mailable
{
    public function __construct(public readonly TestNiveau $test) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre demande de test de niveau — '.$this->test->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.test-niveau', with: ['test' => $this->test, 'kind' => 'accuse']);
    }
}
