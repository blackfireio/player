.DEFAULT_GOAL := help
SHELL=/bin/bash

image_hash = $(shell sha256sum Dockerfile-dev | cut -c -8)
php_image = blackfire/player-test:$(image_hash)

# https://github.com/box-project/box/releases
box_version = 4.6.6
box_image = blackfire/php-internal:8.4-v1.0.84

BOX_BIN=bin/tools/box-$(box_version).phar
PHAR_DIST=bin/blackfire-player.phar
BASE_PHP=@docker run --rm -e "PHP_CS_FIXER_IGNORE_ENV=1" -u `id -u`:`id -g` -v "$(HOME)/.composer:/.composer" -v "$(HOME)/.phive:/.phive" -v "$(PWD):/app" -e HOME=/ -i
BOX=@docker run --rm -v $(PWD):/app -w /app $(box_image)
ifdef CI
	PHP= $(BASE_PHP) $(php_image)
else
	PHP= $(BASE_PHP) -t $(php_image)
endif

ifdef BUILDKITE
define section_start
	$(eval $@_TASK = $(1))
	$(eval $@_LABEL = $(2))
	$(eval $@_COLLAPSED := $(if $(3), $(3), true))
	$(eval $@_NAME := $(if $(4), $(4), $(1)))
	@echo -e "$(if $(filter true, $($@_COLLAPSED)),"---","+++") [make ${$@_TASK}] \033[33m${$@_LABEL}\033[0m"
endef
define section_end
endef
else ifdef GITLAB_CI
define section_start
	$(eval $@_TASK = $(1))
	$(eval $@_LABEL = $(2))
	$(eval $@_COLLAPSED := $(if $(3),$(3),true))
	$(eval $@_NAME := $(if $(4),$(4),$(1)))
	@echo -e "\e[0Ksection_start:$$(date +%s):$$(echo "${$@_NAME}"|sha1sum|cut -f1 -d" ")$(if $(filter true, $($@_COLLAPSED)),"[collapsed=true]","")\r\e[0K[make ${$@_TASK}] \033[33m${$@_LABEL}\033[0m"
endef
define section_end
	$(eval $@_NAME = $(1))
	@echo -e "\e[0Ksection_end:$$(date +%s):$$(echo "${$@_NAME}"|sha1sum|cut -f1 -d" ")\r\e[0K"
endef
else
define section_start
	$(eval $@_TASK = $(1))
	$(eval $@_LABEL = $(2))
	$(eval $@_COLLAPSED := $(if $(3),$(3),true))
	$(eval $@_NAME := $(if $(4),$(4),$(1)))
	@echo -e "[make ${$@_TASK}] \033[33m${$@_LABEL}\033[0m"
endef
define section_end
endef
endif

##
#### General
##

setup: build $(BOX_BIN) composer-install ## Create and initialize containers
.PHONY: setup

php-cs: bin/tools/php-cs-fixer vendor/autoload.php ## Just analyze PHP code with php-cs-fixer
	@$(call section_start, $@, "Running PHP-CS-Fixer", false)

	@$(PHP) php -dmemory_limit=-1 ./bin/tools/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run

	@$(call section_end, $@)
.PHONY: php-cs

php-cs-fix: bin/tools/php-cs-fixer vendor/autoload.php ## Analyze and fix PHP code with php-cs-fixer
	@$(PHP) php -dmemory_limit=-1 ./bin/tools/php-cs-fixer fix --config=.php-cs-fixer.dist.php
.PHONY: php-cs-fix

rector-fix: vendor/bin/rector vendor/autoload.php ## Analyze and fix PHP code with rector
	@$(PHP) php -dmemory_limit=-1 ./vendor/bin/rector
.PHONY: rector-fix

vendor/bin/rector: vendor/autoload.php

phpstan: bin/tools/phpstan vendor/autoload.php ## Analyze PHP code with phpstan
	@$(call section_start, $@, "Running PHPStan", false)

	@$(PHP) php -dmemory_limit=-1 ./bin/tools/phpstan analyse Player -c phpstan.neon -l 1

	@$(call section_end, $@)
.PHONY: phpstan

shell: ## Starts a shell in container
	@$(PHP) bash

package-test: $(BOX_BIN) vendor/autoload.php ## Tests the phar release
	@# The box.no-git.json configuration file disables git placeholder, avoiding git calls during packaging
	@$(BOX) $(BOX_BIN) compile -c box.no-git.json

##
## Not Listed
##

build: ## Build images
	@$(call section_start, $@, "Pulling docker images")

	@docker build --pull -f Dockerfile-dev -t $(php_image) .

	@$(call section_end, $@)
.PHONY: build

clean:
	rm -rf vendor $(PHAR_DIST)
.PHONY: clean

composer-update:
	@$(PHP) composer update --no-interaction $(options)

composer-install: vendor/autoload.php

vendor/autoload.php: composer.lock
	@$(call section_start, $@, "Installing composer dependencies")

	@$(PHP) composer install --no-interaction --prefer-dist --no-scripts
	@touch vendor/autoload.php

	@$(call section_end, $@)

phpunit: vendor/autoload.php ## Run phpunit
	$(eval args ?= )
	@$(call section_start, $@, "Running PHPUnit", false)

	@$(PHP) ./vendor/bin/phpunit $(args)

	@$(call section_end, $@)
.PHONY: phpunit

phive: bin/tools/phpstan bin/tools/php-cs-fixer
.PHONY: phive
bin/tools/phpstan bin/tools/php-cs-fixer: phive.xml
	@$(call section_start, $@, "Installing phive dependencies")

	@$(PHP) php -dmemory_limit=-1 /usr/local/bin/phive --home ./.phive install --copy --trust-gpg-keys 8E730BA25823D8B5,CF1A108D0E7AE720,E82B2FB314E9906E,CA7C2C7A30C8E8E1274A847651C67305FFC2E5C0

	@$(call section_end, $@)

phive-status: ## In case you need to get the phive tools GPG keys
	@$(PHP) phive status
.PHONY: phive-status

phive-update:
	@$(PHP) php -dmemory_limit=-1 /usr/local/bin/phive --home ./.phive update

help:
	@grep -hE '(^[a-zA-Z_-]+:.*?##.*$$)|(^###)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m\n/'
.PHONY: help

$(BOX_BIN):
	@$(call section_start, $@, "Downloading box")

	@mkdir -p bin/tools
	curl --fail --location -o $(BOX_BIN) https://github.com/box-project/box/releases/download/$(box_version)/box.phar && chmod +x $(BOX_BIN)

	@$(call section_end, $@)
