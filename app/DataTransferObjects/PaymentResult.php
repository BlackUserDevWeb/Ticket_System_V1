<?php

namespace App\DataTransferObjects;

/**
 * Résultat normalisé d'une opération Mobile Money (succès, échec ou attente).
 */
final readonly class PaymentResult
{
    public function __construct(
        public string $status,               // pending | paid | failed
        public ?string $transactionId = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public static function pending(string $transactionId, ?string $message = null, array $raw = []): self
    {
        return new self('pending', $transactionId, $message, $raw);
    }

    public static function paid(string $transactionId, array $raw = []): self
    {
        return new self('paid', $transactionId, null, $raw);
    }

    public static function failed(?string $message = null, array $raw = []): self
    {
        return new self('failed', null, $message, $raw);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
