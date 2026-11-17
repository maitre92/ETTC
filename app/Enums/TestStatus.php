<?php

namespace App\Enums;

/** Étapes d'un test de niveau (cahier §3.4) : demandé → planifié → passé, ou annulé. */
enum TestStatus: string
{
    case Demande = 'demande';
    case Planifie = 'planifie';
    case Passe = 'passe';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Demande => 'À planifier',
            self::Planifie => 'Planifié',
            self::Passe => 'Passé',
            self::Annule => 'Annulé',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Demande => 'bg-danger-subtle',
            self::Planifie => 'bg-warning-subtle',
            self::Passe => 'bg-success-subtle',
            self::Annule => 'bg-secondary-subtle',
        };
    }

    /** En attente d'une action de l'équipe (candidat en attente de test). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Demande, self::Planifie], true);
    }
}
