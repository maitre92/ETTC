<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Message de la discussion d'un groupe (demande du centre, 2026-11-21) : écrit par le formateur (ou l'équipe), ou par un
 * apprenant du groupe (compte client). Lu par le groupe, son formateur et l'équipe. « Nouveau » : depuis la dernière
 * visite de la discussion (horodatage en cache, simple entier).
 */
#[Fillable(['groupe_id', 'user_id', 'client_id', 'body'])]
class GroupeMessage extends Model
{
    use LogsActivity, SoftDeletes;

    public function activityNoun(): string
    {
        return 'Message de groupe';
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function fromTeam(): bool
    {
        return $this->user_id !== null;
    }

    public function authorName(): string
    {
        return $this->fromTeam() ? ($this->user?->name ?? 'Le centre') : ($this->client?->name ?? 'Apprenant');
    }

    /** Clé de la dernière lecture : « user:12 » (formateur, équipe) ou « client:5 » (apprenant). */
    public static function markSeen(string $reader, int $groupeId): void
    {
        Cache::forever("groupe_seen:{$reader}:{$groupeId}", (int) (GroupeMessage::where('groupe_id', $groupeId)->max('id') ?? 0));
    }

    /** Messages des AUTRES arrivés depuis la dernière lecture. */
    public static function unreadCount(string $reader, int $groupeId): int
    {
        $seen = (int) Cache::get("groupe_seen:{$reader}:{$groupeId}", 0);
        [$kind, $id] = explode(':', $reader);

        return GroupeMessage::where('groupe_id', $groupeId)->where('id', '>', $seen)
            ->where(fn ($q) => $kind === 'user' ? $q->whereNull('user_id')->orWhere('user_id', '!=', (int) $id) : $q->whereNull('client_id')->orWhere('client_id', '!=', (int) $id))
            ->count();
    }
}
