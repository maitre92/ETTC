<?php

namespace App\Services\Paiements;

use App\Enums\InscriptionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentNature;
use App\Enums\PaymentStatus;
use App\Mail\PaiementConfirme;
use App\Mail\PaiementDeclare;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Support\PhoneNumber;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paiements des inscriptions (cahier §3.8, décision du centre 2026-11-18) :
 * - en présentiel, au centre : l'équipe enregistre le paiement reçu (espèces ou mobile money au guichet) → confirmé, reçu ;
 * - en ligne, par mobile money : l'apprenant déclare sa transaction (moyen, référence) → « À vérifier » ; l'équipe confirme après l'avoir
 *   retrouvée sur le compte marchand, ou la rejette. Le retour du navigateur ne confirme jamais un paiement (§3.8 A).
 * Un paiement confirmé actualise l'inscription (montant payé ; à valider ou en attente de paiement → en cours). Annulation
 * (erreur, aucun argent) et remboursement (argent rendu) motivés. Une ligne d'audit métier par geste.
 */
class PaiementService
{
    public const PREFIX = 'REC';

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SequenceService $sequences,
        private readonly SiteSettings $settings,
    ) {}

    /** Moyens proposés à l'apprenant : ceux dont le centre a renseigné le numéro marchand. @return array<string, string> */
    public function merchantNumbers(): array
    {
        return collect(PaymentMethod::mobileMoney())
            ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => trim($this->settings->get($m->settingKey()))])
            ->filter()->all();
    }

    /**
     * Paiement reçu au centre, enregistré par l'équipe : confirmé tout de suite.
     *
     * @param  array{amount: int|string, method: string, reference?: ?string, payer_phone?: ?string, period?: ?string, paid_at?: ?string, notes?: ?string}  $data
     */
    public function record(Inscription $inscription, array $data, User $actor): Paiement
    {
        $this->ensurePayable($inscription, (int) $data['amount']);

        $paiement = DB::transaction(function () use ($inscription, $data, $actor) {
            $paiement = Paiement::create($this->attributes($inscription, $data) + [
                'status' => PaymentStatus::Confirme, 'source' => 'centre', 'recorded_by' => $actor->id,
                'confirmed_at' => now(), 'confirmed_by' => $actor->id,
            ]);
            $this->logger->log('paiements.recorded', 'paiements', "Paiement enregistré — {$paiement->number} : {$paiement->money()} par {$paiement->method->label()} ({$inscription->number}, {$inscription->name})", $paiement, actor: $actor);
            $this->afterConfirmation($inscription, $actor);

            return $paiement;
        });

        $this->mailReceipt($paiement);

        return $paiement;
    }

    /**
     * Paiement déclaré par l'apprenant depuis son espace : à vérifier par l'équipe.
     *
     * @param  array{amount: int|string, method: string, reference: string, payer_phone?: ?string, period?: ?string}  $data
     */
    public function declare(Inscription $inscription, array $data, Client $client, Request $request): Paiement
    {
        if (! array_key_exists($data['method'], $this->merchantNumbers())) {
            throw ValidationException::withMessages(['payment_method' => 'Ce moyen de paiement n\'est pas proposé par le centre.']);
        }
        if (Paiement::where('method', $data['method'])->where('reference', trim($data['reference']))->whereIn('status', [PaymentStatus::AVerifier->value, PaymentStatus::Confirme->value])->exists()) {
            throw ValidationException::withMessages(['reference' => 'Cette référence de transaction a déjà été déclarée.']);
        }
        $this->ensurePayable($inscription, (int) $data['amount'], pending: true);

        $paiement = DB::transaction(function () use ($inscription, $data, $client, $request) {
            $paiement = Paiement::create($this->attributes($inscription, ['paid_at' => null] + $data) + [
                'status' => PaymentStatus::AVerifier, 'source' => 'apprenant', 'client_id' => $client->id, 'ip_address' => $request->ip(),
            ]);
            $this->logger->log('paiements.declared', 'paiements', "Paiement déclaré par l'apprenant — {$paiement->number} : {$paiement->money()} par {$paiement->method->label()}, référence {$paiement->reference} ({$inscription->number})", $paiement);

            return $paiement;
        });

        $this->mail(fn () => Mail::to($this->settings->notificationEmail())->send(new PaiementDeclare($paiement)), $paiement, 'alerte à l\'équipe', (bool) $this->settings->notificationEmail());

        return $paiement;
    }

    /** Déclaration retrouvée sur le compte marchand : confirmée, reçu envoyé. */
    public function confirm(Paiement $paiement, User $actor): Paiement
    {
        $this->ensureStatus($paiement, PaymentStatus::AVerifier, 'Seul un paiement « À vérifier » peut être confirmé.');
        $inscription = $paiement->inscription;
        $this->ensurePayable($inscription, $paiement->amount);

        DB::transaction(function () use ($paiement, $inscription, $actor) {
            $this->change($paiement, ['status' => PaymentStatus::Confirme, 'confirmed_at' => now(), 'confirmed_by' => $actor->id], 'paiements.confirmed', 'Paiement confirmé', $actor);
            $this->afterConfirmation($inscription, $actor);
        });
        $this->mailReceipt($paiement);

        return $paiement;
    }

    /** Déclaration introuvable ou erronée : rejetée (motif communiqué à l'apprenant dans son espace). */
    public function reject(Paiement $paiement, string $reason, User $actor): Paiement
    {
        $this->ensureStatus($paiement, PaymentStatus::AVerifier, 'Seul un paiement « À vérifier » peut être rejeté.');

        return DB::transaction(fn () => $this->change($paiement, ['status' => PaymentStatus::Rejete, 'cancel_reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $actor->id], 'paiements.rejected', "Paiement rejeté : {$reason}", $actor));
    }

    /** Paiement confirmé par erreur (aucun argent reçu) : annulé, le montant payé de l'inscription diminue. */
    public function cancel(Paiement $paiement, string $reason, User $actor): Paiement
    {
        return $this->reverse($paiement, PaymentStatus::Annule, $reason, 'paiements.cancelled', 'Paiement annulé', $actor);
    }

    /** Argent rendu à l'apprenant : remboursé, le montant payé de l'inscription diminue. */
    public function refund(Paiement $paiement, string $reason, User $actor): Paiement
    {
        return $this->reverse($paiement, PaymentStatus::Rembourse, $reason, 'paiements.refunded', 'Paiement remboursé', $actor);
    }

    /** Numéros marchands et consignes (Paiements → Moyens de paiement). @param  array<string, ?string>  $values */
    public function saveSettings(array $values, User $actor): void
    {
        $values = array_map(fn ($v) => trim(str_replace("\r\n", "\n", (string) $v)), $values);
        $this->settings->save($values);
        $this->logger->log('paiements.settings_updated', 'paiements', 'Moyens de paiement modifiés', new: $values, actor: $actor);
    }

    private function reverse(Paiement $paiement, PaymentStatus $to, string $reason, string $action, string $what, User $actor): Paiement
    {
        $this->ensureStatus($paiement, PaymentStatus::Confirme, 'Seul un paiement confirmé peut être annulé ou remboursé.');

        return DB::transaction(function () use ($paiement, $to, $reason, $action, $what, $actor) {
            $this->change($paiement, ['status' => $to, 'cancel_reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $actor->id], $action, "{$what} : {$reason}", $actor);
            $this->syncAmountPaid($paiement->inscription);

            return $paiement;
        });
    }

    /** @param  array<string, mixed>  $values */
    private function change(Paiement $paiement, array $values, string $action, string $what, User $actor): Paiement
    {
        $old = $paiement->status;
        Paiement::withoutActivityLog(fn () => $paiement->update($values));
        $this->logger->log($action, 'paiements', "{$what} — {$paiement->number} ({$paiement->money()}, {$paiement->inscription->number})", $paiement,
            ['status' => $old->value], ['status' => $paiement->status->value] + array_intersect_key($values, ['cancel_reason' => true]), $actor);

        return $paiement;
    }

    /** Montant payé recalculé ; un dossier à valider ou en attente de paiement passe « En cours » (cahier §3.8 A, B). */
    private function afterConfirmation(Inscription $inscription, User $actor): void
    {
        $this->syncAmountPaid($inscription);
        if ($inscription->status->isPending()) {
            $old = $inscription->status;
            Inscription::withoutActivityLog(fn () => $inscription->update([
                'status' => InscriptionStatus::EnCours,
                'validated_at' => $inscription->validated_at ?? now(), 'validated_by' => $inscription->validated_by ?? $actor->id,
                'started_at' => $inscription->started_at ?? now(),
            ]));
            $this->logger->log('inscriptions.paid', 'inscriptions', "Inscription confirmée par le paiement — {$inscription->number} ({$inscription->name})", $inscription, ['status' => $old->value], ['status' => InscriptionStatus::EnCours->value], $actor);
        }
    }

    private function syncAmountPaid(Inscription $inscription): void
    {
        $paid = (int) Paiement::where('inscription_id', $inscription->id)->confirmed()->sum('amount');
        Inscription::withoutActivityLog(fn () => $inscription->forceFill(['amount_paid' => $paid])->save());
    }

    private function ensurePayable(Inscription $inscription, int $amount, bool $pending = false): void
    {
        if (in_array($inscription->status, [InscriptionStatus::Annulee], true)) {
            throw ValidationException::withMessages(['amount' => 'Cette inscription est annulée : aucun paiement ne peut y être enregistré.']);
        }
        $remaining = $inscription->total_amount - $inscription->amount_paid;
        if ($pending) {
            $remaining -= (int) Paiement::where('inscription_id', $inscription->id)->where('status', PaymentStatus::AVerifier->value)->sum('amount');
        }
        if ($amount > $remaining) {
            throw ValidationException::withMessages(['amount' => $remaining > 0
                ? 'Le montant dépasse le reste à payer ('.Formule::money($remaining).').'
                : 'Cette inscription est déjà entièrement payée'.($pending ? ' ou un paiement est en cours de vérification' : '').'.']);
        }
    }

    private function ensureStatus(Paiement $paiement, PaymentStatus $expected, string $message): void
    {
        if ($paiement->status !== $expected) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Inscription $inscription, array $data): array
    {
        $phone = trim((string) ($data['payer_phone'] ?? ''));

        return [
            'number' => $this->sequences->next(self::PREFIX),
            'inscription_id' => $inscription->id,
            'client_id' => $inscription->client_id,
            'nature' => PaymentNature::Formation,
            'amount' => (int) $data['amount'],
            'method' => PaymentMethod::from($data['method']),
            'reference' => isset($data['reference']) && trim($data['reference']) !== '' ? Str::upper(trim($data['reference'])) : null,
            'payer_phone' => $phone !== '' ? (PhoneNumber::normalize($phone) ?? $phone) : null,
            'period' => isset($data['period']) && trim($data['period']) !== '' ? trim($data['period']) : null,
            'paid_at' => ! empty($data['paid_at']) ? $data['paid_at'] : now(),
            'notes' => isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null,
        ];
    }

    private function mailReceipt(Paiement $paiement): void
    {
        $email = $paiement->inscription->email;
        $this->mail(fn () => Mail::to($email, $paiement->inscription->name)->send(new PaiementConfirme($paiement)), $paiement, 'reçu à l\'apprenant', (bool) $email);
    }

    private function mail(callable $send, Paiement $paiement, string $what, bool $possible): void
    {
        if (! $possible) {
            return;
        }
        try {
            $send();
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 480);
            Log::error("Envoi ({$what}) du paiement {$paiement->number} impossible : {$message}");
            $this->logger->log('paiements.mail_failed', 'paiements', "Échec de l'envoi ({$what}) — {$paiement->number}", $paiement, new: ['error' => $message]);
        }
    }
}
