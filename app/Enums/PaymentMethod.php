<?php

namespace App\Enums;

/**
 * Moyens de paiement (cahier §3.8) : au centre (espèces) ou électroniques (Orange Money, Wave, carte). En attendant le
 * prestataire en ligne (phase 5), l'équipe enregistre à la main un paiement reçu, avec sa référence.
 */
enum PaymentMethod: string
{
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';
    case Wave = 'wave';
    case Carte = 'carte';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Especes => 'Espèces (au centre)',
            self::OrangeMoney => 'Orange Money',
            self::Wave => 'Wave',
            self::Carte => 'Carte bancaire',
            self::Autre => 'Autre',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
