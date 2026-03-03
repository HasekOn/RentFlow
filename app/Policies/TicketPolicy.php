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
        // Landlord owns the property
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        // Manager manages this property
        if ($user->role === 'manager' && $ticket->property) {
            return $user->managedProperties()
                ->where('properties.id', $ticket->property_id)
                ->exists();
        }

        // Tenant created this ticket
        return $ticket->tenant_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['tenant', 'landlord', 'manager']);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        // Landlord owns the property
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        // Manager manages this property
        if ($user->role === 'manager' && $ticket->property) {
            return $user->managedProperties()
                ->where('properties.id', $ticket->property_id)
                ->exists();
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
