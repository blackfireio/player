FROM php:8.1-cli-alpine

ARG VERSION_SUFFIX=''

ADD "https://get.blackfire.io/blackfire-player$VERSION_SUFFIX.phar" /usr/local/bin/blackfire-player
RUN chmod +x /usr/local/bin/blackfire-player

RUN touch /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'display_startup_errors=Off' >> /usr/local/etc/php/conf.d/error_reporting.ini \
    && echo 'error_reporting=0' >> /usr/local/etc/php/conf.d/error_reporting.ini

WORKDIR /app

ENTRYPOINT ["/usr/local/bin/blackfire-player"]
