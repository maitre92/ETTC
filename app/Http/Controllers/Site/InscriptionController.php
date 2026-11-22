<?php

namespace App\Http\Controllers\Site;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\InscriptionRequest;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Services\Formations\Tarification;
use App\Services\Inscriptions\InscriptionService;
use App\Services\Paiements\PaiementService;
use App\Support\Seo;
use App\Support\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'mobileMoneyUssd' => app(PaiementService::class)->merchantUssd(),
            // Frais d'inscription et promotions automatiques en cours, affichés sur les cartes des formules.
            'fee' => app(Tarification::class)->registrationFee(),
            'promos' => app(Tarification::class)->automaticDiscounts($formules),
            'preset' => [
                'niveau' => $niveaux->firstWhere('slug', (string) $request->query('niveau'))?->id,
                'formule' => $formules->firstWhere('slug', (string) $request->query('formule'))?->id ?? ($formules->count() === 1 ? $formules->first()->id : null),
                'mode' => FormationMode::tryFrom((string) $request->query('mode'))?->value,
            ],
            'seo' => Seo::make('Inscription à une formation', 'Inscrivez-vous en ligne à une formation d\'anglais : choisissez votre niveau, votre formule et votre mode, puis réglez en ligne ou au centre.')->noIndex(),
        ]);
    }

    /**
     * Tarif détaillé d'une formule dans un mode, avec le code promo saisi : même calcul que l'enregistrement
     * (Tarification). Un code non valable n'empêche pas le calcul : il est signalé.
     */
    public function tarif(Request $request, Tarification $tarification): JsonResponse
    {
        $formule = Formule::published()->find((int) $request->query('formule_id'));
        $mode = FormationMode::tryFrom((string) $request->query('mode'));
        if (! $formule || ! $mode || $formule->{$mode->priceColumn()} === null) {
            return response()->json(['ok' => false, 'message' => 'Cette formule n\'est pas proposée dans ce mode.']);
        }
        $code = Promotion::normalizeCode((string) $request->query('promo_code'));
        $withFee = ! $request->boolean('sans_frais');
        $codeError = null;
        try {
            $q = $tarification->quote($formule, $mode, $code, $withFee);
        } catch (ValidationException $e) {
            $codeError = collect($e->errors())->flatten()->first();
            $q = $tarification->quote($formule, $mode, null, $withFee);
        }
        $monthly = $q['billing'] === Billing::Mensuel && $q['duration_months'] > 1;
        $formation = $q['formation_amount'] - $q['discount_amount'];
        $installment = $monthly ? (int) ceil($formation / $q['duration_months']) : $formation;
        $lines = [['Frais de formation'.($monthly ? ' ('.Formule::money($q['unit_price']).' × '.$q['duration_months'].' mois)' : ''), Formule::money($q['formation_amount']), '']];
        if ($q['discount_amount'] > 0) {
            $lines[] = ['Promotion « '.$q['promotion']->name.' » '.$q['promotion']->badge(), '-'.Formule::money($q['discount_amount']), 'promo'];
        }
        if ($q['registration_fee'] > 0) {
            $lines[] = ["Frais d'inscription (une seule fois)", Formule::money($q['registration_fee']), ''];
        }
        $lines[] = ['Total à payer', Formule::money($q['total_amount']), 'total'];

        return response()->json([
            'ok' => true,
            'lines' => $lines,
            'total' => $q['total_amount'],
            'first' => $q['registration_fee'] + $installment,
            'first_label' => $q['registration_fee'] > 0
                ? Formule::money($q['registration_fee'] + $installment).' ('.($monthly ? '1er mois' : 'formation').' + frais d\'inscription)'
                : Formule::money($installment),
            'promo' => $q['promotion'] ? ['name' => $q['promotion']->name, 'badge' => $q['promotion']->badge(), 'code' => $q['promo_code']] : null,
            'code_error' => $codeError,
            'code_ok' => $code && ! $codeError && $q['promo_code'] === $code,
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
                .Formule::money($inscription->total_amount).' au total'
                .($inscription->registration_fee > 0 ? ', dont '.Formule::money($inscription->registration_fee).' de frais d\'inscription' : '')
                .($inscription->discount_amount > 0 ? ', promotion de '.Formule::money($inscription->discount_amount).' déduite' : '')
                .'. Premier paiement : '.Formule::money($inscription->nextInstallment()).'. '
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
