<?php

namespace App\Enums;

/** Mode de formation (cahier §3.3) et colonne du tarif correspondant dans `formules`. */
enum FormationMode: string
{
    case EnLigne = 'en_ligne';
    case Presentiel = 'presentiel';

    public function label(): string
    {
        return match ($this) {
            self::EnLigne => 'En ligne',
            self::Presentiel => 'En présentiel',
        };
    }

    /** Colonne du tarif dans `formules`. */
    public function priceColumn(): string
    {
        return match ($this) {
            self::EnLigne => 'price_online',
            self::Presentiel => 'price_onsite',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
