<?php

namespace App\Services\Paiements\Gateway;

use App\Models\Paiement;

/**
 * Prestataire de paiement automatique (agrégateur agréé par la BCEAO). Le site ouvre la page de paiement du prestataire,
 * puis CONFIRME uniquement après avoir lu l'état de la transaction chez lui (fetch) : la notification reçue et le retour
 * du navigateur ne sont que des signaux qui déclenchent cette lecture (cahier §3.8 A).
 */
interface PaymentGateway
{
    /** Identifiant court (« cinetpay », « simulation ») enregistré dans paiements.gateway. */
    public function name(): string;

    /** Nom affiché (« CinetPay »). */
    public function label(): string;

    /** Clés présentes dans .env : le paiement automatique peut être proposé. */
    public function isConfigured(): bool;

    /**
     * Crée la transaction chez le prestataire et renvoie l'adresse de sa page de paiement.
     *
     * @throws GatewayException
     */
    public function initiate(Paiement $paiement, string $returnUrl, string $notifyUrl): string;

    /**
     * Lit l'état de la transaction chez le prestataire.
     *
     * @throws GatewayException
     */
    public function fetch(string $transactionId): GatewayResult;
}
