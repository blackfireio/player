FROM php:8.2-cli-alpine

ARG VERSION_SUFFIX=''
ARG PHPEXTINST_VERSION=2.1.34 # https://github.com/mlocati/docker-php-extension-installer/releases
ARG UUID_VERSION=1.2.0        # https://pecl.php.net/package/uuid

RUN curl -fsLo /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/download/${PHPEXTINST_VERSION}/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions

RUN install-php-extensions \
    mbstring-stable \
    intl-stable \
    uuid-${UUID_VERSION}

RUN touch /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_startup_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'error_reporting=0' >> /usr/local/etc/php/conf.d/error_reporting.ini

WORKDIR /app

ADD "https://get.blackfire.io/blackfire-player$VERSION_SUFFIX.phar" /usr/local/bin/blackfire-player
RUN chmod +x /usr/local/bin/blackfire-player

ENTRYPOINT ["/usr/local/bin/blackfire-player"]
