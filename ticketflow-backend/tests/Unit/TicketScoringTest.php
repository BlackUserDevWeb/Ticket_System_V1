<?php

/**
 * Scoring qualité/prix — l'algorithme signature de TicketFlow (inspiré du
 * Score Report de TickPick). Le service expose scoreUnits(Event, Collection)
 * qui renvoie des offres triées : ['id','label','section','price_displayed',
 * 'score','is_best_deal'] avec score ∈ [0..10].
 */

use App\Models\Event;
use App\Models\Section;
use App\Models\TicketType;
use App\Models\TicketUnit;
use App\Models\Venue;
use App\Services\TicketScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Fabrique un événement + une section + des unités en base (SQLite). */
function scoringFixture(array $seatSpecs): array
{
    $organizer = \App\Models\User::factory()->organizer()->create();
    $venue = Venue::factory()->create(['city_id' => \App\Models\City::factory()->create()->id]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'venue_id' => $venue->id]);

    $section = Section::create([
        'event_id' => $event->id, 'name' => 'Tribune', 'quality_score' => 70,
        'shape' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100],
    ]);
    $type = TicketType::create([
        'event_id' => $event->id, 'section_id' => $section->id, 'name' => 'Standard',
        'base_price' => 20000, 'max_price' => 30000, 'stock' => count($seatSpecs), 'per_user_limit' => 6,
    ]);

    $units = collect($seatSpecs)->map(fn ($spec, $i) => TicketUnit::create([
        'event_id' => $event->id, 'ticket_type_id' => $type->id, 'section_id' => $section->id,
        'label' => 'A-' . ($i + 1), 'row_number' => 1, 'seat_number' => $i + 1,
        'pos_x' => $i * 10, 'pos_y' => 10, 'view_score' => $spec['view'], 'status' => 'available',
    ]));

    return [$event, $units];
}

it('classe une meilleure vue devant une moins bonne au même prix', function () {
    [$event, $units] = scoringFixture([['view' => 95], ['view' => 30]]);
    $offers = app(TicketScoringService::class)->scoreUnits($event, $units);

    expect($offers[0]['id'])->toBe($units[0]->id)
        ->and($offers[0]['score'])->toBeGreaterThan($offers[1]['score']);
});

it('borne tous les scores entre 0 et 10', function () {
    [$event, $units] = scoringFixture([
        ['view' => 100], ['view' => 0], ['view' => 50], ['view' => 80],
    ]);
    $offers = app(TicketScoringService::class)->scoreUnits($event, $units);

    foreach ($offers as $o) {
        expect($o['score'])->between(0, 10);
    }
});

it('désigne une unique meilleure offre', function () {
    [$event, $units] = scoringFixture([['view' => 90], ['view' => 50], ['view' => 70]]);
    $offers = app(TicketScoringService::class)->scoreUnits($event, $units);

    expect(collect($offers)->where('is_best_deal', true)->count())->toBe(1)
        ->and($offers[0]['is_best_deal'])->toBeTrue(); // triée par score décroissant
});

it('intègre les frais de service dans le prix affiché', function () {
    [$event, $units] = scoringFixture([['view' => 60]]);
    $scoring = app(TicketScoringService::class);
    $displayed = $scoring->displayedPrice($units->first());
    $net = $units->first()->ticketType->base_price;

    expect($displayed)->toBeGreaterThan($net)
        ->and((float) $displayed / $net)->toBeAround(1 + config('ticketflow.service_rate'), 0.01);
});
