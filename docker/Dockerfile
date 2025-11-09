FROM php:8.4-fpm

# Set timezone
ENV TZ=Europe/Berlin
ENV DEBIAN_FRONTEND=noninteractive

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libsqlite3-dev \
    pkg-config \
    zip

# Clean up to reduce image size
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) intl

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configure Xdebug
COPY ./docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Install Composer globally
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/tmi/translation-bundle/var/cache && chown -R www-data:www-data /var/www/tmi/translation-bundle/var/cache

WORKDIR /var/www/tmi/translation-bundle
