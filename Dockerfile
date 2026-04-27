FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libjpeg-dev \
        libpng-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# OPcache + Apache modules. mod_expires + mod_headers power the static
# asset cache headers in assets/.htaccess.
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
RUN a2enmod rewrite expires headers

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 80
