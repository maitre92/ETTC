<?php

namespace App\Models;

use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\GroupeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Groupe d'apprenants (cahier §3.6, §5) : niveau, mode, formateur, lien Google Meet habituel (en ligne) ou salle
 * (présentiel), horaire, période. Ses séances et ses supports ne sont visibles que de ses apprenants.
 */
#[Fillable(['name', 'niveau_id', 'mode', 'teacher', 'meet_url', 'location', 'schedule', 'starts_on', 'ends_on', 'notes', 'is_active'])]
class Groupe extends Model
{
    /** @use HasFactory<GroupeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['mode' => FormationMode::class, 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function activityNoun(): string
    {
        return 'Groupe';
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class)->withTrashed();
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /** Apprenants actuels : inscriptions non annulées. */
    public function members(): HasMany
    {
        return $this->hasMany(Inscription::class)->where('status', '!=', InscriptionStatus::Annulee->value)->orderBy('name');
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class)->orderBy('starts_at');
    }

    public function supports(): HasMany
    {
        return $this->hasMany(Support::class)->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function nextSeance(): ?Seance
    {
        return $this->seances()->where('is_cancelled', false)->where('starts_at', '>=', now()->subHours(2))->first();
    }
}
