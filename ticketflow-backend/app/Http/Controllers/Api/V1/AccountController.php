<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateResaleRequest;
use App\Http\Requests\TransferTicketRequest;
use App\Http\Resources\EventSummaryResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ResaleResource;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PriceAlert;
use App\Models\Resale;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Services\ResaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * AccountController — espace acheteur : commandes, tickets, transfert, revente/Autolist,
 * favoris, alertes prix, notifications et recommandations personnalisées.
 */
class AccountController extends Controller
{
    public function __construct(private ResaleService $resaleService) {}

    /** GET /me/orders */
    public function orders(Request $request): JsonResponse
    {
        $orders = Order::with('event.venue.city', 'items.unit.section', 'items.tickets', 'payments')
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->paginate(15);
        return OrderResource::collection($orders)->response();
    }

    /** GET /me/tickets — portefeuille de l'acheteur (tri par date d'événement). */
    public function tickets(Request $request): JsonResponse
    {
        $tickets = Ticket::with('unit.event', 'unit.section', 'orderItem')
            ->where('owner_id', $request->user()->id)
            ->whereIn('status', ['active', 'used'])
            ->orderBy(
                \Illuminate\Support\Facades\DB::table('ticket_units')
                    ->select('starts_at')->join('events', 'events.id', '=', 'ticket_units.event_id')
                    ->whereColumn('ticket_units.id', 'tickets.ticket_unit_id')->limit(1)
            )
            ->paginate(30);
        return TicketResource::collection($tickets)->response();
    }

    /** POST /tickets/{code}/transfer — transfert tracé vers e-mail ou téléphone +228. */
    public function transfer(Request $request, TransferTicketRequest $data, string $code): JsonResponse
    {
        $ticket = Ticket::where('code', $code)->where('owner_id', $request->user()->id)->firstOrFail();
        if (!$ticket->canBeTransferred()) {
            return response()->json(['message' => 'Ce ticket ne peut plus être transféré.'], 422);
        }

        $recipient = User::where('email', $data->recipient)
            ->orWhere('phone', $data->recipient)
            ->first();
        if (!$recipient || $recipient->id === $ticket->owner_id) {
            return response()->json(['message' => 'Destinataire introuvable. Créez-lui un compte si besoin.'], 422);
        }

        // Transfert immédiat + tracé (le receveur est notifié ; pas de "pending" pour garder
        // la simplicité du flux Mobile Money où le QR doit changer immédiatement).
        TicketTransfer::create([
            'ticket_id' => $ticket->id,
            'from_user_id' => $ticket->owner_id,
            'to_user_id' => $recipient->id,
            'accepted_at' => now(),
        ]);
        $ticket->update([
            'owner_id' => $recipient->id,
            'transfer_depth' => $ticket->transfer_depth + 1,
        ]);

        Notification::create([
            'user_id' => $recipient->id,
            'type' => 'transfer_received',
            'title' => $request->user()->name . ' vous a transféré un billet',
            'body' => "« {$ticket->unit->event->title} » — siège {$ticket->unit->label}",
            'link' => '/mes-billets',
        ]);

        return response()->json(['message' => 'Billet transféré.', 'ticket' => new TicketResource($ticket)]);
    }

    /** POST /resales — mise en vente classique OU Autolist (revente automatique). */
    public function createResale(CreateResaleRequest $request): JsonResponse
    {
        $ticket = Ticket::where('code', $request->ticket_code)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        try {
            $resale = $this->resaleService->list($ticket, (int) $request->ask_price, (bool) $request->boolean('autolist', true));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(new ResaleResource($resale), 201);
    }

    /** DELETE /resales/{id} — retrait d'une annonce non vendue. */
    public function cancelResale(Request $request, int $id): JsonResponse
    {
        $resale = Resale::where('id', $id)->where('seller_id', $request->user()->id)
            ->where('status', 'listed')->firstOrFail();
        $resale->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Annonce retirée.']);
    }

    /** GET /events/{id}/resales — annonces Autolist visibles sur la page événement. */
    public function eventResales(int $eventId): JsonResponse
    {
        $resales = Resale::with('ticket.unit.section')
            ->where('event_id', $eventId)->where('status', 'listed')
            ->orderBy('ask_price')->paginate(25);
        return ResaleResource::collection($resales)->response();
    }

    /** POST /resales/{id}/purchase — un acheteur se présente → transaction Auto (Autolist). */
    public function buyResale(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:moov_money,mixx_yas'],
            'phone' => ['required', 'regex:/^\+228\d{8}$/'],
        ]);
        $resale = Resale::findOrFail($id);
        try {
            $order = $this->resaleService->purchase($resale, $request->user()->id, $data['provider'], $data['phone']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
        return response()->json([
            'order_id' => $order->id,
            'total_formatted' => \App\Support\Money::format((int) $order->total),
            'message' => 'Confirmez le paiement sur votre téléphone.',
        ], 202);
    }

    /** POST /favorites — bouton cœur (événement ou artiste/équipe nommé). */
    public function toggleFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['nullable', 'exists:events,id'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);
        $target = $data['event_id'] ?? null;
        $existing = Favorite::where('user_id', $request->user()->id)
            ->when($target, fn ($q) => $q->where('favoritable_id', $target))
            ->when(!$target, fn ($q) => $q->whereNull('favoritable_id')->where('name', $data['name'] ?? ''))
            ->first();
        if ($existing) {
            $existing->delete();
            return response()->json(['favorited' => false]);
        }
        Favorite::create([
            'user_id' => $request->user()->id,
            'favoritable_type' => $target ? Event::class : null,
            'favoritable_id' => $target,
            'name' => $data['name'] ?? null,
        ]);
        return response()->json(['favorited' => true], 201);
    }

    /** POST /price-alerts — alerte de baisse de prix sur un événement. */
    public function storePriceAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'target_price' => ['required', 'integer', 'min:500'],
        ]);
        $alert = PriceAlert::updateOrCreate(
            ['user_id' => $request->user()->id, 'event_id' => $data['event_id']],
            ['target_price' => $data['target_price'], 'is_active' => true, 'triggered_at' => null]
        );
        return response()->json($alert, 201);
    }

    /** GET /me/notifications + PATCH mark-as-read. */
    public function notifications(Request $request): JsonResponse
    {
        return NotificationResource::collection(
            Notification::where('user_id', $request->user()->id)->latest()->paginate(20)
        )->response();
    }

    public function readNotification(Request $request, int $id): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->whereKey($id)
            ->update(['read_at' => now()]);
        return response()->json(['message' => 'OK']);
    }

    /**
     * GET /me/recommendations — moteur de recommandation simple mais efficace :
     *   1. événements à venir dans les catégories déjà achetées
     *   2. événements partageant un artiste/équipe des favoris nommés
     *   3. même ville que les achats précédents ("Près de chez vous")
     * Score = nb de critères remplis, tri desc, exclut ce qui est déjà acheté/favori.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        $boughtEventIds = Order::where('buyer_id', $user->id)->where('status', 'paid')->pluck('event_id');
        $favNames = Favorite::where('user_id', $user->id)->whereNotNull('name')->pluck('name')->all();
        $categories = Event::whereIn('id', $boughtEventIds)->distinct()->pluck('category');
        $cityId = Event::whereIn('id', $boughtEventIds)
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->orderByDesc('events.starts_at')->value('venues.city_id');

        $candidates = Event::published()->upcoming()
            ->whereNotIn('id', $boughtEventIds)
            ->with('venue.city')
            ->get();

        $scored = $candidates->map(function (Event $e) use ($categories, $favNames, $cityId) {
            $score = 0;
            if ($categories->contains($e->category)) {
                $score += 2;
            }
            foreach ((array) $e->lineup as $artist) {
                if (in_array($artist, $favNames, true)) {
                    $score += 3; // correspondance artiste favori = signal fort
                }
            }
            if ($cityId && $e->venue?->city_id === $cityId) {
                $score += 1;
            }
            return ['event' => $e, 'score' => $score];
        })
            ->filter(fn ($r) => $r['score'] > 0)
            ->sortByDesc('score')
            ->take(12)
            ->values();

        return EventSummaryResource::collection($scored->pluck('event'))->response();
    }
}
