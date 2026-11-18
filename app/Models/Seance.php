<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Carbon\CarbonInterface;
use Database\Factories\SeanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Séance d'un groupe (cahier §3.6) : date et heure, durée, thème, lien Google Meet (celui du groupe par défaut),
 * informations pratiques ; une séance annulée reste affichée (barrée) avec son motif.
 */
#[Fillable(['groupe_id', 'starts_at', 'duration_minutes', 'title', 'meet_url', 'notes', 'is_cancelled', 'cancel_reason'])]
class Seance extends Model
{
    /** @use HasFactory<SeanceFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** Le bouton « Rejoindre » s'active ce nombre de minutes avant le début. */
    public const JOIN_BEFORE_MINUTES = 15;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'duration_minutes' => 'integer', 'is_cancelled' => 'boolean'];
    }

    public function activityNoun(): string
    {
        return 'Séance';
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class)->withTrashed();
    }

    public function endsAt(): CarbonInterface
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    /** Lien de connexion : celui de la séance, sinon celui du groupe. */
    public function joinUrl(): ?string
    {
        return $this->meet_url ?: $this->groupe?->meet_url;
    }

    /** Ouverte aux apprenants : de 15 min avant le début jusqu'à la fin. */
    public function isJoinable(): bool
    {
        return ! $this->is_cancelled && $this->joinUrl() !== null
            && now()->between($this->starts_at->copy()->subMinutes(self::JOIN_BEFORE_MINUTES), $this->endsAt());
    }

    public function isPast(): bool
    {
        return $this->endsAt()->isPast();
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('is_cancelled', false)->where('starts_at', '>=', now()->subMinutes(180));
    }

    /** « lundi 12 janvier · 18h00 – 19h30 ». */
    public function when(): string
    {
        return $this->starts_at->translatedFormat('l j F').' · '.$this->starts_at->format('H\hi').' – '.$this->endsAt()->format('H\hi');
    }
}
