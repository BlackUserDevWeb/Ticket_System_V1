<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Event;
use App\Models\EventExtra;
use App\Models\Section;
use App\Models\SubscriptionPlan;
use App\Models\TicketType;
use App\Models\TicketUnit;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DatabaseSeeder — jeu de données de démonstration 100 % togolais.
 *
 * Objectif : permettre le parcours complet « bout en bout » décrit dans le cahier
 * des charges — un organisateur crée un événement, un acheteur achète via Mobile
 * Money (sandbox), reçoit son QR, et l'organisateur le scanne (même offline).
 *
 * Idempotent : upsert sur les clés naturelles (email, slug, unique pair) pour
 * pouvoir relancer `php artisan db:seed` sans casser une base déjà peuplée.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCities();
        $plans = $this->seedPlans();
        [$admin, $organizer, $buyer] = $this->seedUsers($plans['pro']);
        $event = $this->seedEvent($organizer);

        // Compte staff rattaché à l'organisateur (droit de scan uniquement).
        User::updateOrCreate(
            ['email' => 'staff@ticketflow.tg'],
            ['name' => 'Agent Sécurité', 'phone' => '+22891000003', 'password' => Hash::make('password'), 'role' => 'staff']
        );

        $this->command?->info("Comptes de démo : admin@ / orga@ / acheteur@ticketflow.tg — mot de passe « password ».");
        $this->command?->info("Événement de démo : /evenements/{$event->slug} (" . TicketUnit::where('event_id', $event->id)->count() . " sièges).");
    }

    /** Villes du Togo (lat/lng ≈ réelles → section « Près de chez vous » fonctionnelle). */
    private function seedCities(): void
    {
        foreach ([
            ['name' => 'Lomé',       'region' => 'Maritime',      'lat' => 6.1319, 'lng' => 1.2228],
            ['name' => 'Kara',       'region' => 'Kara',          'lat' => 9.5515, 'lng' => 1.1857],
            ['name' => 'Sokodé',     'region' => 'Centrale',      'lat' => 8.9833, 'lng' => 1.1347],
            ['name' => 'Kpalimé',    'region' => 'Plateaux',      'lat' => 6.6244, 'lng' => 0.6247],
            ['name' => 'Atakpamé',   'region' => 'Oti',           'lat' => 7.5333, 'lng' => 1.1167],
            ['name' => 'Aného',      'region' => 'Maritime',      'lat' => 6.2264, 'lng' => 1.5858],
            ['name' => 'Dapaong',    'region' => 'Savanes',       'lat' => 10.8640, 'lng' => 0.2055],
        ] as $city) {
            City::updateOrCreate(['name' => $city['name']], $city);
        }
    }

    /** Les trois niveaux d'abonnement (modèle économique n°1). */
    private function seedPlans(): array
    {
        $defs = [
            ['slug' => 'starter',    'name' => 'Starter',    'price_monthly' => 0,
             'max_events' => 2,  'features' => ['analytics_basic']],
            ['slug' => 'pro',        'name' => 'Pro',        'price_monthly' => 25000,
             'max_events' => 10, 'features' => ['analytics_basic', 'analytics_advanced', 'dynamic_pricing', 'branding', 'email_campaigns']],
            ['slug' => 'enterprise', 'name' => 'Enterprise', 'price_monthly' => 75000,
             'max_events' => 0,  'features' => ['analytics_basic', 'analytics_advanced', 'dynamic_pricing', 'branding', 'email_campaigns', 'api_access', 'priority_support']],
        ];
        $out = [];
        foreach ($defs as $d) {
            $out[$d['slug']] = SubscriptionPlan::updateOrCreate(['slug' => $d['slug']], $d + ['is_active' => true]);
        }
        return $out;
    }

    /** @return array{0:User,1:User,2:User} admin, organisateur, acheteur. */
    private function seedUsers(SubscriptionPlan $pro): array
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@ticketflow.tg'],
            ['name' => 'Administration TicketFlow', 'phone' => '+22891000001', 'password' => Hash::make('password'), 'role' => 'admin']
        );
        $organizer = User::updateOrCreate(
            ['email' => 'orga@ticketflow.tg'],
            ['name' => 'Agbodjagan Productions', 'phone' => '+22891000002', 'password' => Hash::make('password'), 'role' => 'organizer',
             'preferences' => ['theme' => 'system', 'city_id' => City::where('name', 'Lomé')->value('id')]]
        );
        $buyer = User::updateOrCreate(
            ['email' => 'acheteur@ticketflow.tg'],
            ['name' => 'Kossi Mensah', 'phone' => '+22890112233', 'password' => Hash::make('password'), 'role' => 'buyer']
        );

        // Abonnement Pro actif pour que toutes les fonctionnalités soient testables.
        $organizer->subscription()->updateOrCreate([], [
            'plan_id' => $pro->id, 'status' => 'active', 'renews_at' => now()->addMonth(),
        ]);

        return [$admin, $organizer, $buyer];
    }

    /** Événement phare avec plan de salle généré procéduralement. */
    private function seedEvent(User $organizer): Event
    {
        $lome = City::where('name', 'Lomé')->first();
        $venue = Venue::updateOrCreate(
            ['name' => 'Palais des Congrès de Lomé', 'city_id' => $lome->id],
            ['capacity' => 320, 'address' => 'Boulevard du Mono, Lomé', 'lat' => 6.2085, 'lng' => 1.2240,
             'layout' => ['type' => 'theatre_in_the_round', 'stage' => ['x' => 300, 'y' => 40]]]
        );

        $event = Event::updateOrCreate(
            ['slug' => 'festival-kpezoun-live-2026'],
            [
                'organizer_id' => $organizer->id,
                'venue_id' => $venue->id,
                'title' => 'Festival Kpézoun Live — Fela, Sigda & Amity',
                'category' => 'concert',
                'description' => "La 3ᵉ édition du Festival Kpézoun réunit la crème de la scène afro-togolaise au Palais des Congrès. "
                    . "Trois artistes têtes d'affiche, deux scènes, une ambiance inoubliable. Ouverture des portes à 19h00.",
                'lineup' => ['Fela Anikulani', 'Sigda Tomedjo', 'Amity', 'DJ Gbenyo'],
                'starts_at' => now()->addDays(21)->setTime(19, 0),
                'ends_at' => now()->addDays(21)->setTime(23, 59),
                'sales_open_at' => now()->subWeek(),
                'sales_close_at' => now()->addDays(20)->setTime(23, 59),
                'status' => 'published',
                'dynamic_pricing_enabled' => true,
                'seated_viewing' => true,
                'branding' => ['color' => '#6D5EF8', 'logo_label' => 'Agbodjagan Prod.'],
            ]
        );

        // Sponsorisé : apparaît en tête de l'accueil avec badge « Partenaire ».
        \App\Models\Sponsorship::updateOrCreate(
            ['event_id' => $event->id, 'status' => 'active'],
            ['organizer_id' => $organizer->id, 'budget' => 50000,
             'starts_at' => now()->startOfDay(), 'ends_at' => now()->addDays(14),
             'impressions' => 0, 'clicks' => 0]
        );
        $event->update(['sponsored_until' => now()->addDays(14)]);

        $this->seedSeatMap($event);
        $this->seedExtras($event);

        return $event;
    }

    /**
     * Plan de salle : 4 sections × grille de sièges.
     * quality_score = proximité scène/piste ; view_score par siège (dégradé latéral).
     */
    private function seedSeatMap(Event $event): void
    {
        if ($event->sections()->exists()) {
            return; // idempotent
        }

        $sections = [
            ['name' => 'Piste Or',   'quality_score' => 95, 'rows' => 4,  'cols' => 10, 'base_price' => 35000, 'y0' => 90],
            ['name' => 'Tribune A',  'quality_score' => 80, 'rows' => 6,  'cols' => 12, 'base_price' => 20000, 'y0' => 210],
            ['name' => 'Tribune B',  'quality_score' => 70, 'rows' => 6,  'cols' => 12, 'base_price' => 15000, 'y0' => 330],
            ['name' => 'Balcon',     'quality_score' => 55, 'rows' => 4,  'cols' => 14, 'base_price' => 10000, 'y0' => 450],
        ];

        foreach ($sections as $s) {
            $section = Section::create([
                'event_id' => $event->id,
                'name' => $s['name'],
                'quality_score' => $s['quality_score'],
                'shape' => ['x' => 60, 'y' => $s['y0'] - 20, 'w' => 480, 'h' => $s['rows'] * 26 + 20],
                'panorama_360_path' => null, // photos 360° téléversées par l'organisateur via le dashboard
            ]);

            $type = TicketType::create([
                'event_id' => $event->id,
                'section_id' => $section->id,
                'name' => str_contains($s['name'], 'Piste') ? 'Standard — Piste' : 'Standard',
                'base_price' => $s['base_price'],
                'max_price' => (int) round($s['base_price'] * 1.35), // plafond anti-dérive du moteur dynamique
                'stock' => $s['rows'] * $s['cols'],
                'per_user_limit' => 6,
                'is_active' => true,
            ]);

            // Early Bird sur la section la moins chère : teste le multi-catégories.
            if ($s['name'] === 'Balcon') {
                TicketType::create([
                    'event_id' => $event->id, 'section_id' => $section->id,
                    'name' => 'Early Bird', 'base_price' => 8000, 'max_price' => 8000,
                    'stock' => 20, 'per_user_limit' => 2, 'is_active' => true,
                    'pricing_rules' => ['until' => now()->addDays(5)->toDateString()],
                ]);
            }

            for ($r = 1; $r <= $s['rows']; $r++) {
                for ($c = 1; $c <= $s['cols']; $c++) {
                    // view_score : centré + proche de la scène = meilleure vue (0..100).
                    $centerOffset = abs($c - $s['cols'] / 2) / ($s['cols'] / 2);       // 0 centre → 1 bord
                    $rowPenalty = ($r - 1) / max($s['rows'] - 1, 1);                   // 0 premier rang → 1 fond
                    $view = (int) round(100 - 35 * $centerOffset - 25 * $rowPenalty);

                    TicketUnit::create([
                        'event_id' => $event->id,
                        'ticket_type_id' => $type->id,
                        'section_id' => $section->id,
                        'label' => chr(64 + $r) . '-' . $c, // A-1 … F-12
                        'row_number' => $r,
                        'seat_number' => $c,
                        'pos_x' => 70 + $c * (460 / $s['cols']),
                        'pos_y' => $s['y0'] + $r * 26,
                        'view_score' => max($view, 10),
                        'status' => 'available',
                    ]);
                }
            }
        }
    }

    /** Extras vendus en complément (parking, boissons, goodies). */
    private function seedExtras(Event $event): void
    {
        foreach ([
            ['name' => 'Place de parking sécurisée', 'price' => 2500, 'stock' => 60],
            ['name' => 'Pack boisson + eau',         'price' => 1500, 'stock' => 200],
            ['name' => 'T-shirt officiel du festival','price' => 6000, 'stock' => 80],
        ] as $extra) {
            EventExtra::updateOrCreate(
                ['event_id' => $event->id, 'name' => $extra['name']],
                $extra + ['sold' => 0]
            );
        }
    }
}
