<?php

namespace App\Enums;

/** Périodicité de paiement d'une formule (cahier §3.3 : tarifs « par mois » ; paiement unique possible). */
enum Billing: string
{
    case Mensuel = 'mois';
    case Unique = 'unique';

    public function label(): string
    {
        return match ($this) {
            self::Mensuel => 'Par mois',
            self::Unique => 'Paiement unique',
        };
    }

    /** Suffixe affiché après le prix : « / mois » ou « (paiement unique) ». */
    public function suffix(): string
    {
        return match ($this) {
            self::Mensuel => '/ mois',
            self::Unique => '(paiement unique)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $b) => [$b->value => $b->label()])->all();
    }
}
