<?php

/**
 * routes/api.php — API RESTful TicketFlow v1.
 *
 * Conventions :
 *  - Toutes les réponses sont du JSON ; les Resources Laravel formatent les sorties.
 *  - Versionnage par préfixe /api/v1 pour permettre une v2 sans casser les clients.
 *  - Rate limiting ciblé sur les endpoints sensibles (auth, checkout, revente) :
 *    le marché togolais est très mobile, les pics de trafic arrivent à l'ouverture
 *    des ventes — on protège la base tout en laissant la lecture publique cachée (Redis).
 */

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\OrganizerController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    /* --------------------------------------------------------------------
     | 0. Santé & métadonnées publiques
     * ------------------------------------------------------------------ */
    Route::get('/health', fn () => response()->json(['ok' => true, 'app' => config('app.name'), 'time' => now()->toIso8601String()]));

    // Liste des moyens de paiement disponibles (Mobile Money uniquement — pas de CB).
    Route::get('/payment-providers', function (\App\Services\Payment\PaymentProviderManager $manager) {
        return response()->json(['data' => $manager->all()]);
    });

    // Villes pour les filtres de recherche (le Togo uniquement — marché fermé).
    Route::get('/cities', fn () => \App\Models\City::orderBy('name')->get());

    /* --------------------------------------------------------------------
     | 1. Authentification (Sanctum, bearer tokens côté SPA)
     |    throttle:auth = 10 req/min → limite le brute-force (voir RouteServiceProvider).
     * ------------------------------------------------------------------ */
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('/register', 'register')->middleware('throttle:auth');
        Route::post('/login', 'login')->middleware('throttle:auth');
        Route::post('/logout', 'logout')->middleware('auth:sanctum');
        Route::get('/me', 'me')->middleware('auth:sanctum');
    });

    /* --------------------------------------------------------------------
     | 2. Marketplace publique (lecture seule, fortement mise en cache Redis)
     * ------------------------------------------------------------------ */
    Route::prefix('events')->controller(EventController::class)->group(function () {
        Route::get('/', 'index');                 // recherche + filtres + pagination
        Route::get('/suggest', 'suggest');        // autocomplétion (nom artiste/événement/lieu)
        Route::get('/{slug}', 'show');            // page événement + offres scorées
        Route::get('/{eventId}/sections', 'sections'); // plan de salle (statique, cache long)
        Route::get('/{eventId}/units', 'units');       // état des sièges (court TTL + temps réel Reverb)
    });

    // Marché secondaire public : annonces Autolist disponibles sur un événement.
    Route::get('/resales/event/{eventId}', [AccountController::class, 'eventResales']);

    /* --------------------------------------------------------------------
     | 3. Webhooks opérateurs Mobile Money — PAS d'auth Sanctum.
     |    Authentifiés par signature HMAC du corps brut (voir PaymentWebhookController).
     |    Doivent répondre en < 5 s : le traitement lourd passe en job.
     * ------------------------------------------------------------------ */
    Route::post('/webhooks/payment/{provider}', PaymentWebhookController::class)
        ->middleware('throttle:60,1')
        ->name('webhooks.payment');

    /* --------------------------------------------------------------------
     | 4. Tunnel d'achat — authentifié, throttlé sévèrement (anti-bot).
     |    hold → createOrder → initiatePayment → polling paymentStatus.
     * ------------------------------------------------------------------ */
    Route::middleware(['auth:sanctum', 'throttle:checkout'])->controller(CheckoutController::class)->group(function () {
        Route::post('/checkout/hold', 'hold');                       // pose un hold 5 min
        Route::delete('/checkout/hold/{holdId}', 'releaseHold');     // libération volontaire
        Route::post('/checkout/order', 'createOrder');               // hold → commande pending
        Route::post('/checkout/{order}/pay/{provider}', 'initiatePayment'); // USSD push opérateur
        Route::get('/payments/{payment}/status', 'paymentStatus');   // polling client
    });

    /* --------------------------------------------------------------------
     | 5. Espace acheteur (tout ce qui touche « mes données »)
     * ------------------------------------------------------------------ */
    Route::middleware('auth:sanctum')->prefix('me')->controller(AccountController::class)->group(function () {
        Route::get('/orders', 'orders');
        Route::get('/tickets', 'tickets');                   // portefeuille + QR
        Route::post('/tickets/{code}/transfer', 'transfer'); // transfert tracé
        Route::get('/recommendations', 'recommendations');   // reco personnalisées

        // Favoris & alertes prix (notifications intelligentes).
        Route::post('/favorites', 'toggleFavorite');
        Route::post('/price-alerts', 'storePriceAlert');

        // Notifications in-app.
        Route::get('/notifications', 'notifications');
        Route::post('/notifications/{id}/read', 'readNotification');
    });

    /* --------------------------------------------------------------------
     | 6. Revente / Autolist — throttle strict (risque de spéculation & fraude).
     * ------------------------------------------------------------------ */
    Route::middleware(['auth:sanctum', 'throttle:resale'])->controller(AccountController::class)->prefix('resale')->group(function () {
        Route::post('/', 'createResale');            // lister un ticket (auto ou manuel)
        Route::delete('/{id}', 'cancelResale');      // retirer une annonce
        Route::post('/{id}/buy', 'buyResale');       // achat instantané d'une annonce Autolist
    });

    /* --------------------------------------------------------------------
     | 7. Dashboard organisateur (Angular) — rôle organizer.
     * ------------------------------------------------------------------ */
    Route::middleware(['auth:sanctum', 'role:organizer'])->prefix('organizer')->controller(OrganizerController::class)->group(function () {
        Route::get('/events', 'events');
        Route::post('/events', 'store');                          // étape 1 : infos générales
        Route::put('/events/{event}', 'update');                  // étapes suivantes (partial updates)
        Route::post('/events/{event}/sections', 'storeSections'); // plan de salle
        Route::post('/events/{event}/ticket-types', 'storeTicketTypes'); // tarifs / stocks
        Route::post('/events/{event}/extras', 'storeExtras');     // options payantes (parking, boissons…)

        // Suivi temps réel & exports.
        Route::get('/events/{event}/stats', 'stats');              // ventes, revenus, remplissage
        Route::get('/events/{event}/live', 'live');                // snapshot check-ins (polling léger)
        Route::get('/events/{event}/participants', 'participants'); // liste + export CSV

        // Abonnement (modèle économique n°1).
        Route::get('/subscription', 'subscription');
        Route::post('/subscription/{plan}', 'changePlan');
    });

    /* --------------------------------------------------------------------
     | 8. Check-in agent/staff — scan QR en ligne + batch offline signé.
     |    (Les méthodes réelles de CheckInController : store/batch/sync/live.)
     * ------------------------------------------------------------------ */
    Route::middleware(['auth:sanctum', 'role:organizer,staff'])->prefix('checkin')->controller(CheckInController::class)->group(function () {
        Route::post('/', 'store');                          // scan online unitaire
        Route::get('/batch/{eventId}', 'batch');            // liste blanche signée pour le cache offline
        Route::post('/sync', 'sync');                       // remontée des scans IndexedDB (mode hors-ligne)
        Route::get('/live/{eventId}', 'live');              // compteur d'entrées en direct
    });
});
