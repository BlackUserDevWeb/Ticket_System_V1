<?php

/**
 * bootstrap/app.php — Application Laravel 11 (structure « slim »).
 *
 * Choix justifiés :
 *  - API-only : pas de session web, Sanctum en mode bearer token pour les 3 SPA
 *    (React marketplace, Angular organisateur, Angular admin) + le scanner Vanilla.
 *  - Alias middleware « role: » pour protéger les groupes de routes par rôle.
 *  - Exceptions normalisées en JSON : les SPA ne doivent jamais recevoir de HTML.
 *  - Scheduler : les crons métier (holds expirés, rappels, alertes prix) sont
 *    critiques pour l'intégrité du stock dans un contexte Mobile Money instable.
 */

use App\Console\CheckPriceAlerts;
use App\Console\ReleaseExpiredHolds;
use App\Console\SendEventReminders;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // role:organizer,staff → EnsureRole (contrôle d'accès simple et explicite).
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Les webhooks opérateurs arrivent sans CSRF token (appel serveur-à-serveur).
        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Une API REST ne doit JAMAIS renvoyer du HTML : toute erreur devient un
        // JSON lisible par les SPA, avec un code HTTP parlant.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null; // laisser le handler web classique agir
            }

            return match (true) {
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException =>
                    response()->json(['message' => 'Ressource introuvable.'], 404),
                $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException =>
                    response()->json(['message' => 'Accès refusé.'], 403),
                $e instanceof \Illuminate\Auth\AuthenticationException =>
                    response()->json(['message' => 'Authentification requise.'], 401),
                $e instanceof \Illuminate\Validation\ValidationException =>
                    response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422),
                default => response()->json([
                    'message' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur.',
                ], 500),
            };
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        /*
         | ReleaseExpiredHolds : libère les sièges dont le hold (5 min) a expiré.
         |   Sans lui, des sièges resteraient « réservés » après un abandon de tunnel
         |   (fréquent avec les échecs USSD Mobile Money).
         | SendEventReminders : rappels avant événement (notifications intelligentes).
         | CheckPriceAlerts : alertes de baisse de prix sur les événements suivis.
         */
        $schedule->command(ReleaseExpiredHolds::class)->everyMinute();
        $schedule->command(SendEventReminders::class)->everyTenMinutes();
        $schedule->command(CheckPriceAlerts::class)->everyFifteenMinutes();
    })
    ->create();
