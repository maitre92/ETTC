<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentNature;
use App\Enums\PaymentStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\PaiementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Paiement d'une inscription (cahier §3.8) : montant, moyen, référence de la transaction, période réglée, état. Numéro
 * `REC-AAAA-NNNN` = numéro du reçu. Jamais supprimé : une erreur s'annule avec un motif (suivi financier vérifiable).
 */
#[Fillable([
    'number', 'inscription_id', 'client_id', 'nature', 'amount', 'proof_path', 'proof_name', 'commission_rate', 'commission_amount', 'method', 'reference', 'payer_phone', 'period', 'status',
    'source', 'gateway', 'gateway_transaction_id', 'gateway_status', 'gateway_checked_at', 'paid_at', 'recorded_by', 'confirmed_at', 'confirmed_by', 'notes', 'cancel_reason', 'cancelled_at',
    'cancelled_by', 'cancel_request_type', 'cancel_request_reason', 'cancel_requested_by', 'cancel_requested_at', 'ip_address',
])]
class Paiement extends Model
{
    /** @use HasFactory<PaiementFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'nature' => PaymentNature::class, 'method' => PaymentMethod::class, 'status' => PaymentStatus::class,
            'amount' => 'integer', 'commission_rate' => 'float', 'commission_amount' => 'integer', 'paid_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'gateway_checked_at' => 'datetime', 'cancel_requested_at' => 'datetime',
        ];
    }

    public function activityNoun(): string
    {
        return 'Paiement';
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Confirme->value);
    }

    /** Paiements des inscriptions de l'apprenant (mêmes règles que Inscription::forClient). */
    public function scopeForClient(Builder $query, Client $client): Builder
    {
        return $query->whereHas('inscription', fn (Builder $q) => $q->forClient($client));
    }

    public function money(): string
    {
        return Formule::money($this->amount);
    }

    /** Secrétaire qui a demandé l'annulation ou le remboursement (validation par l'administrateur). */
    public function cancelRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_requested_by')->withTrashed();
    }

    public function hasCancelRequest(): bool
    {
        return $this->cancel_request_type !== null;
    }

    public function scopeCancelRequested(Builder $query): Builder
    {
        return $query->whereNotNull('cancel_request_type')->where('status', PaymentStatus::Confirme->value);
    }
}
