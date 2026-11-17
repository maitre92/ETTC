<?php

namespace App\Services\Paiements\Gateway;

use App\Models\Paiement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Prestataire SIMULÉ (développement et démonstration seulement, jamais en production) : une page du site joue le rôle de
 * la page de l'opérateur (« Payer » / « Refuser »). Permet de tester tout le parcours automatique avant d'avoir le compte
 * marchand. L'état « chez le prestataire » est gardé en cache (tableau, cache.serializable_classes = false).
 */
class SimulationGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'simulation';
    }

    public function label(): string
    {
        return 'Simulation (test)';
    }

    public function isConfigured(): bool
    {
        return ! app()->isProduction();
    }

    public function initiate(Paiement $paiement, string $returnUrl, string $notifyUrl): string
    {
        Cache::put($this->key($paiement->gateway_transaction_id), ['state' => GatewayResult::PENDING, 'amount' => $paiement->amount, 'return' => $returnUrl, 'notify' => $notifyUrl], now()->addDay());

        return URL::temporarySignedRoute('site.paiement-simule', now()->addHour(), ['transaction' => $paiement->gateway_transaction_id]);
    }

    public function fetch(string $transactionId): GatewayResult
    {
        $data = Cache::get($this->key($transactionId));
        if (! is_array($data)) {
            throw new GatewayException('Transaction simulée inconnue.');
        }

        return new GatewayResult($data['state'], $data['amount'], 'OM', $data['state'] === GatewayResult::ACCEPTED ? 'SIM'.strtoupper(substr($transactionId, -8)) : null, rawStatus: strtoupper($data['state']));
    }

    /** Décision prise sur la page simulée : l'« opérateur » enregistre l'état. @return array{return: string, notify: string}|null */
    public function decide(string $transactionId, bool $accept): ?array
    {
        $data = Cache::get($this->key($transactionId));
        if (! is_array($data)) {
            return null;
        }
        $data['state'] = $accept ? GatewayResult::ACCEPTED : GatewayResult::REFUSED;
        Cache::put($this->key($transactionId), $data, now()->addDay());

        return ['return' => $data['return'], 'notify' => $data['notify']];
    }

    private function key(string $transactionId): string
    {
        return 'paiement-simule.'.$transactionId;
    }
}
