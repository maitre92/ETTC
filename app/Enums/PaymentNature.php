<?php

namespace App\Enums;

/**
 * Nature d'un paiement (cahier §3.8 C : « test ou formation », identifiés séparément). Le test de niveau est GRATUIT
 * (décision du centre, 2026-11-17) : seuls les frais de formation sont encaissés pour l'instant ; la colonne garde la
 * distinction pour le suivi financier (commission, cahier §8 : frais de formation seulement).
 */
enum PaymentNature: string
{
    case Formation = 'formation';

    public function label(): string
    {
        return match ($this) {
            self::Formation => 'Frais de formation',
        };
    }
}
