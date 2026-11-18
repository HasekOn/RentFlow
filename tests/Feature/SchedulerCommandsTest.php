<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Notifications\LeaseExpiringNotification;
use App\Notifications\PaymentOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SchedulerCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_overdue_marks_only_unpaid_payments_older_than_five_days(): void
    {
        Carbon::setTestNow('2026-03-20 08:00');
        $lease = Lease::factory()->create();
        $late = Payment::factory()->unpaid()->create(['lease_id' => $lease->id, 'due_date' => '2026-03-10']);
        $recent = Payment::factory()->unpaid()->create(['lease_id' => $lease->id, 'due_date' => '2026-03-18']);
        $paid = Payment::factory()->paid()->create(['lease_id' => $lease->id, 'due_date' => '2026-03-01']);

        $this->artisan('rentflow:check-overdue-payments')->assertSuccessful();

        $this->assertSame(PaymentStatus::Overdue, $late->refresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $recent->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $paid->refresh()->status);
        Notification::assertSentToTimes($lease->tenant, PaymentOverdueNotification::class, 1);
    }

    public function test_check_expiring_ends_expired_lease_and_frees_property(): void
    {
        Carbon::setTestNow('2026-03-20 08:00');
        $freed = Property::factory()->occupied()->create();
        $expired = Lease::factory()->create([
            'property_id' => $freed->id, 'start_date' => '2025-03-20', 'end_date' => '2026-03-19',
        ]);
        $stillRented = Property::factory()->occupied()->create();
        $expiring = Lease::factory()->create([
            'property_id' => $stillRented->id, 'start_date' => '2025-04-10', 'end_date' => '2026-04-10',
        ]);

        $this->artisan('rentflow:check-expiring-leases')->assertSuccessful();

        $this->assertSame(LeaseStatus::Ended, $expired->refresh()->status);
        $this->assertSame(PropertyStatus::Available, $freed->refresh()->status);
        $this->assertSame(LeaseStatus::Active, $expiring->refresh()->status);
        $this->assertSame(PropertyStatus::Occupied, $stillRented->refresh()->status);
        Notification::assertSentToTimes($expiring->tenant, LeaseExpiringNotification::class, 1);
        Notification::assertNotSentTo($expired->tenant, LeaseExpiringNotification::class);
    }

    public function test_generate_payments_twice_does_not_create_duplicates(): void
    {
        Carbon::setTestNow('2026-03-01 06:00');
        $active = Lease::factory()->create(['utility_advances' => 3000]);
        $ended = Lease::factory()->ended()->create();

        $this->artisan('rentflow:generate-payments')->assertSuccessful();
        $this->artisan('rentflow:generate-payments')->assertSuccessful();

        $payments = Payment::query()->where('lease_id', $active->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame([PaymentType::Rent, PaymentType::Utilities], $payments->pluck('type')->all());
        $this->assertSame(PaymentStatus::Unpaid, $payments->first()->status);
        $this->assertSame('2026-03-15', $payments->first()->due_date->toDateString());
        $this->assertSame(0, Payment::query()->where('lease_id', $ended->id)->count());
    }
}
