<?php

namespace App\Services\MobileMoney;

use App\DataTransferObjects\PaymentResult;

/**
 * Contrat commun des passerelles Mobile Money (Moov Money, Mixx by Yas).
 */
interface MobileMoneyGateway
{
    public function name(): string;

    /**
     * Initier un débit sur le wallet du client.
     */
    public function charge(string $phoneNumber, int $amountXof, string $reference): PaymentResult;

    /**
     * Vérifier l'état d'une transaction auprès du fournisseur.
     */
    public function status(string $reference): PaymentResult;

    /**
     * Remboursement partiel ou total.
     */
    public function refund(string $reference, int $amountXof): PaymentResult;
}
