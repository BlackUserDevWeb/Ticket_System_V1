<?php

namespace App\Console;

use App\Models\Notification;
use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Cron (horaire) : rappels J-1 et H-2 avant événement pour les porteurs de tickets.
 * Fenêtres glissantes ±30 min autour du seuil pour éviter doublons et oublis.
 */
class SendEventReminders extends Command
{
    protected $signature = 'ticketflow:reminders';
    protected $description = 'Envoie les rappels avant événement (config ticketflow.notifications.event_reminder_hours)';

    public function handle(): int
    {
        foreach (config('ticketflow.notifications.event_reminder_hours') as $hours) {
            $start = now()->addHours($hours)->subMinutes(30);
            $end = now()->addHours($hours)->addMinutes(30);

            Ticket::with('owner', 'unit.event')
                ->where('status', 'active')
                ->whereHas('unit.event', fn ($q) => $q->whereBetween('starts_at', [$start, $end]))
                ->chunkById(500, function ($tickets) use ($hours) {
                    foreach ($tickets as $ticket) {
                        $dedupeKey = "reminder:{$ticket->id}:{$hours}";
                        if (cache()->has($dedupeKey)) {
                            continue;
                        }
                        Notification::create([
                            'user_id' => $ticket->owner_id,
                            'type' => 'reminder_event',
                            'title' => "« {$ticket->unit->event->title} » commence dans {$hours} h",
                            'body' => 'Présentez votre QR code à l\'entrée — pensez à le télécharger avant de perdre la réseau.',
                            'link' => '/mes-billets',
                        ]);
                        cache()->put($dedupeKey, 1, now()->addDay());
                    }
                });
        }
        return self::SUCCESS;
    }
}
