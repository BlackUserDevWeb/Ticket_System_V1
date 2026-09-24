<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use App\Services\Payment\PaymentProviderManager;
use App\Services\ResaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentWebhookController — réception des confirmations opérateurs.
 *
 * Règles d'or appliquées ici :
 *  1. Réponse 200 RAPIDE : le traitement lourd (QR, emails) est synchrone mais court ;
 *     les e-mails partent en file (SendOrderConfirmation).
 *  2. Idempotence : statut terminal ignoré + verrou de rangée dans finalizePaidOrder.
 *  3. Signature HMAC vérifiée AVANT toute lecture du payload.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentProviderManager $providers,
        private CheckoutService $checkout,
        private ResaleService $resale,
    ) {}

    /** POST /webhooks/{provider} — route SANS auth Sanctum, protégée par signature. */
    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            $impl = $this->providers->get($provider);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => 'Fournisseur inconnu.'], 404);
        }

        // Vérification signature sur le corps BRUT (le parsing invaliderait le HMAC).
        $signature = (string) $request->header('X-Webhook-Signature', '');
        if (!$impl->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning("[webhook:{$provider}] signature invalide", ['ip' => $request->ip()]);
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $event = $impl->normalizeWebhook($request->input());
        if (!$event['provider_reference']) {
            return response()->json(['message' => 'Payload sans référence.'], 422);
        }

        // Retrouver le paiement : provider_reference exacte OU clientReference TF-<ref>.
        $payment = DB::transaction(fn () => Payment::where('provider_reference', $event['provider_reference'])
            ->lockForUpdate()
            ->first());

        if (!$payment) {
            Log::info("[webhook:{$provider}] paiement inconnu", $event);
            return response()->json(['message' => 'OK (ignoré)']); // 200 pour ne pas faire retry l'opérateur
        }

        if ($payment->isTerminal()) {
            return response()->json(['message' => 'OK (déjà traité)']); // idempotence stricte
        }

        $payment->update([
            'status' => $event['status'],
            'failure_reason' => $event['failure_reason'],
            'webhook_payload' => $request->input(),
            'confirmed_at' => $event['status'] === 'success' ? now() : null,
        ]);

        $order = $payment->order()->with('items.unit.ticketType', 'event.organizer', 'buyer')->first();

        switch ($event['status']) {
            case 'success':
                // Commande classique → tickets + QR ; commande de revente → transfert auto (Autolist).
                if ($order->items()->whereNotNull('meta')->exists()) {
                    $this->resale->finalizePurchase($order);
                } else {
                    $this->checkout->finalizePaidOrder($order);
                }
                break;

            case 'failed':
                $order->update(['status' => 'failed']);
                // Les sièges sont libérés par ReleaseExpiredHolds (statut reserved/kept → open).
                break;

            case 'pending':
            default:
                // Le push USSI attend encore la confirmation du client sur son téléphone.
                break;
        }

        return response()->json(['message' => 'OK']);
    }
}
