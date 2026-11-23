<?php

namespace App\Mail;

use App\Models\TestNiveau;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Résultat du test de niveau en ligne : au candidat ($kind = candidat) ou à l'équipe ($kind = equipe). */
class TestEnLigneResultat extends Mailable
{
    public function __construct(public readonly TestNiveau $test, public readonly string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.($this->kind === 'equipe' ? 'Test en ligne passé — '.$this->test->name : 'Votre résultat au test de niveau'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.test-en-ligne', with: ['test' => $this->test, 'kind' => $this->kind]);
    }
}
