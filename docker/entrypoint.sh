#!/bin/bash
set -e

echo "Vérification de la connexion à la base de données..."

# Test de connexion via PHP
until php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=5432;dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }"; do
  echo "Base de données non prête... attente de 2s"
  sleep 2
done

echo "Base de données connectée !"

# storage/ et bootstrap/cache/ vivent sur des volumes nommés partagés entre
# les conteneurs app et worker. Si une commande y écrit en root (ex: artisan
# lancé manuellement via `docker compose exec`), les fichiers créés bloquent
# ensuite php-fpm (qui tourne en www-data) en écriture -> 500 sur tout Filament.
# On remet la propriété à plat à chaque démarrage pour que ça reste réparé
# même après un rebuild/recreate.
chown -R www-data:www-data storage bootstrap/cache

if [ "$1" = "php-fpm" ]; then
    echo "Exécution des migrations..."
    php artisan migrate --force

    if [ ! -L public/storage ]; then
        echo "Création du lien symbolique public/storage..."
        php artisan storage:link
    fi

    # Le master php-fpm doit rester root pour démarrer son pool de workers
    # en www-data (voir docker/php-fpm/www.conf) : pas de gosu ici.
    exec "$@"
fi

# Toute autre commande (ex: le worker "php artisan queue:work") doit tourner
# en www-data dès le départ, sinon elle recrée les mêmes fichiers root que le
# chown ci-dessus vient de corriger.
exec gosu www-data "$@"