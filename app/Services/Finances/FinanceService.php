<?php

namespace App\Services\Finances;

use App\Enums\InscriptionStatus;
use App\Enums\PaymentNature;
use App\Enums\PaymentStatus;
use App\Models\CommissionVersement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord financier (cahier §5, §8) : recettes par période, frais de test et de formation séparés, part du
 * centre, commission du développeur (figée sur chaque paiement confirmé), commissions versées et restant à payer,
 * statistiques des inscriptions. Seuls les paiements CONFIRMÉS comptent (remboursés et annulés exclus).
 */
class FinanceService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Totaux d'une période (dates incluses).
     *
     * @return array{test: int, formation: int, centre: int, commission: int, paid: int, due: int, count: int, pending: int, refunded: int}
     */
    public function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $confirmed = $this->confirmed()->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()]);
        $formation = (int) (clone $confirmed)->where('nature', PaymentNature::Formation->value)->sum('amount');
        $commission = (int) (clone $confirmed)->sum('commission_amount');
        $periods = $this->months($from, $to)->map->format('Y-m');
        $paid = (int) CommissionVersement::whereIn('period', $periods)->sum('amount');

        return [
            'test' => 0, // test de niveau gratuit (décision du centre, 2026-11-17)
            'formation' => $formation,
            'centre' => $formation - $commission,
            'commission' => $commission,
            'paid' => $paid,
            'due' => $commission - $paid,
            'count' => (clone $confirmed)->count(),
            'pending' => (int) Paiement::where('status', PaymentStatus::AVerifier->value)->sum('amount'),
            'refunded' => (int) Paiement::where('status', PaymentStatus::Rembourse->value)->whereBetween('cancelled_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount'),
        ];
    }

    /**
     * Mois par mois (du plus récent au plus ancien) : encaissé, part du centre, commission, versé, reste.
     *
     * @return Collection<int, array{period: string, label: string, formation: int, centre: int, commission: int, paid: int, due: int}>
     */
    public function monthly(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        // Regroupement par mois en PHP (portable : MariaDB en mode strict refuse un GROUP BY sur un alias calculé).
        $rows = $this->confirmed()->whereBetween('paid_at', [$from->startOfMonth(), $to->endOfMonth()])
            ->get(['paid_at', 'nature', 'amount', 'commission_amount'])
            ->groupBy(fn (Paiement $p) => $p->paid_at->format('Y-m'))
            ->map(fn (Collection $items) => (object) [
                'formation' => $items->filter(fn (Paiement $p) => $p->nature === PaymentNature::Formation)->sum('amount'),
                'commission' => $items->sum('commission_amount'),
            ]);
        $paid = CommissionVersement::selectRaw('period, SUM(amount) as total')->groupBy('period')->pluck('total', 'period');

        return $this->months($from, $to)->reverse()->values()->map(function (CarbonImmutable $month) use ($rows, $paid) {
            $key = $month->format('Y-m');
            $formation = (int) ($rows[$key]->formation ?? 0);
            $commission = (int) ($rows[$key]->commission ?? 0);
            $versed = (int) ($paid[$key] ?? 0);

            return ['period' => $key, 'label' => ucfirst($month->translatedFormat('F Y')), 'formation' => $formation, 'centre' => $formation - $commission, 'commission' => $commission, 'paid' => $versed, 'due' => $commission - $versed];
        });
    }

    /** Depuis le début : commission totale, versée, reste à payer. @return array{commission: int, paid: int, due: int} */
    public function overall(): array
    {
        $commission = (int) $this->confirmed()->sum('commission_amount');
        $paid = (int) CommissionVersement::sum('amount');

        return ['commission' => $commission, 'paid' => $paid, 'due' => $commission - $paid];
    }

    /** Statistiques du cahier §5 : apprenants, inscriptions par niveau, formule et mode, en attente. @return array<string, mixed> */
    public function stats(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $period = Inscription::with('niveau')->where('status', '!=', InscriptionStatus::Annulee->value)->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->get();

        return [
            'inscriptions' => $period->count(),
            'learners' => Inscription::whereIn('status', [InscriptionStatus::Validee->value, InscriptionStatus::EnCours->value])->count(),
            'unpaid' => (int) Inscription::unpaid()->whereIn('status', [InscriptionStatus::Validee->value, InscriptionStatus::EnCours->value, InscriptionStatus::Terminee->value])->get()->sum(fn (Inscription $i) => $i->balance()),
            'byNiveau' => $period->groupBy(fn ($i) => $i->niveau?->name ?? 'À déterminer')->map->count()->sortDesc(),
            'byFormule' => $period->groupBy('formule_name')->map->count()->sortDesc(),
            'byMode' => $period->groupBy(fn ($i) => $i->mode->label())->map->count()->sortDesc(),
        ];
    }

    /** @return Collection<int, Paiement> */
    public function payments(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->confirmed()->with('inscription')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->orderBy('paid_at')->get();
    }

    /** @param  array{period: string, amount: int|string, paid_on: string, method?: ?string, reference?: ?string, notes?: ?string}  $data */
    public function recordPayout(array $data, User $actor): CommissionVersement
    {
        return DB::transaction(function () use ($data, $actor) {
            $versement = CommissionVersement::withoutActivityLog(fn () => CommissionVersement::create($data + ['recorded_by' => $actor->id]));
            $this->logger->log('finances.payout_recorded', 'finances', 'Commission versée pour '.$data['period'].' : '.number_format((int) $data['amount'], 0, ',', ' ').' FCFA', $versement, new: ['amount' => (int) $data['amount']], actor: $actor);

            return $versement;
        });
    }

    private function confirmed()
    {
        return Paiement::where('status', PaymentStatus::Confirme->value);
    }

    /** @return Collection<int, CarbonImmutable> */
    private function months(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $months = collect();
        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            $months->push($month);
        }

        return $months;
    }
}
