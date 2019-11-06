ARG PHP_VERSION=7.3

FROM php:${PHP_VERSION}-cli

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install --no-install-recommends -y git unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app
