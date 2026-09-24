<?php

namespace App\Services;

use App\Models\TicketType;
use Illuminate\Support\Facades\Cache;

/**
 * DynamicPricingEngine — tarification dynamique intelligente (plan Pro+).
 *
 * Facteurs analysés :
 *   - taux de remplissage du type de billet (sold/stock)
 *   - temps restant avant l'événement (fenêtre "dernière minute")
 *   - vélocité des ventes sur les 6 dernières heures (proxy de la demande, via Redis)
 *
 * Garanties de sécurité :
 *   - le multiplier reste dans [min_multiplier, max_multiplier] de la config globale ;
 *   - il ne peut JAMAIS faire dépasser ticket_types.max_price choisi par l'organisateur ;
 *   - résultat mis en cache 60 s (Redis) : stable pendant une session d'achat,
 *     et identique pour tous les utilisateurs (pas de prix personnalisés cachés — éthique).
 */
class DynamicPricingEngine
{
    public function currentNetPrice(TicketType $type): int
    {
        return Cache::remember(
            "dynamic-price:{$type->id}",
            60,
            fn () => $this->compute($type)
        );
    }

    private function compute(TicketType $type): int
    {
        $event = $type->event;
        if (!$event->dynamic_pricing_enabled) {
            return $type->base_price;
        }

        $cfg = config('ticketflow.dynamic_pricing');
        $multiplier = 1.0;

        // 1) Pression sur le stock : au-delà du seuil de remplissage, hausse progressive.
        $fill = $type->stock > 0 ? $type->sold / $type->stock : 0;
        if ($fill >= $cfg['fill_threshold_hot']) {
            // linéaire jusqu'à +20 % à 100 % de remplissage
            $multiplier += ($fill - $cfg['fill_threshold_hot']) / (1 - $cfg['fill_threshold_hot']) * 0.20;
        } elseif ($fill <= 0.15) {
            // Under-demande early : légère baisse pour amorcer les ventes (max −10 %).
            $multiplier -= 0.10 * (1 - $fill / 0.15);
        }

        // 2) Fenêtre dernière minute : la demande togolaise explose J-3 → +10 %.
        $hoursLeft = now()->diffInHours($event->starts_at, false);
        if ($hoursLeft > 0 && $hoursLeft <= $cfg['hours_before_hot']) {
            $multiplier += 0.10;
        }

        // 3) Vélocité des ventes (évènements Redis incrémentés à chaque vente).
        $velocity = (int) Cache::get("sales-velocity:{$type->id}", 0); // ventes / 6 h
        if ($velocity >= 30) {
            $multiplier += 0.08;
        } elseif ($velocity >= 12) {
            $multiplier += 0.04;
        }

        // Bornes globales puis plafond organisateur.
        $multiplier = max($cfg['min_multiplier'], min($cfg['max_multiplier'], $multiplier));
        $price = (int) round($type->base_price * $multiplier);
        if ($type->max_price) {
            $price = min($price, $type->max_price);
        }

        return max($type->base_price, $price); // ne descend jamais sous le filet ci-après plancher organisé
    }

    /** À appeler après chaque vente confirmée (CheckoutService) pour alimenter le signal de demande. */
    public function recordSale(TicketType $type, int $quantity = 1): void
    {
        $key = "sales-velocity:{$type->id}";
        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->addHours(6));
        }
        Cache::increment($key, $quantity);
        Cache::forget("dynamic-price:{$type->id}"); // force recalcul au prochain affichage
    }
}
