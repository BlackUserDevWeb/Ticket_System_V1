<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\EventView;
use Illuminate\Http\Request;

/**
 * Petite classe utilitaire de contrôleur : enregistre une vue d'événement SANS bloquer
 * la réponse (after response via queue si config queue != sync) et déduplique 10 min
 * par (event, user/ip) pour ne pas fausser le taux de conversion.
 */
class EventViewRecorder
{
    public static function record(Event $event, Request $request): void
    {
        $visitorKey = 'view:' . $event->id . ':' . md5((string) ($request->user()?->id ?? $request->ip()));
        if (cache()->has($visitorKey)) {
            return;
        }
        cache()->put($visitorKey, 1, now()->addMinutes(10));

        EventView::create([
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'source' => match (parse_url((string) $request->header('referer'), PHP_URL_HOST) ?? '') {
                '' => 'direct',
                str_contains((string) $request->header('referer'), 'facebook') => 'reseau_social',
                str_contains((string) $request->header('referer'), 'google') => 'recherche',
                default => 'externe',
            },
            'viewed_at' => now(),
        ]);
    }
}
