<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Owner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Administrateur ─────────────────────────────────────────
        User::create([
            'name'              => 'Admin myTerrain',
            'email'             => 'admin@myterrain.com',
            'phone'             => '+221770000000',
            'password'          => Hash::make('Admin@1234'),
            'role'              => 'admin',
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        // ── 2. Loueur de test ─────────────────────────────────────────
        $ownerUser = User::create([
            'name'              => 'Moussa Diop',
            'email'             => 'loueur@myterrain.com',
            'phone'             => '+221771111111',
            'password'          => Hash::make('Loueur@1234'),
            'role'              => 'owner',
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        Owner::create([
            'user_id'         => $ownerUser->id,
            'business_name'   => 'Dakar Sport SARL',
            'address'         => '45 Rue des Terrains, Medina, Dakar',
            'siret'           => 'SN-DKR-2024-001',
            'verified_at'     => now(),
            'commission_rate' => 10.00,
        ]);

        // ── 3. Joueurs de test ────────────────────────────────────────
        $players = [
            ['name' => 'Mamadou Sall',     'email' => 'mamadou@test.com',   'phone' => '+221772222222'],
            ['name' => 'Ibrahima Ndiaye',  'email' => 'ibrahima@test.com',  'phone' => '+221773333333'],
            ['name' => 'Fatou Diouf',      'email' => 'fatou@test.com',     'phone' => '+221774444444'],
        ];

        foreach ($players as $player) {
            User::create([
                'name'              => $player['name'],
                'email'             => $player['email'],
                'phone'             => $player['phone'],
                'password'          => Hash::make('Player@1234'),
                'role'              => 'player',
                'email_verified_at' => now(),
                'is_active'         => true,
            ]);
        }

        $this->command->info('✅ Utilisateurs de test créés avec succès !');
        $this->command->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['Admin',  'admin@myterrain.com',   'Admin@1234'],
                ['Loueur', 'loueur@myterrain.com',  'Loueur@1234'],
                ['Joueur', 'mamadou@test.com',       'Player@1234'],
            ]
        );
    }
}
