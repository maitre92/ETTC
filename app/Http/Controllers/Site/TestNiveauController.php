<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\TestNiveauRequest;
use App\Services\Tests\TestNiveauService;
use App\Support\Seo;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Page publique du test de niveau (cahier §3.4) : présentation et conditions (réglées dans l'administration), test
 * gratuit (décision du centre, 2026-11-17), demande ou réservation du test. Envoi en AJAX (JSON) ou classique ; un robot reçoit la même réponse
 * qu'un visiteur mais rien n'est enregistré (SpamGuard).
 */
class TestNiveauController extends Controller
{
    public function __construct(private readonly TestNiveauService $service, private readonly SiteSettings $settings) {}

    public function show(): View
    {
        return view('site.tests.show', [
            'seo' => Seo::make('Test de niveau d\'anglais', 'Passez le test de niveau d\'anglais avant votre formation : il vous oriente vers le niveau qui vous correspond. Réservez-le en ligne auprès de '.$this->settings->get('company.short_name').'.'),
        ]);
    }

    public function store(TestNiveauRequest $request): JsonResponse|RedirectResponse
    {
        $number = null;
        if (! SpamGuard::isSpam($request)) {
            $number = $this->service->request($request->validated(), $request)->number;
        }

        $title = 'Demande de test envoyée';
        $message = 'Merci ! Votre demande de test de niveau est enregistrée'.($number ? " sous la référence {$number}" : '').' : nous vous recontactons rapidement pour fixer la date.';

        if ($request->expectsJson()) {
            return response()->json(['title' => $title, 'message' => $message]);
        }

        return back()->with('demande_sent', ['title' => $title, 'message' => $message]);
    }
}
