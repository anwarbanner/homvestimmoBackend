# Architecture — Homvest Backend

Document de référence technique : modèle de données, flux de publication social media, structure de l'admin, et points d'attention identifiés lors de l'audit du 2026-09-23.

## 1. Modèle de données

```
Property (biens)
├── reference        auto-générée (max existant + 1), unique, verrouillée dans le formulaire
├── slug             auto-généré depuis le titre
├── type             apartment | villa | house | office | land | commercial | other
├── transaction_type sale | rent
├── rental_term      short_term | long_term — visible/requis uniquement si transaction_type = rent
├── status           draft | published | archived
├── published_at     auto-définie à la première transition vers "published", jamais réécrite ensuite
└── images (hasMany PropertyImage)

PropertyImage
├── path             chemin sur le disque S3/MinIO
├── sort_order
└── is_main

SocialPost (historique de publication par plateforme)
├── platform         facebook | instagram
├── status           queued | published | failed | deleted
├── caption
├── platform_post_id
└── error_message

User
└── HasRoles (spatie/laravel-permission) — installé mais non utilisé, voir §5
```

## 2. Cycle de vie d'un bien

1. **Création** (`CreateProperty`) : le formulaire soumet `publish_to_social` (case à cocher, non persistée) et `new_images` (upload, non persisté). `CreateProperty::handleRecordCreation()` extrait `publish_to_social` avant la sauvegarde et positionne `Property::$skipSocialPublish` (propriété PHP transitoire, pas une colonne) — c'est nécessaire car `PropertyObserver::created()` se déclenche pendant `save()`, donc **avant** `afterCreate()` où les images sont normalement rattachées.
2. **Rattachement des images** : `CreateProperty::afterCreate()` / `EditProperty::afterSave()` créent les lignes `PropertyImage` **après** la création/mise à jour du bien — c'est pourquoi les jobs de publication ne doivent jamais vérifier la présence d'images de façon synchrone dans l'observer (ils le font au moment de l'exécution réelle, une fois la requête HTTP terminée).
3. **Publication automatique** (`PropertyObserver`) : si `status === 'published'` (à la création ou lors d'un changement de statut) et qu'aucun post Facebook actif n'existe déjà pour ce bien, un `SocialPost` (`platform: facebook`) est créé et `PublishPropertyToFacebook` est mis en queue.
4. **Chaînage vers Instagram** : `PublishPropertyToFacebook::handle()` ne déclenche `PublishPropertyToInstagram` **qu'après** succès réel de la publication Facebook, en réutilisant la même légende. En cas d'échec Facebook, Instagram n'est jamais tenté. Aucune queue séparée n'est déclenchée par l'observer pour Instagram — le seul point d'entrée est ce chaînage.
5. **Suppression** (`PropertyObserver::deleting()`, avant la cascade DB) : les fichiers image sont supprimés du disque S3/MinIO, et un job `DeleteSocialPost` est déclenché pour chaque post actif (`platform_post_id` non nul). Ce hook s'exécute aussi bien en suppression unitaire qu'en suppression groupée (`DeleteBulkAction` de Filament appelle `$record->delete()` par enregistrement, donc les événements Eloquent se déclenchent normalement).

## 3. Pipeline de publication social media

### Facebook (`FacebookPublisher`)

Le token configuré (`FACEBOOK_PAGE_TOKEN`) est un token **System User** (Business Manager), pas un token de Page classique. Confirmé par test direct : ce token fonctionne pour publier via `/{page-id}/photos` + `/{page-id}/feed`, **mais est rejeté** par l'API pour :
- l'upload de photos "non publiées" (flux multi-photos) : `"Unpublished posts must be posted to a page as the page itself"`
- la publication directe d'une seule photo avec `published=true` : `"(#200) The permission(s) publish_actions are not available"`
- la suppression d'un post : `"Page token is required to delete posts on page"`

**Solution en place** : `FacebookPublisher::pageAccessToken()` échange une fois le token System User contre un vrai Page Access Token via `GET /{page-id}?fields=access_token`, et le met en cache pour l'instance. Toutes les opérations passent par `request()`, qui utilise ce token résolu. **Ne jamais revenir à l'utilisation directe de `pageToken`** pour un appel Graph API scopé à la Page.

`PublishPropertyToFacebook` a un timeout de 180s et un garde-fou d'idempotence (`if déjà publié → return`) pour éviter les doublons en cas de retry.

### Instagram (`InstagramPublisher`)

Flux Graph API standard en 2 étapes (`/media` puis `/media_publish`), photo unique ou carrousel. Deux limitations confirmées par test, structurelles côté Meta, pas des bugs :

1. **`image_url` doit être publiquement accessible** — Instagram va chercher l'image lui-même, contrairement à Facebook qui reçoit les octets directement en upload. En local, MinIO n'est pas exposé publiquement : un tunnel `cloudflared` (`cloudflared tunnel --url http://localhost:9000`) est utilisé temporairement, avec `AWS_URL` pointé vers l'URL générée. **Ce tunnel expire périodiquement** (limite des tunnels gratuits trycloudflare) et est aussi limité en bande passante sur cette machine (~97 Ko OK, ~450 Ko a provoqué un timeout côté Meta). **À remplacer avant mise en production** par un vrai bucket S3 public ou MinIO derrière un nom de domaine réel.
2. **La suppression d'un post Instagram publié n'est pas supportée par l'API**, quelles que soient les permissions accordées (`(#10) Insufficient permissions to access this data` confirmé avec toutes les permissions). `DeleteSocialPost` ne fait donc rien pour `platform === 'instagram'` : la suppression doit être faite manuellement dans l'app Instagram.

## 4. Admin Filament

- **`PropertyResource`** : formulaire principal + action de table "Publier sur Facebook" (avec garde-fou anti-doublon).
- **`SocialPostResource`** : lecture seule côté création (`canCreate() => false`), actions "Republier" (route vers le bon job selon la plateforme) et "Supprimer de Facebook" (visible uniquement pour `platform === 'facebook'`), action d'en-tête "Rafraîchir" qui revérifie le statut réel de chaque post Facebook publié via `FacebookPublisher::exists()`.
- **`ImagesRelationManager`** : seul point d'entrée pour gérer les images une fois le bien créé (voir §5, point 1 — corrigé le 2026-09-23).

## 5. Points d'attention identifiés (à corriger ou clarifier)

Liste établie lors de l'audit du 2026-09-23, par ordre approximatif d'impact. Tous les points ont été traités le jour même (corrigés, ou laissés tels quels après décision explicite — voir points 4 et 10).

1. ~~**Deux chemins différents pour ajouter des images à un bien**~~ **— corrigé.** Le champ `new_images` du formulaire principal n'est désormais visible qu'à la création (`->visible(fn (string $operation) => $operation === 'create')`), puisqu'un relation manager n'est de toute façon pas disponible avant que le bien existe. En édition, seul l'onglet **Images** (`ImagesRelationManager`) permet d'ajouter/modifier/supprimer des images, avec un contrôle explicite de `sort_order`/`is_main` — plus de double chemin ni de risque d'incohérence. `EditProperty::afterSave()` (qui traitait l'ancien champ) a été supprimé, devenu inutile.
2. ~~**`canAccessPanel()` retourne toujours `true`**~~ **— corrigé.** `User::canAccessPanel()` vérifie désormais `$this->hasRole('admin')`. Le rôle `admin` (guard `web`) est assigné automatiquement au compte créé par `DatabaseSeeder`, disponible via `User::factory()->admin()` dans les tests, et a été assigné à tous les comptes réels existants au moment du changement (personne n'a été bloqué). **Pour donner accès au panel à un nouveau compte**, assigner le rôle explicitement, ex. via tinker : `App\Models\User::find($id)->assignRole('admin')`.
3. ~~**`spatie/laravel-permission` installé mais jamais utilisé**~~ **— corrigé** (cf. point 2, qui en fait maintenant un usage réel). Un seul rôle (`admin`) existe pour l'instant — pas de permissions fines par ressource, ce n'était pas demandé.
4. **`laravel/sanctum` installé mais aucune route protégée** — décision explicite du 2026-09-23 : laissé tel quel, en réserve pour un usage futur (espace propriétaire authentifié, etc.). L'API publique reste sans middleware d'authentification.
5. ~~**Config Slack présente mais jamais utilisée**~~ **— corrigé.** `app/Services/SlackNotifier.php` envoie un message via l'API Slack (`chat.postMessage`, authentifié par `SLACK_BOT_USER_OAUTH_TOKEN`) quand une publication Facebook ou Instagram échoue **définitivement** (méthode `failed()` des jobs, après épuisement des tentatives). Aucun package supplémentaire installé — un simple appel HTTP, dans le même esprit que `FacebookPublisher`/`InstagramPublisher`. Ne fait rien si `SLACK_BOT_USER_OAUTH_TOKEN`/`SLACK_BOT_USER_DEFAULT_CHANNEL` ne sont pas renseignés (voir `.env.example`) — **à configurer côté Slack (créer une app, un bot, l'inviter dans un canal) pour activer réellement les alertes.**
6. ~~**`PropertyCaptionGenerator` ne mentionne pas `rental_term`**~~ **— corrigé.** Une ligne `📅 Courte durée` / `📅 Longue durée` est désormais ajoutée à la légende générée lorsque `transaction_type === 'rent'` et que `rental_term` est renseigné.
7. ~~**Pas de suite de tests pour l'API publique**~~ **— corrigé.** `tests/Feature/PropertyApiTest.php` couvre `index` (filtre par statut publié, filtre `transactionType`, pagination plafonnée à 48, images triées avec URL publique) et `show` (détail complet, 404 sur brouillon/archivé/inexistant).
8. ~~**Pas de CI**~~ **— corrigé.** `.github/workflows/tests.yml` lance `composer test` sur chaque push et pull request (PHP 8.3, SQLite en mémoire comme en local — pas besoin de Postgres/Redis en CI puisque `phpunit.xml` force déjà `DB_CONNECTION=sqlite`). Non vérifié en conditions réelles (pas d'exécution GitHub Actions possible depuis cet environnement) — à confirmer au premier push.
9. **Sécurité des tests / secrets** (déjà corrigée, à garder en tête) : `docker-compose.yml` charge les vraies variables d'environnement dans les conteneurs `app`/`worker` via `env_file: .env`. Deux incidents réels se sont produits avant que ce soit verrouillé :
   - Lancer `php artisan test` directement (au lieu de `composer test`) a fait tourner `RefreshDatabase` contre la vraie base Postgres, effaçant les données réelles.
   - Un test modifiant le statut d'un bien sans `Queue::fake()` a déclenché une vraie publication Facebook (queue forcée en `sync` pour les tests) avec les vrais tokens, publiant du contenu Faker sur la Page Facebook réelle.

   Trois protections sont en place : `tests/TestCase.php` bloque tout appel HTTP réel non mocké (`Http::preventStrayRequests()`), `composer.json` neutralise les variables sensibles avant de lancer les tests, et `phpunit.xml` leur donne des valeurs factices en secours. **Toujours utiliser `composer test`, jamais `artisan test` directement, et toujours `Queue::fake()` dans un test qui touche au statut d'un `Property`.**
10. **`Property::nextReference()`** charge toutes les références existantes en mémoire pour calculer le max +1. Largement suffisant à l'échelle actuelle (dizaines/centaines de biens) ; à revisiter (séquence PostgreSQL dédiée) si le volume devient très important.

## 6. Passage complet des CRUD/flux le 2026-09-23 — bugs trouvés et corrigés

En testant systématiquement chaque flux admin (biens, images, publications), deux problèmes réels sont ressortis, en plus des 10 points ci-dessus :

1. **Suppression d'une image individuelle ne nettoyait pas MinIO.** Depuis que l'onglet "Images" est devenu l'unique moyen de gérer les images en édition (point 1), ce trou devenait le seul chemin de suppression d'image restant. `Property::deleting()` ne nettoyait que la suppression *complète* d'un bien (boucle manuelle, nécessaire à cause de la cascade DB — voir §2). Corrigé par `app/Observers/PropertyImageObserver.php`, qui supprime le fichier S3/MinIO dès qu'une ligne `PropertyImage` est supprimée individuellement (`deleting()`), enregistré dans `AppServiceProvider::boot()`. Pas de double-suppression avec la boucle de `PropertyObserver` : celle-ci appelle directement `Storage::delete()`, jamais `$image->delete()`, donc n'active pas cet observer.
2. **Le statut `deleted` d'un `SocialPost` n'était en réalité utilisable que sur Postgres.** La migration qui l'ajoutait à la contrainte (`2026_09_14_225551_add_deleted_status_to_social_posts_table`) était un no-op sur tout autre driver — donc sur SQLite (utilisé par toute la suite de tests et la CI), toute tentative de passer un post en `deleted` provoquait une violation de contrainte CHECK, silencieusement jamais détectée jusqu'à ce que les actions "Rafraîchir" et "Supprimer de Facebook" soient réellement testées de bout en bout. Corrigé par une nouvelle migration (`2026_09_23_003000_convert_social_posts_status_to_plain_string`) qui transforme `status` en simple `VARCHAR` sur tous les drivers, supprimant la contrainte CHECK pgsql-only — la validation des valeurs reste de toute façon entièrement côté application (le champ n'est jamais alimenté par une saisie utilisateur libre).

**Couverture ajoutée à cette occasion** : `tests/Feature/ImagesRelationManagerTest.php` (création/édition/suppression d'image), `tests/Feature/SocialPostResourceTest.php` (exécution réelle de "Republier" pour Facebook et Instagram, "Supprimer de Facebook", "Rafraîchir" avec un post toujours présent et un post supprimé côté Facebook). Suite complète : 58 tests, tous verts.
