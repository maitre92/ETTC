<?php

namespace App\Services\Paiements\Gateway;

/** Prestataire choisi dans .env (PAYMENT_GATEWAY) ; null si aucun n'est actif (paiement par déclaration seulement). */
class GatewayManager
{
    public function active(): ?PaymentGateway
    {
        $gateway = $this->make((string) config('services.paiement.gateway'));

        return $gateway?->isConfigured() ? $gateway : null;
    }

    public function make(string $name): ?PaymentGateway
    {
        return match ($name) {
            'cinetpay' => new CinetPayGateway((array) config('services.cinetpay')),
            'simulation' => app(SimulationGateway::class),
            default => null,
        };
    }
}
