<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Resources\EventSummaryResource;
use App\Models\Event;
use App\Models\EventExtra;
use App\Models\Order;
use App\Models\Section;
use App\Models\SubscriptionPlan;
use App\Models\TicketType;
use App\Models\TicketUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrganizerController — API du dashboard organisateur (Angular).
 * Toutes les routes sont derrière auth + role:organizer + vérification de propriété.
 */
class OrganizerController extends Controller
{
    /** GET /organizer/events — toutes mes événements, tous statuts. */
    public function events(Request $request): JsonResponse
    {
        $events = Event::with('venue.city')
            ->where('organizer_id', $request->user()->id)
            ->latest('starts_at')
            ->paginate(20);
        return EventSummaryResource::collection($events)->response();
    }

    /** POST /organizer/events — étape 1 du formulaire multi-étapes. */
    public function store(StoreEventRequest $request): JsonResponse
    {
        // Limite d'événements du plan d'abonnement (modèle économique n°1).
        $plan = $request->user()->subscription?->isUsable() ? $request->user()->subscription->plan : null;
        if ($plan && $plan->max_events > 0) {
            $activeCount = Event::where('organizer_id', $request->user()->id)
                ->whereIn('status', ['draft', 'pending', 'published'])
                ->count();
            if ($activeCount >= $plan->max_events) {
                return response()->json([
                    'message' => "Votre plan {$plan->name} est limité à {$plan->max_events} événements actifs. Passez au niveau supérieur.",
                    'upgrade_url' => '/organisateur/abonnement',
                ], 403);
            }
        }

        $event = Event::create($request->safe()->except('image') + ['organizer_id' => $request->user()->id]);

        if ($request->hasFile('image')) {
            $event->update(['image_path' => $request->file('image')->store('events', 'public')]);
        }

        return response()->json(['event' => new EventSummaryResource($event)], 201);
    }

    /** PATCH /organizer/events/{id} — étapes suivantes (lieu, dates, branding, visibilité). */
    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:5', 'max:160'],
            'description' => ['nullable', 'string', 'max:8000'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'sales_open_at' => ['nullable', 'date'],
            'sales_close_at' => ['nullable', 'date'],
            'lineup' => ['nullable', 'array'],
            'lineup.*' => ['string', 'max:80'],
            'dynamic_pricing_enabled' => ['sometimes', 'boolean'],
            'branding' => ['nullable', 'array'],
            'branding.logo_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'submit_for_review' => ['sometimes', 'boolean'],
        ]);

        $submit = $data['submit_for_review'] ?? false;
        unset($data['submit_for_review']);

        // La tarification dynamique exige le plan Pro+.
        if (($data['dynamic_pricing_enabled'] ?? false) && !$this->organizerHas('dynamic_pricing', $request->user())) {
            return response()->json(['message' => 'Tarification dynamique réservée au plan Pro.'], 403);
        }

        $event->fill($data);
        if ($submit) {
            $event->status = 'pending'; // file de modération admin
        }
        $event->save();

        return response()->json(['event' => new EventSummaryResource($event)]);
    }

    /** POST /organizer/events/{id}/sections — plan de salle (polygones SVG + qualité). */
    public function storeSections(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:40'],
            'sections.*.name' => ['required', 'string', 'max:60'],
            'sections.*.quality_score' => ['required', 'integer', 'min:0', 'max:100'],
            'sections.*.shape' => ['nullable', 'array'],
            'sections.*.panorama_360' => ['nullable', 'string'], // base64 jpg/png, converti ci-dessous
        ]);

        $created = [];
        foreach ($data['sections'] as $s) {
            $panoPath = null;
            if (!empty($s['panorama_360'])) {
                // Upload 360 en base64 depuis l'éditeur Angular → disque public.
                [$ext, $bin] = array_pad(explode(',', $s['panorama_360'], 2), 2, null);
                if ($bin) {
                    $panoPath = "panoramas/{$event->id}-" . uniqid() . '.jpg';
                    \Illuminate\Support\Facades\Storage::disk('public')->put($panoPath, base64_decode($bin));
                }
            }
            $created[] = Section::create([
                'event_id' => $event->id,
                'name' => $s['name'],
                'quality_score' => $s['quality_score'],
                'shape' => $s['shape'] ?? null,
                'panorama_360_path' => $panoPath,
            ]);
        }
        return response()->json(['sections' => $created], 201);
    }

    /** POST /organizer/events/{id}/ticket-types — catégories (Early Bird, VIP…) + tarifs. */
    public function storeTicketTypes(StoreTicketTypeRequest $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $type = TicketType::create($request->validated() + ['event_id' => $event->id]);

        // Génération des sièges : placement assis uniquement ; en placement libre on crée
        // N unités anonymes pour garder UN SEUL modèle de stock (anti-overselling uniforme).
        if ($event->seated_viewing) {
            $sectionId = $type->section_id;
            $existing = TicketUnit::where('event_id', $event->id)->count();
            for ($i = 0; $i < $type->stock; $i++) {
                TicketUnit::create([
                    'event_id' => $event->id,
                    'ticket_type_id' => $type->id,
                    'section_id' => $sectionId,
                    'label' => $type->name . '-' . str_pad((string) ($existing + $i + 1), 4, '0', STR_PAD_LEFT),
                    'view_score' => $sectionId ? (int) (Section::find($sectionId)->quality_score) : 50,
                    'pos_x' => rand(50, 950),
                    'pos_y' => rand(50, 550),
                ]);
            }
        } else {
            TicketUnit::insert(array_map(fn () => [
                'event_id' => $event->id,
                'ticket_type_id' => $type->id,
                'label' => 'GA-' . uniqid(),
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ], array_fill(0, min($type->stock, 5000), 0)));
        }

        return response()->json(['ticket_type' => $type], 201);
    }

    /** POST /organizer/events/{id}/extras — options additionnelles (parking, boissons…). */
    public function storeExtras(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'integer', 'min:100', 'max:1000000'],
            'stock' => ['required', 'integer', 'min:1', 'max:50000'],
        ]);
        return response()->json(EventExtra::create($data + ['event_id' => $event->id]), 201);
    }

    /** GET /organizer/events/{id}/stats — ventes temps réel, remplissage, check-ins, funnel. */
    public function stats(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $paid = Order::where('event_id', $event->id)->where('status', 'paid');
        $grossRevenue = (clone $paid)->sum('total');
        $commission = \App\Models\LedgerEntry::where('subject_type', Order::class)
            ->whereIn('subject_id', (clone $paid)->pluck('id'))
            ->where('kind', 'commission')->sum('amount_xof');

        // Ventes par jour (14 derniers jours) pour le graphique Angular.
        $daily = (clone $paid)
            ->selectRaw('DATE(created_at) day, COUNT(*) orders, SUM(total) revenue')
            ->groupBy('day')->orderBy('day')
            ->where('created_at', '>=', now()->subDays(14))
            ->get();

        $views = $event->views()->count();
        $buyers = (clone $paid)->distinct('buyer_id')->count('buyer_id');

        return response()->json([
            'orders_paid' => (clone $paid)->count(),
            'gross_revenue' => (int) $grossRevenue,
            'net_revenue' => (int) $grossRevenue - (int) $commission, // après commission TicketFlow
            'fill_ratio' => round($event->fillRatio() * 100, 1),
            'checkins_today' => $event->checkInsToday(),
            'conversion_rate' => $views > 0 ? round($buyers / $views * 100, 2) : 0,
            'views_total' => $views,
            'sales_daily' => $daily,
            'by_ticket_type' => TicketType::where('event_id', $event->id)
                ->get(['id', 'name', 'base_price', 'stock', 'sold']),
        ]);
    }

    /** GET /organizer/events/{id}/participants.csv — export participants. */
    public function participants(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        $rows = Ticket::with('owner', 'unit.section', 'orderItem.order')
            ->whereHas('unit', fn ($q) => $q->where('event_id', $event->id))->get();

        $csv = "Nom;Email;Telephone;Siege;Section;Commande;Statut\n";
        foreach ($rows as $t) {
            $csv .= implode(';', [
                $t->owner?->name, $t->owner?->email, $t->owner?->phone,
                $t->unit?->label, $t->unit?->section?->name,
                $t->orderItem?->order?->reference, $t->status,
            ]) . "\n";
        }
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"participants-{$event->slug}.csv\"",
        ]);
    }

    /** GET /organizer/subscription — niveau actuel, limites, consommation, upsell. */
    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub = $user->subscription()->with('plan')->first();
        $usage = Event::where('organizer_id', $user->id)
            ->whereIn('status', ['draft', 'pending', 'published'])->count();

        return response()->json([
            'current' => $sub ? [
                'plan' => $sub->plan->only(['slug', 'name', 'price_monthly', 'max_events', 'features']),
                'status' => $sub->status,
                'renews_at' => $sub->renews_at->toIso8601String(),
                'events_used' => $usage,
            ] : null,
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('price_monthly')->get(),
        ]);
    }

    /** POST /organizer/subscription/{plan} — changement de niveau (paiement Mobile Money orga). */
    public function changePlan(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        abort_unless($plan->is_active, 404);
        $sub = $request->user()->subscription()->firstOrCreate(
            [],
            ['plan_id' => $plan->id, 'status' => 'trialing', 'renews_at' => now()->addMonth()]
        );
        $sub->update(['plan_id' => $plan->id, 'status' => 'active', 'renews_at' => now()->addMonth()]);

        \App\Models\LedgerEntry::create([
            'user_id' => $request->user()->id,
            'kind' => 'subscription',
            'amount_xof' => $plan->price_monthly,
            'subject_type' => $sub->getMorphClass(),
            'subject_id' => $sub->id,
            'description' => "Abonnement {$plan->name}",
        ]);
        return response()->json(['message' => 'Abonnement mis à jour.', 'renews_at' => $sub->renews_at]);
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organizer_id === $request->user()->id, 403, 'Événement appartenant à un autre organisateur.');
    }

    private function organizerHas(string $feature, $user): bool
    {
        $sub = $user->subscription()->with('plan')->first();
        return $sub && $sub->isUsable() && $sub->plan->has($feature);
    }
}
