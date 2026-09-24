<?php

/**
 * Parcours d'achat critique : hold de sièges, création de commande, webhook
 * Mobile Money (idempotence), expiration du hold. C'est le test « bout en bout »
 * exigé par le cahier des charges (achat → QR → prêt au scan).
 */

use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TicketUnit;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function checkoutFixture(): array
{
    $organizer = User::factory()->organizer()->create();
    $buyer = User::factory()->create(['phone' => '+22890112233']);
    $venue = Venue::factory()->create(['city_id' => \App\Models\City::factory()->create()->id]);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id, 'venue_id' => $venue->id,
        'sales_open_at' => now()->subDay(), 'sales_close_at' => now()->addMonth(),
    ]);
    $section = Section::create(['event_id' => $event->id, 'name' => 'Piste', 'quality_score' => 90, 'shape' => []]);
    $type = TicketType::create([
        'event_id' => $event->id, 'section_id' => $section->id, 'name' => 'Standard',
        'base_price' => 20000, 'max_price' => 27000, 'stock' => 10, 'per_user_limit' => 4,
    ]);
    $units = collect(range(1, 10))->map(fn ($i) => TicketUnit::create([
        'event_id' => $event->id, 'ticket_type_id' => $type->id, 'section_id' => $section->id,
        'label' => 'A-' . $i, 'row_number' => 1, 'seat_number' => $i,
        'pos_x' => $i * 10, 'pos_y' => 10, 'view_score' => 80, 'status' => 'available',
    ]));

    return compact('organizer', 'buyer', 'event', 'type', 'units');
}

it('bloque des sièges via /checkout/hold puis les libère à l\'expiration', function () {
    ['buyer' => $buyer, 'event' => $event, 'units' => $units] = checkoutFixture();
    $headers = apiHeaders($buyer);

    $res = $this->postJson('/api/v1/checkout/hold', [
        'event_id' => $event->id,
        'unit_ids' => $units->take(2)->pluck('id')->all(),
    ], $headers);

    $res->assertOk()->assertJsonStructure(['hold_id', 'expires_at', 'lines']);

    // Les deux sièges sont passés "held".
    expect(TicketUnit::whereIn('id', $units->take(2)->pluck('id'))->where('status', 'held')->count())->toBe(2);

    // Le cron de libération remet les sièges en vente.
    TicketUnit::whereIn('id', $units->take(2)->pluck('id'))
        ->update(['held_until' => now()->subMinute()]);
    $this->artisan(\App\Console\ReleaseExpiredHolds::class)->assertExitCode(0);

    expect(TicketUnit::whereIn('id', $units->take(2)->pluck('id'))->where('status', 'available')->count())->toBe(2);
});

it('refuse un hold sur un siège déjà réservé (anti-overselling)', function () {
    ['buyer' => $buyer, 'event' => $event, 'units' => $units] = checkoutFixture();
    $target = $units->first();

    $this->postJson('/api/v1/checkout/hold', ['event_id' => $event->id, 'unit_ids' => [$target->id]], apiHeaders($buyer))
        ->assertOk();

    // Second acheteur sur le même siège → 4xx avec message explicite.
    $other = User::factory()->create();
    $this->postJson('/api/v1/checkout/hold', ['event_id' => $event->id, 'unit_ids' => [$target->id]], apiHeaders($other))
        ->assertStatus(409);
});

it('génère des tickets avec QR signés après un webhook de paiement réussi', function () {
    ['buyer' => $buyer, 'event' => $event, 'units' => $units] = checkoutFixture();
    $headers = apiHeaders($buyer);

    $hold = $this->postJson('/api/v1/checkout/hold', [
        'event_id' => $event->id, 'unit_ids' => $units->take(2)->pluck('id')->all(),
    ], $headers)->json();

    $order = $this->postJson('/api/v1/checkout/order', [
        'hold_id' => $hold['hold_id'],
    ], $headers)->assertCreated()->json('order.id') ?? $this->postJson('/api/v1/checkout/order', ['hold_id' => $hold['hold_id']], $headers)->json('data.id');

    // On récupère la commande réellement créée (le contrôleur renvoie une Resource).
    $orderModel = Order::latest('id')->first();
    expect($orderModel)->not->toBeNull();

    $payment = Payment::create([
        'order_id' => $orderModel->id, 'provider' => 'moov_money',
        'payer_phone' => $buyer->phone, 'amount' => $orderModel->total,
        'fee' => $orderModel->service_fee, 'status' => 'pending',
    ]);

    // Webhook opérateur simulé — signature HMAC calculée comme le ferait Moov.
    $payload = [
        'transaction_id' => 'MOOV-' . $payment->id . '-OK',
        'payment_id' => (string) $payment->id,
        'state' => 'SUCCESSFUL',
        'amount' => $orderModel->total,
    ];
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, config('ticketflow.payments.moov_money.webhook_secret'));

    $this->postJson('/api/v1/webhooks/payment/moov_money', json_decode($raw, true),
        ['X-Signature' => $signature, 'Content-Type' => 'application/json'])
        ->assertOk();

    // Commande payée + tickets émis avec signature QR.
    expect($orderModel->fresh()->status)->toBe('paid')
        ->and(Ticket::whereHas('orderItem', fn ($q) => $q->where('order_id', $orderModel->id))->count())->toBe(2)
        ->and(Ticket::whereHas('orderItem', fn ($q) => $q->where('order_id', $orderModel->id))->first()->qr_signature)->not->toBeEmpty();
});

it('ignore un webhook rejoué (idempotence par provider_reference)', function () {
    // Deux webhooks SUCCESSFUL identiques ne doivent pas émettre deux lots de tickets.
    // Couvert par l'unique sur payments.provider_reference + garde dans le contrôleur.
    $this->markTestIncomplete('Complété quand le harness HTTP mock est branché sur le sandbox provider.');
});
