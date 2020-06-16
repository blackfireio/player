.DEFAULT_GOAL := help
SHELL=/bin/bash

php_version ?= 7.3

image_hash = $(shell sha256sum Dockerfile | cut -c -8)
php_image = blackfire/player-test:$(php_version)-$(image_hash)

PHP=@docker run --rm -it -u `id -u`:`id -g` -v "$(HOME)/.composer:/.composer" -v "$(HOME)/.phive:/.phive" -v "$(PWD):/app" -e HOME=/ $(php_image)

##
#### General
##

test: build-docker-image install ## Run the Player testsuite
	$(eval args ?= )
	@$(PHP) php -v
	@$(PHP) ./vendor/bin/simple-phpunit $(args)
.PHONY: test

php-cs: build-docker-image bin/tools/php-cs-fixer ## Just analyze PHP code with php-cs-fixer
ifdef CI
	@echo -e "+++ [make php-cs] \033[33mRunning PHP-CS-Fixer\033[0m"
endif
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/php-cs-fixer fix --config=.php_cs.dist --dry-run
.PHONY: php-cs

php-cs-fix: build-docker-image bin/tools/php-cs-fixer ## Analyze and fix PHP code with php-cs-fixer
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/php-cs-fixer fix --config=.php_cs.dist
.PHONY: php-cs-fix

phpstan: build-docker-image install bin/tools/phpstan ## Analyze PHP code with phpstan
ifdef CI
	@echo -e "+++ [make phpstan] \033[33mRunning PHPStan\033[0m :phpstan:"
endif
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/phpstan analyse Player -c phpstan.neon -l 1
.PHONY: phpstan

##
## Not Listed
##

clean:
	rm -rf vendor bin/blackfire-player.phar
.PHONY: clean

build-docker-image:
	@docker inspect $(php_image) &> /dev/null \
		|| { echo "Building docker image $(php_image)" ; docker build --build-arg PHP_VERSION=$(php_version) -t $(php_image) . ;}
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
