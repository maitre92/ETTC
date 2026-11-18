<?php

namespace App\Models;

use App\Enums\Billing;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\SortsAutomatically;
use Database\Factories\FormuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Formule de formation (cahier §3.3) : option de durée (2 mois, 3 mois…) avec un tarif par mode — en ligne et en
 * présentiel (null = mode non proposé) — et sa périodicité (par mois ou paiement unique). Les tarifs se modifient
 * à tout moment dans l'administration ; chaque modification est auditée (ancien et nouveau montant).
 */
#[Fillable(['name', 'slug', 'duration_months', 'points', 'price_online', 'price_onsite', 'billing', 'sort_order', 'is_published'])]
class Formule extends Model
{
    /** @use HasFactory<FormuleFactory> */
    use HasFactory, HasSlug, LogsActivity, SoftDeletes, SortsAutomatically;

    /** Modes de formation (cahier §3.3) : colonne du tarif => libellé. */
    public const MODES = ['price_online' => 'En ligne', 'price_onsite' => 'En présentiel'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean', 'sort_order' => 'integer', 'duration_months' => 'integer',
            'price_online' => 'integer', 'price_onsite' => 'integer', 'billing' => Billing::class,
        ];
    }

    public function slugSource(): string
    {
        return 'name';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('duration_months');
    }

    /** @return list<string> */
    public function pointList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->points) ?: [])));
    }

    /**
     * Tarifs proposés, dans l'ordre des modes : [libellé du mode, montant].
     *
     * @return list<array{0: string, 1: int}>
     */
    public function prices(): array
    {
        $prices = [];
        foreach (self::MODES as $column => $label) {
            if ($this->{$column} !== null) {
                $prices[] = [$label, (int) $this->{$column}];
            }
        }

        return $prices;
    }

    /** Montant total de la formule pour un mode (tarif mensuel × durée, ou paiement unique). */
    public function total(string $column): ?int
    {
        $price = $this->{$column};
        if ($price === null) {
            return null;
        }

        return $this->billing === Billing::Mensuel ? (int) $price * $this->duration_months : (int) $price;
    }

    /** « 100 000 FCFA » (espace insécable fine entre les milliers). */
    public static function money(?int $amount): string
    {
        return $amount === null ? '—' : number_format($amount, 0, ',', "\u{202F}").' FCFA';
    }

    public function activityNoun(): string
    {
        return 'Formule';
    }

    public function activityLabel(): string
    {
        return (string) $this->name;
    }
}
