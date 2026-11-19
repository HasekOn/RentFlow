<?php

namespace Tests\Feature\Concerns;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Data ve velikosti demo seedu (pronajímatel, správce, který je jinde nájemníkem, nájemníci, nemovitosti, smlouvy,
 * platby, tikety s komentáři, náklady). `addSeedBatch()` přidá jednu dávku, deset dávek = seed × 10.
 */
trait SeedLikeData
{
    protected User $seedLandlord;

    protected User $seedManager;

    protected User $seedTenant;

    /**
     * Hlavní seznamy a přehledy, na kterých se měří počet dotazů.
     */
    protected const array MEASURED_URLS = [
        '/properties', '/leases', '/payments', '/tickets', '/users', '/expenses',
        '/dashboard/stats', '/dashboard/finance-chart',
    ];

    protected function createSeedLikeData(): void
    {
        $this->seedLandlord = User::factory()->landlord()->create();
        $this->seedManager = User::factory()->manager()->create();
        // správce je nájemníkem u jiného pronajímatele (jako manager@rentflow.cz v seedu)
        Lease::factory()->create([
            'property_id' => Property::factory()->occupied()->create()->id,
            'tenant_id' => $this->seedManager->id,
            'start_date' => today()->subYear(),
            'end_date' => today()->addYear(),
        ]);
        $this->addSeedBatch();
        $this->seedTenant = User::query()->whereKey(Lease::query()->where('tenant_id', '!=', $this->seedManager->id)->value('tenant_id'))->firstOrFail();
    }

    /**
     * Jedna dávka jako seed: 6 nemovitostí (3 spravuje správce), na každé aktivní smlouva s 8 platbami,
     * ukončená smlouva s hodnocením, 2 tikety se 2 komentáři a 4 náklady.
     */
    protected function addSeedBatch(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $property = Property::factory()->occupied()->create(['landlord_id' => $this->seedLandlord->id]);
            if ($i < 3) {
                $property->managers()->attach($this->seedManager->id);
            }

            $lease = Lease::factory()->create([
                'property_id' => $property->id,
                'start_date' => today()->subYear(),
                'end_date' => today()->addYear(),
            ]);
            for ($month = 1; $month <= 8; $month++) {
                Payment::factory()->create([
                    'lease_id' => $lease->id,
                    'type' => $month % 2 ? PaymentType::Rent : PaymentType::Utilities,
                    'due_date' => today()->subMonths($month)->startOfMonth()->addDays(14),
                    'paid_date' => $month > 2 ? today()->subMonths($month)->startOfMonth()->addDays(14) : null,
                    'status' => $month > 2 ? PaymentStatus::Paid : PaymentStatus::Overdue,
                ]);
            }

            $ended = Lease::factory()->ended()->create(['property_id' => $property->id]);
            Rating::factory()->create(['lease_id' => $ended->id, 'rated_by' => $this->seedLandlord->id]);

            Ticket::factory()->count(2)->create(['property_id' => $property->id, 'tenant_id' => $lease->tenant_id])
                ->each(fn (Ticket $ticket) => TicketComment::factory()->count(2)->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $lease->tenant_id,
                ]));

            Expense::factory()->count(4)->create(['property_id' => $property->id, 'expense_date' => today()->subMonths($i)]);
        }
    }

    /**
     * Počet dotazů každé měřené adresy pro uživatele.
     *
     * @return array<string, int>
     */
    protected function countMeasuredQueries(User $user): array
    {
        $counts = [];
        foreach (self::MEASURED_URLS as $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $status = $this->actingAs($user)->getJson($this->apiUrl($url))->status();
            $counts[$url] = count(DB::getQueryLog());
            // 403 je u role bez přístupu v pořádku (i ten musí mít stálý počet dotazů), chyba serveru ne
            $this->assertContains($status, [200, 403], "{$url} vrátil {$status}");
            DB::disableQueryLog();
        }

        return $counts;
    }
}
