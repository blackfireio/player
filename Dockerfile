ARG PHP_VERSION=8.2.10        # https://hub.docker.com/_/php/tags?page=1&name=8.2
ARG COMPOSER_VERSION=2.6.2    # https://hub.docker.com/_/composer/tags
ARG PHPEXTINST_VERSION=2.1.51 # https://github.com/mlocati/docker-php-extension-installer/releases
ARG UUID_VERSION=1.2.0        # https://pecl.php.net/package/uuid

FROM composer:${COMPOSER_VERSION} as build_composer

FROM php:${PHP_VERSION}-alpine as build_installer
ARG PHPEXTINST_VERSION

RUN curl -fsLo /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/download/${PHPEXTINST_VERSION}/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions

FROM php:${PHP_VERSION}-alpine

COPY --from=build_composer /usr/bin/composer /usr/bin/composer
COPY --from=build_installer /usr/local/bin/install-php-extensions /usr/local/bin/install-php-extensions

ARG UUID_VERSION

RUN install-php-extensions \
    uuid-${UUID_VERSION} \
    zip-stable \
    mbstring-stable \
    intl-stable

RUN touch /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_startup_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'error_reporting=0' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini

WORKDIR /app

COPY . /app
RUN chmod +x /app/bin/blackfire-player.php
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
ENV USING_PLAYER_DOCKER_RELEASE=1

ENTRYPOINT ["/app/bin/blackfire-player.php"]
