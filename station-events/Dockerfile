FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    git \
    unzip \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j$(nproc) pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts \
    && composer clear-cache

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chmod +x docker-entrypoint.sh

ENTRYPOINT ["/bin/sh", "/var/www/html/docker-entrypoint.sh"]
CMD ["php-fpm"]
