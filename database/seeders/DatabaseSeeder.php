<?php

namespace Database\Seeders;

use App\Models\Document;
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
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Ensure storage directories exist ────
        Storage::disk('public')->makeDirectory('properties');
        Storage::disk('public')->makeDirectory('documents');
        Storage::disk('public')->makeDirectory('tickets');

        // ─── USERS ────────────────────────────────
        $landlord = User::factory()->landlord()->create([
            'name' => 'Jan Novák',
            'email' => 'landlord@rentflow.cz',
            'phone' => '+420 602 123 456',
        ]);

        $manager = User::factory()->manager()->create([
            'name' => 'Petr Svoboda',
            'email' => 'manager@rentflow.cz',
            'phone' => '+420 603 234 567',
        ]);

        $tenants = collect();

        $tenantData = [
            ['name' => 'Marie Dvořáková', 'email' => 'marie@rentflow.cz', 'phone' => '+420 604 111 222'],
            ['name' => 'Tomáš Černý', 'email' => 'tomas@rentflow.cz', 'phone' => '+420 605 222 333'],
            ['name' => 'Eva Procházková', 'email' => 'eva@rentflow.cz', 'phone' => '+420 606 333 444'],
            ['name' => 'Lukáš Veselý', 'email' => 'lukas@rentflow.cz', 'phone' => '+420 607 444 555'],
            ['name' => 'Kateřina Kučerová', 'email' => 'katerina@rentflow.cz', 'phone' => '+420 608 555 666'],
        ];

        foreach ($tenantData as $td) {
            $tenants->push(User::factory()->tenant()->create($td));
        }

        $this->command->info('✓ Users: 1 landlord, 1 manager, 5 tenants');

        // ─── PROPERTIES ───────────────────────────
        $propertyData = [
            [
                'address' => 'Václavské náměstí 12',
                'city' => 'Praha',
                'zip_code' => '110 00',
                'disposition' => '2+kk',
                'size' => 58.5,
                'floor' => 3,
                'status' => 'occupied',
                'purchase_price' => 4500000,
                'description' => 'Stylový byt v centru Prahy s výhledem na Václavské náměstí. Kompletně zrekonstruovaný, nové rozvody, moderní kuchyňská linka.',
            ],
            [
                'address' => 'Brněnská 45',
                'city' => 'Brno',
                'zip_code' => '602 00',
                'disposition' => '3+1',
                'size' => 82.0,
                'floor' => 2,
                'status' => 'occupied',
                'purchase_price' => 3200000,
                'description' => 'Prostorný rodinný byt v klidné části Brna. Velká kuchyně, dva balkony, sklep.',
            ],
            [
                'address' => 'Masarykova třída 8',
                'city' => 'Ostrava',
                'zip_code' => '702 00',
                'disposition' => '1+1',
                'size' => 38.0,
                'floor' => 5,
                'status' => 'occupied',
                'purchase_price' => 1800000,
                'description' => 'Kompaktní byt vhodný pro jednotlivce nebo pár. Nová koupelna, plastová okna.',
            ],
            [
                'address' => 'Palackého 22',
                'city' => 'Plzeň',
                'zip_code' => '301 00',
                'disposition' => '2+1',
                'size' => 65.0,
                'floor' => 1,
                'status' => 'occupied',
                'purchase_price' => 2800000,
                'description' => 'Byt v přízemí s předzahrádkou. Ideální pro rodinu s dětmi. Parkování před domem.',
            ],
            [
                'address' => 'Dlouhá 5',
                'city' => 'Olomouc',
                'zip_code' => '779 00',
                'disposition' => '1+kk',
                'size' => 32.0,
                'floor' => 4,
                'status' => 'available',
                'purchase_price' => 1500000,
                'description' => 'Útulný byt po rekonstrukci. Vhodný jako investice — vysoká poptávka v centru Olomouce.',
            ],
            [
                'address' => 'Nádražní 17',
                'city' => 'Liberec',
                'zip_code' => '460 01',
                'disposition' => '3+kk',
                'size' => 95.0,
                'floor' => 2,
                'status' => 'renovation',
                'purchase_price' => 3800000,
                'description' => 'Velký byt v rekonstrukci. Nové podlahy, elektroinstalace, koupelna. Dokončení za 2 měsíce.',
            ],
        ];

        $properties = collect();
        foreach ($propertyData as $pd) {
            $properties->push(Property::factory()->create(array_merge($pd, [
                'landlord_id' => $landlord->id,
            ])));
        }

        $this->command->info('✓ Properties: 6 (4 occupied, 1 available, 1 renovation)');

        // ─── MANAGER → PROPERTY ASSIGNMENT ────────
        // Manager spravuje první 3 properties
        $manager->managedProperties()->attach([
            $properties[0]->id,
            $properties[1]->id,
            $properties[2]->id,
        ]);

        $this->command->info('✓ Manager assigned to 3 properties');

        // ─── PROPERTY IMAGES (placeholder SVGs) ───
        $this->createPropertyImages($properties, $landlord);
        $this->command->info('✓ Property images: 3-4 per property');

        // ─── LEASES ───────────────────────────────
        $activeLeases = collect();
        $occupiedProperties = $properties->where('status', 'occupied');

        $leaseData = [
            ['rent' => 18000, 'deposit' => 36000, 'utilities' => 3500, 'months_ago' => 8],
            ['rent' => 15000, 'deposit' => 30000, 'utilities' => 4000, 'months_ago' => 14],
            ['rent' => 10000, 'deposit' => 20000, 'utilities' => 2500, 'months_ago' => 5],
            ['rent' => 13000, 'deposit' => 26000, 'utilities' => 3000, 'months_ago' => 10],
        ];

        foreach ($occupiedProperties->values() as $index => $property) {
            $ld = $leaseData[$index];
            $lease = Lease::factory()->create([
                'property_id' => $property->id,
                'tenant_id' => $tenants[$index]->id,
                'start_date' => now()->subMonths($ld['months_ago'])->startOfMonth(),
                'end_date' => now()->addMonths(12 - $ld['months_ago'])->endOfMonth(),
                'rent_amount' => $ld['rent'],
                'deposit_amount' => $ld['deposit'],
                'utility_advances' => $ld['utilities'],
                'variable_symbol' => (string) (100000 + $property->id * 1000 + $tenants[$index]->id),
            ]);
            $activeLeases->push($lease);
        }

        $this->command->info('✓ Active leases: '.$activeLeases->count());

        // ─── ENDED LEASE WITH RATINGS ─────────────
        $endedLease = Lease::factory()->create([
            'property_id' => $properties[0]->id,
            'tenant_id' => $tenants[4]->id,
            'start_date' => now()->subMonths(20),
            'end_date' => now()->subMonths(9),
            'rent_amount' => 16000,
            'deposit_amount' => 32000,
            'utility_advances' => 3000,
            'variable_symbol' => '999999',
            'status' => 'ended',
        ]);

        $ratingData = [
            ['category' => 'apartment_condition', 'score' => 4, 'comment' => 'Byt v dobrém stavu, drobné opotřebení podlahy.'],
            ['category' => 'communication', 'score' => 5, 'comment' => 'Výborná komunikace, vždy reagoval včas.'],
            ['category' => 'rules', 'score' => 4, 'comment' => 'Dodržoval domovní řád, občas hlučnější o víkendech.'],
            ['category' => 'overall', 'score' => 4, 'comment' => 'Spolehlivý nájemník, doporučuji.'],
        ];

        foreach ($ratingData as $rd) {
            Rating::factory()->create(array_merge($rd, [
                'lease_id' => $endedLease->id,
                'rated_by' => $landlord->id,
            ]));
        }

        // Terminated lease
        Lease::factory()->create([
            'property_id' => $properties[1]->id,
            'tenant_id' => $tenants[4]->id,
            'start_date' => now()->subMonths(30),
            'end_date' => now()->subMonths(24),
            'rent_amount' => 12000,
            'status' => 'terminated',
            'variable_symbol' => '888888',
        ]);

        $this->command->info('✓ Ended lease with ratings + 1 terminated lease');

        // ─── PAYMENTS ─────────────────────────────
        foreach ($activeLeases as $leaseIndex => $lease) {
            $monthsAgo = $leaseData[$leaseIndex]['months_ago'];

            for ($i = $monthsAgo; $i >= 0; $i--) {
                $dueDate = now()->subMonths($i)->startOfMonth()->addDays(14);

                if ($i === 0) {
                    // Current month — unpaid
                    Payment::factory()->unpaid()->create([
                        'lease_id' => $lease->id,
                        'type' => 'rent',
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                    ]);
                } elseif ($i === 1 && $leaseIndex >= 2) {
                    // Last month overdue for some tenants
                    Payment::factory()->create([
                        'lease_id' => $lease->id,
                        'type' => 'rent',
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                        'paid_date' => null,
                        'status' => 'overdue',
                    ]);
                } elseif ($leaseIndex === 1 && $i === 3) {
                    // Late payment for tenant 2
                    Payment::factory()->latePayment()->create([
                        'lease_id' => $lease->id,
                        'type' => 'rent',
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                    ]);
                } else {
                    // Normal paid
                    Payment::factory()->paid()->create([
                        'lease_id' => $lease->id,
                        'type' => 'rent',
                        'amount' => $lease->rent_amount,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol,
                    ]);
                }

                // Utility payment every month
                if ($i > 0) {
                    Payment::factory()->paid()->create([
                        'lease_id' => $lease->id,
                        'type' => 'utilities',
                        'amount' => $lease->utility_advances,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol.'1',
                    ]);
                }
            }

            // Deposit payment at start
            Payment::factory()->paid()->create([
                'lease_id' => $lease->id,
                'type' => 'deposit',
                'amount' => $lease->deposit_amount,
                'due_date' => $lease->start_date,
                'paid_date' => $lease->start_date,
                'variable_symbol' => $lease->variable_symbol.'9',
                'status' => 'paid',
            ]);
        }

        $this->command->info('✓ Payments: rent + utilities + deposits (incl. overdue & late)');

        // ─── EXPENSES ─────────────────────────────
        $expenseTemplates = [
            ['type' => 'insurance', 'description' => 'Pojištění nemovitosti 2025', 'min' => 8000, 'max' => 15000],
            ['type' => 'tax', 'description' => 'Daň z nemovitosti', 'min' => 3000, 'max' => 8000],
            ['type' => 'maintenance', 'description' => 'Revize kotle', 'min' => 2000, 'max' => 5000],
            ['type' => 'repair', 'description' => 'Oprava vodovodního kohoutku', 'min' => 800, 'max' => 3000],
            ['type' => 'maintenance', 'description' => 'Údržba společných prostor', 'min' => 1500, 'max' => 4000],
            ['type' => 'repair', 'description' => 'Výměna zámku', 'min' => 1200, 'max' => 3500],
            ['type' => 'other', 'description' => 'Správní poplatek SVJ', 'min' => 500, 'max' => 2000],
        ];

        foreach ($properties as $property) {
            $count = rand(3, 6);
            $selected = collect($expenseTemplates)->random($count);

            foreach ($selected as $et) {
                Expense::factory()->create([
                    'property_id' => $property->id,
                    'type' => $et['type'],
                    'description' => $et['description'],
                    'amount' => fake()->numberBetween($et['min'], $et['max']),
                    'expense_date' => fake()->dateTimeBetween('-10 months'),
                ]);
            }
        }

        $this->command->info('✓ Expenses: 3-6 per property');

        // ─── TICKETS ──────────────────────────────
        $ticketScenarios = [
            [
                'title' => 'Teče kohoutek v kuchyni',
                'description' => 'Od včerejška kape voda z kuchyňského kohoutku i když je zavřený. Prosím o opravu co nejdříve.',
                'category' => 'plumbing',
                'priority' => 'high',
                'status' => 'resolved',
                'comments' => [
                    ['from' => 'tenant', 'message' => 'Přikládám fotku, teče čím dál víc.'],
                    ['from' => 'manager', 'message' => 'Děkuji za nahlášení. Instalatér přijde zítra dopoledne.'],
                    ['from' => 'manager', 'message' => 'Kohoutek vyměněn, problém vyřešen.'],
                ],
            ],
            [
                'title' => 'Nefunguje topení v obýváku',
                'description' => 'Radiátor v obývacím pokoji je studený i při nastavení na maximum. Ostatní radiátory fungují normálně.',
                'category' => 'heating',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'comments' => [
                    ['from' => 'tenant', 'message' => 'Teplota v pokoji klesla na 16 stupňů.'],
                    ['from' => 'manager', 'message' => 'Technik diagnostikoval vzduch v systému. Naplánováno odvzdušnění na pátek.'],
                ],
            ],
            [
                'title' => 'Prasklá těsnění u okna v ložnici',
                'description' => 'Gumové těsnění kolem okna v ložnici je popraskané a táhne studený vzduch.',
                'category' => 'structural',
                'priority' => 'medium',
                'status' => 'new',
                'comments' => [],
            ],
            [
                'title' => 'Myčka vydává podivné zvuky',
                'description' => 'Myčka při běhu vydává hlasité bručení a vibruje víc než obvykle. Myje ale normálně.',
                'category' => 'appliance',
                'priority' => 'low',
                'status' => 'resolved',
                'comments' => [
                    ['from' => 'manager', 'message' => 'Technik vyčistil filtr a čerpadlo, problém vyřešen.'],
                ],
            ],
            [
                'title' => 'Zásuvka v koupelně jiskří',
                'description' => 'Při zapojení fénu do zásuvky vedle zrcadla občas vidím jiskry. Bojím se to používat.',
                'category' => 'electrical',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'comments' => [
                    ['from' => 'manager', 'message' => 'NEPOUŽÍVEJTE tuto zásuvku! Elektrikář přijde dnes odpoledne.'],
                    ['from' => 'tenant', 'message' => 'Rozumím, nebudu používat. Díky za rychlou reakci.'],
                ],
            ],
            [
                'title' => 'WC stále protéká',
                'description' => 'Záchod neustále protéká, slyším neustálé šumění vody.',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'resolved',
                'comments' => [
                    ['from' => 'tenant', 'message' => 'Zkusil jsem pohnout s plovákem, ale nepomohlo.'],
                    ['from' => 'manager', 'message' => 'Vyměněna membrána v nádržce. Funguje správně.'],
                ],
            ],
        ];

        $ticketIndex = 0;
        foreach ($activeLeases as $lease) {
            // 1-2 tickets per lease
            $count = rand(1, 2);
            for ($t = 0; $t < $count && $ticketIndex < count($ticketScenarios); $t++) {
                $scenario = $ticketScenarios[$ticketIndex];
                $ticketIndex++;

                $resolvedAt = $scenario['status'] === 'resolved'
                    ? fake()->dateTimeBetween('-2 weeks')
                    : null;

                $ticket = Ticket::factory()->create([
                    'property_id' => $lease->property_id,
                    'tenant_id' => $lease->tenant_id,
                    'title' => $scenario['title'],
                    'description' => $scenario['description'],
                    'category' => $scenario['category'],
                    'priority' => $scenario['priority'],
                    'status' => $scenario['status'],
                    'assigned_to' => $scenario['status'] !== 'new' ? $manager->id : null,
                    'resolved_at' => $resolvedAt,
                ]);

                foreach ($scenario['comments'] as $comment) {
                    $userId = $comment['from'] === 'manager'
                        ? $manager->id
                        : $lease->tenant_id;

                    TicketComment::factory()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $userId,
                        'message' => $comment['message'],
                    ]);
                }
            }
        }

        $this->command->info('✓ Tickets: '.$ticketIndex.' with realistic comments');

        // ─── METERS & READINGS ────────────────────
        foreach ($properties as $property) {
            $meterConfigs = [
                ['type' => 'water', 'location' => 'Koupelna', 'base' => 150],
                ['type' => 'electricity', 'location' => 'Chodba - rozvaděč', 'base' => 2500],
            ];

            if (in_array($property->disposition, ['2+1', '3+1', '3+kk'])) {
                $meterConfigs[] = ['type' => 'gas', 'location' => 'Kuchyně', 'base' => 80];
            }

            if ($property->disposition === '3+1') {
                $meterConfigs[] = ['type' => 'heat', 'location' => 'Chodba - hlavní rozvod', 'base' => 5];
            }

            foreach ($meterConfigs as $mc) {
                $meter = Meter::factory()->create([
                    'property_id' => $property->id,
                    'meter_type' => $mc['type'],
                    'location' => $mc['location'],
                ]);

                $value = $mc['base'] + fake()->randomFloat(3, 0, 50);
                $tenant = $activeLeases->firstWhere('property_id', $property->id)?->tenant_id;

                for ($i = 5; $i >= 0; $i--) {
                    // Realistic increments based on meter type
                    $increment = match ($mc['type']) {
                        'water' => fake()->randomFloat(3, 1.5, 5.0),
                        'electricity' => fake()->randomFloat(3, 80, 250),
                        'gas' => fake()->randomFloat(3, 2, 12),
                        'heat' => fake()->randomFloat(3, 0.3, 1.5),
                        default => fake()->randomFloat(3, 1, 10),
                    };

                    $value += $increment;

                    MeterReading::factory()->create([
                        'meter_id' => $meter->id,
                        'reading_value' => round($value, 3),
                        'reading_date' => now()->subMonths($i)->startOfMonth(),
                        'submitted_by' => $tenant ?? $landlord->id,
                    ]);
                }
            }
        }

        $this->command->info('✓ Meters with 6 months of readings');

        // ─── INVENTORY ────────────────────────────
        $inventoryPerProperty = [
            // Praha - modern 2+kk
            [
                ['name' => 'Lednice Samsung', 'category' => 'Spotřebič', 'condition' => 'new', 'price' => 14000],
                ['name' => 'Pračka Bosch', 'category' => 'Spotřebič', 'condition' => 'good', 'price' => 12000],
                ['name' => 'Myčka Whirlpool', 'category' => 'Spotřebič', 'condition' => 'good', 'price' => 9000],
                ['name' => 'Pohovka IKEA Kivik', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 15000],
                ['name' => 'Postel 160×200', 'category' => 'Nábytek', 'condition' => 'new', 'price' => 8000],
                ['name' => 'Jídelní stůl + 4 židle', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 6000],
            ],
            // Brno - family 3+1
            [
                ['name' => 'Lednice LG', 'category' => 'Spotřebič', 'condition' => 'good', 'price' => 11000],
                ['name' => 'Pračka se sušičkou AEG', 'category' => 'Spotřebič', 'condition' => 'fair', 'price' => 18000],
                ['name' => 'Skříň šatní 3dv.', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 7000],
                ['name' => 'Rohová sedací souprava', 'category' => 'Nábytek', 'condition' => 'fair', 'price' => 20000],
                ['name' => 'Pracovní stůl', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 3500],
                ['name' => 'Regál na knihy', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 2500],
                ['name' => 'Mikrovlnná trouba', 'category' => 'Spotřebič', 'condition' => 'good', 'price' => 2000],
            ],
            // Ostrava - small 1+1
            [
                ['name' => 'Mini lednice', 'category' => 'Spotřebič', 'condition' => 'fair', 'price' => 5000],
                ['name' => 'Pračka Beko', 'category' => 'Spotřebič', 'condition' => 'good', 'price' => 7000],
                ['name' => 'Postel 140×200', 'category' => 'Nábytek', 'condition' => 'fair', 'price' => 5000],
                ['name' => 'Skříňka pod TV', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 2000],
            ],
            // Plzeň - 2+1
            [
                ['name' => 'Lednice Electrolux', 'category' => 'Spotřebič', 'condition' => 'good', 'price' => 10000],
                ['name' => 'Pračka Candy', 'category' => 'Spotřebič', 'condition' => 'poor', 'price' => 6000, 'note' => 'Hlučná při odstřeďování, plánovaná výměna'],
                ['name' => 'Rozkládací pohovka', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 12000],
                ['name' => 'Jídelní stůl dřevěný', 'category' => 'Nábytek', 'condition' => 'good', 'price' => 4500],
                ['name' => 'Vestavěná skříň', 'category' => 'Nábytek', 'condition' => 'new', 'price' => 25000],
            ],
            // Olomouc - 1+kk (available, minimal)
            [
                ['name' => 'Lednice Gorenje', 'category' => 'Spotřebič', 'condition' => 'new', 'price' => 8000],
                ['name' => 'Kuchyňská linka', 'category' => 'Nábytek', 'condition' => 'new', 'price' => 35000],
            ],
            // Liberec - 3+kk (renovation)
            [
                ['name' => 'Starý kotel', 'category' => 'Spotřebič', 'condition' => 'broken', 'price' => 30000, 'note' => 'K výměně v rámci rekonstrukce'],
                ['name' => 'Vana litinová', 'category' => 'Nábytek', 'condition' => 'poor', 'price' => 5000, 'note' => 'Bude nahrazena sprchovým koutem'],
            ],
        ];

        foreach ($properties as $index => $property) {
            if (isset($inventoryPerProperty[$index])) {
                foreach ($inventoryPerProperty[$index] as $item) {
                    InventoryItem::factory()->create([
                        'property_id' => $property->id,
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'condition' => $item['condition'],
                        'purchase_price' => $item['price'],
                        'purchase_date' => fake()->dateTimeBetween('-3 years', '-3 months'),
                        'note' => $item['note'] ?? null,
                    ]);
                }
            }
        }

        $this->command->info('✓ Inventory: realistic items per property');

        // ─── DOCUMENTS ────────────────────────────
        $docTemplates = [
            ['name' => 'Pojistná smlouva 2025.pdf', 'type' => 'insurance'],
            ['name' => 'Revize plynového kotle.pdf', 'type' => 'inspection'],
            ['name' => 'Energetický štítek.pdf', 'type' => 'energy_certificate'],
            ['name' => 'Předávací protokol.pdf', 'type' => 'protocol'],
            ['name' => 'Daňové přiznání 2024.pdf', 'type' => 'tax'],
        ];

        foreach ($properties->take(4) as $propIndex => $property) {
            $count = rand(2, 4);
            $docs = collect($docTemplates)->random($count);

            foreach ($docs as $doc) {
                // Create a simple dummy PDF file
                $path = 'documents/'.$property->id.'/'.str_replace(' ', '_', $doc['name']);
                Storage::disk('public')->put($path, '%PDF-1.4 dummy document content for '.$doc['name']);

                Document::create([
                    'property_id' => $property->id,
                    'document_type' => $doc['type'],
                    'name' => $doc['name'],
                    'file_path' => $path,
                    'uploaded_by' => $landlord->id,
                ]);
            }
        }

        $this->command->info('✓ Documents: 2-4 per occupied property');

        // ─── NOTICES ──────────────────────────────
        Notice::factory()->create([
            'property_id' => $properties[0]->id,
            'created_by' => $landlord->id,
            'title' => 'Odstávka teplé vody 20.3.',
            'content' => 'Dne 20. března proběhne plánovaná odstávka teplé vody v době od 8:00 do 14:00. Omlouváme se za komplikace.',
            'is_active' => true,
        ]);

        Notice::factory()->create([
            'property_id' => $properties[0]->id,
            'created_by' => $landlord->id,
            'title' => 'Nový systém třídění odpadu',
            'content' => 'Od příštího měsíce budou ve dvoře nové kontejnery na tříděný odpad. Plastové a papírové kontejnery budou vedle hlavního vchodu.',
            'is_active' => true,
        ]);

        Notice::factory()->create([
            'property_id' => $properties[1]->id,
            'created_by' => $landlord->id,
            'title' => 'Kontrola požárních hlásičů',
            'content' => 'V pátek 28. března proběhne pravidelná kontrola požárních hlásičů. Prosíme o zpřístupnění bytů.',
            'is_active' => true,
        ]);

        Notice::factory()->create([
            'property_id' => $properties[2]->id,
            'created_by' => $landlord->id,
            'title' => 'Oprava výtahu dokončena',
            'content' => 'Výtah byl opraven a je opět v provozu. Děkujeme za trpělivost.',
            'is_active' => false,
        ]);

        $this->command->info('✓ Notices: 4 (3 active, 1 inactive)');

        // ─── TRUST SCORE RECALC ───────────────────
        foreach ($tenants as $tenant) {
            $tenant->recalculateTrustScore();
        }

        $this->command->info('✓ Trust scores recalculated');

        // ─── SUMMARY ──────────────────────────────
        $this->command->newLine();
        $this->command->info('════════════════════════════════════════');
        $this->command->info('   SEED COMPLETE — RentFlow');
        $this->command->info('════════════════════════════════════════');
        $this->command->newLine();
        $this->command->info('Login credentials (password for all: "password"):');
        $this->command->table(
            ['Role', 'Name', 'Email'],
            [
                ['🏠 Landlord', $landlord->name, $landlord->email],
                ['🔧 Manager', $manager->name, $manager->email],
                ...($tenants->map(fn ($t) => ['👤 Tenant', $t->name, $t->email])->toArray()),
            ]
        );
        $this->command->newLine();
        $this->command->info('Data overview:');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Properties', Property::count()],
                ['Active Leases', Lease::where('status', 'active')->count()],
                ['Payments', Payment::count()],
                ['Tickets', Ticket::count()],
                ['Documents', Document::count()],
                ['Notices', Notice::count()],
                ['Meters', Meter::count()],
            ]
        );
    }

    /**
     * Create placeholder SVG images for properties
     */
    private function createPropertyImages($properties, $landlord): void
    {
        $rooms = [
            ['name' => 'Obývací pokoj', 'color' => '#4A90D9', 'icon' => 'Living Room'],
            ['name' => 'Kuchyně', 'color' => '#E67E22', 'icon' => 'Kitchen'],
            ['name' => 'Koupelna', 'color' => '#1ABC9C', 'icon' => 'Bathroom'],
            ['name' => 'Ložnice', 'color' => '#9B59B6', 'icon' => 'Bedroom'],
        ];

        foreach ($properties as $property) {
            $count = $property->status === 'renovation' ? 2 : (rand(3, 4));

            for ($i = 0; $i < $count; $i++) {
                $room = $rooms[$i % count($rooms)];

                // Generate a simple SVG placeholder
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">'
                    .'<rect width="800" height="600" fill="'.$room['color'].'"/>'
                    .'<rect x="50" y="50" width="700" height="500" rx="20" fill="white" opacity="0.15"/>'
                    .'<text x="400" y="280" text-anchor="middle" font-family="Arial" font-size="48" fill="white" font-weight="bold">'.$room['icon'].'</text>'
                    .'<text x="400" y="340" text-anchor="middle" font-family="Arial" font-size="24" fill="white" opacity="0.8">'.$property->address.'</text>'
                    .'</svg>';

                $filename = 'property_'.$property->id.'_'.($i + 1).'.svg';
                $path = 'properties/'.$property->id.'/'.$filename;

                Storage::disk('public')->put($path, $svg);

                PropertyImage::factory()->create([
                    'property_id' => $property->id,
                    'uploaded_by' => $landlord->id,
                    'image_path' => $path,
                    'type' => $i === 0 ? 'marketing' : ($property->status === 'renovation' ? 'defect' : 'marketing'),
                    'description' => $room['name'],
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
