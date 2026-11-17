<?php

namespace App\Http\Controllers\Site;

use App\Enums\NeedType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\DemandeRequest;
use App\Services\Demandes\DemandeService;
use App\Support\Seo;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Formulaires du site : contact et demande avec pièce jointe (« devis » : traduction, formation personnalisée,
 * excursion). Envoi en AJAX (réponse JSON) ou classique (redirection) ; un robot reçoit la même réponse qu'un
 * visiteur mais rien n'est enregistré (SpamGuard).
 */
class DemandeController extends Controller
{
    public function __construct(private readonly DemandeService $service, private readonly SiteSettings $settings) {}

    public function contact(): View
    {
        return view('site.demandes.contact', [
            'seo' => Seo::make('Contact', 'Contactez '.$this->settings->get('company.name').' par téléphone, WhatsApp, e-mail ou grâce au formulaire : nous vous répondons rapidement.'),
        ]);
    }

    public function devis(Request $request): View
    {
        return view('site.demandes.devis', [
            'subjectModel' => null,
            'need' => NeedType::tryFrom((string) $request->query('type')),
            'besoin' => mb_substr((string) $request->query('besoin', ''), 0, 150),
            'seo' => Seo::make('Faire une demande', 'Traduction, formation personnalisée ou excursion : décrivez votre besoin, '.$this->settings->get('company.name').' vous répond avec une proposition adaptée.')->noIndex(),
        ]);
    }

    public function storeContact(DemandeRequest $request): JsonResponse|RedirectResponse
    {
        return $this->store($request, null, 'Message envoyé', 'Merci ! Votre message a bien été reçu : nous vous répondrons dans les meilleurs délais.');
    }

    public function storeDevis(DemandeRequest $request): JsonResponse|RedirectResponse
    {
        return $this->store($request, null, 'Demande envoyée', 'Merci ! Votre demande a bien été reçue : nous l\'étudions et revenons vers vous rapidement avec une proposition.');
    }

    private function store(DemandeRequest $request, ?Model $subject, string $title, string $message): JsonResponse|RedirectResponse
    {
        if (! SpamGuard::isSpam($request)) {
            $this->service->receive($request->demandeType(), $request->validated(), $request, $subject, $request->file('attachment'));
        }

        if ($request->expectsJson()) {
            return response()->json(['title' => $title, 'message' => $message]);
        }

        return back()->with('demande_sent', ['title' => $title, 'message' => $message]);
    }
}
