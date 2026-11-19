<?php

namespace App\Mail;

use App\Models\Paiement;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/** Paiement mobile money déclaré par un apprenant, pour l'équipe : à vérifier sur le compte marchand. */
class PaiementDeclare extends Mailable
{
    public function __construct(public readonly Paiement $paiement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] Paiement à vérifier — '.$this->paiement->inscription->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.paiement', with: ['paiement' => $this->paiement, 'kind' => 'equipe']);
    }

    /** Capture du paiement (si l'apprenant en a joint une) : la personne qui vérifie l'a sous les yeux. @return list<\Illuminate\Mail\Mailables\Attachment> */
    public function attachments(): array
    {
        $paiement = $this->paiement;
        if (! $paiement?->proof_path || ! Storage::disk('local')->exists($paiement->proof_path)) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $paiement->proof_path)->as('Capture-'.$paiement->number.'.'.pathinfo($paiement->proof_path, PATHINFO_EXTENSION))];
    }
}
