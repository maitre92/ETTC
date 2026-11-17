<?php

namespace App\Enums;

/**
 * Mode de paiement choisi à l'inscription (cahier §3.5, §3.8) : au centre (inscription « en attente de paiement »
 * jusqu'à l'encaissement) ou en ligne (Orange Money, Wave, carte : prestataire à intégrer au module Paiements).
 */
enum PaymentChoice: string
{
    case AuCentre = 'au_centre';
    case EnLigne = 'en_ligne';

    public function label(): string
    {
        return match ($this) {
            self::AuCentre => 'Au centre',
            self::EnLigne => 'En ligne (Orange Money, Wave, carte)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
