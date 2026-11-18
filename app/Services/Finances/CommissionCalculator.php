<?php

namespace App\Services\Finances;

use App\Models\Inscription;
use App\Support\SiteSettings;

/**
 * Commission du développeur (cahier §8) : un pourcentage (20 % par défaut, réglable) des FRAIS DE FORMATION
 * effectivement encaissés ; jamais sur le test de niveau. Les formations personnalisées n'y entrent que si le centre
 * l'a convenu (réglage, désactivé par défaut). Calculée à la confirmation du paiement et figée sur lui.
 */
class CommissionCalculator
{
    public const DEFAULT_RATE = 20.0;

    public function __construct(private readonly SiteSettings $settings) {}

    public function rate(): float
    {
        $rate = (float) str_replace(',', '.', $this->settings->get('finance.commission_rate'));

        return $rate >= 0 && $rate <= 100 ? $rate : self::DEFAULT_RATE;
    }

    public function includesPersonalised(): bool
    {
        return $this->settings->get('finance.include_personnalisee') === '1';
    }

    public function isEligible(Inscription $inscription): bool
    {
        return $inscription->formule_id !== null || $this->includesPersonalised();
    }

    /** @return array{commission_rate: ?float, commission_amount: int} */
    public function forPayment(Inscription $inscription, int $amount): array
    {
        if (! $this->isEligible($inscription)) {
            return ['commission_rate' => null, 'commission_amount' => 0];
        }
        $rate = $this->rate();

        return ['commission_rate' => $rate, 'commission_amount' => (int) round($amount * $rate / 100)];
    }
}
