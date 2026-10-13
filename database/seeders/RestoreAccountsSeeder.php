<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Owner;
use Illuminate\Support\Facades\Hash;

/**
 * Crée / restaure les comptes Admin et Gérant.
 * Les mots de passe viennent du .env du serveur (ADMIN_PASSWORD, OWNER_PASSWORD),
 * jamais du code : ce fichier est dans le dépôt Git.
 *
 * Lancer : php artisan db:seed --class=RestoreAccountsSeeder
 */
class RestoreAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');
        $ownerPassword = env('OWNER_PASSWORD');

        if (empty($adminPassword) || empty($ownerPassword)) {
            $this->command->error('Définissez ADMIN_PASSWORD et OWNER_PASSWORD dans le .env avant de lancer ce seeder.');
            return;
        }

        // Admin
        User::updateOrCreate(
            ['email' => 'admin@myterrain.sn'],
            [
                'name'              => 'Admin myTerrain',
                'phone'             => '+221700000099',
                'password'          => Hash::make($adminPassword),
                'role'              => 'admin',
                'email_verified_at' => now(),
                'is_active'         => true,
            ]
        );
        $this->command->info('✅ Admin : admin@myterrain.sn (mot de passe = ADMIN_PASSWORD du .env)');

        // Gérant
        $ownerUser = User::updateOrCreate(
            ['email' => 'gerant@myterrain.sn'],
            [
                'name'              => 'Mactar Seck',
                'phone'             => '+221700000098',
                'password'          => Hash::make($ownerPassword),
                'role'              => 'owner',
                'email_verified_at' => now(),
                'is_active'         => true,
            ]
        );

        Owner::updateOrCreate(
            ['user_id' => $ownerUser->id],
            [
                'business_name'   => 'myTerrain',
                'address'         => 'Dakar, Sénégal',
                'siret'           => 'SN-DKR-2024-009',
                'verified_at'     => now(),
                'commission_rate' => 10.00,
            ]
        );
        $this->command->info('✅ Gérant : gerant@myterrain.sn (mot de passe = OWNER_PASSWORD du .env)');
    }
}
