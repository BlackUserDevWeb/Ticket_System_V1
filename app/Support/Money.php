<?php

namespace App\Support;

/**
 * Formatage monétaire centralisé : FCFA uniquement, ex. « 12 500 FCFA ».
 */
final class Money
{
    public static function format(int|float|null $amount): string
    {
        return number_format((int) round((float) $amount), 0, ',', ' ').' FCFA';
    }

    /**
     * Convertit un prix HT (organisateur) en prix affiché à l'acheteur,
     * frais de service et commission intégrés (modèle sans frais cachés détaillés).
     */
    public static function withFees(int $basePrice, int $feePercent = 10): int
    {
        return (int) ceil($basePrice * (1 + $feePercent / 100) / 25) * 25; // arrondi aux 25 FCFA supérieurs
    }

    /**
     * Part commission plateforme prélevée sur un prix affiché.
     */
    public static function platformFee(int $displayedPrice, int $feePercent = 10): int
    {
        return (int) round($displayedPrice * ($feePercent / (100 + $feePercent)));
    }
}
