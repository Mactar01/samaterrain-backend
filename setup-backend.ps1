#!/usr/bin/env pwsh
# setup-backend.ps1
# Script de configuration automatique du backend myTerrain
# Exécuter depuis le dossier myTerrain/backend/

Write-Host "`n🚀 Configuration du backend myTerrain..." -ForegroundColor Cyan

# ── 1. Copier le .env ──────────────────────────────────────────────
if (-not (Test-Path ".env")) {
    Copy-Item ".env.develop" ".env"
    Write-Host "✅ .env créé depuis .env.develop" -ForegroundColor Green
} else {
    Write-Host "ℹ️  .env existe déjà" -ForegroundColor Yellow
}

# ── 2. Appliquer notre configuration myTerrain ─────────────────────
$envContent = Get-Content ".env" -Raw

# Base de données MySQL
$envContent = $envContent -replace 'DB_CONNECTION=sqlite', 'DB_CONNECTION=mysql'
$envContent = $envContent -replace '#DB_HOST=127\.0\.0\.1', 'DB_HOST=127.0.0.1'
$envContent = $envContent -replace '#DB_PORT=3306', 'DB_PORT=3306'
$envContent = $envContent -replace '#DB_DATABASE=laravel', 'DB_DATABASE=myterrain'
$envContent = $envContent -replace '#DB_USERNAME=root', 'DB_USERNAME=root'
$envContent = $envContent -replace '#DB_PASSWORD=', 'DB_PASSWORD='

# Timezone
$envContent = $envContent -replace 'APP_TIMEZONE=UTC', 'APP_TIMEZONE=Africa/Dakar'

# Langue
$envContent = $envContent -replace 'APP_LOCALE=en', 'APP_LOCALE=fr'

Set-Content ".env" $envContent
Write-Host "✅ .env configuré pour MySQL" -ForegroundColor Green

# ── 3. Générer la clé d'application ───────────────────────────────
php artisan key:generate
Write-Host "✅ Clé d'application générée" -ForegroundColor Green

# ── 4. Installer Sanctum ───────────────────────────────────────────
Write-Host "`n📦 Installation de Laravel Sanctum..." -ForegroundColor Cyan
composer require laravel/sanctum
Write-Host "✅ Sanctum installé" -ForegroundColor Green

# ── 5. Créer la base de données MySQL ─────────────────────────────
Write-Host "`n🗄️  Création de la base de données MySQL..." -ForegroundColor Cyan
& "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS myterrain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>$null

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Base de données 'myterrain' créée" -ForegroundColor Green
} else {
    Write-Host "⚠️  Impossible de créer la BDD automatiquement. Créez-la manuellement dans MySQL Workbench :" -ForegroundColor Yellow
    Write-Host "   CREATE DATABASE myterrain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -ForegroundColor White
}

# ── 6. Supprimer les migrations Laravel par défaut ────────────────
Write-Host "`n🗂️  Suppression des migrations par défaut de Laravel..." -ForegroundColor Cyan
$defaultMigrations = @(
    "database\migrations\0001_01_01_000000_create_users_table.php",
    "database\migrations\0001_01_01_000001_create_cache_table.php",
    "database\migrations\0001_01_01_000002_create_jobs_table.php"
)
foreach ($file in $defaultMigrations) {
    if (Test-Path $file) {
        Remove-Item $file
        Write-Host "  🗑️  Supprimé : $file" -ForegroundColor Gray
    }
}

# ── 7. Lancer les migrations ───────────────────────────────────────
Write-Host "`n⚡ Lancement des migrations..." -ForegroundColor Cyan
php artisan migrate

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Migrations exécutées avec succès" -ForegroundColor Green
} else {
    Write-Host "❌ Erreur lors des migrations. Vérifiez la connexion MySQL." -ForegroundColor Red
    exit 1
}

# ── 8. Publier la config Sanctum ──────────────────────────────────
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag="sanctum-migrations" 2>$null
php artisan migrate 2>$null

# ── 9. Seeder ─────────────────────────────────────────────────────
Write-Host "`n🌱 Insertion des données de test..." -ForegroundColor Cyan
php artisan db:seed

Write-Host "`n✅ Backend myTerrain configuré et prêt !" -ForegroundColor Green
Write-Host "`n🚀 Démarrage du serveur de développement..." -ForegroundColor Cyan
Write-Host "   URL : http://localhost:8000/api/v1" -ForegroundColor White
Write-Host "`n📋 Comptes de test :" -ForegroundColor Cyan
Write-Host "   Admin  : admin@myterrain.com  / Admin@1234" -ForegroundColor White
Write-Host "   Loueur : loueur@myterrain.com / Loueur@1234" -ForegroundColor White
Write-Host "   Joueur : mamadou@test.com     / Player@1234" -ForegroundColor White
Write-Host ""

php artisan serve
