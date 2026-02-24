<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Lease;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::factory()->landlord()->create([
            'name' => 'Jan Pronajímatel',
            'email' => 'landlord@rentflow.cz',
        ]);

        $manager = User::factory()->manager()->create([
            'name' => 'Petr Správce',
            'email' => 'manager@rentflow.cz',
        ]);

        $tenants = User::factory()->tenant()->count(5)->create();

        $this->command->info('Users created: 1 landlord, 1 manager, 5 tenants');

        $properties = Property::factory()
            ->count(6)
            ->sequence(
                ['address' => 'Václavské náměstí 12, Praha 1', 'city' => 'Praha', 'disposition' => '2+kk', 'status' => 'occupied'],
                ['address' => 'Brněnská 45, Brno', 'city' => 'Brno', 'disposition' => '3+1', 'status' => 'occupied'],
                ['address' => 'Masarykova 8, Ostrava', 'city' => 'Ostrava', 'disposition' => '1+1', 'status' => 'occupied'],
                ['address' => 'Palackého 22, Plzeň', 'city' => 'Plzeň', 'disposition' => '2+1', 'status' => 'occupied'],
                ['address' => 'Dlouhá 5, Olomouc', 'city' => 'Olomouc', 'disposition' => '1+kk', 'status' => 'available'],
                ['address' => 'Nádražní 17, Liberec', 'city' => 'Liberec', 'disposition' => '3+kk', 'status' => 'renovation'],
            )
            ->create(['landlord_id' => $landlord->id]);

        $this->command->info('Properties created: 6 (4 occupied, 1 available, 1 renovation)');

        $activeLeases = collect();
        $occupiedProperties = $properties->where('status', 'occupied');

        foreach ($occupiedProperties as $index => $property) {
            $lease = Lease::factory()->create([
                'property_id' => $property->id,
                'tenant_id' => $tenants[$index]->id,
                'start_date' => now()->subMonths(rand(3, 12)),
                'end_date' => now()->addMonths(rand(2, 12)),
            ]);
            $activeLeases->push($lease);
        }

        $this->command->info('Active leases created: '.$activeLeases->count());

        $endedLease = Lease::factory()->ended()->create([
            'property_id' => $properties[0]->id,
            'tenant_id' => $tenants[4]->id,
        ]);

        foreach (['apartment_condition', 'communication', 'rules', 'overall'] as $category) {
            Rating::factory()->create([
                'lease_id' => $endedLease->id,
                'rated_by' => $landlord->id,
                'category' => $category,
                'score' => fake()->numberBetween(3, 5),
            ]);
        }

        $this->command->info('Ended lease with 4 ratings created');

        foreach ($activeLeases as $lease) {
            for ($i = 5; $i >= 0; $i--) {
                $dueDate = now()->subMonths($i)->startOfMonth()->addDays(14);

                if ($i === 0) {
                    // Current month — unpaid
                    Payment::factory()->unpaid()->create([
                        'lease_id' => $lease->id,
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                    ]);
                } elseif ($i === 1 && fake()->boolean(30)) {
                    // Last month — 30% chance late payment
                    Payment::factory()->latePayment()->create([
                        'lease_id' => $lease->id,
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                    ]);
                } else {
                    // Older months — paid on time
                    Payment::factory()->paid()->create([
                        'lease_id' => $lease->id,
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                    ]);
                }
            }
        }

        $this->command->info('Payments created: 6 months × '.$activeLeases->count().' leases');

        foreach ($properties as $property) {
            Expense::factory()->count(rand(2, 5))->create([
                'property_id' => $property->id,
            ]);
        }

        $this->command->info('Expenses created for all properties');

        foreach ($activeLeases as $lease) {
            Ticket::factory()
                ->resolved()
                ->count(rand(1, 2))
                ->create([
                    'property_id' => $lease->property_id,
                    'tenant_id' => $lease->tenant_id,
                    'assigned_to' => $manager->id,
                ])
                ->each(function ($ticket) use ($lease, $manager) {
                    // Add comments thread
                    TicketComment::factory()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $lease->tenant_id,
                        'message' => 'Please fix this as soon as possible.',
                    ]);

                    TicketComment::factory()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $manager->id,
                        'message' => 'We will send a technician tomorrow.',
                    ]);

                    TicketComment::factory()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $manager->id,
                        'message' => 'Issue has been fixed. Closing ticket.',
                    ]);
                });

            if (fake()->boolean(50)) {
                Ticket::factory()->open()->create([
                    'property_id' => $lease->property_id,
                    'tenant_id' => $lease->tenant_id,
                ]);
            }
        }

        $this->command->info('Tickets with comments created');

        foreach ($properties as $property) {
            $meters = collect();

            // Each property gets water + electricity meters
            $meters->push(Meter::factory()->create([
                'property_id' => $property->id,
                'meter_type' => 'water',
            ]));
            $meters->push(Meter::factory()->create([
                'property_id' => $property->id,
                'meter_type' => 'electricity',
            ]));

            // Gas meter for bigger apartments
            if (in_array($property->disposition, ['2+1', '3+1', '3+kk'])) {
                $meters->push(Meter::factory()->create([
                    'property_id' => $property->id,
                    'meter_type' => 'gas',
                ]));
            }

            // Generate 6 months of readings per meter (ascending values)
            foreach ($meters as $meter) {
                $baseValue = fake()->randomFloat(3, 100, 500);
                $tenant = $activeLeases->firstWhere('property_id', $property->id)?->tenant_id;

                for ($i = 5; $i >= 0; $i--) {
                    $baseValue += fake()->randomFloat(3, 5, 30);
                    MeterReading::factory()->create([
                        'meter_id' => $meter->id,
                        'reading_value' => $baseValue,
                        'reading_date' => now()->subMonths($i)->startOfMonth(),
                        'submitted_by' => $tenant ?? $landlord->id,
                    ]);
                }
            }
        }

        $this->command->info('Meters and readings created');

        foreach ($properties as $property) {
            InventoryItem::factory()->count(rand(4, 8))->create([
                'property_id' => $property->id,
            ]);
        }

        $this->command->info('Inventory items created');

        foreach ($properties as $property) {
            PropertyImage::factory()->count(rand(2, 4))->create([
                'property_id' => $property->id,
                'uploaded_by' => $landlord->id,
            ]);
        }

        $this->command->info('Property images created');

        foreach ($occupiedProperties->take(2) as $property) {
            Notice::factory()->create([
                'property_id' => $property->id,
                'created_by' => $landlord->id,
            ]);
        }

        $this->command->info('Notices created');

        foreach ($tenants as $tenant) {
            $tenant->recalculateTrustScore();
        }

        $this->command->info('Trust scores recalculated');

        $this->command->newLine();
        $this->command->info('=== SEED COMPLETE ===');
        $this->command->info('Login credentials (password: "password"):');
        $this->command->table(
            ['Role', 'Email'],
            [
                ['Landlord', 'landlord@rentflow.cz'],
                ['Manager', 'manager@rentflow.cz'],
                ...($tenants->map(fn ($t) => ['Tenant', $t->email])->toArray()),
            ]
        );
    }
}
