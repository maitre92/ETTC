<?php

namespace App\Enums;

/**
 * Étapes d'une inscription (cahier §3.5, §3.8 B, §5) : à valider (dossier reçu) → validée (en attente du paiement) →
 * confirmée (paiement reçu, en formation ; libellé « Confirmée », valeur technique en_cours) → terminée ; ou annulée. Un
 * paiement confirmé valide l'inscription automatiquement.
 */
enum InscriptionStatus: string
{
    case AValider = 'a_valider';
    case Validee = 'validee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::AValider => 'À valider',
            self::Validee => 'En attente de paiement',
            self::EnCours => 'Confirmée',
            self::Terminee => 'Terminée',
            self::Annulee => 'Annulée',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::AValider => 'bg-danger-subtle',
            self::Validee => 'bg-warning-subtle',
            self::EnCours => 'bg-primary-subtle',
            self::Terminee => 'bg-success-subtle',
            self::Annulee => 'bg-secondary-subtle',
        };
    }

    /** Dossier encore suivi par l'équipe (cahier §5 « suivre les inscriptions en attente »). */
    public function isPending(): bool
    {
        return in_array($this, [self::AValider, self::Validee], true);
    }
}
