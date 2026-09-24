<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventSummaryResource;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\QrCode\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CheckInController — scan des QR codes à l'entrée (organisateurs et leur staff).
 *
 * Mode offline : le scanner Vanilla JS ne peut PAS vérifier la signature HMAC sans clé
 * serveur. Il télécharge donc une LISTE BLANCHE signée (batch) des tickets valides,
 * vérifie localement uuid+signature contre cette liste, puis synchronise les scans
 * dès que le réseau revient (/checkin/sync). Cet endpoint gère aussi le scan online.
 */
class CheckInController extends Controller
{
    public function __construct(private QrCodeService $qr) {}

    /** POST /checkin — scan online : vérifie payload, marque utilisé, journalise. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string'],
            'gate' => ['nullable', 'string', 'max:40'],
        ]);

        $uuid = $this->qr->verifyPayload($data['payload']);
        if (!$uuid) {
            return response()->json(['valid' => false, 'reason' => 'QR invalide ou contrefait.'], 422);
        }

        return $this->consume($request, Ticket::where('code', $uuid)->first(), $data['gate'] ?? 'principale');
    }

    /** GET /checkin/batch/{eventId} — snapshot offline signé pour le cache IndexedDB. */
    public function batch(Request $request, int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        $this->assertScannerCanAccess($request, $event);

        // On n'envoie QUE ce qui est nécessaire au scan offline : code + signature + siège.
        $tickets = Ticket::with('unit')
            ->whereHas('unit', fn ($q) => $q->where('event_id', $eventId))
            ->whereIn('status', ['active'])
            ->get()
            ->map(fn (Ticket $t) => [
                'code' => $t->code,
                'sig' => $t->qr_signature,
                'seat' => $t->unit->label,
                'section' => $t->unit->section?->name,
                'holder' => $t->owner?->name, // aide visuelle à l'agent de sécurité
            ]);

        return response()->json([
            'event_id' => $eventId,
            'generated_at' => now()->toIso8601String(),
            // Signature du lot (HMAC APP_KEY tronqué) : le scanner refuse un lot non signé.
            'batch_signature' => substr(hash_hmac('sha256', $eventId . '|' . $tickets->count(), config('app.key')), 0, 16),
            'tickets' => $tickets,
        ]);
    }

    /** POST /checkin/sync — remontée des scans effectués hors-ligne par le scanner. */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.code' => ['required', 'uuid'],
            'entries.*.gate' => ['nullable', 'string', 'max:40'],
            'entries.*.scanned_at' => ['required', 'date'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        $this->assertScannerCanAccess($request, $event);

        $accepted = 0;
        $rejected = [];
        foreach ($data['entries'] as $entry) {
            $ticket = Ticket::where('code', $entry['code'])->lockForUpdate()->first();
            if (!$ticket || $ticket->unit->event_id !== $event->id) {
                $rejected[] = ['code' => $entry['code'], 'reason' => 'inconnu'];
                continue;
            }
            if (CheckIn::where('ticket_id', $ticket->id)->exists()) {
                $rejected[] = ['code' => $entry['code'], 'reason' => 'deja_scane'];
                continue;
            }
            CheckIn::create([
                'ticket_id' => $ticket->id,
                'event_id' => $event->id,
                'scanned_by' => $request->user()->id,
                'gate' => $entry['gate'] ?? 'principale',
                'synced' => true,
                'scanned_at' => $entry['scanned_at'],
            ]);
            $ticket->update(['status' => 'used']);
            $accepted++;
        }

        return response()->json(['accepted' => $accepted, 'rejected' => $rejected]);
    }

    /** GET /checkin/live/{eventId} — compteur d'entrées en direct (auto-refresh Angular). */
    public function live(Request $request, int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        $this->assertScannerCanAccess($request, $event);

        return response()->json([
            'checked_in' => CheckIn::where('event_id', $eventId)->count(),
            'sold' => Ticket::whereHas('unit', fn ($q) => $q->where('event_id', $eventId))->count(),
            'last' => CheckIn::with('ticket.unit')->where('event_id', $eventId)->latest('scanned_at')->limit(10)->get(),
        ]);
    }

    /** Consommation d'un ticket (scan online) — premier scan gagnant. */
    private function consume(Request $request, ?Ticket $ticket, string $gate): JsonResponse
    {
        if (!$ticket) {
            return response()->json(['valid' => false, 'reason' => 'Billet inconnu.'], 422);
        }
        $this->assertScannerCanAccess($request, $ticket->unit->event);

        if ($ticket->status === 'used') {
            return response()->json([
                'valid' => false,
                'reason' => 'Déjà scanné à ' . optional(CheckIn::where('ticket_id', $ticket->id)->first())->scanned_at?->format('H:i'),
            ], 409);
        }
        if ($ticket->status !== 'active') {
            return response()->json(['valid' => false, 'reason' => 'Billet ' . $ticket->status . '.'], 409);
        }

        CheckIn::create([
            'ticket_id' => $ticket->id,
            'event_id' => $ticket->unit->event_id,
            'scanned_by' => $request->user()->id,
            'gate' => $gate,
            'synced' => true,
            'scanned_at' => now(),
        ]);
        $ticket->update(['status' => 'used']);

        return response()->json([
            'valid' => true,
            'seat' => $ticket->unit->label,
            'section' => $ticket->unit->section?->name,
            'holder' => $ticket->owner?->name,
        ]);
    }

    /** Un scanner = organisateur de l'événement, admin/support (staff autorisé plus tard via table dédiée). */
    private function assertScannerCanAccess(Request $request, Event $event): void
    {
        $user = $request->user();
        abort_unless($user->isAdminOrSupport() || $user->id === $event->organizer_id, 403);
    }
}
