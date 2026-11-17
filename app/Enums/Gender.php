<?php

namespace App\Enums;

/** Sexe de l'apprenant (cahier §3.5 « informations demandées »). */
enum Gender: string
{
    case Femme = 'femme';
    case Homme = 'homme';

    public function label(): string
    {
        return match ($this) {
            self::Femme => 'Femme',
            self::Homme => 'Homme',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $g) => [$g->value => $g->label()])->all();
    }
}
