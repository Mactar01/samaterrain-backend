# myTerrain — Backend API ⚽ (Laravel 12)

API RESTful de la plateforme **myTerrain**, développée avec **Laravel 12 (PHP 8.2+)**, authentifiée avec **Laravel Sanctum**, intégrant les paiements mobiles (Wave, Orange Money) et les notifications temps réel.

---

## 🌍 Environnements

| Environnement | Cible & Hébergement | Fichier | URL API | Simulation Paiement |
| :--- | :--- | :--- | :--- | :--- |
| **develop** | Machine locale Dev | `.env.develop` | `http://localhost:8000` | `true` |
| **preprod** | **DigitalOcean** (Droplet / App Platform) | `.env.preprod` | `https://staging-api.myterrain.sn` | `true` (Sandbox) |
| **prod** | **Serveur Dédié Réel** | `.env.prod` | `https://api.myterrain.sn` | `false` (Direct Wave/OM) |

---

## 🚀 Démarrage Rapide (Local - develop)

```bash
# 1. Cloner le projet et installer les dépendances
composer install

# 2. Configurer l'environnement local
cp .env.develop .env

# 3. Générer la clé applicative
php artisan key:generate

# 4. Lancer les migrations et les données initiales
php artisan migrate --seed

# 5. Démarrer le serveur API et le worker de queue
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work
```

---

## 🐳 Déploiement Docker (DigitalOcean)

Un `Dockerfile` est fourni à la racine. Pour déployer sur un conteneur :
```bash
docker build -t myterrain-backend .
docker run -p 8000:80 --env-file .env.preprod myterrain-backend
```

---

## 💳 Passerelle de Paiement
Les services de paiement se situent dans `app/Services/Payments/` (Pattern Strategy) :
- `WaveService.php` : Intégration Wave Business
- `OrangeMoneyService.php` : Intégration Orange Developer
- Webhooks :
  - `POST /api/v1/webhooks/wave`
  - `POST /api/v1/webhooks/orangemoney`

---

## 🔄 Git Workflow
- `develop` : Développement quotidien
- `preprod` : Déploiement automatique sur DigitalOcean via Pull Request
- `main` : Production réelle via Pull Request
