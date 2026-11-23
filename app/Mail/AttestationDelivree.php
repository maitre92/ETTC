<?php

namespace App\Mail;

use App\Enums\FormationMode;
use App\Models\Inscription;
use App\Services\Attestations\AttestationService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Attestation délivrée (demande du centre, 2026-11-23) : formation EN LIGNE → l'attestation est jointe (PDF) et
 * téléchargeable dans l'espace apprenant (délivrance à distance) ; EN PRÉSENTIEL → « prête, à retirer au centre ».
 */
class AttestationDelivree extends Mailable
{
    public function __construct(public readonly Inscription $inscription) {}

    public function remote(): bool
    {
        return $this->inscription->mode === FormationMode::EnLigne;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.($this->remote() ? 'Votre attestation de formation' : 'Votre attestation est prête'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attestation', with: ['inscription' => $this->inscription, 'remote' => $this->remote()]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->remote()) {
            return [];
        }
        $attestations = app(AttestationService::class);

        return [Attachment::fromData(fn () => $attestations->pdf($this->inscription)->output(), $attestations->filename($this->inscription))->withMime('application/pdf')];
    }
}
