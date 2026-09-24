<?php

namespace App\Services\Payment;

use InvalidArgumentException;

/**
 * Résout le fournisseur Mobile Money demandé par son slug d'URL (/payment/{provider}/initiate).
 * Enregistré comme singleton dans AppServiceProvider pour rester ouvert/fermé.
 */
class PaymentProviderManager
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): PaymentProvider
    {
        if (!isset($this->providers[$key])) {
            throw new InvalidArgumentException("Fournisseur de paiement inconnu : {$key}");
        }
        return $this->providers[$key];
    }

    /** Liste pour l'affichage des choix dans le tunnel d'achat. */
    public function all(): array
    {
        return array_map(
            fn (PaymentProvider $p) => ['key' => $p->key(), 'label' => $p->label()],
            array_values($this->providers)
        );
    }
}
