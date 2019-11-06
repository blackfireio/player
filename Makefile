.DEFAULT_GOAL := help
SHELL=/bin/bash

php_version ?= 7.3

image_hash = $(shell sha256sum Dockerfile | cut -c -8)
php_image = blackfire/player-test:$(php_version)-$(image_hash)

PHP=@docker run --rm -it -u `id -u`:`id -g` -v $(HOME)/.composer:/.composer -v $(PWD):/app -e HOME=/ $(php_image)

clean:
	rm -rf vendor bin/blackfire-player.phar
.PHONY: clean

build-docker-image:
	@docker inspect $(php_image) &> /dev/null \
		|| { echo "Building docker image $(php_image)" ; docker build --build-arg PHP_VERSION=$(php_version) -t $(php_image) . ;}
.PHONY: build-docker-image

install:
	$(PHP) composer install --no-interaction
.PHONY: install

test: build-docker-image install ## Run the Player testsuite
	$(eval args ?= )
	@$(PHP) php -v
	@$(PHP) ./vendor/bin/simple-phpunit $(args)
.PHONY: test

help:
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'
.PHONY: help
