ARG PHP_VERSION=8.4.10-alpine  # https://hub.docker.com/_/php/tags?page=1&name=8.4
ARG COMPOSER_VERSION=2.8.10    # https://hub.docker.com/_/composer/tags
ARG PHPEXTINST_VERSION=2.8.2  # https://github.com/mlocati/docker-php-extension-installer/releases
ARG UUID_VERSION=1.3.0        # https://pecl.php.net/package/uuid

FROM php:${PHP_VERSION} AS build_installer
ARG PHPEXTINST_VERSION

RUN curl -fsLo /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/download/${PHPEXTINST_VERSION}/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions

FROM composer:${COMPOSER_VERSION} AS build_composer
WORKDIR /app

COPY --from=build_installer /usr/local/bin/install-php-extensions /usr/local/bin/install-php-extensions
RUN install-php-extensions \
    zip \
    intl

COPY composer.json composer.lock /app/

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

FROM alpine AS sources

WORKDIR /app

COPY --from=build_composer /app/vendor /app/vendor
COPY ./. /app/

RUN rm composer.json composer.lock

FROM php:${PHP_VERSION}
ARG UUID_VERSION \
    BLACKFIRE_PLAYER_VERSION

ENV BLACKFIRE_PLAYER_VERSION=$BLACKFIRE_PLAYER_VERSION

COPY --from=build_installer /usr/local/bin/install-php-extensions /usr/local/bin/install-php-extensions

RUN install-php-extensions \
    uuid-${UUID_VERSION} \
    mbstring \
    intl

RUN touch /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_startup_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'error_reporting=0' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini

WORKDIR /app

COPY --from=sources /app /usr/lib/blackfire
RUN ln -s /usr/lib/blackfire/bin/blackfire-player.php /bin/blackfire-player

ENV USING_PLAYER_DOCKER_RELEASE=1

ENTRYPOINT ["/bin/blackfire-player"]
