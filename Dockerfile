FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist

FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=vendor /app/vendor /app/vendor
COPY . /app
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
