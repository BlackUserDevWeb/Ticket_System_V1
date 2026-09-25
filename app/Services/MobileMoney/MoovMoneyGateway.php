<?php

namespace App\Services\MobileMoney;

use App\DataTransferObjects\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Passerelle Moov Money Togo (Flooz) — intégration de service Laravel dédiée.
 *
 * En mode "fake" (MOOV_FAKE=true, par défaut en local/dev/tests), la passerelle
 * simule le comportement du fournisseur : confirmation automatique après un délai,
 * échec aléatoire désactivable, webhooks simulables. En production, les appels HTTP
 * réels sont effectués vers l'API Flooz avec signature HMAC des requêtes.
 */
class MoovMoneyGateway implements MobileMoneyGateway
{
    public function __construct(
        private readonly bool $fake = true,
        private readonly string $baseUrl = '',
        private readonly string $apiKey = '',
        private readonly string $secret = '',
        private readonly int $timeout = 30,
    ) {}

    public function name(): string
    {
        return 'moov_money';
    }

    public function charge(string $phoneNumber, int $amountXof, string $reference): PaymentResult
    {
        if (! $this->isValidNumber($phoneNumber)) {
            return PaymentResult::failed('Numéro Moov invalide (format +228 XX XX XX XX attendu).');
        }

        if ($this->fake) {
            return $this->fakeCharge($reference);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->signedHeaders($reference, $amountXof))
                ->post($this->baseUrl.'/v1/payments/debit', [
                    'msisdn' => $phoneNumber,
                    'amount' => $amountXof,
                    'currency' => 'XOF',
                    'external_reference' => $reference,
                ]);

            if ($response->failed()) {
                return PaymentResult::failed('Moov Money: erreur fournisseur ('.$response->status().').', $response->json() ?? []);
            }

            $json = $response->json();

            return match ($json['status'] ?? 'pending') {
                'success' => PaymentResult::paid($json['transaction_id'] ?? $reference, $json),
                'failed' => PaymentResult::failed($json['message'] ?? 'Échec inconnu', $json),
                default => PaymentResult::pending($json['transaction_id'] ?? $reference, 'Confirmez le paiement sur votre téléphone.', $json),
            };
        } catch (\Throwable $e) {
            report($e);

            return PaymentResult::failed('Moov Money injoignable, réessayez.');
        }
    }

    public function status(string $reference): PaymentResult
    {
        if ($this->fake) {
            return $this->fakeStatus($reference);
        }

        try {
            $json = Http::timeout($this->timeout)
                ->withHeaders($this->signedHeaders($reference, 0))
                ->get($this->baseUrl.'/v1/payments/'.$reference)
                ->json() ?? [];

            return match ($json['status'] ?? 'pending') {
                'success' => PaymentResult::paid($json['transaction_id'] ?? $reference, $json),
                'failed' => PaymentResult::failed($json['message'] ?? null, $json),
                default => PaymentResult::pending($reference, null, $json),
            };
        } catch (\Throwable $e) {
            report($e);

            return PaymentResult::pending($reference, 'Statut indisponible.');
        }
    }

    public function refund(string $reference, int $amountXof): PaymentResult
    {
        if ($this->fake) {
            return PaymentResult::paid('ref-'.Str::random(10), ['refund_of' => $reference]);
        }

        $json = Http::timeout($this->timeout)
            ->withHeaders($this->signedHeaders($reference, $amountXof))
            ->post($this->baseUrl.'/v1/payments/refund', [
                'transaction_id' => $reference,
                'amount' => $amountXof,
            ])->json() ?? [];

        return ($json['status'] ?? '') === 'success'
            ? PaymentResult::paid($json['refund_id'] ?? Str::random(10), $json)
            : PaymentResult::failed($json['message'] ?? 'Remboursement impossible', $json);
    }

    private function signedHeaders(string $reference, int $amount): array
    {
        $payload = $reference.$amount;

        return [
            'X-API-Key' => $this->apiKey,
            'X-Signature' => hash_hmac('sha256', $payload, $this->secret),
            'Accept' => 'application/json',
        ];
    }

    private function isValidNumber(string $phone): bool
    {
        return (bool) preg_match('/^\+228[0-9\s]{8,12}$/', $phone);
    }

    // ---- Simulation locale ----

    private function fakeCharge(string $reference): PaymentResult
    {
        session()->put("moov_fake.{$reference}", now()->timestamp);

        return PaymentResult::pending('MOOV-FAKE-'.$reference, 'Simulez la validation du paiement.', ['fake' => true]);
    }

    private function fakeStatus(string $reference): PaymentResult
    {
        $started = session("moov_fake.{$reference}");

        // Réussite simulée après 3 secondes ; échec si le préfixe REFUS est utilisé.
        if (str_contains($reference, 'REFUS')) {
            return PaymentResult::failed('Paiement refusé par le client.');
        }

        return $started && now()->timestamp - $started >= 3
            ? PaymentResult::paid('MOOV-FAKE-'.substr(md5($reference), 0, 12))
            : PaymentResult::pending('MOOV-FAKE-'.$reference, 'En attente de confirmation.');
    }
}
