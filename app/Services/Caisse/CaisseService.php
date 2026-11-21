<?php

namespace App\Services\Caisse;

use App\Enums\CashCategory;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CommissionVersement;
use App\Models\Formule;
use App\Models\MouvementCaisse;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Caisse du centre (demande du centre, 2026-11-21) : journal des entrées et sorties, soldes par moyen (espèces, mobile
 * money, banque). Sources :
 *  - AUTOMATIQUES (lues dans leur table, jamais recopiées) : frais de formation encaissés (paiements confirmés),
 *    remboursements (sortie à la date du remboursement), versements de la commission ;
 *  - SAISIES : revenus divers, dépenses (justificatif facultatif), transferts entre moyens (dépôt en banque…).
 * Un mouvement saisi ne se supprime jamais : il s'annule avec un motif (audit).
 */
class CaisseService
{
    public const PREFIX = 'CAI';

    public function __construct(private readonly ActivityLogger $logger, private readonly SequenceService $sequences) {}

    /**
     * @param  array{type: string, category: string, label: string, amount: int|string, method: string, to_method?: ?string, reference?: ?string, party?: ?string, occurred_on: string, notes?: ?string}  $data
     */
    public function record(array $data, ?UploadedFile $proof, User $actor): MouvementCaisse
    {
        $category = CashCategory::from($data['category']);
        if ($category->type() !== $data['type']) {
            throw ValidationException::withMessages(['category' => 'Cette catégorie ne correspond pas au type de mouvement.']);
        }
        if ($data['type'] === 'transfert' && ($data['to_method'] ?? null) === $data['method']) {
            throw ValidationException::withMessages(['to_method' => 'Choisissez un moyen d\'arrivée différent du moyen de départ.']);
        }

        return DB::transaction(function () use ($data, $category, $proof, $actor) {
            $file = $proof ? [
                'proof_path' => $proof->storeAs('caisse', Str::random(32).'.'.$proof->extension(), 'local'),
                'proof_name' => Str::limit($proof->getClientOriginalName(), 250, ''),
            ] : [];
            $mouvement = MouvementCaisse::withoutActivityLog(fn () => MouvementCaisse::create([
                'number' => $this->sequences->next(self::PREFIX),
                'type' => $data['type'],
                'category' => $category,
                'label' => trim($data['label']),
                'amount' => (int) $data['amount'],
                'method' => $data['method'],
                'to_method' => $data['type'] === 'transfert' ? $data['to_method'] : null,
                'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
                'party' => filled($data['party'] ?? null) ? trim($data['party']) : null,
                'occurred_on' => $data['occurred_on'],
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'recorded_by' => $actor->id,
                'status' => 'valide',
            ] + $file));
            $what = MouvementCaisse::TYPES[$mouvement->type];
            $this->logger->log('caisse.recorded', 'caisse', "{$what} — {$mouvement->number} : {$mouvement->label}, ".Formule::money($mouvement->amount).' ('.$mouvement->method->shortLabel().($mouvement->to_method ? ' → '.$mouvement->to_method->shortLabel() : '').')', $mouvement,
                new: ['type' => $mouvement->type, 'category' => $category->value, 'amount' => $mouvement->amount, 'method' => $mouvement->method->value], actor: $actor);

            return $mouvement;
        });
    }

    public function cancel(MouvementCaisse $mouvement, string $reason, User $actor): MouvementCaisse
    {
        if ($mouvement->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'Ce mouvement est déjà annulé.']);
        }

        return DB::transaction(function () use ($mouvement, $reason, $actor) {
            MouvementCaisse::withoutActivityLog(fn () => $mouvement->update(['status' => 'annule', 'cancel_reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $actor->id]));
            $this->logger->log('caisse.cancelled', 'caisse', "Mouvement de caisse annulé — {$mouvement->number} ({$mouvement->label}) : {$reason}", $mouvement,
                ['status' => 'valide'], ['status' => 'annule', 'cancel_reason' => $reason], $actor);

            return $mouvement;
        });
    }

    /**
     * Journal de la période, du plus récent au plus ancien. Chaque ligne : date, numéro, libellé, catégorie, moyen,
     * entrée, sortie, transfert, source (paiement, remboursement, commission, manuel), objet, annulé.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function journal(CarbonImmutable $from, CarbonImmutable $to, ?string $family = null): Collection
    {
        $rows = collect();

        Paiement::with('inscription')->whereIn('status', [PaymentStatus::Confirme->value, PaymentStatus::Rembourse->value])
            ->whereBetween('paid_at', [$from, $to])->get()
            ->each(function (Paiement $p) use ($rows) {
                $rows->push($this->row($p->paid_at, $p->number, 'Frais de formation — '.($p->inscription?->name ?? ''), 'Frais de formation', $p->method, $p->amount, 0, 'paiement', $p));
            });
        Paiement::with('inscription')->where('status', PaymentStatus::Rembourse->value)->whereBetween('cancelled_at', [$from, $to])->get()
            ->each(function (Paiement $p) use ($rows) {
                $rows->push($this->row($p->cancelled_at, $p->number, 'Remboursement — '.($p->inscription?->name ?? '').($p->cancel_reason ? ' ('.$p->cancel_reason.')' : ''), 'Remboursement', $p->method, 0, $p->amount, 'remboursement', $p));
            });
        CommissionVersement::whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->get()
            ->each(function (CommissionVersement $v) use ($rows) {
                $rows->push($this->row(CarbonImmutable::parse($v->paid_on), 'COM-'.$v->period, 'Commission du développeur ('.$v->period.')', 'Commission versée', self::payoutMethod($v->method), 0, $v->amount, 'commission', $v));
            });
        MouvementCaisse::with(['recorder', 'canceller'])->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])->get()
            ->each(function (MouvementCaisse $m) use ($rows) {
                $row = $this->row(CarbonImmutable::parse($m->occurred_on), $m->number, $m->label.($m->party ? ' — '.$m->party : ''), $m->category->label(), $m->method,
                    $m->type === 'entree' ? $m->amount : 0, $m->type === 'sortie' ? $m->amount : 0, 'manuel', $m);
                $row['transfer'] = $m->type === 'transfert' ? $m->amount : 0;
                $row['to_method'] = $m->to_method;
                $row['cancelled'] = $m->isCancelled();
                $rows->push($row);
            });

        if ($family) {
            $rows = $rows->filter(fn (array $r) => $r['method']->family() === $family || ($r['to_method'] && $r['to_method']->family() === $family));
        }

        return $rows->sortByDesc(fn (array $r) => $r['date']->format('Y-m-d H:i:s').'|'.$r['number'])->values();
    }

    /**
     * Totaux d'une période (mouvements annulés exclus ; transferts hors revenus et dépenses) et répartition par catégorie.
     *
     * @return array{in: int, out: int, result: int, by_category: array<string, array{in: int, out: int}>}
     */
    public function totals(Collection $journal): array
    {
        $valid = $journal->reject(fn (array $r) => $r['cancelled']);
        $byCategory = [];
        foreach ($valid as $r) {
            if ($r['transfer']) {
                continue;
            }
            $byCategory[$r['category']]['in'] = ($byCategory[$r['category']]['in'] ?? 0) + $r['in'];
            $byCategory[$r['category']]['out'] = ($byCategory[$r['category']]['out'] ?? 0) + $r['out'];
        }
        uasort($byCategory, fn ($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));
        $in = (int) $valid->sum('in');
        $out = (int) $valid->sum('out');

        return ['in' => $in, 'out' => $out, 'result' => $in - $out, 'by_category' => $byCategory];
    }

    /**
     * Soldes à une date (fin de journée) par moyen : espèces (caisse), mobile money, banque, total.
     *
     * @return array{especes: int, mobile_money: int, banque: int, total: int}
     */
    public function balances(?CarbonImmutable $until = null): array
    {
        $until ??= CarbonImmutable::now()->endOfDay();
        $balance = ['especes' => 0, 'mobile_money' => 0, 'banque' => 0];
        $add = function (?PaymentMethod $method, int $amount) use (&$balance) {
            $balance[($method ?? PaymentMethod::Especes)->family()] += $amount;
        };

        Paiement::whereIn('status', [PaymentStatus::Confirme->value, PaymentStatus::Rembourse->value])->where('paid_at', '<=', $until)
            ->get(['method', 'amount'])->each(fn (Paiement $p) => $add($p->method, $p->amount));
        Paiement::where('status', PaymentStatus::Rembourse->value)->where('cancelled_at', '<=', $until)
            ->get(['method', 'amount'])->each(fn (Paiement $p) => $add($p->method, -$p->amount));
        CommissionVersement::where('paid_on', '<=', $until->toDateString())->get(['method', 'amount'])
            ->each(fn (CommissionVersement $v) => $add(self::payoutMethod($v->method), -$v->amount));
        MouvementCaisse::valid()->where('occurred_on', '<=', $until->toDateString())->get(['type', 'method', 'to_method', 'amount'])
            ->each(function (MouvementCaisse $m) use ($add) {
                match ($m->type) {
                    'entree' => $add($m->method, $m->amount),
                    'sortie' => $add($m->method, -$m->amount),
                    default => [$add($m->method, -$m->amount), $add($m->to_method, $m->amount)],
                };
            });

        return $balance + ['total' => array_sum($balance)];
    }

    /** Moyen d'un versement de commission (valeur du formulaire, ou ancien texte libre « Virement », « Orange Money »…). */
    public static function payoutMethod(?string $method): PaymentMethod
    {
        if ($found = PaymentMethod::tryFrom((string) $method)) {
            return $found;
        }
        $text = mb_strtolower((string) $method);

        return match (true) {
            str_contains($text, 'orange') => PaymentMethod::OrangeMoney,
            str_contains($text, 'wave') => PaymentMethod::Wave,
            str_contains($text, 'moov') => PaymentMethod::MoovMoney,
            (bool) preg_match('/virement|banque|ch[eè]que|bordereau|versement/u', $text) => PaymentMethod::Banque,
            default => PaymentMethod::Especes,
        };
    }

    /** @return array<string, mixed> */
    private function row(mixed $date, string $number, string $label, string $category, PaymentMethod $method, int $in, int $out, string $source, mixed $model): array
    {
        return [
            'date' => CarbonImmutable::parse($date), 'number' => $number, 'label' => $label, 'category' => $category, 'method' => $method,
            'to_method' => null, 'in' => $in, 'out' => $out, 'transfer' => 0, 'source' => $source, 'model' => $model, 'cancelled' => false,
        ];
    }
}
