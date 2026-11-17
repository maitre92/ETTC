<?php

namespace App\Enums;

/**
 * Familles de catégories (cahier ETTC §3.11 actualités). Une catégorie appartient à une seule
 * famille ; son adresse est unique dans sa famille.
 */
enum CategoryType: string
{
    case Article = 'article';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Actualités',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Article => 'bx bx-news',
        };
    }

    /** Permission qui gère les catégories de cette famille (celle du module de contenu concerné). */
    public function permission(): string
    {
        return match ($this) {
            self::Article => 'articles.update',
        };
    }

    /**
     * Catégories proposées par le cahier, créées par ContenuInitialSeeder.
     *
     * @return list<string>
     */
    public function defaults(): array
    {
        return match ($this) {
            self::Article => ['Sessions et inscriptions', 'Événements', 'Promotions', 'Résultats', 'Excursions'],
        };
    }
}
