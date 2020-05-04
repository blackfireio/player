ARG PHP_VERSION=7.3

FROM php:${PHP_VERSION}-cli

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install --no-install-recommends -y git unzip wget gnupg dirmngr \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo 'date.timezone = "Europe/Paris"' > /usr/local/etc/php/conf.d/timezone.ini

RUN mkdir ~/.gnupg
RUN echo "disable-ipv6" >> ~/.gnupg/dirmngr.conf

RUN wget -O phive.phar "https://phar.io/releases/phive.phar" && \
    wget -O phive.phar.asc "https://phar.io/releases/phive.phar.asc" && \
    gpg --no-tty --keyserver pool.sks-keyservers.net --recv-keys 0x9D8A98B29B2D5D79 && \
    gpg --no-tty --verify phive.phar.asc phive.phar && \
    rm phive.phar.asc && \
    chmod +x phive.phar && \
    mv phive.phar /usr/local/bin/phive

WORKDIR /app
