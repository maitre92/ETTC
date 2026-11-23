<?php

namespace App\Services\Remunerations;

use App\Enums\PaymentStatus;
use App\Models\FormateurVersement;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Support\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rémunération des formateurs (demande du centre, 2026-11-22) :
 *  - formation PERSONNALISÉE (individuelle, en ligne ou en présentiel ; formateur attribué à l'inscription) : 1/3 de
 *    la mensualité, soit le tiers de chaque paiement de formation encaissé ;
 *  - formation EN GROUPE (formateur du groupe) : 20 % du montant encaissé ;
 *  - TEST DE NIVEAU fait passer par un formateur : les frais d'inscription (5 000 F) de l'inscription qui suit lui
 *    reviennent en entier (test passé seul en ligne : ils restent au centre).
 * Calculée sur les frais de formation ENCAISSÉS (paiements confirmés) du mois, hors frais d'inscription ; taux
 * réglables (« 1/3 », « 20 »). Versements par mois et par formateur, annulables avec un motif, lus par la caisse.
 */
class RemunerationService
{
    public const PREFIX = 'RMF';

    public function __construct(
        private readonly SiteSettings $settings,
        private readonly ActivityLogger $logger,
        private readonly SequenceService $sequences,
    ) {}

    /** Taux réglé : « 1/3 », « 20 », « 20 % » ou « 33,33 » → fraction. */
    public static function parseRate(string $value): float
    {
        $value = trim(str_replace([',', '%', ' '], ['.', '', ''], $value));
        if (preg_match('~^(\d+(?:\.\d+)?)/(\d+(?:\.\d+)?)$~', $value, $m) && (float) $m[2] > 0) {
            return min(1, (float) $m[1] / (float) $m[2]);
        }

        return is_numeric($value) ? max(0, min(1, (float) $value / 100)) : 0.0;
    }

    /** @return array{personnalisee: float, groupe: float} */
    public function rates(): array
    {
        return [
            'personnalisee' => self::parseRate($this->settings->get('formateurs.rate_personnalisee')),
            'groupe' => self::parseRate($this->settings->get('formateurs.rate_groupe')),
        ];
    }

    /** « 1/3 » ou « 20 % » tel que réglé, pour l'affichage. */
    public function rateLabel(string $kind): string
    {
        if ($kind === 'test') {
            return '100 % des frais d\'inscription';
        }
        $raw = trim($this->settings->get('formateurs.rate_'.$kind));

        return str_contains($raw, '/') ? $raw : rtrim(rtrim(str_replace('.', ',', $raw), '0'), ',').' %';
    }

    /**
     * Lignes du mois : un paiement de formation confirmé = une ligne (apprenant, type, base hors frais d'inscription,
     * taux, part du formateur). Formateur : celui de l'inscription (personnalisée), sinon celui du groupe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(string $period): Collection
    {
        [$from, $to] = $this->bounds($period);
        $paiements = Paiement::with(['inscription.groupe', 'inscription.testNiveau'])->where('status', PaymentStatus::Confirme->value)->whereBetween('paid_at', [$from, $to])->get();
        $feeParts = $this->feeParts($paiements->pluck('inscription_id')->unique()->all());
        $rates = $this->rates();

        $lines = collect();
        foreach ($paiements as $p) {
            $inscription = $p->inscription;
            $kind = $inscription->isPersonalised() ? 'personnalisee' : 'groupe';
            $fee = $feeParts[$p->id] ?? 0;
            $base = max(0, $p->amount - $fee);
            if ($base > 0) {
                $lines->push([
                    'paiement' => $p, 'inscription' => $inscription, 'teacher_id' => $inscription->teacherId(), 'kind' => $kind,
                    'base' => $base, 'rate' => $rates[$kind], 'share' => (int) round($base * $rates[$kind]),
                ]);
            }
            // Test de niveau fait passer par un formateur : les frais d'inscription lui reviennent en entier.
            if ($fee > 0 && ($examiner = $inscription->testNiveau?->examiner_id)) {
                $lines->push(['paiement' => $p, 'inscription' => $inscription, 'teacher_id' => $examiner, 'kind' => 'test', 'base' => $fee, 'rate' => 1.0, 'share' => $fee]);
            }
        }

        return $lines;
    }

    /**
     * Récapitulatif par formateur : apprenants, base encaissée, part due, versé, reste ; et les paiements sans formateur.
     *
     * @return array{teachers: Collection<int, array<string, mixed>>, unassigned: Collection<int, array<string, mixed>>, totals: array{due: int, paid: int, remaining: int}}
     */
    public function summary(string $period): array
    {
        $lines = $this->lines($period);
        $payouts = FormateurVersement::valid()->where('period', $period)->get()->groupBy('user_id');
        $ids = $lines->pluck('teacher_id')->filter()->merge($payouts->keys())->unique();
        $users = User::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        $teachers = $ids->map(function (int $id) use ($lines, $payouts, $users) {
            $own = $lines->where('teacher_id', $id)->values();
            $due = (int) $own->sum('share');
            $paid = (int) ($payouts[$id] ?? collect())->sum('amount');

            return [
                'user' => $users[$id], 'lines' => $own, 'learners' => $own->pluck('inscription.id')->unique()->count(),
                'base' => (int) $own->sum('base'), 'due' => $due, 'paid' => $paid, 'remaining' => max(0, $due - $paid),
            ];
        })->sortBy(fn (array $t) => $t['user']->name)->values();

        return [
            'teachers' => $teachers,
            'unassigned' => $lines->whereNull('teacher_id')->values(),
            'totals' => ['due' => (int) $teachers->sum('due'), 'paid' => (int) $teachers->sum('paid'), 'remaining' => (int) $teachers->sum('remaining')],
        ];
    }

    /** Ce que gagne un formateur sur un mois (son espace). @return array{due: int, paid: int, remaining: int, lines: Collection<int, array<string, mixed>>} */
    public function forTeacher(User $user, string $period): array
    {
        $lines = $this->lines($period)->where('teacher_id', $user->id)->values();
        $due = (int) $lines->sum('share');
        $paid = (int) FormateurVersement::valid()->where('user_id', $user->id)->where('period', $period)->sum('amount');

        return ['due' => $due, 'paid' => $paid, 'remaining' => max(0, $due - $paid), 'lines' => $lines];
    }

    /** @param  array{period: string, amount: int|string, paid_on: string, method: string, reference?: ?string, notes?: ?string}  $data */
    public function pay(User $formateur, array $data, User $actor): FormateurVersement
    {
        $remaining = $this->forTeacher($formateur, $data['period'])['remaining'];
        if ((int) $data['amount'] > $remaining) {
            throw ValidationException::withMessages(['amount' => 'Le montant dépasse ce qui reste à payer pour ce mois ('.Formule::money($remaining).').']);
        }

        return DB::transaction(function () use ($formateur, $data, $actor) {
            $versement = FormateurVersement::withoutActivityLog(fn () => FormateurVersement::create([
                'number' => $this->sequences->next(self::PREFIX),
                'user_id' => $formateur->id,
                'period' => $data['period'],
                'amount' => (int) $data['amount'],
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'recorded_by' => $actor->id,
                'status' => 'valide',
            ]));
            $this->logger->log('remunerations.paid', 'remunerations', "Rémunération versée — {$versement->number} : {$formateur->name}, ".Formule::money($versement->amount)." ({$versement->period}, {$versement->method->shortLabel()})", $versement,
                new: ['user_id' => $formateur->id, 'period' => $versement->period, 'amount' => $versement->amount], actor: $actor);

            return $versement;
        });
    }

    public function cancel(FormateurVersement $versement, string $reason, User $actor): FormateurVersement
    {
        if ($versement->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'Ce versement est déjà annulé.']);
        }

        return DB::transaction(function () use ($versement, $reason, $actor) {
            FormateurVersement::withoutActivityLog(fn () => $versement->update(['status' => 'annule', 'cancel_reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $actor->id]));
            $this->logger->log('remunerations.cancelled', 'remunerations', "Versement au formateur annulé — {$versement->number} ({$versement->formateur->name}) : {$reason}", $versement,
                ['status' => 'valide'], ['status' => 'annule', 'cancel_reason' => $reason], $actor);

            return $versement;
        });
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function bounds(string $period): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $period);

        return [$start->startOfMonth(), $start->endOfMonth()];
    }

    /**
     * Part « frais d'inscription » de chaque paiement confirmé (payés en premier, dans l'ordre des paiements).
     *
     * @param  list<int>  $inscriptionIds
     * @return array<int, int>
     */
    private function feeParts(array $inscriptionIds): array
    {
        $parts = [];
        Paiement::with('inscription:id,registration_fee')->where('status', PaymentStatus::Confirme->value)->whereIn('inscription_id', $inscriptionIds)
            ->orderBy('paid_at')->orderBy('id')->get()->groupBy('inscription_id')
            ->each(function (Collection $payments) use (&$parts) {
                $left = (int) $payments->first()->inscription->registration_fee;
                foreach ($payments as $p) {
                    $part = min($p->amount, $left);
                    $parts[$p->id] = $part;
                    $left -= $part;
                }
            });

        return $parts;
    }
}
