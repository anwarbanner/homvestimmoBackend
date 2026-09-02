FROM php:8.4-fpm

# 1. Dépendances Système
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libpq-dev \
    libzip-dev autoconf build-essential libicu-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Extensions PHP (avec Redis et OpCache pour la performance)
RUN pecl channel-update pecl.php.net \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip opcache intl

# 3. Configuration PHP optimisée (OpCache)
RUN echo "opcache.enable=1\nopcache.memory_consumption=256\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/opcache.ini

# 4. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 5. Dépendances PHP (mises en cache tant que composer.json/lock ne changent pas)
# Ce compose sert au dev local (bind-mount, worker, MinIO) donc on garde les
# paquets de dev (phpunit, pail...) utilisables depuis le conteneur.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# 6. Code applicatif (écrasé par le bind-mount en dev, utilisé tel quel en prod)
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# 7. Pool PHP-FPM dédié (voir docker/php-fpm/www.conf)
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# 8. Script d'entrée pour automatiser les tâches
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]