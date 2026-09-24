<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventSummaryResource;
use App\Models\Event;
use App\Models\TicketUnit;
use App\Services\TicketScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * EventController — découverte marketplace (public, fortement mis en cache Redis).
 *
 * Performance : 100 utilisateurs simultanés au Togo passent par `Cache::remember`
 * (60 s sur les listes, requête + jointure prix min) ; la pagination est systématique.
 */
class EventController extends Controller
{
    public function __construct(private TicketScoringService $scoring) {}

    /** GET /events?q=&category=&city=&date_from=&date_to=&sort= — recherche + filtres. */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'events:list:' . md5($request->fullUrlWithQuery([]));

        $data = Cache::remember($cacheKey, 60, function () use ($request) {
            $q = Event::query()
                ->published()
                ->upcoming()
                ->with(['venue.city', 'organizer:id,name']);

            // Filtres optionnels combinables.
            if ($search = trim((string) $request->query('q'))) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        // Recherche dans le lineup JSON (artistes/équipes) — portable MySQL 8 :
                        ->orWhereRaw("JSON_SEARCH(lineup, 'one', ?) IS NOT NULL", [$like]);
                });
            }
            if ($cat = $request->query('category')) {
                $q->where('category', $cat);
            }
            if ($city = $request->query('city')) {
                $q->whereHas('venue.city', fn ($c) => $c->where('name', 'like', $city . '%'));
            }
            if ($from = $request->query('date_from')) {
                $q->where('starts_at', '>=', $from);
            }
            if ($to = $request->query('date_to')) {
                $q->where('starts_at', '<=', $to . ' 23:59:59');
            }

            // Tri : sponsorisés EN TÊTE (modèle économique), puis pertinence demandée.
            $sort = $request->query('sort', 'date');
            $q->orderByRaw('CASE WHEN sponsored_until > NOW() THEN 0 ELSE 1 END');
            match ($sort) {
                'price_asc' => $q->orderBy(
                    TicketUnit::query()->selectRaw('COALESCE(MIN(price_override + 0), 0)')
                        ->whereColumn('ticket_units.event_id', 'events.id')
                        ->where('status', 'open')->limit(1)
                ),
                'popular' => $q->orderByDesc(
                    \Illuminate\Support\Facades\DB::table('event_views')
                        ->selectRaw('count(*)')->whereColumn('event_id', 'events.id')
                        ->where('viewed_at', '>', now()->subDays(7))
                ),
                'newest' => $q->orderByDesc('created_at'),
                default => $q->orderBy('starts_at'),
            };

            // "Prix à partir de" calculé en sous-requête d agrégat → une seule requête.
            $q->addSelect(
                TicketUnit::query()->selectRaw('COALESCE(MIN(COALESCE(ticket_units.price_override, tt.base_price)), 0)')
                    ->join('ticket_types as tt', 'tt.id', '=', 'ticket_units.ticket_type_id')
                    ->whereColumn('ticket_units.event_id', 'events.id')
                    ->where('ticket_units.status', 'open')
                    ->limit(1)
                    ->as('min_price_net')
            );

            return $q->paginate(min(48, (int) $request->query('per_page', 18)))
                ->through(EventSummaryResource::class)
                ->toArray();
        });

        // Post-traitement hors cache : conversion net → affiché (frais intégrés).
        foreach ($data['data'] as &$row) {
            $row['price_from'] = isset($row['price_from']) && $row['price_from'] > 0
                ? (int) round($row['price_from'] * (1 + config('ticketflow.service_rate')))
                : null;
        }

        return response()->json($data);
    }

    /** GET /events/{slug} — page événement complète avec offres scorées. */
    public function show(string $slug): JsonResponse
    {
        $event = Event::with([
            'venue.city', 'organizer:id,name', 'sections', 'extras',
            'units.section', 'units.ticketType',
        ])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Journalisation vue (analytics organisateur) hors chemin critique.
        EventViewRecorder::record($event, request());

        // Seules les unités ouvertes sont scorées (le stock vendable évolue vite).
        $openUnits = $event->units->where('status', TicketUnit::STATUS_OPEN)->values();
        $event->scored_offers = $this->scoring->scoreUnits($event, $openUnits);
        $event->resales_listed_count = $event->resales()->where('status', 'listed')->count();

        return (new EventDetailResource($event))->response();
    }

    /** GET /events/{id}/sections — plan de salle statique (mis en cache long : rarement modifié). */
    public function sections(int $eventId): JsonResponse
    {
        $sections = Cache::remember("event:{$eventId}:sections", 300, function () use ($eventId) {
            return \App\Http\Resources\SectionResource::collection(
                Event::findOrFail($eventId)->sections()->get()
            )->resource->collection;
        });

        return response()->json(['data' => $sections]);
    }

    /**
     * GET /events/{id}/units — état des sièges pour le plan temps réel React.
     * Léger volontairement : id, position, statut, section. Pas de cache (temps réel via Reverb).
     */
    public function units(int $eventId): JsonResponse
    {
        $units = TicketUnit::query()
            ->where('event_id', $eventId)
            ->whereIn('status', ['open', 'held', 'reserved', 'sold'])
            ->get(['id', 'section_id', 'ticket_type_id', 'label', 'pos_x', 'pos_y', 'status', 'view_score']);

        return response()->json([
            'data' => $units,
            'service_rate' => config('ticketflow.service_rate'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** GET /search/suggest?q= — autocomplétion (événements + artistes du lineup + villes). */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['suggestions' => []]);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        $events = Event::published()->upcoming()->where('title', 'like', $like)
            ->limit(6)->get(['id', 'slug', 'title']);

        // Artistes/équipes extraits des lineups publiés (JSON) + favoris nommés populaires.
        $artistNames = Event::published()->whereNotNull('lineup')
            ->pluck('lineup')->flatten()->unique()->filter(
                fn ($n) => mb_stripos((string) $n, $term) !== false
            )->take(6)->values();

        return response()->json([
            'suggestions' => [
                'events' => $events,
                'artists' => $artistNames,
            ],
        ]);
    }
}
