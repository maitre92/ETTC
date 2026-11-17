<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formule;
use App\Models\Niveau;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\View\View;

/**
 * Catalogue public des formations (cahier ETTC §3.2, §3.3) : niveaux, formules et tarifs par mode, test de niveau.
 * Seuls les niveaux et formules publiés sont visibles ; les tarifs affichés sont ceux réglés dans l'administration.
 */
class FormationController extends Controller
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function index(): View
    {
        return view('site.formations.index', [
            'niveaux' => Niveau::published()->ordered()->get(),
            'formules' => Formule::published()->ordered()->get(),
            'seo' => Seo::make('Nos formations d\'anglais', 'Formations d\'anglais par niveau sur 2 ou 3 mois, en ligne ou en présentiel à Ségou : objectifs, tarifs et test de niveau à '.$this->settings->get('company.short_name').'.'),
        ]);
    }

    public function show(Niveau $niveau): View
    {
        abort_unless($niveau->is_published, 404);

        return view('site.formations.show', [
            'niveau' => $niveau,
            'autres' => Niveau::published()->whereKeyNot($niveau->id)->ordered()->get(),
            'formules' => Formule::published()->ordered()->get(),
            'seo' => Seo::make('Anglais — niveau '.$niveau->name, $niveau->summary),
        ]);
    }
}
