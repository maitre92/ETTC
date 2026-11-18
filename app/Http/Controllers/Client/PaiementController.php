<?php

namespace App\Http\Controllers\Client;

use App\Enums\FormationMode;
use App\Enums\PaymentChoice;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\Paiements\Gateway\GatewayManager;
use App\Services\Paiements\PaiementService;
use App\Services\Paiements\ReceiptService;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paiements de l'apprenant (cahier §4 « historique de ses paiements », « télécharger ses reçus » ; §3.8) : reste à
 * payer par inscription, déclaration d'un paiement mobile money (formation en ligne ou paiement en ligne choisi),
 * historique et reçus. Un apprenant ne voit que ses inscriptions (Inscription::forClient).
 */
class PaiementController extends Controller
{
    public function __construct(private readonly PaiementService $service) {}

    public function index(Request $request, SiteSettings $settings): View
    {
        $client = $this->client($request);
        $inscriptions = Inscription::forClient($client)->with('paiements')->where('status', '!=', 'annulee')->latest('id')->get();

        return view('client.paiements', [
            'inscriptions' => $inscriptions,
            'paiements' => Paiement::forClient($client)->with('inscription')->latest('paid_at')->latest('id')->get(),
            'numbers' => $this->service->merchantNumbers(),
            'qrCodes' => $this->service->merchantQrCodes(),
            'gateway' => app(GatewayManager::class)->active(),
            'instructions' => $settings->get('paiement.instructions'),
            'seo' => Seo::make('Mes paiements')->noIndex(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $client = $this->client($request);
        $data = $request->validate([
            'inscription_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reference' => ['required', 'string', 'min:4', 'max:100'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'period' => ['nullable', 'string', 'max:100'],
        ], [], ['payment_method' => 'moyen de paiement', 'amount' => 'montant', 'reference' => 'référence de la transaction', 'payer_phone' => 'numéro utilisé', 'period' => 'période']);

        $inscription = Inscription::forClient($client)->whereKey($data['inscription_id'])->firstOrFail();
        abort_unless(self::paysOnline($inscription), 404);
        // Champ « payment_method » : un champ nommé « method » masquerait form.method dans les scripts du formulaire.
        $paiement = $this->service->declare($inscription, ['method' => $data['payment_method']] + $data, $client, $request);

        $message = "Merci ! Votre paiement de {$paiement->money()} ({$paiement->method->label()}, transaction {$paiement->reference}) est enregistré sous la référence {$paiement->number}. Le centre le vérifie, puis votre reçu apparaît ici et vous est envoyé par e-mail.";

        return $request->expectsJson()
            ? response()->json(['title' => 'Paiement déclaré', 'message' => $message])
            : redirect()->route('client.paiements')->with('status', $message);
    }

    /** Paiement automatique : ouvre la page de paiement du prestataire (Orange Money, Moov, Wave…). */
    public function online(Request $request, GatewayManager $gateways): RedirectResponse
    {
        $gateway = $gateways->active();
        abort_unless($gateway !== null, 404);
        $client = $this->client($request);
        $data = $request->validate([
            'inscription_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:100', 'max:100000000'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
        ], [], ['amount' => 'montant', 'payer_phone' => 'numéro de téléphone']);

        $inscription = Inscription::forClient($client)->whereKey($data['inscription_id'])->firstOrFail();
        abort_unless(self::paysOnline($inscription), 404);

        return redirect()->away($this->service->startOnline($inscription, (int) $data['amount'], $data['payer_phone'] ?? null, $client, $request, $gateway));
    }

    /** Retour de la page du prestataire : on relit l'état chez lui (jamais d'après l'adresse de retour). */
    public function return(Request $request, Paiement $paiement): RedirectResponse
    {
        abort_unless(Paiement::forClient($this->client($request))->whereKey($paiement->id)->exists(), 404);
        $paiement = $this->service->synchronize($paiement);

        return redirect()->route('client.paiements')->with('status', match ($paiement->status) {
            PaymentStatus::Confirme => "Paiement reçu : {$paiement->money()}. Merci ! Votre reçu {$paiement->number} est disponible ci-dessous.",
            PaymentStatus::Rejete => 'Le paiement n\'a pas abouti : aucun montant n\'a été pris en compte. Vous pouvez réessayer.',
            PaymentStatus::AVerifier => 'Paiement reçu : le centre le contrôle et vous envoie votre reçu.',
            default => 'Paiement en cours de confirmation par l\'opérateur : la page se met à jour dans quelques minutes.',
        });
    }

    /** Reçu PDF (A5 ou ticket thermique) : seulement pour ses propres paiements confirmés. */
    public function receipt(Request $request, Paiement $paiement, ReceiptService $receipts): Response
    {
        abort_unless(Paiement::forClient($this->client($request))->whereKey($paiement->id)->exists() && $paiement->status->hasReceipt(), 404);
        $format = $request->query('format') === 'thermique' ? 'thermique' : 'a5';
        $pdf = $receipts->pdf($paiement, $format);

        return $request->boolean('imprimer') ? $pdf->stream($receipts->filename($paiement, $format)) : $pdf->download($receipts->filename($paiement, $format));
    }

    /** En ligne = mobile money (formation en ligne ou paiement en ligne choisi) ; en présentiel, on règle au centre. */
    public static function paysOnline(Inscription $inscription): bool
    {
        return $inscription->mode === FormationMode::EnLigne || $inscription->payment_choice === PaymentChoice::EnLigne;
    }

    private function client(Request $request): Client
    {
        return $request->user('client');
    }
}
