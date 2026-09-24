<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** E-mail de confirmation d'achat (français, FCFA) — pièces jointes : un PDF/QR par ticket. */
class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Vos billets TicketFlow — {$this->order->reference}");
    }

    public function content(): Content
    {
        $this->order->load('event.venue.city', 'items.tickets.unit.section', 'buyer');
        return new Content(
            inline: 'emails.order-confirmation', // Blade inline (contenu riche + images QR cid:)
            with: ['order' => $this->order],
        );
    }

    /** Attach each QR PNG inline so the email is printable without opening the app. */
    public function build(): static
    {
        foreach ($this->order->items as $item) {
            foreach ($item->tickets as $ticket) {
                $path = storage_path('app/public/tickets/' . $ticket->code . '.png');
                if (is_file($path)) {
                    $this->attachFromStorageDisk('public', "tickets/{$ticket->code}.png",
                        "billet-{$ticket->unit->label}.png");
                }
            }
        }
        return $this;
    }
}
