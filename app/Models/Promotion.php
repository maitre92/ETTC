<?php

namespace App\Models;

use App\Enums\FormationMode;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Promotion sur les FRAIS DE FORMATION (demande du centre, 2026-11-21), jamais sur les frais d'inscription :
 * - sans code : appliquée automatiquement pendant sa période ;
 * - avec code : appliquée quand l'apprenant (ou l'équipe) saisit le code.
 * Pourcentage ou montant (sur le total de la formation), sur une formule et un mode, ou sur toutes. Les deux ne se
 * cumulent pas : la meilleure réduction l'emporte. La réduction est figée sur l'inscription.
 */
#[Fillable(['name', 'description', 'media_id', 'show_on_site', 'code', 'formule_id', 'mode', 'kind', 'value', 'starts_on', 'ends_on', 'max_uses', 'uses_count', 'is_active'])]
class Promotion extends Model
{
    use LogsActivity, SoftDeletes;

    public const KINDS = ['percent' => 'Pourcentage (%)', 'amount' => 'Montant (FCFA)'];

    protected function casts(): array
    {
        return [
            'value' => 'integer', 'max_uses' => 'integer', 'uses_count' => 'integer', 'is_active' => 'boolean', 'show_on_site' => 'boolean',
            'starts_on' => 'date', 'ends_on' => 'date', 'mode' => FormationMode::class,
        ];
    }

    public function activityNoun(): string
    {
        return 'Promotion';
    }

    public function activityLabel(): string
    {
        return (string) $this->name;
    }

    /** Fiche (affiche) de la promotion, image de la bibliothèque. */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Promotions à montrer sur le site : en cours et « Afficher sur le site », les plus fortes d'abord.
     *
     * @return Collection<int, self>
     */
    public static function forSite(): Collection
    {
        return self::running()->where('show_on_site', true)->with(['formule', 'media'])->latest('id')->get()
            ->sortByDesc(fn (self $p) => $p->kind === 'percent' ? $p->value * 1000 : $p->value)->values();
    }

    /** « jusqu'au 31 janvier · plus que 5 jours », « dernier jour ! » ou null (sans date de fin). */
    public function deadline(): ?string
    {
        if (! $this->ends_on) {
            return null;
        }
        $days = (int) today()->diffInDays($this->ends_on, false);

        return match (true) {
            $days <= 0 => 'Dernier jour !',
            $days === 1 => 'Jusqu\'à demain',
            default => 'Jusqu\'au '.$this->ends_on->translatedFormat('j F').' · plus que '.$days.' jours',
        };
    }

    /** Lien « J'en profite » : l'inscription, formule et code promo déjà choisis. */
    public function signupUrl(): string
    {
        return route('site.inscription.create', array_filter([
            'formule' => $this->formule?->slug,
            'mode' => $this->mode?->value,
            'code' => $this->code,
        ]));
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(Formule::class)->withTrashed();
    }

    /** En cours aujourd'hui : active, dans sa période, pas épuisée. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'));
    }

    /** Valable pour cette formule et ce mode. */
    public function scopeFor(Builder $query, Formule $formule, FormationMode $mode): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('formule_id')->orWhere('formule_id', $formule->id))
            ->where(fn (Builder $q) => $q->whereNull('mode')->orWhere('mode', $mode->value));
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && ($this->starts_on === null || $this->starts_on->lte(today()))
            && ($this->ends_on === null || $this->ends_on->gte(today()))
            && ($this->max_uses === null || $this->uses_count < $this->max_uses);
    }

    /** Réduction sur un montant de formation (jamais plus que ce montant). */
    public function discountOn(int $amount): int
    {
        $discount = $this->kind === 'amount' ? $this->value : (int) round($amount * min(100, $this->value) / 100);

        return max(0, min($amount, $discount));
    }

    /** « -20 % » ou « -10 000 FCFA ». */
    public function badge(): string
    {
        return $this->kind === 'amount' ? '-'.Formule::money($this->value) : '-'.$this->value.' %';
    }

    /** « Toutes les formules · en ligne · jusqu'au 31/01/2027 ». */
    public function targetLabel(): string
    {
        return collect([
            $this->formule?->name ?? 'Toutes les formules',
            $this->mode ? mb_strtolower($this->mode->label()) : null,
            $this->ends_on ? "jusqu'au ".$this->ends_on->format('d/m/Y') : null,
        ])->filter()->implode(' · ');
    }

    public static function normalizeCode(?string $code): ?string
    {
        $code = mb_strtoupper(preg_replace('/\s+/', '', (string) $code) ?? '');

        return $code === '' ? null : $code;
    }
}
