<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\Prestation;
use App\Models\Temoignage;
use App\Support\Markdown;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\View\View;

/**
 * Pages institutionnelles du site public (cahier ETTC §3.1) : accueil, à propos, pages légales. Leur contenu se règle dans
 * l'administration (Pages et paramètres, App\Support\SiteSettings).
 */
class PageController extends Controller
{
    public function __construct(private readonly SiteSettings $settings) {}

    /**
     * Accueil d'après la maquette validée (English Training/maquette.jpeg, 2026-11-17) : bandeau, nos services, nos
     * formules, pourquoi nous choisir et témoignages (carrousel, seulement s'il y a des témoignages écrits publiés).
     */
    public function home(): View
    {
        // « Débutant, Intermédiaire et Avancé » : noms des niveaux publiés (carte « Formations par niveau »).
        $niveaux = Niveau::published()->ordered()->pluck('name')->all();
        $niveauxLabel = count($niveaux) > 1 ? implode(', ', array_slice($niveaux, 0, -1)).' et '.end($niveaux) : ($niveaux[0] ?? '');

        return view('site.pages.home', [
            // Formules et tarifs : module Formations (modifiables à tout moment dans l'administration).
            'formules' => Formule::published()->ordered()->get(),
            'niveauxLabel' => $niveauxLabel,
            'temoignages' => Temoignage::with('photo')->published()->whereNotNull('content')->where('content', '!=', '')
                ->orderByDesc('is_featured')->ordered()->limit(8)->get(),
            'seo' => Seo::make(canonical: route('site.home')),
        ]);
    }

    /** Formations et services du centre (cahier ETTC §3.1 : formations, test de niveau, traduction, activités). */
    public function services(): View
    {
        return view('site.pages.services', [
            'prestations' => Prestation::published()->ordered()->get(),
            'seo' => Seo::make('Nos services', 'Formations d\'anglais par niveau, test de niveau, traduction professionnelle et activités linguistiques : les services de '.$this->settings->get('company.name').'.'),
        ]);
    }

    public function about(): View
    {
        return view('site.pages.about', [
            'seo' => Seo::make('À propos', $this->settings->get('about.presentation') ? Markdown::toText($this->settings->get('about.presentation')) : null),
        ]);
    }

    public function legal(): View
    {
        return view('site.pages.legal', [
            'title' => 'Mentions légales',
            'content' => $this->settings->html('legal.mentions'),
            'seo' => Seo::make('Mentions légales', 'Mentions légales du site de '.$this->settings->get('company.name').'.'),
        ]);
    }

    public function privacy(): View
    {
        return view('site.pages.legal', [
            'title' => 'Politique de confidentialité',
            'content' => $this->settings->html('legal.privacy'),
            'seo' => Seo::make('Politique de confidentialité', 'Utilisation des données personnelles collectées par les formulaires du site de '.$this->settings->get('company.name').'.'),
        ]);
    }
}
