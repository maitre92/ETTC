<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Support pédagogique d'un groupe (cahier §3.6) : fichier privé (disque local, téléchargé seulement par l'équipe et les
 * apprenants du groupe) ou lien (vidéo YouTube, dossier Drive… ouvert dans un nouvel onglet, jamais intégré : D-7).
 */
#[Fillable(['groupe_id', 'title', 'kind', 'path', 'url', 'original_name', 'mime', 'size', 'uploaded_by'])]
class Support extends Model
{
    use LogsActivity, SoftDeletes;

    public const KINDS = ['document' => 'Document', 'audio' => 'Audio', 'video' => 'Vidéo', 'lien' => 'Lien'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function activityNoun(): string
    {
        return 'Support';
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class)->withTrashed();
    }

    public function icon(): string
    {
        return match ($this->kind) {
            'audio' => 'bx-music',
            'video' => 'bx-video',
            'lien' => 'bx-link-external',
            default => 'bx-file',
        };
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Document';
    }

    public function sizeLabel(): ?string
    {
        return $this->size ? number_format($this->size / 1048576, 1, ',', ' ').' Mo' : null;
    }
}
