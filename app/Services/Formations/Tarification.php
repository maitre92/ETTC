<?php

namespace App\Services\Formations;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Models\Formule;
use App\Models\Promotion;
use App\Support\SiteSettings;
use Illuminate\Validation\ValidationException;

/**
 * Tarif d'une inscription (cahier §3.3 + demande du centre, 2026-11-21) : frais de formation (tarif du mode × durée, ou
 * paiement unique), moins la meilleure promotion (automatique ou code promo), plus les frais d'inscription payés une
 * fois. Tout est FIGÉ sur l'inscription. Le même calcul sert au site (en direct) et à l'administration.
 */
class Tarification
{
    public function __construct(private readonly SiteSettings $settings) {}

    /** Frais d'inscription en vigueur (réglage Formules et tarifs). */
    public function registrationFee(): int
    {
        return max(0, (int) preg_replace('/\D/', '', $this->settings->get('formation.registration_fee')));
    }

    /**
     * Promotion du code saisi pour cette formule et ce mode. Refus précis (sous le champ « Code promo ») : code inconnu,
     * promotion arrêtée, terminée, épuisée, pas encore commencée, ou qui ne concerne pas cette formule / ce mode.
     */
    public function promotionForCode(string $code, Formule $formule, FormationMode $mode): Promotion
    {
        $promo = Promotion::with('formule')->where('code', Promotion::normalizeCode($code))->first();
        $fail = fn (string $message) => throw ValidationException::withMessages(['promo_code' => $message]);

        if (! $promo || ! $promo->is_active) {
            $fail('Ce code promo n\'existe pas : vérifiez-le sur le bandeau des promotions.');
        }
        if ($promo->ends_on && $promo->ends_on->lt(today())) {
            $fail("La promotion « {$promo->name} » est terminée.");
        }
        if ($promo->max_uses !== null && $promo->uses_count >= $promo->max_uses) {
            $fail("Il n'y a plus de place pour la promotion « {$promo->name} ».");
        }
        if (! Promotion::bookable()->whereKey($promo->id)->exists()) {
            $fail("La promotion « {$promo->name} » n'a pas encore commencé (".$promo->deadline().').');
        }
        if (($promo->formule_id && $promo->formule_id !== $formule->id) || ($promo->mode && $promo->mode !== $mode)) {
            $fail("La promotion « {$promo->name} » ne concerne pas cette formule : elle vaut pour "
                .($promo->formule?->name ?? 'toutes les formules').($promo->mode ? ', '.mb_strtolower($promo->mode->label()) : '').'.');
        }

        return $promo;
    }

    /**
     * Tarif : la réduction vient du CODE promo saisi (affiché sur le bandeau, ou déjà rempli depuis la promotion).
     *
     * @return array{formule_name: string, duration_months: int, billing: Billing, unit_price: int, formation_amount: int, discount_amount: int, promotion_id: ?int, promo_code: ?string, registration_fee: int, total_amount: int, promotion: ?Promotion}
     */
    public function quote(Formule $formule, FormationMode $mode, ?string $code = null, bool $withFee = true): array
    {
        $price = $formule->{$mode->priceColumn()};
        if (! $formule->is_published || $price === null) {
            throw ValidationException::withMessages(['mode' => "La formule « {$formule->name} » n'est pas proposée ".mb_strtolower($mode->label()).'.']);
        }
        $formation = (int) $formule->total($mode->priceColumn());
        $promotion = Promotion::normalizeCode($code) ? $this->promotionForCode((string) $code, $formule, $mode) : null;
        $discount = $promotion?->discountOn($formation) ?? 0;
        $fee = $withFee ? $this->registrationFee() : 0;

        return [
            'formule_name' => $formule->name,
            'duration_months' => $formule->duration_months,
            'billing' => $formule->billing,
            'unit_price' => (int) $price,
            'formation_amount' => $formation,
            'discount_amount' => $discount,
            'promotion_id' => $promotion?->id,
            'promo_code' => $promotion?->code,
            'registration_fee' => $fee,
            'total_amount' => $formation - $discount + $fee,
            'promotion' => $promotion,
        ];
    }

    /**
     * Promotions en cours affichées sur le site, par formule et par mode (étiquettes des cartes des formules) :
     * « -20 % avec le code RENTREE26 ».
     *
     * @return array<int, array<string, array{discount: int, label: string, code: ?string}>>
     */
    public function siteDiscounts(iterable $formules): array
    {
        $promos = Promotion::running()->where('show_on_site', true)->get();
        $map = [];
        foreach ($formules as $formule) {
            foreach (FormationMode::cases() as $mode) {
                if ($formule->{$mode->priceColumn()} === null) {
                    continue;
                }
                $amount = (int) $formule->total($mode->priceColumn());
                $best = $promos->filter(fn (Promotion $p) => (! $p->formule_id || $p->formule_id === $formule->id) && (! $p->mode || $p->mode === $mode))
                    ->sortByDesc(fn (Promotion $p) => $p->discountOn($amount))->first();
                if ($best) {
                    $map[$formule->id][$mode->value] = ['discount' => $best->discountOn($amount), 'label' => $best->badge().' avec le code '.$best->code, 'code' => $best->code];
                }
            }
        }

        return $map;
    }

    /** Promotion à proposer pour cette formule et ce mode (affichée sur le site, en cours ou réservable) : la plus forte. */
    public function suggestion(Formule $formule, FormationMode $mode): ?Promotion
    {
        $amount = (int) $formule->total($mode->priceColumn());

        return Promotion::bookable()->where('show_on_site', true)->for($formule, $mode)->get()
            ->sortByDesc(fn (Promotion $p) => $p->discountOn($amount))->first();
    }
}
