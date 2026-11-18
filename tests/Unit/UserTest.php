<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_role_and_trust_score_are_not_mass_assignable(): void
    {
        $fillable = (new User)->getFillable();

        $this->assertNotContains('role', $fillable);
        $this->assertNotContains('trust_score', $fillable);
    }

    public function test_role_is_cast_to_enum_with_tenant_default(): void
    {
        $this->assertSame(Role::Tenant, (new User)->role);
        $this->assertTrue((new User)->forceFill(['role' => 'landlord'])->isLandlord());
    }
}
