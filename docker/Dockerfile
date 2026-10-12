FROM composer:2 AS composer
FROM php:8.5-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/helpdesk.ini
WORKDIR /var/www/html
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
