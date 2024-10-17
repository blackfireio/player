.DEFAULT_GOAL := help
SHELL=/bin/bash

image_hash = $(shell sha256sum Dockerfile-dev | cut -c -8)
php_image = blackfire/player-test:$(image_hash)

# https://github.com/box-project/box/releases
box_version = 4.6.2
box_image = blackfire/php-internal:8.3-v1.0.42

BOX_BIN=bin/tools/box-$(box_version).phar
PHAR_DIST=bin/blackfire-player.phar

PHP=@docker run --rm -it -e "PHP_CS_FIXER_IGNORE_ENV=1" -u `id -u`:`id -g` -v "$(HOME)/.composer:/.composer" -v "$(HOME)/.phive:/.phive" -v "$(PWD):/app" -e HOME=/ $(php_image)
BOX=@docker run --rm -v $(PWD):/app -w /app $(box_image)

##
#### General
##

setup: build-docker-image $(BOX_BIN) install ## Create and initialize containers
.PHONY: setup

# clean vendors is required to install the vendor attached to the PHP version used
test: build-docker-image clean install ## Run the Player testsuite
	$(eval args ?= )
	@$(PHP) php -v
	@$(PHP) ./vendor/bin/simple-phpunit $(args)
.PHONY: test

php-cs: build-docker-image bin/tools/php-cs-fixer ## Just analyze PHP code with php-cs-fixer
ifdef CI
	@echo -e "+++ [make php-cs] \033[33mRunning PHP-CS-Fixer\033[0m"
endif
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run
.PHONY: php-cs

php-cs-fix: build-docker-image bin/tools/php-cs-fixer ## Analyze and fix PHP code with php-cs-fixer
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/php-cs-fixer fix --config=.php-cs-fixer.dist.php
.PHONY: php-cs-fix

phpstan: build-docker-image install bin/tools/phpstan ## Analyze PHP code with phpstan
ifdef CI
	@echo -e "+++ [make phpstan] \033[33mRunning PHPStan\033[0m :phpstan:"
endif
	@# We list tests to force phpunit dependencies installation
	@$(PHP) vendor/bin/simple-phpunit --list-tests 2>&1 > /dev/null
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/phpstan analyse Player -c phpstan.neon -l 1
.PHONY: phpstan

shell: ## Starts a shell in container
	@$(PHP) bash

package-test: build-docker-image $(BOX_BIN) install ## Tests the phar release
	@# The box.no-git.json configuration file disables git placeholder, avoiding git calls during packaging
	@$(BOX) $(BOX_BIN) compile -c box.no-git.json

package: build-docker-image $(BOX_BIN) install ## Generates the phar release
	@$(BOX) $(BOX_BIN) compile -c box.json

##
## Not Listed
##

clean:
	rm -rf vendor $(PHAR_DIST)
.PHONY: clean

build-docker-image:
	@docker inspect $(php_image) &> /dev/null \
		|| { echo "Building docker image $(php_image)" ; docker build -f Dockerfile-dev -t $(php_image) . ;}
.PHONY: build-docker-image

vendor/autoload.php install:
	$(PHP) composer install --no-interaction --prefer-dist
.PHONY: install

phpunit:
	$(eval args ?= )
ifdef CI
	@echo -e "+++ [make phpunit] \033[33mRunning PHPUnit\033[0m :phpunit:"
endif
	@$(PHP) ./vendor/bin/simple-phpunit $(args)
.PHONY: phpunit

phpunit-setup: build-docker-image vendor/autoload.php ## Setup phpunit
ifdef CI
	@echo -e "--- [make phpunit-setup] \033[33mInstalling PHPUnit\033[0m :phpunit:"
endif
	@$(PHP) vendor/bin/simple-phpunit --version 2>&1>/dev/null
.PHONY: phpunit-setup

phive: bin/tools/phpstan bin/tools/php-cs-fixer
.PHONY: phive
bin/tools/phpstan bin/tools/php-cs-fixer: phive.xml
	@$(MAKE) phive-install

phive-install: build-docker-image
ifdef CI
	@echo -e "--- [make phive] \033[33mInstalling phive dependencies\033[0m"
endif
	@$(PHP) php -dmemory_limit=-1 /usr/local/bin/phive --home ./.phive install --copy --trust-gpg-keys 8E730BA25823D8B5,CF1A108D0E7AE720,E82B2FB314E9906E,CA7C2C7A30C8E8E1274A847651C67305FFC2E5C0

phive-status: ## In case you need to get the phive tools GPG keys
	@$(PHP) phive status
.PHONY: phive-status

phive-update: build-docker-image
	@$(PHP) php -dmemory_limit=-1 /usr/local/bin/phive --home ./.phive update

help:
	@grep -hE '(^[a-zA-Z_-]+:.*?##.*$$)|(^###)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m\n/'
.PHONY: help

$(BOX_BIN):
	@mkdir -p bin/tools
	@test -f $(BOX_BIN) || curl --fail --location -o $(BOX_BIN) https://github.com/box-project/box/releases/download/$(box_version)/box.phar && chmod +x $(BOX_BIN)
