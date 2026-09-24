<?php

namespace App\Console;

use App\Models\Event;
use App\Models\PriceAlert;
use App\Services\TicketScoringService;
use Illuminate\Console\Command;

/**
 * Cron (horaire) : compare le prix minimum AFFICHÉ d'un événement au seuil des alertes
 * prix actives ; déclenche notification + e-mail quand le seuil est franchi (une seule fois).
 */
class CheckPriceAlerts extends Command
{
    protected $signature = 'ticketflow:price-alerts';
    protected $description = 'Déclenche les alertes de baisse de prix';

    public function handle(TicketScoringService $scoring): int
    {
        PriceAlert::with('user', 'event')->where('is_active', true)->chunkById(200, function ($alerts) use ($scoring) {
            foreach ($alerts as $alert) {
                $minDisplayed = $alert->event->units()->open()
                    ->get()
                    ->map(fn ($u) => $scoring->displayedPrice($u))
                    ->min();
                if ($minDisplayed !== null && $minDisplayed <= $alert->target_price) {
                    \App\Models\Notification::create([
                        'user_id' => $alert->user_id,
                        'type' => 'price_drop',
                        'title' => "Baisse de prix : {$alert->event->title}",
                        'body' => 'Le prix est passé à ' . \App\Support\Money::format($minDisplayed),
                        'link' => "/evenement/{$alert->event->slug}",
                    ]);
                    $alert->update(['is_active' => false, 'triggered_at' => now()]);
                }
            }
        });
        return self::SUCCESS;
    }
}
