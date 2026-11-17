<?php

namespace App\Enums;

/**
 * Façon de payer choisie à l'inscription (cahier §3.5, §3.8 ; décision du centre, 2026-11-18) : au centre (présentiel :
 * espèces ou mobile money au guichet, enregistré par l'équipe) ou en ligne par mobile money (Orange Money, Wave, Moov
 * Money : l'apprenant déclare la transaction dans son espace, l'équipe la vérifie). Présélectionné d'après le mode.
 */
enum PaymentChoice: string
{
    case AuCentre = 'au_centre';
    case EnLigne = 'en_ligne';

    public function label(): string
    {
        return match ($this) {
            self::AuCentre => 'Au centre',
            self::EnLigne => 'En ligne par mobile money (Orange Money, Wave, Moov Money)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
