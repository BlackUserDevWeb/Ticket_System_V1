<?php

namespace App\Services\MobileMoney;

use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

/**
 * Point d'entrée unique des paiements Mobile Money de TicketFlow.
 */
class PaymentService
{
    public const METHODS = ['moov_money', 'mixx_by_yas'];

    public function __construct(
        private readonly MoovMoneyGateway $moov,
        private readonly MixxByYasGateway $mixx,
    ) {}

    public function gatewayFor(string $method): MobileMoneyGateway
    {
        return match ($method) {
            'moov_money' => $this->moov,
            'mixx_by_yas' => $this->mixx,
            default => throw new InvalidArgumentException("Méthode de paiement inconnue: {$method}"),
        };
    }

    /**
     * Initie le débit d'une commande. La référence fournie au prestataire
     * contient la référence commande + un suffixe de tentative (permet de
     * simuler un échec via le mot-clé REFUS en mode local).
     */
    public function initiate(Order $order, User $user, string $phone): \App\DataTransferObjects\PaymentResult
    {
        $gateway = $this->gatewayFor($order->payment_method ?? 'moov_money');

        return $gateway->charge($phone, $order->total, $order->reference.'-T1');
    }

    public function checkStatus(Order $order): \App\DataTransferObjects\PaymentResult
    {
        $gateway = $this->gatewayFor($order->payment_method ?? 'moov_money');

        return $gateway->status($order->reference.'-T1');
    }

    public function refund(Order $order): \App\DataTransferObjects\PaymentResult
    {
        $gateway = $this->gatewayFor($order->payment_method ?? 'moov_money');

        return $gateway->refund($order->provider_transaction_id ?? $order->reference.'-T1', $order->total);
    }
}
