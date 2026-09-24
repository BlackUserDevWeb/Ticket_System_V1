<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * NotificationService — couche unique de notification.
 *
 * Stratégie : TOUJOURS une ligne `notifications` (in-app, lue par le front via polling
 * ou Reverb) + e-mail si le type est critique. Les envois passent par la QUEUE
 * (ShouldQueue dans les Mailables / ici dispatch différé) pour ne jamais bloquer
 * le webhook de paiement — crucial avec un réseau mobile instable.
 */
class NotificationService
{
    public function inApp(User $user, string $type, string $title, ?string $body = null, ?string $link = null): Notification
    {
        return Notification::create(compact('user', 'type', 'title', 'body', 'link') + ['user_id' => $user->id]);
    }

    /** Confirmation d'achat : in-app + e-mail avec pièce jointe QR (Job en file). */
    public function orderConfirmed(Order $order): void
    {
        $order->load('event', 'buyer');
        $this->inApp(
            $order->buyer,
            'order_paid',
            "Commande {$order->reference} confirmée",
            "Vos billets pour « {$order->event->title} » vous attendent dans votre espace.",
            '/mes-billets'
        );
        // File d'attente : jamais de SMTP synchrone dans un webhook.
        \App\Jobs\SendOrderConfirmation::dispatch($order->id)->onQueue('emails');
    }

    /** Alerte baisse de prix (scannée par CheckPriceAlerts, 1×/heure). */
    public function priceDrop(User $user, \App\Models\Event $event, int $newPrice): void
    {
        $this->inApp($user, 'price_drop', "Baisse de prix : {$event->title}",
            'Le prix est passé à ' . \App\Support\Money::format($newPrice), "/evenement/{$event->slug}");
    }

    /** Alerte revente disponible sur un événement favori (Autolist). */
    public function resaleAvailable(User $user, \App\Models\Event $event, int $price): void
    {
        $this->inApp($user, 'resale_available', "Nouvelle revente : {$event->title}",
            'Une place se libère à ' . \App\Support\Money::format($price), "/evenement/{$event->slug}/revente");
    }

    /** Rappel avant événement (config ticketflow.notifications.event_reminder_hours). */
    public function eventReminder(User $user, \App\Models\Event $event): void
    {
        $this->inApp($user, 'reminder_event', "« {$event->title} » commence bientôt",
            "Rendez-vous le {$event->starts_at->format('d/m/Y à H\\i')} — pensez à votre QR code.",
            '/mes-billets');
    }

    /** Demande de transfert reçue. */
    public function transferRequested(User $receiver, Ticket $ticket, User $from): void
    {
        $this->inApp($receiver, 'transfer_request', $from->name . ' vous offre un billet',
            "« {$ticket->unit->event->title} » — acceptez dans votre espace.", '/mes-billets');
    }
}
