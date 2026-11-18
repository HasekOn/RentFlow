<?php

namespace App\Policies;

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Lease;
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

        // Manager assigned or manages property
        if ($user->isManager()) {
            if ($ticket->assigned_to === $user->id) {
                return true;
            }

            if ($ticket->property) {
                return $user->managedProperties()
                    ->where('properties.id', $ticket->property_id)
                    ->exists();
            }
        }

        // Tenant created this ticket
        if ($ticket->tenant_id === $user->id) {
            return true;
        }

        // Tenant has active lease on this property — can see all tickets for their unit
        if ($user->isTenant() && $ticket->property_id) {
            return Lease::query()
                ->where('tenant_id', $user->id)
                ->where('property_id', $ticket->property_id)
                ->where('status', LeaseStatus::Active)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, Role::cases(), true);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($ticket->property && $ticket->property->landlord_id === $user->id) {
            return true;
        }

        if ($user->isManager()) {
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
