#!/bin/bash
set -e

echo "Vérification de la connexion à la base de données..."

# Test de connexion via PHP
until php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=5432;dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }"; do
  echo "Base de données non prête... attente de 2s"
  sleep 2
done

echo "Base de données connectée !"

if [ "$1" = "php-fpm" ]; then
    echo "Exécution des migrations..."
    php artisan migrate --force

    if [ ! -L public/storage ]; then
        echo "Création du lien symbolique public/storage..."
        php artisan storage:link
    fi
fi

exec "$@"