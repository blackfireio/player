FROM php:8.0-cli-alpine
ARG VERSION_SUFFIX=''
RUN apk add --no-cache wget && \
    wget "https://get.blackfire.io/blackfire-player$VERSION_SUFFIX.phar" && \
    mv blackfire-player$VERSION_SUFFIX.phar /usr/local/bin/blackfire-player && \
    chmod +x /usr/local/bin/blackfire-player

WORKDIR /app

ENTRYPOINT ["/usr/local/bin/blackfire-player"]
