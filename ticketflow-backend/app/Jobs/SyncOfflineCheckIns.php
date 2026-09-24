<?php

namespace App\Jobs;

use App\Models\CheckIn;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reçoit le lot de check-ins enregistrés offline par le scanner (IndexedDB) et les
 * réconcilie avec la vérité serveur. Règle : premier scan gagnant (unique index sur
 * check_ins.ticket_id), les doublons sont marqués "refusés" et renvoyés au front.
 * Retourne le rapport de synchro via le callback passé au contrôleur.
 */
class SyncOfflineCheckIns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int, array{code:string, gate:string, scanned_at:string}> $entries */
    public function __construct(public int $eventId, public array $entries, public ?int $syncBatchId = null) {}

    public function handle(): array
    {
        $accepted = 0;
        $rejected = [];
        foreach ($this->entries as $entry) {
            $ticket = Ticket::where('code', $entry['code'])->lockForUpdate()->first();
            if (!$ticket || $ticket->unit->event_id !== $this->eventId) {
                $rejected[] = ['code' => $entry['code'], 'reason' => 'inconnu'];
                continue;
            }
            $exists = CheckIn::where('ticket_id', $ticket->id)->exists();
            if ($exists) {
                $rejected[] = ['code' => $entry['code'], 'reason' => 'deja_scane'];
                continue;
            }
            CheckIn::create([
                'ticket_id' => $ticket->id,
                'event_id' => $this->eventId,
                'scanned_by' => $ticket->owner_id, // remplacé par l'id du scanner par le contrôleur appelant
                'gate' => $entry['gate'] ?? 'principale',
                'synced' => true,
                'scanned_at' => $entry['scanned_at'],
            ]);
            $ticket->update(['status' => 'used']);
            $accepted++;
        }
        return compact('accepted', 'rejected');
    }
}
