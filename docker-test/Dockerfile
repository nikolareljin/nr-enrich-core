##
## NR EnrichCore — PHP test runner image
##
## Extends the official php:8.1-cli image with all extensions required by
## Pimcore 11 and installs Composer 2.  The bundle source is mounted at
## /var/www/bundle at runtime (see docker-compose.yml) so no COPY of src/
## is needed here — the image stays stable between code changes.
##
FROM php:8.1-cli

# ── System dependencies ────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        exif \
        pdo \
        pdo_mysql \
        intl \
        zip \
        gd \
        opcache \
        xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Xdebug (coverage) ─────────────────────────────────────────────────────
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# ── Composer ──────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# ── Working directory ─────────────────────────────────────────────────────
# The bundle source is bind-mounted here at runtime.
WORKDIR /var/www/bundle
