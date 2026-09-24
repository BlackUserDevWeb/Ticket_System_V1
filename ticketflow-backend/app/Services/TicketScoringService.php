<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketUnit;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * TicketScoringService — algorithme de notation qualité/prix inspiré du Score Report de TickPick.
 *
 * Philosophie : transparence totale. Chaque billet reçoit un score /10 combinant
 *   1. la QUALITÉ intrinsèque (note de section + note de vue du siège, renseignées par l'organisateur)
 *   2. le PRIX RELATIF (prix affiché vs médiane des prix affichés de l'événement)
 *
 *      score = 10 × [ w_q × qualité_norm + w_p × (1 − écart_prix_norm) ]
 *
 * Le badge "Meilleure offre" est attribué à l'offre dont le score est maximal ET ≥ seuil
 * (config ticketflow.scoring.best_deal_min). Le calcul s'appuie sur le prix AFFICHÉ
 * (frais de service intégrés) pour rester honnête vis-à-vis de ce que paie l'acheteur.
 */
class TicketScoringService
{
    /**
     * Scorene une collection d'unités (ou d'offres de revente au format compatible).
     * Retourne des tableaux associatifs triés par score décroissant :
     *   ['id', 'label', 'section', 'price_displayed', 'score', 'is_best_deal']
     *
     * @param Collection<TicketUnit> $units unités vendables d'un même événement
     */
    public function scoreUnits(Event $event, Collection $units): array
    {
        if ($units->isEmpty()) {
            return [];
        }

        $serviceRate = config('ticketflow.service_rate');
        $wQuality = config('ticketflow.scoring.quality_weight');
        $wPrice = config('ticketflow.scoring.price_weight');

        // Prix affichés (frais inclus) → base de comparaison pour les acheteurs.
        $displayed = $units->map(fn (TicketUnit $u) => $this->displayedPrice($u, $serviceRate));
        $median = $this->median($displayed->all());
        // Étale de prix utilisée pour normaliser l'écart (±40 % autour de la médiane suffit
        // à couvrir tous les cas réels ; évite qu'un outlier extrême écrase toute la gamme).
        $spread = max(1, $median * 0.4);

        $scored = $units->map(function (TicketUnit $u) use ($median, $spread, $wQuality, $wPrice, $serviceRate) {
            // Qualité intrinsèque : moyenne pondérée section (60 %) / vue du siège (40 %),
            // le tout ramené sur 0..1.
            $sectionScore = $u->section?->quality_score ?? 50;
            $viewScore = $u->view_score;
            $quality = (($sectionScore * 0.6 + $viewScore * 0.4) / 100);

            // Écart prix : > médiane → malus, < médiane → bonus. Ramené sur 0..1.
            $priceDisplayed = $this->displayedPrice($u, $serviceRate);
            $priceGap = max(-1, min(1, ($priceDisplayed - $median) / $spread));
            $priceValue = (1 - (($priceGap + 1) / 2)); // 1 si nettement sous la médiane

            $score = round(10 * ($wQuality * $quality + $wPrice * $priceValue), 1);

            return [
                'id' => $u->id,
                'label' => $u->label,
                'section' => $u->section?->name,
                'ticket_type' => $u->ticketType?->name,
                'price_displayed' => $priceDisplayed,
                'price_displayed_formatted' => Money::format($priceDisplayed),
                'quality' => round($quality * 10, 1),
                'score' => $score,
            ];
        })->sortByDesc('score')->values()->all();

        // Badge "Meilleure offre" : meilleur score global s'il dépasse le seuil d'honnêteté.
        $threshold = config('ticketflow.scoring.best_deal_min');
        foreach ($scored as $i => &$row) {
            $row['is_best_deal'] = $i === 0 && $row['score'] >= $threshold;
        }

        return $scored;
    }

    /** Prix affiché à l'acheteur : net organisateur + frais de service INTÉGRÉS. */
    public function displayedPrice(TicketUnit $unit, ?float $serviceRate = null): int
    {
        $rate = $serviceRate ?? config('ticketflow.service_rate');
        return (int) round($unit->netPrice() * (1 + $rate));
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);
        return $n % 2 === 1 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
