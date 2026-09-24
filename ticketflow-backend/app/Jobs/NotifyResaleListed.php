<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Resale;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notifie les acheteurs ayant un favori sur l'événement ou une alerte prix active
 * qu'une revente (Autolist) vient d'être publiée. Découplé pour ne pas ralentir
 * la mise en vente côté client.
 */
class NotifyResaleListed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $resaleId) {}

    public function handle(): void
    {
        $resale = Resale::with('event')->findOrFail($this->resaleId);

        // Users qui ont liké l'événement OU une alerte prix active dessus.
        $watchers = User::whereIn('id', function ($q) use ($resale) {
            $q->select('user_id')->from('favorites')
              ->where('favoritable_type', $resale->event->getMorphClass())
              ->where('favoritable_id', $resale->event_id)
              ->union(
                  \Illuminate\Support\Facades\DB::table('price_alerts')
                      ->select('user_id')
                      ->where('event_id', $resale->event_id)
                      ->where('is_active', true)
              );
        })->cursor(); // gros événements → itération sans exploser la mémoire

        foreach ($watchers as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'resale_available',
                'title' => "Nouvelle revente : {$resale->event->title}",
                'body' => 'Une place se libère à ' . \App\Support\Money::format($resale->ask_price),
                'link' => "/evenement/{$resale->event->slug}/revente",
            ]);
        }
    }
}
