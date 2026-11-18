<?php

namespace App\Http\Controllers\Site;

use App\Enums\NeedType;
use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Services\Demandes\PropositionService;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Pages des services du centre (cahier §3.7 formation personnalisée, §3.9 traduction, §3.10 excursion au Ghana) :
 * présentation modifiable dans l'administration + formulaire adapté (envoyé comme une demande, `site.devis.store`).
 * Proposition chiffrée : le demandeur la consulte et l'accepte par son lien personnel.
 */
class ServiceController extends Controller
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function traduction(): View
    {
        return view('site.services.traduction', [
            'seo' => Seo::make('Traduction professionnelle', 'Traduction de documents du français vers l\'anglais et de l\'anglais vers le français : envoyez votre document, recevez une estimation.'),
        ]);
    }

    public function personnalisee(): View
    {
        return view('site.services.personnalisee', [
            'seo' => Seo::make('Formation personnalisée', 'Une formation d\'anglais construite selon vos objectifs, votre niveau et votre rythme : décrivez votre besoin, recevez une proposition.'),
        ]);
    }

    public function excursion(): View
    {
        return view('site.services.excursion', [
            'seo' => Seo::make('Excursion au Ghana', 'Immersion linguistique et culturelle au Ghana avec '.$this->settings->get('company.short_name').' : pratiquer l\'anglais en situation réelle.'),
        ]);
    }

    public function proposition(string $token): View
    {
        $demande = Demande::where('proposal_token', $token)->firstOrFail();

        return view('site.services.proposition', ['demande' => $demande, 'seo' => Seo::make('Votre proposition')->noIndex()]);
    }

    public function accept(string $token, PropositionService $service): RedirectResponse
    {
        $demande = Demande::where('proposal_token', $token)->firstOrFail();
        $service->accept($demande);

        return redirect()->route('site.proposition.show', $token)->with('accepted', true);
    }

    /** Type de demande d'une page de service (valeur cachée du formulaire). */
    public static function need(string $page): NeedType
    {
        return match ($page) {
            'traduction' => NeedType::Traduction,
            'personnalisee' => NeedType::FormationPersonnalisee,
            default => NeedType::Excursion,
        };
    }
}
