<?php

namespace App\Actions\Managers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Odebrání správce z nemovitostí pronajímatele (#14): jen dané nemovitosti, ne přiřazení u jiných pronajímatelů.
 * Tikety, které na nich řešil, zůstanou bez řešitele (tiket už nevidí). Role se přepočítá. Autorizaci dělá volající.
 */
final class RemoveManager
{
    public function __construct(private readonly SyncManagerRole $syncRole) {}

    /**
     * @param  list<int>  $propertyIds
     */
    public function handle(User $user, array $propertyIds): User
    {
        return DB::transaction(function () use ($user, $propertyIds): User {
            $user->managedProperties()->detach($propertyIds);

            Ticket::withTrashed()
                ->whereIn('property_id', $propertyIds)
                ->where('assigned_to', $user->id)
                ->update(['assigned_to' => null]);

            return $this->syncRole->handle($user);
        });
    }
}
