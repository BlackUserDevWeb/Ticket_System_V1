<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Services\Payment\MixxByYasProvider;
use App\Services\Payment\MoovMoneyProvider;
use App\Services\Payment\PaymentProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Url;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider — câblage applicatif TicketFlow.
 *
 * 1) PaymentProviderManager en singleton : les deux opérateurs (Moov Money /
 *    Mixx by Yas) y sont enregistrés. Ajouter un troisième agrégateur (ex: FedaPay)
 *    = une ligne ici, aucun changement de code métier (principe ouvert/fermé).
 * 2) Rate limiters nommés : profils distincts selon la sensibilité de l'endpoint.
 *    - auth     : 10/min/IP   (anti brute-force)
 *    - checkout : 30/min/user (un humain ne clique pas 60 holds par minute)
 *    - resale   : 15/min/user (anti spéculation automatisée)
 * 3) Policies + canal de diffusion des mises à jour de sièges (Reverb).
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentProviderManager::class, function ($app) {
            $manager = new PaymentProviderManager();
            // Les providers sont résolus via le conteneur => injectables et testables (Mockery).
            $manager->register($app->make(MoovMoneyProvider::class));
            $manager->register($app->make(MixxByYasProvider::class));
            return $manager;
        });
    }

    public function boot(): void
    {
        // Politique d'événement : publication réservée à l'organisateur vérifié ou admin.
        Gate::policy(Event::class, EventPolicy::class);

        // Le marché est exclusivement togolais : on normalise early pour éviter
        // tout slug en double entre « Lome » et « lomÉ » (le driver MySQL est
        // case-insensitive mais pas Postgres — on sécurise les deux).
        Url::defaultScheme(request()?->isSecure() ? 'https' : 'http');

        RateLimiter::for('auth', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('checkout', fn ($request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('resale', fn ($request) => Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()));
    }
}
