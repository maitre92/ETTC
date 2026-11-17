<?php

namespace App\Services\Paiements\Gateway;

/**
 * État d'une transaction LU CHEZ LE PRESTATAIRE (appel serveur à serveur), jamais d'après ce que renvoie le navigateur.
 */
final class GatewayResult
{
    public const ACCEPTED = 'accepted';

    public const REFUSED = 'refused';

    public const PENDING = 'pending';

    public function __construct(
        public readonly string $state,
        public readonly ?int $amount = null,
        public readonly ?string $method = null,
        public readonly ?string $operatorReference = null,
        public readonly ?string $phone = null,
        public readonly ?string $rawStatus = null,
        public readonly ?string $message = null,
    ) {}

    public function isAccepted(): bool
    {
        return $this->state === self::ACCEPTED;
    }

    public function isRefused(): bool
    {
        return $this->state === self::REFUSED;
    }
}
