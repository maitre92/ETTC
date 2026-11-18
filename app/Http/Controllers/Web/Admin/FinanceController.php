<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionVersement;
use App\Services\Audit\ActivityLogger;
use App\Services\Finances\CommissionCalculator;
use App\Services\Finances\FinanceService;
use App\Support\SiteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finances et commission (cahier §5, §8) : une période au choix (ce mois, mois dernier, cette année, tout, ou dates),
 * totaux, mois par mois avec « Enregistrer un versement », statistiques, rapports PDF et Excel, réglage du taux.
 */
class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finances) {}

    public function index(Request $request, CommissionCalculator $calculator): View
    {
        abort_unless($request->user()->can('finances.view'), 403);
        [$from, $to, $preset] = $this->period($request);

        return view('finances.index', [
            'from' => $from, 'to' => $to, 'preset' => $preset,
            'totals' => $this->finances->totals($from, $to),
            'overall' => $this->finances->overall(),
            // Seulement les mois qui ont de l'activité (et le mois en cours) : un tableau court et lisible.
            'monthly' => $this->finances->monthly($from->startOfMonth()->max(CarbonImmutable::now()->subMonths(23)->startOfMonth()), $to)
                ->filter(fn (array $row) => $row['formation'] || $row['paid'] || $row['due'] || $row['period'] === now()->format('Y-m'))->values(),
            'stats' => $this->finances->stats($from, $to),
            'payouts' => CommissionVersement::with('recorder')->latest('paid_on')->latest('id')->limit(12)->get(),
            'rate' => $calculator->rate(),
            'includesPersonalised' => $calculator->includesPersonalised(),
        ]);
    }

    public function payout(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('finances.payout'), 403);
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['period' => 'mois', 'amount' => 'montant', 'paid_on' => 'date du versement', 'method' => 'moyen', 'reference' => 'référence']);
        $this->finances->recordPayout($data, $request->user());

        return back()->with('status', 'Versement de la commission enregistré.');
    }

    public function settings(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->can('finances.configure'), 403);
        $data = $request->validate(['rate' => ['required', 'numeric', 'between:0,100']], [], ['rate' => 'taux de commission']);
        $values = ['finance.commission_rate' => rtrim(rtrim(number_format((float) $data['rate'], 2, '.', ''), '0'), '.'), 'finance.include_personnalisee' => $request->boolean('include_personnalisee') ? '1' : '0'];
        $settings->save($values);
        $logger->log('finances.settings_updated', 'finances', 'Commission : taux '.$values['finance.commission_rate'].' %, formations personnalisées '.($values['finance.include_personnalisee'] === '1' ? 'incluses' : 'exclues'), new: $values);

        return back()->with('status', 'Réglage enregistré : il s\'applique aux prochains paiements confirmés.');
    }

    /** Rapport PDF de la période (A4). */
    public function report(Request $request, SiteSettings $settings): Response
    {
        abort_unless($request->user()->can('finances.export'), 403);
        [$from, $to] = $this->period($request);
        $pdf = Pdf::loadView('pdf.rapport-finances', [
            'from' => $from, 'to' => $to, 'site' => $settings,
            'totals' => $this->finances->totals($from, $to),
            'monthly' => $this->finances->monthly($from, $to),
            'stats' => $this->finances->stats($from, $to),
            'payments' => $this->finances->payments($from, $to),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Rapport_financier_'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.pdf');
    }

    /** Paiements de la période pour Excel (CSV ; séparateur « ; », BOM UTF-8). */
    public function export(Request $request, ActivityLogger $logger): StreamedResponse
    {
        abort_unless($request->user()->can('finances.export'), 403);
        [$from, $to] = $this->period($request);
        $payments = $this->finances->payments($from, $to);
        $logger->log('finances.exported', 'finances', 'Export des paiements du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y'));

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Reçu', 'Date', 'Apprenant', 'Inscription', 'Formation', 'Nature', 'Moyen', 'Référence', 'Montant (FCFA)', 'Taux commission (%)', 'Commission (FCFA)', 'Part du centre (FCFA)'], ';');
            foreach ($payments as $p) {
                fputcsv($out, [$p->number, $p->paid_at->format('d/m/Y'), $p->inscription->name, $p->inscription->number, $p->inscription->formule_name, $p->nature->label(), $p->method->label(), $p->reference, $p->amount, $p->commission_rate ?? 0, $p->commission_amount, $p->amount - $p->commission_amount], ';');
            }
            fclose($out);
        }, 'Paiements_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function period(Request $request): array
    {
        $now = CarbonImmutable::now();
        $preset = (string) $request->query('periode', 'mois');

        return match ($preset) {
            'mois-dernier' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), $preset],
            'annee' => [$now->startOfYear(), $now->endOfDay(), $preset],
            'tout' => [CarbonImmutable::parse('2026-01-01'), $now->endOfDay(), $preset],
            'dates' => $this->custom($request, $now),
            default => [$now->startOfMonth(), $now->endOfDay(), 'mois'],
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function custom(Request $request, CarbonImmutable $now): array
    {
        try {
            $from = CarbonImmutable::parse((string) $request->query('du'))->startOfDay();
            $to = CarbonImmutable::parse((string) $request->query('au'))->endOfDay();
        } catch (\Throwable) {
            return [$now->startOfMonth(), $now->endOfDay(), 'mois'];
        }

        return $from->lte($to) ? [$from, $to, 'dates'] : [$to->startOfDay(), $from->endOfDay(), 'dates'];
    }
}
