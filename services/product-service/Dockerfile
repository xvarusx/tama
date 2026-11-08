
FROM php:8.4-fpm-alpine

# Runtime libraries
RUN apk add --no-cache \
    icu-libs \
    rabbitmq-c \
    curl \
    oniguruma

# Install PHP extensions
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        rabbitmq-c-dev \
        oniguruma-dev; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_mysql \
        opcache \
        mbstring; \
    printf "\n" | pecl install amqp; \
    docker-php-ext-enable amqp; \
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

# Install production dependencies
RUN composer install \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

# Copy application source
COPY --chown=www-data:www-data . .

# Prepare Symfony writable directories (product images live under public/)
RUN mkdir -p var/cache var/log public/products-images \
    && chown -R www-data:www-data var public/products-images

COPY --from=infra base/scripts/register.sh /usr/local/bin/register.sh
RUN chmod +x /usr/local/bin/register.sh

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 CMD ["php", "-r", "$socket = @fsockopen('127.0.0.1', 9000); if ($socket === false) { exit(1); } fclose($socket);"]

USER www-data

CMD ["php-fpm"]
