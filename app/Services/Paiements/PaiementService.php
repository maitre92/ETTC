<?php

namespace App\Services\Paiements;

use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentNature;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Client\PaiementController;
use App\Mail\PaiementConfirme;
use App\Mail\PaiementDeclare;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Media;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Finances\CommissionCalculator;
use App\Services\Numbering\SequenceService;
use App\Services\Paiements\Gateway\GatewayException;
use App\Services\Paiements\Gateway\GatewayManager;
use App\Services\Paiements\Gateway\PaymentGateway;
use App\Support\PhoneNumber;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        private readonly GatewayManager $gateways,
        private readonly CommissionCalculator $commissions,
    ) {}

    /**
     * Moyens proposés à l'apprenant : ceux dont le centre a renseigné le numéro marchand OU déposé le QR marchand.
     * Valeur : le numéro (chaîne vide s'il n'y a qu'un QR).
     *
     * @return array<string, string>
     */
    public function merchantNumbers(): array
    {
        $qr = $this->merchantQrCodes();

        return collect(PaymentMethod::mobileMoney())
            ->mapWithKeys(fn (PaymentMethod $m) => [$m->value => trim($this->settings->get($m->settingKey()))])
            ->filter(fn (string $number, string $method) => $number !== '' || isset($qr[$method]))->all();
    }

    /**
     * Comment payer en ligne une inscription (écran de confirmation, e-mail récapitulatif) : montant de la première
     * échéance, moyens du centre (numéro et QR), consignes, message WhatsApp déjà rédigé. Null si l'inscription se paie
     * au centre ou si le centre n'a renseigné aucun moyen.
     *
     * @return array{amount: string, methods: list<array{label: string, number: string, qr: ?string}>, instructions: string, whatsapp: ?string, account: ?string}|null
     */
    public function onlinePaymentInfo(Inscription $inscription): ?array
    {
        $numbers = $this->merchantNumbers();
        if ($numbers === [] || ! PaiementController::paysOnline($inscription)) {
            return null;
        }
        $qr = $this->merchantQrCodes();
        $amount = Formule::money($inscription->nextInstallment());

        return [
            'amount' => $amount,
            'methods' => collect($numbers)->map(fn (string $number, string $method) => [
                'value' => $method, 'label' => PaymentMethod::from($method)->label(), 'number' => $number, 'qr' => $qr[$method] ?? null,
                'qr_path' => $this->merchantQrPaths()[$method] ?? null,
            ])->values()->all(),
            'instructions' => 'Envoyez '.$amount.' (première échéance) au numéro ou au QR du centre, en indiquant la référence '.$inscription->number.'. Gardez le SMS de confirmation : sa référence prouve votre paiement.'
                .($inscription->mode === FormationMode::Presentiel ? ' Vous pouvez aussi payer au centre, à l\'accueil.' : ''),
            'whatsapp' => $this->settings->whatsappLink('Bonjour, j\'ai payé '.$amount.' pour mon inscription '.$inscription->number.'. Référence de la transaction : '),
            'account' => Route::has('client.register') ? route('client.register') : null,
        ];
    }

    /** Fichiers des QR marchands (pour les joindre aux e-mails : une image en ligne ne s'affiche pas toujours). @return array<string, string> */
    public function merchantQrPaths(): array
    {
        $paths = [];
        foreach (PaymentMethod::mobileMoney() as $method) {
            $id = (int) $this->settings->get($method->qrSettingKey());
            $media = $id ? Media::find($id) : null;
            if ($media && Storage::disk('public')->exists($media->path)) {
                $paths[$method->value] = Storage::disk('public')->path($media->path);
            }
        }

        return $paths;
    }

    /** Qui reçoit les paiements à vérifier : l'adresse choisie dans « Moyens de paiement », sinon celle des alertes. */
    public function verifierEmail(): ?string
    {
        $email = trim($this->settings->get('paiement.notify_email'));

        return $email !== '' ? $email : $this->settings->notificationEmail();
    }

    /**
     * Contrôles d'un paiement déclaré AVANT d'enregistrer l'inscription (étape « Paiement » de l'assistant) : moyen
     * proposé par le centre, référence jamais déclarée, montant au plus égal au total.
     */
    public function assertDeclarable(string $method, string $reference, int $amount, int $total): void
    {
        if (! array_key_exists($method, $this->merchantNumbers())) {
            throw ValidationException::withMessages(['pay_method' => 'Ce moyen de paiement n\'est pas proposé par le centre.']);
        }
        if (Paiement::where('method', $method)->where('reference', Str::upper(trim($reference)))->whereIn('status', [PaymentStatus::AVerifier->value, PaymentStatus::Confirme->value])->exists()) {
            throw ValidationException::withMessages(['pay_reference' => 'Cette référence de transaction a déjà été déclarée.']);
        }
        if ($amount < 1 || $amount > $total) {
            throw ValidationException::withMessages(['pay_amount' => 'Le montant doit être compris entre 1 et '.Formule::money($total).'.']);
        }
    }

    /** Paiement déclaré en même temps que l'inscription (visiteur sans compte) : « À vérifier » ; l'alerte part avec l'inscription. */
    public function declareAtInscription(Inscription $inscription, array $data, Request $request): Paiement
    {
        $paiement = Paiement::create($this->attributes($inscription, ['paid_at' => null] + $data) + [
            'status' => PaymentStatus::AVerifier, 'source' => 'apprenant', 'ip_address' => $request->ip(),
        ]);
        $this->logger->log('paiements.declared', 'paiements', "Paiement déclaré à l'inscription — {$paiement->number} : {$paiement->money()} par {$paiement->method->label()}, référence {$paiement->reference} ({$inscription->number})", $paiement);

        return $paiement;
    }

    /** QR marchands déposés par le centre : [moyen => adresse de l'image]. @return array<string, string> */
    public function merchantQrCodes(): array
    {
        $codes = [];
        foreach (PaymentMethod::mobileMoney() as $method) {
            $id = (int) $this->settings->get($method->qrSettingKey());
            $media = $id ? Media::find($id) : null;
            if ($media) {
                $codes[$method->value] = $media->url();
            }
        }

        return $codes;
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
            ] + $this->commissions->forPayment($inscription, (int) $data['amount']));
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

        $this->mail(fn () => Mail::to($this->verifierEmail())->send(new PaiementDeclare($paiement)), $paiement, 'alerte à l\'équipe', (bool) $this->verifierEmail());

        return $paiement;
    }

    /**
     * Paiement automatique : crée la transaction « en attente » et renvoie l'adresse de la page de paiement du
     * prestataire. Rien n'est confirmé ici : voir synchronize().
     */
    public function startOnline(Inscription $inscription, int $amount, ?string $phone, Client $client, Request $request, PaymentGateway $gateway): string
    {
        $this->ensurePayable($inscription, $amount, pending: true);

        $paiement = DB::transaction(function () use ($inscription, $amount, $phone, $client, $request, $gateway) {
            $paiement = Paiement::create($this->attributes($inscription, ['amount' => $amount, 'method' => PaymentMethod::OrangeMoney->value, 'payer_phone' => $phone]) + [
                'status' => PaymentStatus::EnAttente, 'source' => 'en_ligne', 'client_id' => $client->id, 'ip_address' => $request->ip(),
                'gateway' => $gateway->name(), 'gateway_transaction_id' => 'ETTC'.now()->format('ymdHis').Str::upper(Str::random(8)),
            ]);
            $this->logger->log('paiements.online_started', 'paiements', "Paiement en ligne commencé — {$paiement->number} : {$paiement->money()} via {$gateway->label()} ({$inscription->number})", $paiement);

            return $paiement;
        });

        try {
            return $gateway->initiate($paiement, route('client.paiements.return', $paiement), route('site.paiements.notify', $gateway->name()));
        } catch (GatewayException $e) {
            Paiement::withoutActivityLog(fn () => $paiement->update(['status' => PaymentStatus::Rejete, 'cancel_reason' => 'Service de paiement indisponible', 'cancelled_at' => now()]));
            Log::warning("Paiement en ligne {$paiement->number} : {$e->getMessage()}");
            throw ValidationException::withMessages(['amount' => 'Le service de paiement en ligne ne répond pas. Réessayez dans un instant, ou payez au numéro du centre puis déclarez la transaction.']);
        }
    }

    /**
     * Lit l'état de la transaction CHEZ LE PRESTATAIRE et met le paiement à jour (notification serveur, retour de
     * l'apprenant, tâche planifiée). Seule cette lecture serveur à serveur confirme un paiement (cahier §3.8 A).
     */
    public function synchronize(Paiement $paiement): Paiement
    {
        $gateway = $paiement->gateway ? $this->gateways->make($paiement->gateway) : null;
        if (! $gateway || $paiement->status !== PaymentStatus::EnAttente) {
            return $paiement;
        }
        try {
            $result = $gateway->fetch($paiement->gateway_transaction_id);
        } catch (GatewayException $e) {
            Log::warning("Vérification du paiement {$paiement->number} impossible : {$e->getMessage()}");

            return $paiement;
        }

        $confirmed = DB::transaction(function () use ($paiement, $result) {
            $paiement = Paiement::whereKey($paiement->id)->lockForUpdate()->firstOrFail();
            if ($paiement->status !== PaymentStatus::EnAttente) {
                return false;
            }
            $tracking = ['gateway_status' => $result->rawStatus, 'gateway_checked_at' => now()];
            if ($result->isRefused()) {
                $this->change($paiement, $tracking + ['status' => PaymentStatus::Rejete, 'cancel_reason' => 'Paiement refusé ou abandonné chez l\'opérateur', 'cancelled_at' => now()], 'paiements.online_refused', 'Paiement en ligne refusé', null);

                return false;
            }
            if (! $result->isAccepted()) {
                Paiement::withoutActivityLog(fn () => $paiement->update($tracking));

                return false;
            }
            $inscription = $paiement->inscription;
            $values = $tracking + ['reference' => $result->operatorReference ? Str::upper($result->operatorReference) : $paiement->gateway_transaction_id, 'method' => $this->methodFrom($result->method) ?? $paiement->method];
            // Montant reçu différent, ou supérieur au reste (deux paiements en même temps) : l'équipe tranche.
            if ($result->amount !== $paiement->amount || $paiement->amount > $inscription->balance()) {
                $this->change($paiement, $values + ['status' => PaymentStatus::AVerifier, 'notes' => 'Paiement accepté par l\'opérateur ('.($result->amount ?? '?').' FCFA) mais à contrôler : montant attendu '.$paiement->amount.' FCFA, reste '.$inscription->balance().' FCFA.'], 'paiements.online_to_check', 'Paiement en ligne à contrôler', null);

                return false;
            }
            $this->change($paiement, $values + ['status' => PaymentStatus::Confirme, 'confirmed_at' => now()] + $this->commissions->forPayment($inscription, $paiement->amount), 'paiements.online_confirmed', 'Paiement en ligne confirmé par '.$this->gateways->make($paiement->gateway)?->label(), null);
            $this->afterConfirmation($inscription, null);

            return true;
        });

        $paiement->refresh();
        if ($confirmed) {
            $this->mailReceipt($paiement);
        }

        return $paiement;
    }

    /** Paiements en ligne restés « en attente » : relus chez le prestataire ; abandonnés après 24 h. */
    public function synchronizePending(): int
    {
        $count = 0;
        Paiement::where('status', PaymentStatus::EnAttente->value)->where('created_at', '<=', now()->subMinutes(2))->get()
            ->each(function (Paiement $paiement) use (&$count) {
                $this->synchronize($paiement);
                if ($paiement->fresh()->status === PaymentStatus::EnAttente && $paiement->created_at->lt(now()->subDay())) {
                    $this->change($paiement->fresh(), ['status' => PaymentStatus::Rejete, 'cancel_reason' => 'Paiement en ligne non finalisé', 'cancelled_at' => now()], 'paiements.online_expired', 'Paiement en ligne expiré', null);
                }
                $count++;
            });

        return $count;
    }

    private function methodFrom(?string $raw): ?PaymentMethod
    {
        $raw = strtoupper((string) $raw);

        return match (true) {
            str_contains($raw, 'WAVE') => PaymentMethod::Wave,
            str_contains($raw, 'MOOV'), str_contains($raw, 'FLOOZ') => PaymentMethod::MoovMoney,
            str_contains($raw, 'OM'), str_contains($raw, 'ORANGE') => PaymentMethod::OrangeMoney,
            default => null,
        };
    }

    /** Déclaration retrouvée sur le compte marchand : confirmée, reçu envoyé. */
    public function confirm(Paiement $paiement, User $actor): Paiement
    {
        $this->ensureStatus($paiement, PaymentStatus::AVerifier, 'Seul un paiement « À vérifier » peut être confirmé.');
        $inscription = $paiement->inscription;
        $this->ensurePayable($inscription, $paiement->amount);

        DB::transaction(function () use ($paiement, $inscription, $actor) {
            $this->change($paiement, ['status' => PaymentStatus::Confirme, 'confirmed_at' => now(), 'confirmed_by' => $actor->id] + $this->commissions->forPayment($inscription, $paiement->amount), 'paiements.confirmed', 'Paiement confirmé', $actor);
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
    private function change(Paiement $paiement, array $values, string $action, string $what, ?User $actor): Paiement
    {
        $old = $paiement->status;
        Paiement::withoutActivityLog(fn () => $paiement->update($values));
        $this->logger->log($action, 'paiements', "{$what} — {$paiement->number} ({$paiement->money()}, {$paiement->inscription->number})", $paiement,
            ['status' => $old->value], ['status' => $paiement->status->value] + array_intersect_key($values, ['cancel_reason' => true]), $actor);

        return $paiement;
    }

    /** Montant payé recalculé ; un dossier à valider ou en attente de paiement passe « Confirmée » (validée automatiquement) (cahier §3.8 A, B). */
    private function afterConfirmation(Inscription $inscription, ?User $actor): void
    {
        $this->syncAmountPaid($inscription);
        if ($inscription->status->isPending()) {
            $old = $inscription->status;
            Inscription::withoutActivityLog(fn () => $inscription->update([
                'status' => InscriptionStatus::EnCours,
                'validated_at' => $inscription->validated_at ?? now(), 'validated_by' => $inscription->validated_by ?? $actor?->id,
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
            ...$this->storeProof($data['proof'] ?? null),
            'payer_phone' => $phone !== '' ? (PhoneNumber::normalize($phone) ?? $phone) : null,
            'period' => isset($data['period']) && trim($data['period']) !== '' ? trim($data['period']) : null,
            'paid_at' => ! empty($data['paid_at']) ? $data['paid_at'] : now(),
            'notes' => isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null,
        ];
    }

    /**
     * Capture du paiement (facultative) : fichier privé (disque local), jamais public.
     *
     * @return array{proof_path?: string, proof_name?: string}
     */
    private function storeProof(mixed $file): array
    {
        if (! $file instanceof UploadedFile) {
            return [];
        }

        return [
            'proof_path' => $file->storeAs('paiements/preuves/'.now()->format('Y/m'), Str::uuid().'.'.$file->extension(), 'local'),
            'proof_name' => Str::limit($file->getClientOriginalName(), 250, ''),
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
