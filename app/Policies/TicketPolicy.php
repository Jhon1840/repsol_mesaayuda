<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->is_active && ($user->isSupport() || $ticket->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isSupport();
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
