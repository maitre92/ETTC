<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\Medias\MediaService;
use App\Services\Paiements\Gateway\GatewayManager;
use App\Services\Paiements\PaiementService;
use App\Services\Paiements\ReceiptService;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paiements (cahier §3.8, §5 « Gestion financière ») : liste et recettes, paiements mobile money à vérifier, impayés,
 * enregistrement au centre, confirmation / rejet, annulation / remboursement, reçu imprimable, moyens de paiement.
 * Contrôleur mince : les règles vivent dans PaiementService ; formulaires envoyés en AJAX (crud-ajax.js).
 */
class PaiementController extends Controller
{
    public function __construct(private readonly PaiementService $service) {}

    public function index(SiteSettings $settings, GatewayManager $gateways): View
    {
        Gate::authorize('viewAny', Paiement::class);
        $month = now()->startOfMonth();

        return view('paiements.index', [
            'paiements' => Paiement::with('inscription')->latest('paid_at')->latest('id')->get(),
            'unpaid' => Inscription::unpaid()->whereIn('status', ['validee', 'en_cours', 'terminee'])->orderBy('created_at')->get(),
            'monthTotal' => (int) Paiement::confirmed()->where('paid_at', '>=', $month)->sum('amount'),
            'gateway' => $gateways->active(),
            'refundedMonth' => (int) Paiement::where('status', PaymentStatus::Rembourse->value)->where('cancelled_at', '>=', $month)->sum('amount'),
            'qrCodes' => $this->service->merchantQrCodes(),
            'settings' => collect(PaymentMethod::mobileMoney())->mapWithKeys(fn (PaymentMethod $m) => [$m->settingKey() => $settings->get($m->settingKey())])
                ->put('paiement.instructions', $settings->get('paiement.instructions'))->all(),
        ]);
    }

    public function store(Request $request, Inscription $inscription): RedirectResponse
    {
        Gate::authorize('create', Paiement::class);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', Rule::requiredIf(fn () => $request->input('method') !== PaymentMethod::Especes->value), 'string', 'max:100'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'period' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['reference.required' => 'Indiquez la référence de la transaction mobile money.'], [
            'amount' => 'montant', 'method' => 'moyen de paiement', 'reference' => 'référence', 'payer_phone' => 'numéro du payeur',
            'period' => 'période', 'paid_at' => 'date du paiement', 'notes' => 'remarque',
        ]);

        $paiement = $this->service->record($inscription, $data, $request->user());

        $back = match ($request->input('back')) {
            'paiements' => route('paiements.index'),
            'inscriptions' => route('inscriptions.index'),
            default => route('inscriptions.show', $inscription),
        };

        return redirect()->to($back)->with('status', "Paiement enregistré : reçu {$paiement->number}.")->with('receipt_id', $paiement->id);
    }

    public function show(Paiement $paiement): View
    {
        Gate::authorize('view', $paiement);

        return view('paiements.show', [
            'paiement' => $paiement->load(['inscription', 'recorder', 'confirmer']),
            'history' => ActivityLog::where('subject_type', $paiement->getMorphClass())->where('subject_id', $paiement->id)->latest('id')->limit(20)->get(),
        ]);
    }

    /** Reçu PDF : ?format=a5 (défaut) ou thermique ; ?imprimer=1 l'affiche dans l'onglet pour l'imprimer. */
    public function receipt(Request $request, Paiement $paiement, ReceiptService $receipts): Response
    {
        Gate::authorize('view', $paiement);
        abort_unless($paiement->status->hasReceipt(), 404);
        $format = $request->query('format') === 'thermique' ? 'thermique' : 'a5';
        $pdf = $receipts->pdf($paiement, $format);

        return $request->boolean('imprimer') ? $pdf->stream($receipts->filename($paiement, $format)) : $pdf->download($receipts->filename($paiement, $format));
    }

    public function confirm(Request $request, Paiement $paiement): RedirectResponse
    {
        Gate::authorize('verify', $paiement);
        $this->service->confirm($paiement, $request->user());

        return redirect()->route('paiements.show', $paiement)->with('status', 'Paiement confirmé : le reçu est envoyé à l\'apprenant.');
    }

    public function reject(Request $request, Paiement $paiement): RedirectResponse
    {
        Gate::authorize('verify', $paiement);
        $this->service->reject($paiement, $this->reason($request), $request->user());

        return redirect()->route('paiements.show', $paiement)->with('status', 'Paiement rejeté.');
    }

    public function cancel(Request $request, Paiement $paiement): RedirectResponse
    {
        Gate::authorize('refund', $paiement);
        $this->service->cancel($paiement, $this->reason($request), $request->user());

        return redirect()->route('paiements.show', $paiement)->with('status', 'Paiement annulé.');
    }

    public function refund(Request $request, Paiement $paiement): RedirectResponse
    {
        Gate::authorize('refund', $paiement);
        $this->service->refund($paiement, $this->reason($request), $request->user());

        return redirect()->route('paiements.show', $paiement)->with('status', 'Remboursement enregistré.');
    }

    /** Numéros mobile money et consignes affichés à l'apprenant (paiement en ligne). */
    public function settings(Request $request, MediaService $medias): RedirectResponse
    {
        abort_unless($request->user()->can('paiements.configure'), 403);
        $rules = ['instructions' => ['nullable', 'string', 'max:1000']];
        $names = [];
        foreach (PaymentMethod::mobileMoney() as $method) {
            $rules[$method->value] = ['nullable', 'string', 'max:60'];
            $rules[$method->value.'_qr'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $names[$method->value] = 'numéro '.$method->label();
            $names[$method->value.'_qr'] = 'QR '.$method->label();
        }
        $data = $request->validate($rules, [], $names);

        $values = ['paiement.instructions' => $data['instructions'] ?? ''];
        foreach (PaymentMethod::mobileMoney() as $method) {
            $values[$method->settingKey()] = $data[$method->value] ?? '';
            // QR marchand : nouvelle image déposée, ou retirée (case « Retirer »).
            if ($request->hasFile($method->value.'_qr')) {
                $values[$method->qrSettingKey()] = (string) $medias->store($request->file($method->value.'_qr'), $request->user(), 'QR '.$method->label(), 'Paiements')->id;
            } elseif ($request->boolean($method->value.'_qr_remove')) {
                $values[$method->qrSettingKey()] = '';
            }
        }
        $this->service->saveSettings($values, $request->user());

        return redirect()->route('paiements.index')->with('status', 'Moyens de paiement mis à jour.');
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => 'motif'])['reason'];
    }
}
