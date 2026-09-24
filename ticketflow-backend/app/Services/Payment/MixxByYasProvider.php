<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mixx by Yas (Togocom) — intégration API paiement marchand.
 *
 * Différence avec Moov : authentification par paire api_key + timestamp + signature
 * MD5 requête (spécificité Togocom), et webhook sous envelope {"result": {...}}.
 */
class MixxByYasProvider extends PaymentProvider
{
    public function key(): string { return 'mixx_yas'; }
    public function label(): string { return 'Mixx by Yas'; }

    private function cfg(string $k)
    {
        return config("ticketflow.payments.mixx_yas.$k");
    }

    public function initiate(Order $order, Payment $payment): array
    {
        if (!$this->cfg('api_key')) {
            Log::info('[MixxByYas][sandbox] initiation simulée', ['order' => $order->reference]);
            return ['reference' => 'SANDBOX-' . $payment->id, 'status' => 'pending', 'message' => null];
        }

        $timestamp = (string) now()->timestamp;
        // Signature spécifique Togocom : md5(api_key + secret + timestamp + montant).
        $signature = md5($this->cfg('api_key') . $this->cfg('secret') . $timestamp . $payment->amount);

        try {
            $response = Http::timeout(20)->post($this->cfg('base_url') . '/api/v2/debit', [
                'apikey' => $this->cfg('api_key'),
                'timestamp' => $timestamp,
                'signature' => $signature,
                'msisdn' => $payment->payer_phone,
                'amount' => $payment->amount,
                'currency' => 'XOF',
                'clientReference' => 'TF-' . $order->reference,
                'label' => 'Billetterie TicketFlow',
            ]);

            if ($response->failed()) {
                return ['reference' => null, 'status' => 'failed', 'message' => $response->json('message', 'Erreur passerelle Mixx by Yas')];
            }

            return [
                'reference' => $response->json('data.id'),
                'status' => 'pending',
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[MixxByYas] exception initiation', ['error' => $e->getMessage()]);
            return ['reference' => null, 'status' => 'failed', 'message' => 'Service temporairement indisponible'];
        }
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = $this->cfg('webhook_secret');
        if (!$secret) {
            return app()->environment('local');
        }
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader);
    }

    public function normalizeWebhook(array $payload): array
    {
        // Format Togocom : { "result": { "id": "...", "status": "succeeded|declined|pending", "message": "..." } }
        $r = $payload['result'] ?? [];
        return [
            'provider_reference' => $r['id'] ?? null,
            'status' => match (strtolower($r['status'] ?? '')) {
                'succeeded' => 'success',
                'declined', 'failed', 'expired' => 'failed',
                default => 'pending',
            },
            'failure_reason' => $r['message'] ?? null,
        ];
    }

    public function refund(Payment $payment, int $amountXof): bool
    {
        if (!$this->cfg('api_key')) {
            return true; // sandbox
        }
        $timestamp = (string) now()->timestamp;
        $signature = md5($this->cfg('api_key') . $this->cfg('secret') . $timestamp . $amountXof);
        $response = Http::post($this->cfg('base_url') . '/api/v2/refund', [
            'apikey' => $this->cfg('api_key'),
            'timestamp' => $timestamp,
            'signature' => $signature,
            'transactionId' => $payment->provider_reference,
            'amount' => $amountXof,
        ]);
        return $response->successful();
    }
}
