<?php

namespace App\Http\Controllers\Site;

use App\Enums\FormationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\InscriptionRequest;
use App\Models\Formule;
use App\Models\Niveau;
use App\Services\Inscriptions\InscriptionService;
use App\Services\Paiements\PaiementService;
use App\Support\Seo;
use App\Support\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inscription en ligne (cahier §3.5) : consultation des niveaux et formules, choix du niveau, du mode et de la durée,
 * informations personnelles, mode de paiement, confirmation et récapitulatif. Assistant en étapes (ettc.js), envoi en
 * AJAX ; sans JavaScript, le formulaire complet s'envoie normalement. Un robot reçoit la même réponse, rien n'est créé.
 */
class InscriptionController extends Controller
{
    public function __construct(private readonly InscriptionService $service) {}

    public function create(Request $request): View
    {
        $niveaux = Niveau::published()->ordered()->get();
        $formules = Formule::published()->ordered()->get();

        return view('site.inscriptions.create', [
            'niveaux' => $niveaux,
            'formules' => $formules,
            // Numéros et QR mobile money du centre, montrés dès que « Payer en ligne » est choisi (étape 4).
            'mobileMoney' => app(PaiementService::class)->merchantNumbers(),
            'mobileMoneyQr' => app(PaiementService::class)->merchantQrCodes(),
            'preset' => [
                'niveau' => $niveaux->firstWhere('slug', (string) $request->query('niveau'))?->id,
                'formule' => $formules->firstWhere('slug', (string) $request->query('formule'))?->id ?? ($formules->count() === 1 ? $formules->first()->id : null),
                'mode' => FormationMode::tryFrom((string) $request->query('mode'))?->value,
            ],
            'seo' => Seo::make('Inscription à une formation', 'Inscrivez-vous en ligne à une formation d\'anglais : choisissez votre niveau, votre formule et votre mode, puis réglez en ligne ou au centre.')->noIndex(),
        ]);
    }

    public function store(InscriptionRequest $request): JsonResponse|RedirectResponse
    {
        $title = 'Inscription envoyée';
        $payment = null;
        $message = 'Merci ! Votre inscription est enregistrée : le centre vérifie votre dossier et vous recontacte rapidement.';

        if (! SpamGuard::isSpam($request)) {
            $inscription = $this->service->submit($request->validated(), $request);
            $message = "Merci ! Votre inscription est enregistrée sous la référence {$inscription->number} : {$inscription->summary()}, "
                .Formule::money($inscription->total_amount).' au total ('.$inscription->priceLabel().'). '
                .($inscription->niveau ? '' : 'Le centre vous contacte d\'abord pour le test de niveau (gratuit). ')
                .'Un récapitulatif vous est envoyé'.($inscription->email ? ' par e-mail' : ' par le centre').'.';
            $declared = $inscription->getRelation('declaredPayment');
            if ($declared) {
                // Paiement déjà fait : en vérification ; l'e-mail de confirmation (avec le reçu) suit la validation.
                $title = 'Inscription et paiement envoyés';
                $message = "Merci ! Votre inscription {$inscription->number} est enregistrée et votre paiement de {$declared->money()} ({$declared->method->label()}, référence {$declared->reference}) est en cours de vérification. "
                    .($inscription->email ? 'Dès qu\'il est confirmé, vous recevez par e-mail la confirmation de votre inscription et votre reçu.' : 'Dès qu\'il est confirmé, le centre vous contacte avec votre reçu.');
            } else {
                // Pas encore payé : comment payer par mobile money (numéros et QR du centre).
                $payment = app(PaiementService::class)->onlinePaymentInfo($inscription);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['title' => $title, 'message' => $message, 'payment' => $payment]);
        }

        return back()->with('demande_sent', ['title' => $title, 'message' => $message, 'payment' => $payment]);
    }
}
