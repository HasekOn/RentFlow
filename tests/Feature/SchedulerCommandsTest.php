<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeaseExpiringNotification;
use App\Notifications\PaymentOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SchedulerCommandsTest extends TestCase
{
    use RefreshDatabase;

    /** Factory dává náhodné období podle skutečného dne; generování bere jen smlouvy platné v měsíci (#32). */
    private const array VALID_IN_MARCH = ['start_date' => '2025-06-01', 'end_date' => '2027-05-31'];

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
        // upozornění jen 30 a 7 dní před koncem (#33), dřív každý den
        $expiring = Lease::factory()->create([
            'property_id' => $stillRented->id, 'start_date' => '2025-04-19', 'end_date' => '2026-04-19',
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
        $active = Lease::factory()->create(['utility_advances' => 3000, ...self::VALID_IN_MARCH]);
        $ended = Lease::factory()->ended()->create(self::VALID_IN_MARCH);

        $this->artisan('rentflow:generate-payments')->assertSuccessful();
        $this->artisan('rentflow:generate-payments')->assertSuccessful();

        $payments = Payment::query()->where('lease_id', $active->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame([PaymentType::Rent, PaymentType::Utilities], $payments->pluck('type')->all());
        $this->assertSame(PaymentStatus::Unpaid, $payments->first()->status);
        $this->assertSame('2026-03-15', $payments->first()->due_date->toDateString());
        $this->assertSame(0, Payment::query()->where('lease_id', $ended->id)->count());
    }

    public function test_api_and_command_share_generation_and_api_covers_only_own_leases(): void
    {
        Carbon::setTestNow('2026-03-01 06:00');
        $landlord = User::factory()->landlord()->create();
        $own = Lease::factory()->create([
            'property_id' => Property::factory()->create(['landlord_id' => $landlord->id])->id,
            'utility_advances' => 0,
            ...self::VALID_IN_MARCH,
        ]);
        $foreign = Lease::factory()->create(self::VALID_IN_MARCH);

        $this->actingAs($landlord)
            ->postJson($this->apiUrl('/payments/generate-monthly'))
            ->assertStatus(200)
            ->assertJsonPath('created', 1);
        $this->assertSame(0, Payment::query()->where('lease_id', $foreign->id)->count());

        // příkaz potom doplní jen cizí smlouvu, API znovu nic
        $this->artisan('rentflow:generate-payments')->assertSuccessful();
        $this->actingAs($landlord)->postJson($this->apiUrl('/payments/generate-monthly'))->assertJsonPath('created', 0);

        $this->assertSame(1, Payment::query()->where('lease_id', $own->id)->count());
        $this->assertSame(2, Payment::query()->where('lease_id', $foreign->id)->count());
    }

    public function test_generation_reads_do_not_depend_on_lease_count(): void
    {
        Carbon::setTestNow('2026-03-01 06:00');
        Lease::factory()->count(2)->create(self::VALID_IN_MARCH);
        $this->artisan('rentflow:generate-payments')->assertSuccessful();
        $few = $this->countSelectsOfGeneration();

        Lease::factory()->count(5)->create(self::VALID_IN_MARCH);
        $this->artisan('rentflow:generate-payments')->assertSuccessful();

        $this->assertSame($few, $this->countSelectsOfGeneration());
    }

    /**
     * Opakované generování (vše už existuje): jen čtecí dotazy, bez vkládání.
     */
    private function countSelectsOfGeneration(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->artisan('rentflow:generate-payments')->assertSuccessful();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return count(array_filter($queries, fn (string $sql) => str_starts_with(strtolower($sql), 'select')));
    }
}
