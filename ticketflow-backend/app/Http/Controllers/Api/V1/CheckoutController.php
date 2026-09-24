<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SeatMapUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\HoldSeatsRequest;
use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use App\Services\Payment\PaymentProvider;
use App\Services\Payment\PaymentProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * CheckoutController — tunnel d'achat : hold → commande → paiement Mobile Money.
 *
 * Sécurité : les montants sont TOUJOURS recalculés côté serveur (jamais fournis par
 * le client). Rate limiting strict (throttle:checkout) sur hold/initiate.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private PaymentProviderManager $providers,
    ) {}

    /** POST /checkout/hold — bloque des sièges 5 minutes. */
    public function hold(HoldSeatsRequest $request): JsonResponse
    {
        $event = Event::published()->whereKey($request->integer('event_id'))->firstOrFail();

        try {
            $result = $this->checkout->holdSeats($event, $request->unit_ids, $request->user()->id);
        } catch (RuntimeException $e) {
            // Conflit de siège → 409 pour que le front affiche "siège pris" et rafraîchisse le plan.
            return response()->json(['message' => $e->getMessage()], 409);
        }

        broadcast(new SeatMapUpdated($event->id));
        return response()->json($result, 201);
    }

    /** DELETE /checkout/hold/{holdId} — annulation explicite du hold. */
    public function releaseHold(Request $request, string $holdId): JsonResponse
    {
        $payload = cache()->get("hold:{$holdId}");
        if (!$payload || $payload['buyer_id'] !== $request->user()->id) {
            abort(404);
        }
        $this->checkout->releaseHold($holdId);
        broadcast(new SeatMapUpdated($payload['event_id']));
        return response()->json(['message' => 'Réservation annulée.']);
    }

    /** POST /checkout/order — crée la commande pending depuis un hold valide + extras. */
    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hold_id' => ['required', 'string'],
            'extras' => ['sometimes', 'array', 'max:5'],
            'extras.*.id' => ['required_with:extras', 'integer'],
            'extras.*.quantity' => ['required_with:extras', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $order = $this->checkout->createOrderFromHold(
                $request->user()->id,
                $data['hold_id'],
                $data['extras'] ?? []
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'order_id' => $order->id,
            'reference' => $order->reference,
            'total' => $order->total,
            'total_formatted' => \App\Support\Money::format((int) $order->total),
            'expires_at' => $order->hold_expires_at->toIso8601String(),
            'providers' => $this->providers->all(),
        ], 201);
    }

    /** POST /payment/{provider}/initiate — push USSI vers le téléphone de l'acheteur. */
    public function initiatePayment(InitiatePaymentRequest $request, string $provider): JsonResponse
    {
        /** @var PaymentProvider $impl */
        try {
            $impl = $this->providers->get($provider);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'Fournisseur inconnu.'], 422);
        }

        $order = Order::with('event')->findOrFail($request->order_id);
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Cette commande n\'est plus payable.'], 409);
        }
        if (!PaymentProvider::isValidTogoPhone($request->phone)) {
            return response()->json(['message' => 'Numéro Mobile Money invalide.'], 422);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => $impl->key(),
            'payer_phone' => $request->phone,
            'amount' => (int) $order->total,
            'fee' => (int) $order->service_fee,
            'status' => 'initiated',
        ]);

        $result = $impl->initiate($order, $payment);
        $payment->update([
            'status' => $result['status'],
            'provider_reference' => $result['reference'],
            'failure_reason' => $result['message'],
        ]);

        if ($result['status'] === 'failed') {
            return response()->json([
                'payment_id' => $payment->id,
                'status' => 'failed',
                'message' => $result['message'] ?? 'Le paiement a été refusé, réessayez.',
            ], 502);
        }

        return response()->json([
            'payment_id' => $payment->id,
            'status' => 'pending',
            'message' => "Confirmez le paiement sur votre téléphone (composez aussi *175# si rien n'arrive).",
            'poll_url' => "/api/v1/payment/{$payment->id}/status",
        ], 202);
    }

    /** GET /payment/{id}/status — polling léger côté React (le webhook reste la source de vérité). */
    public function paymentStatus(Request $request, int $payment): JsonResponse
    {
        $p = Payment::with('order.items.tickets')->whereKey($payment)->firstOrFail();
        abort_unless($p->order->buyer_id === $request->user()->id, 403);

        return response()->json([
            'status' => $p->status,
            'order_status' => $p->order->status,
            'tickets_ready' => $p->order->status === 'paid',
        ]);
    }
}
