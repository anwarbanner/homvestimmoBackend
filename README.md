# Homvest — Backend

API et back-office (admin) pour Homvest, une plateforme de gestion et publication de biens immobiliers (vente et location) à Marrakech. Le backend expose une API publique en lecture pour un frontend séparé, et un panel d'administration Filament pour gérer les biens, leurs images, et leur publication automatique sur Facebook et Instagram.

Pour une analyse technique détaillée (architecture, pipeline de publication social media, points d'attention et pistes d'amélioration), voir **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

## Stack technique

- **Framework** : Laravel 13 (PHP 8.3+)
- **Admin** : Filament 3
- **Base de données** : PostgreSQL
- **Queue / Cache** : Redis
- **Stockage fichiers** : S3-compatible (MinIO en local)
- **Permissions** : spatie/laravel-permission (installé, non branché — voir ARCHITECTURE.md)
- **Conteneurisation** : Docker Compose (`app`, `worker`, `nginx`, `db`, `redis`, `minio`)

## Démarrage rapide (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

L'application est servie sur `http://localhost:8000`. `/` redirige vers `/admin`.

Pour créer un compte admin :

```bash
docker compose exec app php artisan tinker --execute="
App\Models\User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Illuminate\Support\Facades\Hash::make('password')]);
"
```

N'importe quel utilisateur créé peut accéder au panel `/admin` (voir ARCHITECTURE.md — pas de restriction par rôle actuellement).

## Variables d'environnement clés

| Variable | Rôle |
|---|---|
| `AWS_*` | Connexion au disque S3/MinIO (stockage des images) |
| `FACEBOOK_PAGE_ID` / `FACEBOOK_PAGE_TOKEN` | Publication automatique sur la Page Facebook |
| `INSTAGRAM_BUSINESS_ACCOUNT_ID` / `INSTAGRAM_ACCESS_TOKEN` | Publication automatique sur Instagram (déclenchée après succès Facebook) |
| `REDIS_QUEUE_RETRY_AFTER` | Timeout de visibilité de la queue Redis — doit rester **supérieur** au `$timeout` des jobs de publication (180s) pour éviter les doublons |

Voir `.env.example` pour la liste complète.

## Fonctionnalités principales

- **Biens immobiliers** (`app/Filament/Resources/PropertyResource.php`) : CRUD complet, référence auto-générée, date de publication auto-définie, champ "durée de location" (courte/longue) conditionnel, upload d'images multiples.
- **Publication automatique** : à la publication d'un bien (statut → "Publié"), une publication Facebook est déclenchée automatiquement ; une fois celle-ci réussie, Instagram est publié à son tour avec la même légende/images. Peut être désactivé au cas par cas via une case à cocher en création.
- **Suppression en cascade** : supprimer un bien supprime aussi ses images sur le stockage S3/MinIO et déclenche la suppression du post Facebook associé.
- **Suivi des publications** (`SocialPostResource`) : historique par plateforme, republier un post en échec, supprimer un post Facebook, vérifier/resynchroniser le statut réel via le bouton "Rafraîchir".
- **API publique** (`routes/api.php`, `PropertyController`) : liste et détail des biens publiés, consommée par un frontend séparé (`FRONTEND_URL`).

## Tests

```bash
docker compose exec app composer test
```

⚠️ **Ne jamais lancer `php artisan test` directement** (ni via `docker compose exec app php artisan test`). Ce projet injecte les vraies variables d'environnement (base de données, tokens Facebook/Instagram) dans le conteneur via `env_file`, et seul le script `composer test` les neutralise avant de lancer la suite. Cette règle a déjà causé deux incidents réels (perte de la base de données de dev, puis publication de faux posts sur la vraie Page Facebook) avant d'être corrigée — voir ARCHITECTURE.md.
