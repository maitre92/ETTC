<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\Attestations\AttestationService;
use App\Services\Paiements\Gateway\SimulationGateway;
use App\Services\Paiements\PaiementService;
use App\Services\Paiements\ReceiptService;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Notification serveur du prestataire de paiement (notify_url) : simple signal. Le site retrouve la transaction et relit
 * son état chez le prestataire (PaiementService::synchronize) ; le contenu reçu n'est jamais cru tel quel.
 * Page « opérateur simulé » : développement et démonstration seulement.
 */
class PaiementNotificationController extends Controller
{
    public function notify(Request $request, string $gateway, PaiementService $service): Response
    {
        // CinetPay : cpm_trans_id ; autres prestataires : transaction_id.
        $transaction = (string) ($request->input('cpm_trans_id') ?? $request->input('transaction_id') ?? '');
        $paiement = $transaction !== '' ? Paiement::where('gateway', $gateway)->where('gateway_transaction_id', $transaction)->first() : null;
        if ($paiement) {
            $service->synchronize($paiement);
        }

        return response('OK', 200);
    }

    /** Vérification publique d'un reçu (QR code imprimé) : authentique si le numéro et l'empreinte correspondent. */
    public function verify(string $number, string $key, ReceiptService $receipts): View
    {
        $paiement = Paiement::with('inscription')->where('number', $number)->first();
        $valid = $paiement && $paiement->status->hasReceipt() && $receipts->matches($paiement, $key);

        return view('site.paiements.verification', ['paiement' => $valid ? $paiement : null, 'number' => $number, 'seo' => Seo::make('Vérification d\'un reçu')->noIndex()]);
    }

    /** Vérification publique d'une attestation (QR du certificat) : nom, formation, période, référence. */
    public function verifyCertificate(int $inscription, string $key, AttestationService $attestations): View
    {
        $found = Inscription::with('niveau')->find($inscription);
        $valid = $found && $attestations->matches($found, $key);

        return view('site.paiements.certificat', [
            'inscription' => $valid ? $found : null,
            'training' => $valid ? $attestations->trainingName($found) : null,
            'period' => $valid ? $attestations->period($found) : null,
            'seo' => Seo::make('Vérification d\'une attestation')->noIndex(),
        ]);
    }

    public function simulation(Request $request, string $transaction): View
    {
        abort_if(app()->isProduction(), 404);
        $paiement = Paiement::with('inscription')->where('gateway', 'simulation')->where('gateway_transaction_id', $transaction)->firstOrFail();

        return view('site.paiements.simulation', ['paiement' => $paiement, 'transaction' => $transaction]);
    }

    public function simulate(Request $request, string $transaction, SimulationGateway $gateway, PaiementService $service): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);
        $urls = $gateway->decide($transaction, $request->boolean('accept'));
        abort_if($urls === null, 404);
        // L'« opérateur » prévient le site (notification serveur), puis renvoie l'apprenant sur le site.
        $paiement = Paiement::where('gateway', 'simulation')->where('gateway_transaction_id', $transaction)->first();
        if ($paiement) {
            $service->synchronize($paiement);
        }

        return redirect()->to($urls['return']);
    }
}
