# Journal de session — HÔMVEST (frontend + backend)

Résumé de tout ce qui a été fait avec Claude Code sur le projet HÔMVEST (landing page React/Vite + backend Laravel/Filament). Date : 2026-09-01 → 2026-09-02.

Projets concernés :
- Frontend : `Homvest landing page (comming soon ) (1)` — React + Vite + TypeScript + react-router-dom
- Backend : `homvest-backend` — Laravel 13 + Filament 3 (admin) + PostgreSQL + MinIO (S3) + Redis, dockerisé

---

## 1. Frontend — Landing page

### Correctif CORS initial
Le backend (`config/cors.php`) n'autorisait que `localhost:8080` / `5173`, alors que le frontend tournait sur un autre port (Vite avait choisi 5174). Origines ajoutées : `5174`, puis plus tard `http://localhost` / `:80` (conteneur Docker du frontend).

### Biens immobiliers
- Remplacement du carrousel unique "Nos biens" par **deux sections distinctes** : *Biens immobiliers à la vente* et *à la location*, chacune avec un bouton **"Voir plus d'annonces"**.
- Backend : `PropertyController@index` étendu avec filtre `transactionType` + pagination (`page`, `per_page`), réponse avec `meta` (currentPage/lastPage/perPage/total).
- Nouvelles pages `/a-vendre` et `/a-louer` (`PropertyListing.tsx`) avec grille complète + pagination.
- **Cartes cliquables** → page de détail `/biens/:id` (`PropertyDetail.tsx`) : galerie avec vignettes, infos complètes, description, section **"Autres biens similaires"** (même type de transaction, bien courant exclu).
- Backend : ajout de `PropertyController@show` (`GET /api/properties/{id}`, avec description/latitude/longitude).

### Devise EUR → DH
- Frontend : `fmtPrice()` affiche désormais `1 250 000 DH` (plus de symbole €).
- Backend admin (Filament `PropertyResource`) : champ prix et colonne liste passés de `€` à `DH`.

### Contact → WhatsApp / E-mail
- Ajout de `whatsappUrl()` / `mailUrl()` dans `shared.tsx` (numéro `+212 (0)7 00 02 97 16`, email `contact@homvestimmo.com`).
- Navbar : bouton "Nous contacter" devenu un menu déroulant (WhatsApp / E-mail), desktop + mobile.
- Page détail bien : boutons WhatsApp/E-mail avec message pré-rempli citant le bien consulté.
- Section contact de l'accueil : téléphone et email rendus cliquables.

### Navbar (refonte complète, `src/Navbar.tsx`)
- Composant unique réutilisé sur les 3 pages (accueil, listings, détail) pour la cohérence.
- Logo cliquable vers l'accueil, agrandi par itérations successives (taille finale : 110px mobile / 132px desktop), header en `position: fixed` avec spacer pour compenser la hauteur.
- Liens "Biens à la vente" / "Biens à la location" en style pill contourée (cohérent avec le bouton "Nous contacter" plein), état actif visible.
- Menu hamburger mobile (bug corrigé : un style inline `display:flex` écrasait la classe Tailwind `md:hidden` — toujours visible en desktop ; remplacé par classes Tailwind `flex md:hidden`).

### Favicon
- Monogramme "HV" extrait et recadré depuis le logo (via Pillow), génération de `favicon.ico` + PNG multi-tailles + `apple-touch-icon.png`.

### Docker
- Build de l'image frontend (`homvest-frontend:latest`, ~95 Mo) via le `Dockerfile` existant.
- Ajout d'un `.dockerignore` (node_modules/dist/.git exclus) → build de contexte réduit de ~45 s à ~6 s.
- Conteneur lancé via `docker compose up -d`, servi sur `http://localhost` (port 80).

---

## 2. Backend — Nettoyage et fonctionnalités

### Nettoyage ("je veux seulement la gestion des biens")
Suppression des fonctionnalités scaffoldées mais jamais branchées à rien (0 ligne en base, aucune route/job/config associée) :
- Modèles + migrations + Filament Resources : `Customer`, `Service`, `SocialPost`, `Document`, `Feature`.
- Rollback propre des 4 tables correspondantes (`customers`, `services`, `documents`, `social_posts`) — vides, aucune perte de données.
- Relations orphelines retirées de `Property.php` (`services()`, `socialPosts()`, `documents()`).
- Widget "Filament Info" (bandeau générique "powered by Filament") retiré du dashboard.

### Dashboard analytique (`app/Filament/Widgets/`)
- `PropertyStatsOverview` : total des biens, biens publiés (%), valeur du portefeuille (somme des prix publiés), prix moyen — en DH.
- `PropertyTransactionChart` : doughnut Vente vs Location.
- `PropertyStatusChart` : doughnut par statut (brouillon/publié/archivé).
- `PropertyByCityChart` : barres, top 8 villes.
- Responsive nativement (grille Filament standard).

### Sécurité git (avant toute suppression)
Le dossier backend n'était pas un dépôt git → `git init` + commit de l'état initial comme point de sauvegarde avant les suppressions. Historique complet :
```
682353b Checkpoint before removing non-property features
10bc4df Remove non-property features + add property analytics dashboard widgets
baccb4c Fix pre-existing test failures (APP_ENV, FilamentUser::canAccessPanel, route /)
43f5966 Fix critical test isolation bug (env stripping pour composer test)
```

### Correction des tests pré-existants (4 tests en échec avant intervention)
- **Cause n°1** : `docker-compose.yml` charge `.env` comme variables d'environnement réelles du conteneur (`APP_ENV=local`), ce qui empêchait `phpunit.xml` de forcer `APP_ENV=testing` — Filament désactivait son mode test, les formulaires (login, création de bien) restaient vides à la soumission.
- **Cause n°2** : sans `FilamentUser::canAccessPanel()` implémenté, Filament bloque l'accès admin (403) sauf en environnement `local` — garde-fou volontaire. Corrigé en implémentant `canAccessPanel()` sur `User` (autorise tout utilisateur authentifié).
- **Cause n°3** : route `/` inexistante (404) → ajout d'une redirection `/` → `/admin`.
- Script `composer test` corrigé : `env -u APP_ENV -u DB_CONNECTION -u DB_DATABASE ... php artisan test` pour neutraliser les variables polluées par `env_file`.

### ⚠️ Incident critique découvert et corrigé
**Ce qui s'est passé** : avant la correction ci-dessus, chaque lancement de `composer test` / `php artisan test` faisait en réalité tourner `RefreshDatabase` contre la **vraie base PostgreSQL** (au lieu d'une base SQLite isolée), à cause du même conflit d'environnement. Résultat : plusieurs runs de tests ont **vidé la vraie base** (table `properties` et `users`) pendant le débogage.

**Impact** : perte des biens de test initiaux et d'un compte admin créé en cours de session — aucune donnée client réelle, uniquement des données de démo/test.

**Correctif vérifié** : après la correction du script `composer test`, plusieurs runs successifs confirment que la vraie base reste désormais intacte (testé explicitement : compte + biens présents avant ET après un `composer test` complet).

**Compte admin actuel** : `admin@homvestimmo.com` / `Homvest2026!` (⚠️ à changer après connexion) — accès via `http://localhost:8000/admin`.

### Données de démonstration
20 biens créés (10 vente / 10 location) répartis sur 7 villes marocaines, avec 2 photos réelles chacun (Unsplash, licence libre, hébergées sur MinIO). Total en base : 24 biens (12 vente / 12 location). Générés via une commande Artisan temporaire (`SeedDemoProperties`), supprimée après exécution — seules les données restent.

### Accès PostgreSQL
Le port n'est pas exposé sur l'hôte. CLI : `docker exec -it homvest-db psql -U homvest_user -d homvest_db`.

---

## 3. En attente (prochaine étape demandée, pas encore commencée)

**Automatisation réseaux sociaux** : publication automatique sur Facebook + Instagram à la création/publication d'un bien. Plan détaillé fourni (étapes côté Meta à faire par l'utilisateur : compte Instagram Business lié à la Page, création d'App Meta, tokens ; étapes côté code : table `SocialPost` recréée proprement, service de publication, job en file d'attente). **Non exécuté** — en attente du signal de l'utilisateur et des identifiants Meta.

---

*Ce fichier a été généré à la demande de l'utilisateur pour conserver une trace lisible de la session, en complément de l'historique git et de la session Claude Code elle-même (reprenable via `claude --continue` / `--resume`).*
