<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Owner;
use App\Models\Field;
use App\Models\TimeSlot;
use App\Models\Reservation;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get or create Owner
        $ownerUser = User::where('email', 'loueur@myterrain.com')->first();
        if (!$ownerUser) {
            $this->command->error("Veuillez d'abord lancer UserSeeder.");
            return;
        }
        $owner = Owner::where('user_id', $ownerUser->id)->first();

        // 2. Create some fields
        $fieldsData = [
            [
                'name' => 'Terrain Synthétique Premium',
                'address' => 'Sacré Coeur 3',
                'city' => 'Dakar',
                'latitude' => 14.7171,
                'longitude' => -17.4578,
                'type' => 'artificial_grass',
                'price_per_hour' => 15000,
                'is_active' => true,
                'description' => 'Superbe terrain synthétique de dernière génération. Éclairage LED inclus pour les matchs en soirée.'
            ],
            [
                'name' => 'Futsal Arena',
                'address' => 'Point E',
                'city' => 'Dakar',
                'latitude' => 14.6937,
                'longitude' => -17.4627,
                'type' => 'futsal',
                'price_per_hour' => 20000,
                'is_active' => true,
                'description' => 'Terrain de Futsal couvert de qualité professionnelle.'
            ],
            [
                'name' => 'Terrain de Proximité',
                'address' => 'Almadies',
                'city' => 'Dakar',
                'latitude' => 14.7431,
                'longitude' => -17.5181,
                'type' => 'concrete',
                'price_per_hour' => 10000,
                'is_active' => true,
                'description' => 'Terrain en béton bien entretenu, idéal pour le street-football.'
            ]
        ];

        $fields = [];
        foreach ($fieldsData as $data) {
            $fields[] = $owner->fields()->create($data);
        }
        $this->command->info("✅ Terrains créés.");

        // 3. Create Slots for the current week for the first field
        $mainField = $fields[0];
        $startDate = Carbon::today()->startOfWeek(); // Monday
        $endDate = Carbon::today()->endOfWeek(); // Sunday

        $createdSlots = 0;
        for ($date = clone $startDate; $date->lte($endDate); $date->addDay()) {
            for ($hour = 16; $hour < 23; $hour++) {
                // Determine slot status randomly
                $rand = rand(1, 10);
                $status = 'available';
                $promoLabel = null;
                $promoPrice = null;

                if ($rand <= 3) {
                    $status = 'reserved';
                } elseif ($rand == 4) {
                    $status = 'blocked';
                } elseif ($rand == 5) {
                    $promoLabel = 'Heure Creuse -20%';
                    $promoPrice = 12000;
                }

                $slot = $mainField->timeSlots()->create([
                    'date' => $date->format('Y-m-d'),
                    'start_time' => sprintf('%02d:00:00', $hour),
                    'end_time' => sprintf('%02d:00:00', $hour + 1),
                    'price' => $mainField->price_per_hour,
                    'status' => $status,
                    'promo_label' => $promoLabel,
                    'promo_price' => $promoPrice,
                ]);

                // Create a reservation if reserved
                if ($status === 'reserved') {
                    $slot->reservation()->create([
                        'field_id' => $mainField->id,
                        'user_id' => 3, // Assuming Mamadou is ID 3
                        'total_price' => $mainField->price_per_hour,
                        'status' => 'confirmed',
                    ]);
                }

                $createdSlots++;
            }
        }
        
        // Populate dashboard stats (create some past reservations for revenues)
        for ($i=1; $i<=15; $i++) {
            // Pick a date from the previous month to avoid any collision
            $pastDate = Carbon::now()->subDays(rand(20, 45))->format('Y-m-d');
            $h = rand(10, 20);
            
            // Try to create, ignore if exception
            try {
                $pastSlot = $mainField->timeSlots()->create([
                    'date' => $pastDate,
                    'start_time' => sprintf('%02d:00:00', $h),
                    'end_time' => sprintf('%02d:00:00', $h + 1),
                    'price' => $mainField->price_per_hour,
                    'status' => 'reserved',
                ]);

                $pastSlot->reservation()->create([
                    'field_id' => $mainField->id,
                    'user_id' => 3,
                    'total_price' => 15000,
                    'status' => 'completed',
                    'created_at' => Carbon::parse($pastDate)->subDays(1)
                ]);
            } catch (\Exception $e) {
                // Ignore duplicates
            }
        }

        $this->command->info("✅ $createdSlots créneaux générés avec quelques réservations, promos et blocages.");
        $this->command->info("✅ Données de présentation prêtes !");
    }
}

