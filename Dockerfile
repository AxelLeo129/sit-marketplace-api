# Imagen de producción de la API (Railway). FrankenPHP sirve public/ con PHP 8.3.

# 1. Dependencias de Composer, sin las de desarrollo.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 2. Imagen final.
FROM dunglas/frankenphp:1-php8.3

# GD (con WebP) para recodificar imágenes, intl para formatos y pdo_mysql para la base de datos.
RUN install-php-extensions pdo_mysql gd intl opcache pcntl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-sit.ini"

WORKDIR /app
COPY --from=vendor /app /app

# Carpetas de trabajo de Laravel. El proceso no corre como root.
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy

USER www-data

# Railway define PORT; en local se usa 8080.
EXPOSE 8080
CMD ["sh", "docker/start.sh"]
