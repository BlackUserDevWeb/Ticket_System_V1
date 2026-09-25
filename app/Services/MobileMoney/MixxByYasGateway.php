<?php

namespace App\Services\MobileMoney;

use App\DataTransferObjects\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Passerelle Mixx by Yas (ex-Flooz / Togocom) — intégration de service Laravel dédiée.
 * Même contrat que MoovMoneyGateway, mode "fake" pour le dev/local/tests.
 */
class MixxByYasGateway implements MobileMoneyGateway
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
        return 'mixx_by_yas';
    }

    public function charge(string $phoneNumber, int $amountXof, string $reference): PaymentResult
    {
        if (! $this->isValidNumber($phoneNumber)) {
            return PaymentResult::failed('Numéro Mixx by Yas invalide (format +228 XX XX XX XX attendu).');
        }

        if ($this->fake) {
            session()->put("mixx_fake.{$reference}", now()->timestamp);

            return PaymentResult::pending('MIXX-FAKE-'.$reference, 'Validez le paiement USSD sur votre téléphone.', ['fake' => true]);
        }

        try {
            $json = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'X-Signature' => hash_hmac('sha256', $reference.$amountXof, $this->secret),
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl.'/api/v2/collections', [
                    'customer_msisdn' => $phoneNumber,
                    'amount' => $amountXof,
                    'currency' => 'XOF',
                    'reference' => $reference,
                ])->json() ?? [];

            return match ($json['data']['status'] ?? 'pending') {
                'SUCCESSFUL' => PaymentResult::paid($json['data']['checkout_id'] ?? $reference, $json),
                'FAILED' => PaymentResult::failed($json['data']['message'] ?? 'Échec Mixx', $json),
                default => PaymentResult::pending($json['data']['checkout_id'] ?? $reference, 'Confirmation requise.', $json),
            };
        } catch (\Throwable $e) {
            report($e);

            return PaymentResult::failed('Mixx by Yas injoignable, réessayez.');
        }
    }

    public function status(string $reference): PaymentResult
    {
        if ($this->fake) {
            $started = session("mixx_fake.{$reference}");

            if (str_contains($reference, 'REFUS')) {
                return PaymentResult::failed('Paiement refusé par le client.');
            }

            return $started && now()->timestamp - $started >= 3
                ? PaymentResult::paid('MIXX-FAKE-'.substr(md5($reference), 0, 12))
                : PaymentResult::pending('MIXX-FAKE-'.$reference, 'En attente de confirmation.');
        }

        $json = Http::timeout($this->timeout)
            ->withHeaders(['Authorization' => 'Bearer '.$this->apiKey])
            ->get($this->baseUrl.'/api/v2/collections/'.$reference)
            ->json() ?? [];

        return match ($json['data']['status'] ?? 'pending') {
            'SUCCESSFUL' => PaymentResult::paid($reference, $json),
            'FAILED' => PaymentResult::failed(null, $json),
            default => PaymentResult::pending($reference, null, $json),
        };
    }

    public function refund(string $reference, int $amountXof): PaymentResult
    {
        if ($this->fake) {
            return PaymentResult::paid('ref-'.Str::random(10), ['refund_of' => $reference]);
        }

        $json = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'X-Signature' => hash_hmac('sha256', $reference.$amountXof, $this->secret),
            ])
            ->post($this->baseUrl.'/api/v2/refunds', [
                'checkout_id' => $reference,
                'amount' => $amountXof,
            ])->json() ?? [];

        return ($json['data']['status'] ?? '') === 'SUCCESSFUL'
            ? PaymentResult::paid($json['data']['refund_id'] ?? Str::random(10), $json)
            : PaymentResult::failed('Remboursement impossible', $json);
    }

    private function isValidNumber(string $phone): bool
    {
        return (bool) preg_match('/^\+228[0-9\s]{8,12}$/', $phone);
    }
}
