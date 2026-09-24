<?php

namespace App\Support;

/**
 * Utilitaire monétaire XOF.
 * Justification : tous les montants circulent en ENTIERS XOF dans toute la stack
 * (pas de centimes en FCFA). Les arrondis se font une seule fois, au moment du calcul
 * du prix affiché, avec round() — cohérent avec les usages Mobile Money.
 */
final class Money
{
    /** Formate 12500 → "12 500 FCFA" (conforme à la spec UI). */
    public static function format(int $xof): string
    {
        return number_format($xof, 0, ',', ' ') . ' FCFA';
    }

    /** Applique un taux (frais de service, commission) et retourne un entier XOF arrondi. */
    public static function applyRate(int $amount, float $rate): int
    {
        return (int) round($amount * $rate);
    }

    /** Valide qu'un montant reçu de l'API publique est plausible (< 10 M FCFA par ligne). */
    public static function isPlausible(int $xof): bool
    {
        return $xof > 0 && $xof <= 10_000_000;
    }
}
