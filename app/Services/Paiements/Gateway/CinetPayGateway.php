<?php

namespace App\Services\Paiements\Gateway;

use App\Models\Paiement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * CinetPay (CinetPay Africa SA, établissement de paiement agréé par la BCEAO) : Orange Money, Moov Money, Wave… selon
 * les moyens ouverts sur le compte marchand. API « checkout » v2 :
 *  - POST {base}/v2/payment       : apikey, site_id, transaction_id, amount, currency, description, notify_url,
 *                                    return_url, channels → data.payment_url ;
 *  - POST {base}/v2/payment/check : apikey, site_id, transaction_id → data.status (ACCEPTED, REFUSED, …), data.amount,
 *                                    data.payment_method, data.operator_id.
 * Champs à RE-VÉRIFIER avec la documentation remise à l'ouverture du compte (docs.cinetpay.com) avant la mise en ligne.
 * Clés dans .env (CINETPAY_*), jamais dans le code ni dans la base.
 */
class CinetPayGateway implements PaymentGateway
{
    /** @param  array{api_key?: ?string, site_id?: ?string, base_url?: ?string, channels?: ?string}  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'cinetpay';
    }

    public function label(): string
    {
        return 'CinetPay';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null) && filled($this->config['site_id'] ?? null);
    }

    public function initiate(Paiement $paiement, string $returnUrl, string $notifyUrl): string
    {
        $inscription = $paiement->inscription;
        $response = $this->post('/v2/payment', [
            'transaction_id' => $paiement->gateway_transaction_id,
            'amount' => $paiement->amount,
            'currency' => 'XOF',
            'description' => Str::limit(Str::ascii("Formation {$inscription->formule_name} {$paiement->number}"), 100, ''),
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'channels' => $this->config['channels'] ?? 'MOBILE_MONEY',
            'lang' => 'fr',
            'customer_name' => Str::limit($inscription->name, 100, ''),
            'customer_phone_number' => $paiement->payer_phone ?? $inscription->phone,
            'customer_email' => $inscription->email,
        ]);

        $url = data_get($response, 'data.payment_url');
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new GatewayException('CinetPay n\'a pas renvoyé de page de paiement ('.(data_get($response, 'message') ?? 'réponse inattendue').').');
        }

        return $url;
    }

    public function fetch(string $transactionId): GatewayResult
    {
        $response = $this->post('/v2/payment/check', ['transaction_id' => $transactionId]);
        $status = strtoupper((string) data_get($response, 'data.status', ''));

        return new GatewayResult(
            state: match ($status) {
                'ACCEPTED' => GatewayResult::ACCEPTED,
                'REFUSED', 'CANCELED', 'CANCELLED', 'FAILED' => GatewayResult::REFUSED,
                default => GatewayResult::PENDING,
            },
            amount: is_numeric(data_get($response, 'data.amount')) ? (int) data_get($response, 'data.amount') : null,
            method: data_get($response, 'data.payment_method'),
            operatorReference: data_get($response, 'data.operator_id'),
            phone: data_get($response, 'data.phone_number'),
            rawStatus: $status ?: (string) data_get($response, 'code'),
            message: data_get($response, 'message'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::timeout(20)->acceptJson()->asJson()
                ->post(rtrim($this->config['base_url'] ?? 'https://api-checkout.cinetpay.com', '/').$path, [
                    'apikey' => $this->config['api_key'], 'site_id' => $this->config['site_id'],
                ] + $payload);
        } catch (Throwable $e) {
            throw new GatewayException('CinetPay est injoignable : '.$e->getMessage(), previous: $e);
        }

        return (array) $response->json();
    }
}
