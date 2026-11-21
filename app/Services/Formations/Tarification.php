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

    /** Meilleure promotion automatique (sans code) en cours pour cette formule et ce mode. */
    public function automaticPromotion(Formule $formule, FormationMode $mode): ?Promotion
    {
        $amount = (int) $formule->total($mode->priceColumn());

        return Promotion::running()->whereNull('code')->for($formule, $mode)->get()
            ->sortByDesc(fn (Promotion $p) => $p->discountOn($amount))->first();
    }

    /** Promotion d'un code saisi ; null si le code n'existe pas ou ne s'applique pas. */
    public function codePromotion(?string $code, Formule $formule, FormationMode $mode): ?Promotion
    {
        $code = Promotion::normalizeCode($code);

        return $code ? Promotion::running()->where('code', $code)->for($formule, $mode)->first() : null;
    }

    /**
     * @return array{formule_name: string, duration_months: int, billing: Billing, unit_price: int, formation_amount: int, discount_amount: int, promotion_id: ?int, promo_code: ?string, registration_fee: int, total_amount: int, promotion: ?Promotion}
     */
    public function quote(Formule $formule, FormationMode $mode, ?string $code = null, bool $withFee = true): array
    {
        $price = $formule->{$mode->priceColumn()};
        if (! $formule->is_published || $price === null) {
            throw ValidationException::withMessages(['mode' => "La formule « {$formule->name} » n'est pas proposée ".mb_strtolower($mode->label()).'.']);
        }
        $formation = (int) $formule->total($mode->priceColumn());

        $promotion = $this->automaticPromotion($formule, $mode);
        if (Promotion::normalizeCode($code)) {
            $byCode = $this->codePromotion($code, $formule, $mode);
            if (! $byCode) {
                throw ValidationException::withMessages(['promo_code' => 'Ce code promo n\'est pas valable pour cette formule (code inconnu, terminé ou épuisé).']);
            }
            if (! $promotion || $byCode->discountOn($formation) >= $promotion->discountOn($formation)) {
                $promotion = $byCode;
            }
        }
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
     * Réductions automatiques par formule et mode, pour l'affichage en direct (site, administration).
     *
     * @return array<int, array<string, array{discount: int, label: string}>>
     */
    public function automaticDiscounts(iterable $formules): array
    {
        $map = [];
        foreach ($formules as $formule) {
            foreach (FormationMode::cases() as $mode) {
                if ($formule->{$mode->priceColumn()} === null) {
                    continue;
                }
                $promo = $this->automaticPromotion($formule, $mode);
                if ($promo) {
                    $map[$formule->id][$mode->value] = ['discount' => $promo->discountOn((int) $formule->total($mode->priceColumn())), 'label' => $promo->name.' ('.$promo->badge().')'];
                }
            }
        }

        return $map;
    }
}
