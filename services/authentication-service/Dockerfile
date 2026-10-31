
FROM php:8.4-fpm-alpine

# Runtime libraries
RUN apk add --no-cache \
    icu-libs

# Install PHP extensions
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_mysql \
        opcache; \
    apk del .build-deps

# PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# PHP-FPM pool configuration
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /srv/app

# Copy Composer manifests first for better build caching
COPY composer.json composer.lock ./

# Install dependencies (including dev, since APP_ENV=dev)
RUN composer install \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

# Copy application source
COPY --chown=www-data:www-data . .

# Prepare Symfony writable directories
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

COPY --from=infra base/scripts/register.sh /usr/local/bin/register.sh
RUN chmod +x /usr/local/bin/register.sh

EXPOSE 9000

CMD ["php-fpm"]
