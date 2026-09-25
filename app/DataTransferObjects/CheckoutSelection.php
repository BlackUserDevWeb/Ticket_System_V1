<?php

namespace App\DataTransferObjects;

/**
 * Sélection d'achat transmise au checkout : sièges ou types de tickets sans plan.
 */
final readonly class CheckoutSelection
{
    /**
     * @param  array<int, int>  $seatIds      Sièges réservés (événement avec plan de salle)
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $typeItems  Tickets génériques
     */
    public function __construct(
        public int $eventId,
        public array $seatIds = [],
        public array $typeItems = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (int) $data['event_id'],
            seatIds: array_map('intval', $data['seat_ids'] ?? []),
            typeItems: array_values(array_filter(
                $data['type_items'] ?? [],
                fn ($i) => isset($i['ticket_type_id']) && (int) $i['quantity'] > 0
            )),
        );
    }

    public function isEmpty(): bool
    {
        return $this->seatIds === [] && $this->typeItems === [];
    }

    public function totalTickets(): int
    {
        return count($this->seatIds) + array_sum(array_column($this->typeItems, 'quantity'));
    }
}
