<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Versement de sa rémunération à un formateur, pour un mois (jamais supprimé : annulé avec un motif). */
#[Fillable(['number', 'user_id', 'period', 'amount', 'paid_on', 'method', 'reference', 'notes', 'recorded_by', 'status', 'cancel_reason', 'cancelled_at', 'cancelled_by'])]
class FormateurVersement extends Model
{
    use LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'date', 'cancelled_at' => 'datetime', 'method' => PaymentMethod::class];
    }

    public function activityNoun(): string
    {
        return 'Versement au formateur';
    }

    public function activityLabel(): string
    {
        return "{$this->number} — {$this->period}";
    }

    public function formateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
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
