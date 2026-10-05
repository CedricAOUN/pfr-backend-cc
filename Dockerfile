# syntax=docker/dockerfile:1

############################
# Base: shared system setup
############################
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring xml zip bcmath curl fileinfo \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ensure $_ENV is populated from the process environment so real env vars
# take precedence over .env values (phpdotenv createImmutable)
RUN echo "variables_order = EGPCS" > /usr/local/etc/php/conf.d/app.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app


############################
# Test: runs the suite on in-memory SQLite (dev deps included)
############################
FROM base AS test

# Laravel's fake image uploads need GD; keep this test dependency out of production.
RUN apt-get update && apt-get install -y libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Process env vars win over .env, so these force the test configuration
ENV APP_ENV=testing \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory:

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan test \
    && touch /tmp/tests-passed


############################
# Production: no dev deps, MySQL only
############################
FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

# Makes this stage depend on the test stage, so BuildKit can't skip it.
# If tests fail, this image is never built.
COPY --from=test /tmp/tests-passed /tmp/tests-passed

RUN composer dump-autoload --optimize --no-dev \
    && (php artisan storage:link || true)

EXPOSE 8080

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
