<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Contrat unique des fournisseurs Mobile Money (Moov Money/Flooz et Mixx by Yas).
 *
 * Justification de l'abstraction : les deux agrégateurs togolais ont des API très
 * différentes (OAuth client-credentials chez l'un, HMAC-signature chez l'autre) mais
 * le parcours métier est identique : initier un débit USSI → attendre un webhook →
 * réconcilier. Ce contrat isole cette variabilité ; un ajout Wave/MTN futur ne touche
 * que le contrôleur et la config.
 */
abstract class PaymentProvider
{
    /** Identifiant machine stocké dans payments.provider ('moov_money' | 'mixx_yas'). */
    abstract public function key(): string;

    /** Nom affiché dans l'interface ("Moov Money", "Mixx by Yas"). */
    abstract public function label(): string;

    /**
     * Déclenche la demande de débit côté opérateur (push USSI vers le téléphone client).
     * Retourne ['reference' => string|null, 'status' => 'pending'|'failed', 'message' => ?string].
     */
    abstract public function initiate(Order $order, Payment $payment): array;

    /** Vérifie la signature d'un webhook entrant (protection contre la falsification). */
    abstract public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool;

    /**
     * Normalise un webhook en événement interne :
     * ['provider_reference' => ..., 'status' => 'success'|'failed'|'pending', 'failure_reason' => ...].
     */
    abstract public function normalizeWebhook(array $payload): array;

    /** Demande de remboursement (reverse Mobile Money) — asynchrone, confirmé par webhook. */
    abstract public function refund(Payment $payment, int $amountXof): bool;

    /** Génère une référence de transaction locale non devinable, envoyée à l'opérateur. */
    protected function localReference(): string
    {
        return 'TFX' . now()->format('ymdHis') . Str::upper(Str::random(6));
    }

    /** Valide grossièrement un numéro togolais (+228XXXXXXXX, 8 chiffres nationaux). */
    public static function isValidTogoPhone(string $phone): bool
    {
        return (bool) preg_match('/^\+?228?[01]\d{7}$/', str_replace(' ', '', $phone));
    }
}
