<?php

namespace Database\Seeders;

use App\Enums\Billing;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\Prestation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contenu initial du site, utilisable en production et idempotent : les formations et services cités par le cahier
 * ETTC (§3.1 à §3.10) et les catégories d'actualités (§3.11, CategoryType::defaults()).
 * Textes génériques, sans promesse inventée : à relire et compléter par le centre (cahier ETTC §9.1 « informations exactes
 * relatives aux formations […] et prestations »). Un service déjà présent (même adresse) n'est pas modifié.
 */
class ContenuInitialSeeder extends Seeder
{
    /** [titre, icône, résumé] */
    public const PRESTATIONS = [
        ['Formations d\'anglais par niveau', 'bx-book-open', 'Des formations organisées par niveau, sur deux ou trois mois, en présentiel au centre ou en ligne.'],
        ['Cours en ligne interactifs', 'bx-video', 'Des séances en direct avec un formateur sur Google Meet : une pédagogie pratique, pas des vidéos à suivre seul.'],
        ['Test de niveau', 'bx-target-lock', 'Avant de commencer, un test pour évaluer vos connaissances et vous orienter vers le niveau qui vous correspond.'],
        ['Formation personnalisée', 'bx-customize', 'Un programme construit selon vos objectifs, après un entretien avec le centre.'],
        ['Traduction professionnelle', 'bx-transfer-alt', 'Traduction de vos documents : envoyez votre demande, le centre vous adresse une estimation.'],
        ['Excursion linguistique au Ghana', 'bx-world', 'Pratiquer l\'anglais en situation réelle et découvrir une autre culture.'],
    ];

    /**
     * Niveaux proposés par le cahier §3.2 « à titre de structure » : appellations définitives à fournir par le centre.
     * [nom, résumé, objectifs, compétences, prérequis]
     */
    public const NIVEAUX = [
        ['Débutant', 'Découvrir l\'anglais et communiquer dans les situations simples de la vie quotidienne.',
            "Se présenter et présenter quelqu'un\nComprendre et utiliser des expressions courantes\nPoser des questions simples et y répondre",
            "Compréhension orale de base\nVocabulaire du quotidien\nPrononciation", null],
        ['Intermédiaire', 'Gagner en aisance à l\'oral et à l\'écrit pour les études, le travail et les voyages.',
            "Tenir une conversation sur des sujets familiers\nRaconter une expérience et donner son avis\nRédiger des textes simples et structurés",
            "Expression orale en continu\nGrammaire des temps\nRédaction de courriers simples", 'Avoir le niveau débutant (vérifié par le test de niveau).'],
        ['Avancé', 'Maîtriser l\'anglais avec précision dans un cadre professionnel ou universitaire.',
            "Argumenter et débattre avec aisance\nComprendre des documents complexes\nS'exprimer avec précision à l'écrit",
            "Anglais professionnel\nPrise de parole en public\nRédaction de rapports", 'Avoir le niveau intermédiaire (vérifié par le test de niveau).'],
    ];

    /**
     * Formules et tarifs communiqués par le centre (cahier §3.3, à confirmer par écrit avant publication définitive) :
     * [nom, durée en mois, tarif en ligne / mois, tarif en présentiel / mois]. Modifiables à tout moment ensuite.
     */
    public const FORMULES = [
        ['Formule 2 mois', 2, 100000, 50000],
        ['Formule 3 mois', 3, 50000, 20000],
    ];

    public const FORMULE_POINTS = "Formation intensive\nSuivi personnalisé\nÉvaluations régulières\nAttestation de formation";

    public function run(): void
    {
        foreach (self::NIVEAUX as $order => [$name, $summary, $objectives, $skills, $prerequisites]) {
            if (! Niveau::withTrashed()->where('slug', Str::slug($name))->exists()) {
                Niveau::create(['name' => $name, 'summary' => $summary, 'objectives' => $objectives, 'skills' => $skills, 'prerequisites' => $prerequisites, 'sort_order' => ($order + 1) * 10, 'is_published' => true]);
            }
        }

        // Un tarif déjà réglé par le centre n'est jamais écrasé : seule une formule absente est créée.
        foreach (self::FORMULES as $order => [$name, $months, $online, $onsite]) {
            if (! Formule::withTrashed()->where('slug', Str::slug($name))->exists()) {
                Formule::create(['name' => $name, 'duration_months' => $months, 'points' => self::FORMULE_POINTS, 'price_online' => $online, 'price_onsite' => $onsite, 'billing' => Billing::Mensuel, 'sort_order' => ($order + 1) * 10, 'is_published' => true]);
            }
        }

        foreach (CategoryType::cases() as $type) {
            foreach ($type->defaults() as $order => $name) {
                if (! Category::withTrashed()->ofType($type)->where('name', $name)->exists()) {
                    Category::create(['type' => $type, 'name' => $name, 'sort_order' => ($order + 1) * 10]);
                }
            }
        }

        foreach (self::PRESTATIONS as $order => [$title, $icon, $summary]) {
            $slug = Str::slug($title);
            if (Prestation::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            Prestation::create(['title' => $title, 'slug' => $slug, 'icon' => $icon, 'summary' => $summary, 'sort_order' => ($order + 1) * 10, 'is_published' => true]);
        }
    }
}
