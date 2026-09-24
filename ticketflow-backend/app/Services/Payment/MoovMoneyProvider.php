<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moov Money Togo (plateforme Flooz) — intégration API REST.
 *
 * Parcours : POST /collections (push USSI sur le +228 du payeur) puis confirmation
 * par webhook signé HMAC-SHA256. En sandbox (MOOV_API_KEY absente), on simule une
 * réponse "pending" pour permettre le développement local sans contrat opérateur.
 */
class MoovMoneyProvider extends PaymentProvider
{
    public function key(): string { return 'moov_money'; }
    public function label(): string { return 'Moov Money'; }

    private function cfg(string $k)
    {
        return config("ticketflow.payments.moov_money.$k");
    }

    public function initiate(Order $order, Payment $payment): array
    {
        // Sandbox local : pas de credentials → la confirmation se fera via le endpoint
        // de test ou un webhook simulé (voir tests Pest PaymentWebhookTest).
        if (!$this->cfg('api_key')) {
            Log::info('[MoovMoney][sandbox] initiation simulée', ['order' => $order->reference]);
            return ['reference' => 'SANDBOX-' . $payment->id, 'status' => 'pending', 'message' => null];
        }

        try {
            $response = Http::withToken($this->cfg('api_key'))
                ->timeout(20)
                ->post($this->cfg('base_url') . '/v1/collections', [
                    'merchantId' => $this->cfg('secret'),       // id marchand fourni par Moov
                    'amount' => $payment->amount,               // entier XOF
                    'currency' => 'XOF',
                    'payerMsisdn' => $payment->payer_phone,
                    'externalReference' => $payment->order_id . '-' . $payment->created_at->timestamp,
                    'description' => "TicketFlow commande {$order->reference}",
                ]);

            if ($response->failed()) {
                return ['reference' => null, 'status' => 'failed', 'message' => $response->json('message', 'Erreur passerelle Moov')];
            }

            return [
                'reference' => $response->json('data.transactionId'),
                'status' => 'pending', // push USSI envoyé, attente du webhook
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[MoovMoney] exception initiation', ['error' => $e->getMessage()]);
            return ['reference' => null, 'status' => 'failed', 'message' => 'Service temporairement indisponible'];
        }
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = $this->cfg('webhook_secret');
        if (!$secret) {
            return app()->environment('local'); // accepte en local uniquement
        }
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader);
    }

    public function normalizeWebhook(array $payload): array
    {
        // Format Flooz : { "transactionId": "...", "state": "SUCCESS|FAILED|PENDING", "reason": "..." }
        return [
            'provider_reference' => $payload['transactionId'] ?? null,
            'status' => match (strtoupper($payload['state'] ?? '')) {
                'SUCCESS' => 'success',
                'FAILED', 'REJECTED' => 'failed',
                default => 'pending',
            },
            'failure_reason' => $payload['reason'] ?? null,
        ];
    }

    public function refund(Payment $payment, int $amountXof): bool
    {
        if (!$this->cfg('api_key')) {
            return true; // sandbox
        }
        $response = Http::withToken($this->cfg('api_key'))
            ->post($this->cfg('base_url') . '/v1/refunds', [
                'originalTransactionId' => $payment->provider_reference,
                'amount' => $amountXof,
                'currency' => 'XOF',
            ]);
        return $response->successful();
    }
}
