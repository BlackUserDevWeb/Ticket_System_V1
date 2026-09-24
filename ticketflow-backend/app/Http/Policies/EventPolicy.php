<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Un organisateur ne manipule QUE ses événements (les admins via gate admin.*). */
    public function manage(User $user, Event $event): bool
    {
        return $user->id === $event->organizer_id;
    }

    public function before(User $user, string $ability): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }
}
