.DEFAULT_GOAL := help
SHELL=/bin/bash

php_version ?= 7.4

image_hash = $(shell sha256sum Dockerfile-dev | cut -c -8)
php_image = blackfire/player-test:$(php_version)-$(image_hash)

PHP=@docker run --rm -it -u `id -u`:`id -g` -v "$(HOME)/.composer:/.composer" -v "$(HOME)/.phive:/.phive" -v "$(PWD):/app" -e HOME=/ $(php_image)

##
#### General
##

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

package-test: build-docker-image install bin/tools/box-2.7.4.phar ## Tests the phar release
	@# The box.no-git.json configuration file disables git placeholder, avoiding git calls during packaging
	@$(PHP) php -d phar.readonly=0 bin/tools/box-2.7.4.phar build -c box.no-git.json

package: build-docker-image install bin/tools/box-2.7.4.phar ## Generates the phar release
	@$(PHP) php -d phar.readonly=0 bin/tools/box-2.7.4.phar build -c box.json

##
## Not Listed
##

clean:
	rm -rf vendor bin/blackfire-player.phar
.PHONY: clean

build-docker-image:
	@docker inspect $(php_image) &> /dev/null \
		|| { echo "Building docker image $(php_image)" ; docker build -f Dockerfile-dev --build-arg PHP_VERSION=$(php_version) -t $(php_image) . ;}
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

bin/tools/php-cs-fixer bin/tools/phpstan phive: build-docker-image
ifdef CI
	@echo -e "--- [make phive] \033[33mInstalling phive dependencies\033[0m"
endif
	@$(PHP) phive --home ./.phive install --copy --trust-gpg-keys E82B2FB314E9906E,CF1A108D0E7AE720

phive-update: build-docker-image
	@$(PHP) phive --home ./.phive update

help:
	@grep -hE '(^[a-zA-Z_-]+:.*?##.*$$)|(^###)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m\n/'
.PHONY: help

bin/tools/box-2.7.4.phar:
	@mkdir -p bin/tools
	@test -f bin/tools/box-2.7.4.phar || curl --fail --location -o bin/tools/box-2.7.4.phar https://github.com/box-project/box2/releases/download/2.7.4/box-2.7.4.phar
