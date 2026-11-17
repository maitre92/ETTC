<?php

namespace App\Enums;

/** Nature d'une demande reçue par le site (cahier §6.2 à §6.4). */
enum DemandeType: string
{
    case Contact = 'contact';
    case Devis = 'devis';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contact',
            self::Devis => 'Demande de devis',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Contact => 'bx bx-envelope',
            self::Devis => 'bx bx-file',
        };
    }
}
