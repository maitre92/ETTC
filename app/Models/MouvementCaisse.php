<?php

namespace App\Models;

use App\Enums\CashCategory;
use App\Enums\PaymentMethod;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mouvement de caisse saisi à la main : revenu (entrée), dépense (sortie) ou transfert entre moyens (ex. dépôt des
 * espèces en banque). Jamais supprimé : annulé avec un motif.
 */
#[Fillable(['number', 'type', 'category', 'label', 'amount', 'method', 'to_method', 'reference', 'party', 'occurred_on', 'notes', 'proof_path', 'proof_name', 'recorded_by', 'status', 'cancel_reason', 'cancelled_at', 'cancelled_by'])]
class MouvementCaisse extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'mouvements_caisse';

    public const TYPES = ['entree' => 'Revenu (entrée)', 'sortie' => 'Dépense (sortie)', 'transfert' => 'Transfert'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'occurred_on' => 'date', 'cancelled_at' => 'datetime',
            'category' => CashCategory::class, 'method' => PaymentMethod::class, 'to_method' => PaymentMethod::class,
        ];
    }

    public function activityNoun(): string
    {
        return 'Mouvement de caisse';
    }

    public function activityLabel(): string
    {
        return "{$this->number} — {$this->label}";
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'valide');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'annule';
    }
}
