<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        if ($user->role === 'manager') {
            if ($ticket->assigned_to === $user->id) {
                return true;
            }

            if ($ticket->property) {
                return $user->managedProperties()
                    ->where('properties.id', $ticket->property_id)
                    ->exists();
            }
        }

        return $ticket->tenant_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['tenant', 'landlord', 'manager']);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        if ($user->role === 'manager') {
            // Assigned directly to this ticket
            if ($ticket->assigned_to === $user->id) {
                return true;
            }

            // Manages this property
            if ($ticket->property) {
                return $user->managedProperties()
                    ->where('properties.id', $ticket->property_id)
                    ->exists();
            }
        }

        return false;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $ticket->property && $ticket->property->landlord_id === $user->id;
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
