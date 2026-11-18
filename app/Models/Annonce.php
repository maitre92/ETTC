<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/** Annonce du centre (cahier §4) : à tous les apprenants ou à un groupe, jusqu'à une date facultative. */
#[Fillable(['title', 'body', 'groupe_id', 'expires_on', 'created_by', 'emailed_count'])]
class Annonce extends Model
{
    use LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return ['expires_on' => 'date', 'emailed_count' => 'integer'];
    }

    public function activityNoun(): string
    {
        return 'Annonce';
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Encore affichée : sans date de fin, ou date de fin aujourd'hui ou plus tard. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()));
    }

    /** Annonces visibles par un apprenant : celles pour tous et celles de ses groupes. @param  Collection<int, int>  $groupIds */
    public function scopeVisibleTo(Builder $query, Collection $groupIds): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('groupe_id')->orWhereIn('groupe_id', $groupIds));
    }

    public function audience(): string
    {
        return $this->groupe ? 'Groupe '.$this->groupe->name : 'Tous les apprenants';
    }
}
