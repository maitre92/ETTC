<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CashCategory;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\MouvementCaisse;
use App\Services\Caisse\CaisseService;
use App\Services\Paiements\ReceiptService;
use App\Support\SiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Caisse (demande du centre, 2026-11-21) : soldes par moyen, journal de la période (paiements des apprenants,
 * remboursements, commission, revenus, dépenses, transferts), saisie en modale, annulation motivée, rapports.
 */
class CaisseController extends Controller
{
    public const PRESETS = ['jour' => 'Aujourd\'hui', 'mois' => 'Ce mois', 'mois-dernier' => 'Mois dernier', 'annee' => 'Cette année'];

    public function __construct(private readonly CaisseService $service) {}

    public function index(Request $request): View
    {
        [$from, $to, $preset] = $this->period($request);
        $family = array_key_exists((string) $request->query('moyen'), PaymentMethod::FAMILIES) ? (string) $request->query('moyen') : null;
        $journal = $this->service->journal($from, $to, $family);

        return view('caisse.index', [
            'journal' => $journal,
            'totals' => $this->service->totals($journal),
            'balances' => $this->service->balances(),
            'from' => $from, 'to' => $to, 'preset' => $preset, 'family' => $family,
            'presets' => self::PRESETS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = (string) $request->input('type');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(MouvementCaisse::TYPES))],
            'category' => ['required', Rule::in(array_keys(CashCategory::optionsFor($type)))],
            'label' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'to_method' => ['nullable', Rule::requiredIf($type === 'transfert'), Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'party' => ['nullable', 'string', 'max:150'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'category.in' => 'Choisissez une catégorie.',
            'to_method.required' => 'Choisissez où va l\'argent.',
            'occurred_on.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ], [
            'type' => 'type', 'category' => 'catégorie', 'label' => 'libellé', 'amount' => 'montant', 'method' => 'moyen', 'to_method' => 'vers',
            'reference' => 'référence', 'party' => 'bénéficiaire ou payeur', 'occurred_on' => 'date', 'notes' => 'remarque', 'proof' => 'justificatif',
        ]);
        $mouvement = $this->service->record($data, $request->file('proof'), $request->user());
        $what = ['entree' => 'Revenu', 'sortie' => 'Dépense', 'transfert' => 'Transfert'][$mouvement->type];

        return redirect()->route('caisse.index', $request->only('periode', 'du', 'au', 'moyen'))->with('status', "{$what} enregistré : {$mouvement->number}.");
    }

    public function cancel(Request $request, MouvementCaisse $mouvement): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => 'motif'])['reason'];
        $this->service->cancel($mouvement, $reason, $request->user());

        return back()->with('status', "Mouvement {$mouvement->number} annulé.");
    }

    public function proof(MouvementCaisse $mouvement): StreamedResponse
    {
        abort_unless($mouvement->proof_path && Storage::disk('local')->exists($mouvement->proof_path), 404);

        return Storage::disk('local')->response($mouvement->proof_path, $mouvement->proof_name);
    }

    /** Journal de la période pour Excel (CSV ; séparateur « ; », BOM UTF-8). */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $journal = $this->service->journal($from, $to);

        return response()->streamDownload(function () use ($journal) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'N°', 'Libellé', 'Catégorie', 'Moyen', 'Entrée', 'Sortie', 'Transfert', 'Statut'], ';');
            foreach ($journal->reverse() as $r) {
                fputcsv($out, [$r['date']->format('d/m/Y'), $r['number'], $r['label'], $r['category'],
                    $r['method']->shortLabel().($r['to_method'] ? ' → '.$r['to_method']->shortLabel() : ''),
                    $r['in'] ?: '', $r['out'] ?: '', $r['transfer'] ?: '', $r['cancelled'] ? 'Annulé' : 'Valide'], ';');
            }
            fclose($out);
        }, 'caisse-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Journal de caisse en PDF (en-tête commun des documents). */
    public function report(Request $request, SiteSettings $site): Response
    {
        [$from, $to] = $this->period($request);
        $journal = $this->service->journal($from, $to);

        return Pdf::loadView('pdf.journal-caisse', [
            'journal' => $journal->reverse()->values(),
            'totals' => $this->service->totals($journal),
            'balances' => $this->service->balances($to),
            'from' => $from, 'to' => $to, 'site' => $site,
            'logo' => ReceiptService::logoDataUri(),
        ])->setPaper('a4')->download('journal-caisse-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function period(Request $request): array
    {
        $now = CarbonImmutable::now();
        $preset = (string) $request->query('periode', 'jour');
        if ($preset === 'dates') {
            try {
                $from = CarbonImmutable::parse((string) $request->query('du'))->startOfDay();
                $to = CarbonImmutable::parse((string) $request->query('au'))->endOfDay();

                return $from->lte($to) ? [$from, $to, 'dates'] : [$to->startOfDay(), $from->endOfDay(), 'dates'];
            } catch (\Throwable) {
                $preset = 'jour';
            }
        }

        return match ($preset) {
            'mois' => [$now->startOfMonth(), $now->endOfDay(), $preset],
            'mois-dernier' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), $preset],
            'annee' => [$now->startOfYear(), $now->endOfDay(), $preset],
            default => [$now->startOfDay(), $now->endOfDay(), 'jour'],
        };
    }
}
