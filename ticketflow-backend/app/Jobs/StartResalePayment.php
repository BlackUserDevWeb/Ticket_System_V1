<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payment\PaymentProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Déclenche le push USSI d'une commande de revente hors requête HTTP (lenteur opérateur). */
class StartResalePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $paymentId) {}

    public function handle(PaymentProviderManager $providers): void
    {
        $payment = Payment::with('order')->findOrFail($this->paymentId);
        if (!in_array($payment->status, ['initiated'], true)) {
            return; // déjà traité
        }
        $result = $providers->get($payment->provider)->initiate($payment->order, $payment);
        $payment->update([
            'status' => $result['status'],
            'provider_reference' => $result['reference'],
            'failure_reason' => $result['message'],
        ]);
    }
}
