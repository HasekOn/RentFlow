<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Property;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Zobrazit a stáhnout smí ten, komu dokument vrátí scope visibleTo (jedna pravda pro seznam i stažení).
     */
    public function view(User $user, Document $document): bool
    {
        return Document::query()->visibleTo($user)->whereKey($document->id)->exists();
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->property?->landlord_id === $user->id;
    }
}
