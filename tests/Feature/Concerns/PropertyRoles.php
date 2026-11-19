<?php

namespace Tests\Feature\Concerns;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;

/**
 * Role vůči jedné nemovitosti pro testy oprávnění (#38): vlastník, cizí pronajímatel, přiřazený a nepřiřazený
 * správce, nájemník s aktivní a s ukončenou smlouvou. Přiřazený správce je zároveň nájemníkem cizí nemovitosti.
 */
trait PropertyRoles
{
    protected User $landlord;

    protected User $otherLandlord;

    protected Property $property;

    protected Property $otherProperty;

    protected User $manager;

    protected User $strangerManager;

    protected User $tenant;

    protected User $formerTenant;

    protected Lease $lease;

    protected Lease $endedLease;

    protected Lease $managerLease;

    protected function createPropertyRoles(): void
    {
        $this->landlord = User::factory()->landlord()->create();
        $this->otherLandlord = User::factory()->landlord()->create();
        $this->property = Property::factory()->occupied()->create(['landlord_id' => $this->landlord->id]);
        $this->otherProperty = Property::factory()->occupied()->create(['landlord_id' => $this->otherLandlord->id]);

        $this->manager = User::factory()->manager()->create();
        $this->property->managers()->attach($this->manager->id);
        $this->strangerManager = User::factory()->manager()->create();
        $this->otherProperty->managers()->attach($this->strangerManager->id);

        $this->tenant = User::factory()->tenant()->create();
        $this->lease = Lease::factory()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->subYear()->startOfDay(),
            'end_date' => now()->addYear()->startOfDay(),
        ]);
        $this->formerTenant = User::factory()->tenant()->create();
        $this->endedLease = Lease::factory()->ended()->create([
            'property_id' => $this->property->id,
            'tenant_id' => $this->formerTenant->id,
        ]);
        $this->managerLease = Lease::factory()->create([
            'property_id' => $this->otherProperty->id,
            'tenant_id' => $this->manager->id,
        ]);
    }

    /**
     * Kdo k nemovitosti nemá žádný vztah, který by mu dovolil ji vidět.
     *
     * @return list<User>
     */
    protected function outsiders(): array
    {
        return [$this->otherLandlord, $this->strangerManager, $this->formerTenant];
    }
}
