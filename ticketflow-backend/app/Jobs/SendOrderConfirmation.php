<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi asynchrone de l'e-mail de confirmation + PDF des billets.
 * Doit être dans une file : un SMTP lent ne doit JAMAIS faire timeout un webhook Mobile Money.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;          // le réseau togolais est capricieux → retries exponentiels
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('buyer', 'event.venue.city', 'items.tickets')->findOrFail($this->orderId);
        Mail::to($order->buyer->email)->send(new \App\Mail\OrderConfirmationMail($order));
    }
}
