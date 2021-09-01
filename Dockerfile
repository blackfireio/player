FROM php:8.0-cli-alpine

ARG VERSION_SUFFIX=''

ADD "https://get.blackfire.io/blackfire-player$VERSION_SUFFIX.phar" /usr/local/bin/blackfire-player
RUN chmod +x /usr/local/bin/blackfire-player

WORKDIR /app

ENTRYPOINT ["/usr/local/bin/blackfire-player"]
