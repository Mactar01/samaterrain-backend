<?php
// Script temporaire pour créer les comptes Admin et Gérant
// Lancer avec : php artisan tinker --execute="require base_path('create_accounts.php');"
// Les mots de passe viennent du .env (ADMIN_PASSWORD, OWNER_PASSWORD), jamais du code.
// (Équivalent : php artisan db:seed --class=RestoreAccountsSeeder)

use App\Models\User;
use App\Models\Owner;
use Illuminate\Support\Facades\Hash;

$adminPassword = env('ADMIN_PASSWORD');
$ownerPassword = env('OWNER_PASSWORD');

if (empty($adminPassword) || empty($ownerPassword)) {
    echo "❌ Définissez ADMIN_PASSWORD et OWNER_PASSWORD dans le .env avant de lancer ce script.\n";
    return;
}

// Créer le compte Admin
$admin = User::firstOrCreate(
    ['email' => 'admin@myterrain.sn'],
    [
        'name'              => 'Admin myTerrain',
        'phone'             => '+221700000001',
        'password'          => Hash::make($adminPassword),
        'role'              => 'admin',
        'email_verified_at' => now(),
        'is_active'         => true,
    ]
);
echo "✅ Admin créé: admin@myterrain.sn (mot de passe = ADMIN_PASSWORD du .env)\n";

// Créer le compte Gérant
$ownerUser = User::firstOrCreate(
    ['email' => 'gerant@myterrain.sn'],
    [
        'name'              => 'Mactar Seck',
        'phone'             => '+221700000002',
        'password'          => Hash::make($ownerPassword),
        'role'              => 'owner',
        'email_verified_at' => now(),
        'is_active'         => true,
    ]
);

Owner::firstOrCreate(
    ['user_id' => $ownerUser->id],
    [
        'business_name'   => 'myTerrain',
        'address'         => 'Dakar, Sénégal',
        'siret'           => 'SN-DKR-2024-002',
        'verified_at'     => now(),
        'commission_rate' => 10.00,
    ]
);
echo "✅ Gérant créé: gerant@myterrain.sn (mot de passe = OWNER_PASSWORD du .env)\n";
