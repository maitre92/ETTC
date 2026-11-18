<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\SortsAutomatically;
use Database\Factories\PrestationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Formation ou service proposé par le centre (page Formations et services). Masqué = invisible sur le site, conservé dans l'administration.
 */
#[Fillable(['title', 'slug', 'icon', 'summary', 'description', 'sort_order', 'is_published'])]
class Prestation extends Model
{
    /** @use HasFactory<PrestationFactory> */
    use HasFactory, HasSlug, LogsActivity, SoftDeletes, SortsAutomatically;

    /** Icônes proposées dans le formulaire (Boxicons, servies localement). */
    public const ICONS = [
        'bx-book-open' => 'Formation', 'bx-chalkboard' => 'Cours', 'bx-video' => 'Cours en ligne', 'bx-target-lock' => 'Test de niveau',
        'bx-customize' => 'Sur mesure', 'bx-transfer-alt' => 'Traduction', 'bx-file' => 'Document', 'bx-world' => 'Voyage',
        'bx-chat' => 'Conversation', 'bx-microphone' => 'Oral', 'bx-pencil' => 'Écrit', 'bx-headphone' => 'Écoute',
        'bx-award' => 'Certificat', 'bx-group' => 'Groupe', 'bx-user-voice' => 'Accompagnement', 'bx-calendar' => 'Session',
        'bx-support' => 'Assistance', 'bx-check-circle' => 'Générique',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function slugSource(): string
    {
        return 'title';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function activityNoun(): string
    {
        return 'Service';
    }

    public function activityLabel(): string
    {
        return (string) $this->title;
    }
}
