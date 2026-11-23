<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\SortsAutomatically;
use Database\Factories\NiveauFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Niveau de formation (cahier §3.2) : fiche du catalogue avec objectifs, compétences visées et prérequis. Les
 * appellations et le nombre de niveaux sont fixés par le centre (cahier §12) et se gèrent dans l'administration.
 */
#[Fillable(['name', 'slug', 'summary', 'objectives', 'skills', 'prerequisites', 'min_score', 'sort_order', 'is_published'])]
class Niveau extends Model
{
    /** @use HasFactory<NiveauFactory> */
    use HasFactory, HasSlug, LogsActivity, SoftDeletes, SortsAutomatically;

    protected $table = 'niveaux';

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer', 'min_score' => 'integer'];
    }

    public function slugSource(): string
    {
        return 'name';
    }

    public function publicPathPrefix(): ?string
    {
        return '/formations';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Lignes non vides d'un champ « une ligne = un élément » (objectifs, compétences, prérequis). @return list<string> */
    public function lines(string $field): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->{$field}) ?: [])));
    }

    public function activityNoun(): string
    {
        return 'Niveau';
    }

    public function activityLabel(): string
    {
        return (string) $this->name;
    }
}
