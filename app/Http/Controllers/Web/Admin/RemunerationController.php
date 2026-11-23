<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\FormateurVersement;
use App\Models\User;
use App\Services\Attestations\AttestationAssets;
use App\Services\Audit\ActivityLogger;
use App\Services\Paiements\ReceiptService;
use App\Services\Remunerations\RemunerationService;
use App\Support\SiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Rémunération des formateurs (demande du centre, 2026-11-22) : mois choisi, part due à chaque formateur (détail par
 * paiement), versé, reste, « Payer » en modale, fiche PDF, paiements sans formateur à attribuer, réglage des taux.
 */
class RemunerationController extends Controller
{
    public function __construct(private readonly RemunerationService $service) {}

    public function index(Request $request): View
    {
        $period = $this->period($request);

        return view('remunerations.index', [
            'period' => $period,
            'month' => CarbonImmutable::createFromFormat('!Y-m', $period),
            'summary' => $this->service->summary($period),
            'payouts' => FormateurVersement::with(['formateur', 'recorder'])->where('period', $period)->latest('id')->get(),
            'rates' => ['personnalisee' => $this->service->rateLabel('personnalisee'), 'groupe' => $this->service->rateLabel('groupe'), 'test' => $this->service->rateLabel('test')],
            'settings' => app(SiteSettings::class),
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['amount' => 'montant', 'paid_on' => 'date', 'method' => 'moyen', 'reference' => 'référence']);
        $formateur = User::findOrFail($data['user_id']);
        $versement = $this->service->pay($formateur, $data, $request->user());

        return redirect()->route('remunerations.index', ['mois' => $data['period']])->with('status', "Versement enregistré : {$versement->number} ({$formateur->name}). Il sort de la caisse.");
    }

    public function cancel(Request $request, FormateurVersement $versement): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => 'motif'])['reason'];
        $this->service->cancel($versement, $reason, $request->user());

        return redirect()->route('remunerations.index', ['mois' => $versement->period])->with('status', "Versement {$versement->number} annulé.");
    }

    public function settings(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'rate_personnalisee' => ['required', 'string', 'max:10'],
            'rate_groupe' => ['required', 'string', 'max:10'],
        ], [], ['rate_personnalisee' => 'taux de la formation personnalisée', 'rate_groupe' => 'taux de la formation en groupe']);
        foreach ($data as $key => $value) {
            $rate = RemunerationService::parseRate($value);
            if ($rate <= 0 || $rate > 1) {
                throw ValidationException::withMessages([$key => 'Écrivez une fraction (ex. 1/3) ou un pourcentage (ex. 20).']);
            }
        }
        $values = ['formateurs.rate_personnalisee' => trim($data['rate_personnalisee']), 'formateurs.rate_groupe' => trim($data['rate_groupe'])];
        $settings->save($values);
        $logger->log('remunerations.settings_updated', 'remunerations', 'Taux des formateurs : personnalisée '.$values['formateurs.rate_personnalisee'].', en groupe '.$values['formateurs.rate_groupe'], new: $values);

        return redirect()->route('remunerations.index', ['mois' => $request->input('mois')])->with('status', 'Taux enregistrés : ils s\'appliquent au calcul de chaque mois.');
    }

    /** Fiche de rémunération d'un formateur pour le mois (PDF). */
    public function sheet(Request $request, User $formateur, SiteSettings $site): Response
    {
        $period = $this->period($request);
        $data = $this->service->forTeacher($formateur, $period);

        return Pdf::loadView('pdf.remuneration-formateur', [
            'formateur' => $formateur, 'period' => $period, 'month' => CarbonImmutable::createFromFormat('!Y-m', $period),
            'data' => $data, 'payouts' => FormateurVersement::valid()->where('user_id', $formateur->id)->where('period', $period)->get(),
            'rates' => ['personnalisee' => $this->service->rateLabel('personnalisee'), 'groupe' => $this->service->rateLabel('groupe'), 'test' => $this->service->rateLabel('test')],
            'site' => $site, 'logo' => ReceiptService::logoDataUri(),
            'signature' => app(AttestationAssets::class)->dataUri(app(AttestationAssets::class)->signaturePath()),
            'stamp' => app(AttestationAssets::class)->dataUri(app(AttestationAssets::class)->documentStampPath()),
            'signer' => app(AttestationAssets::class)->name(),
        ])->setPaper('a4')->download('Remuneration_'.str_replace(' ', '_', $formateur->name).'_'.$period.'.pdf');
    }

    private function period(Request $request): string
    {
        $value = (string) $request->query('mois', $request->input('mois', ''));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : now()->format('Y-m');
    }
}
