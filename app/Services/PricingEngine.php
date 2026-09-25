<?php

namespace App\Services;

use App\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * Moteur de tarification dynamique + score qualité/prix (inspiré du Score Report TickPick).
 */
class PricingEngine
{
    /**
     * Prix dynamiquement ajusté selon demande, remplissage et temps restant.
     * Réservé aux plans Pro/Enterprise (contrôle effectué côté organisateur).
     */
    public function dynamicPrice(TicketType $type): int
    {
        $event = $type->event;
        $fillRate = $event ? $event->fillRate() : 0;
        $daysLeft = $event && $event->starts_at ? max(0, now()->diffInDays($event->starts_at, false)) : 30;

        $multiplier = 1.0;

        // Remplissage élevé => hausse modérée plafonnée à +25 %
        if ($fillRate >= 0.9) {
            $multiplier += 0.25;
        } elseif ($fillRate >= 0.7) {
            $multiplier += 0.12;
        } elseif ($fillRate >= 0.5) {
            $multiplier += 0.05;
        }

        // Faible remplissage à J-7 => baisse pour remplir (max -15 %)
        if ($daysLeft <= 7 && $fillRate < 0.4) {
            $multiplier -= 0.15;
        }

        $price = (int) ceil($type->price_displayed * min(1.25, max(0.85, $multiplier)) / 25) * 25;

        return max($price, (int) $type->base_price);
    }

    /**
     * Score qualité/prix sur 10.
     * Confort perçu par type de ticket vs prix relatif dans l'événement.
     */
    public function valueScore(TicketType $type): float
    {
        $comfort = match ($type->kind) {
            'early_bird' => 6.5,
            'standard' => 6.0,
            'vip' => 9.0,
            'loge' => 9.5,
            default => 5.5,
        };

        $event = $type->event;
        $prices = $event
            ? $event->ticketTypes()->where('is_active', true)->pluck('price_displayed')->filter()
            : collect([$type->price_displayed]);

        $min = $prices->min() ?: 1;
        $max = $prices->max() ?: 1;

        // Rapport prix relatif : plus le ticket est proche du prix plancher, meilleur est son score.
        $relative = $max > $min ? ($type->price_displayed - $min) / ($max - $min) : 0;
        $priceValue = 10 - ($relative * 6);

        $score = round(($comfort * 0.4) + ($priceValue * 0.6), 1);

        return max(0.0, min(10.0, $score));
    }

    /**
     * Identifie la "meilleure offre" d'un événement (score le plus élevé).
     *
     * @param  Collection<int, TicketType>  $types
     */
    public function bestOffer(Collection $types): ?TicketType
    {
        return $types->sortByDesc(fn (TicketType $t) => $this->valueScore($t))->first();
    }
}
