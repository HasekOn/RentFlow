<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Can user see list of tickets?
     * All roles — controller filters by role
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can user see this ticket?
     * Landlord owns the property, manager is assigned, tenant created it
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Landlord owns the property
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        // Manager is assigned to this ticket
        if ($user->role === 'manager' && $ticket->assigned_to === $user->id) {
            return true;
        }

        // Tenant created this ticket
        return $ticket->tenant_id === $user->id;
    }

    /**
     * Can user create tickets?
     * Tenants and landlords (landlord might report issues too)
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['tenant', 'landlord']);
    }

    /**
     * Can user update this ticket? (status, priority, assignment)
     * Landlord owns the property OR assigned manager
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Landlord owns the property
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        // Assigned manager
        return $user->role === 'manager' && $ticket->assigned_to === $user->id;
    }

    /**
     * Can user delete this ticket?
     * Only landlord who owns the property
     */
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
