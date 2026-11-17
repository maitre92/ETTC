<?php

namespace App\Enums;

/**
 * Type de besoin d'une demande « devis » (formulaire générique avec pièce jointe). Valeurs reprises du cahier ETTC
 * (§3.7 formation personnalisée, §3.9 traduction, §3.10 excursion) en attendant leurs formulaires dédiés.
 */
enum NeedType: string
{
    case Traduction = 'traduction';
    case FormationPersonnalisee = 'formation_personnalisee';
    case Excursion = 'excursion';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Traduction => 'Traduction',
            self::FormationPersonnalisee => 'Formation personnalisée',
            self::Excursion => 'Excursion au Ghana',
            self::Autre => 'Autre',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $n) => [$n->value => $n->label()])->all();
    }
}
