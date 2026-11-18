<?php

namespace App\Mail;

use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Seance;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Calendrier des séances programmées, ou annulation d'une séance, envoyé à un apprenant du groupe. */
class SeancesCalendrier extends Mailable
{
    /** @param  Collection<int, Seance>  $seances */
    public function __construct(
        public readonly Groupe $groupe,
        public readonly Inscription $inscription,
        public readonly Collection $seances,
        public readonly string $kind,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.($this->kind === 'annulee' ? 'Séance annulée — ' : 'Vos séances — ').$this->groupe->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.seances', with: ['groupe' => $this->groupe, 'inscription' => $this->inscription, 'seances' => $this->seances, 'kind' => $this->kind]);
    }
}
