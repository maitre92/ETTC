<?php

namespace App\Mail;

use App\Models\TestNiveau;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Convocation au test de niveau (date, lieu ou lien de connexion), envoyée à la planification et à chaque report. */
class TestNiveauConvocation extends Mailable
{
    public function __construct(public readonly TestNiveau $test) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Convocation à votre test de niveau — '.$this->test->scheduled_at?->format('d/m/Y à H:i'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.test-niveau', with: ['test' => $this->test, 'kind' => 'convocation']);
    }
}
