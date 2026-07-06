.DEFAULT_GOAL := help
SHELL?=/bin/bash

image_hash = $(shell sha256sum Dockerfile-dev | cut -c -8)
php_image = blackfire/player-test:$(image_hash)

# https://github.com/box-project/box/releases
# keep in sync with .github/workflows/build_tags.yml and .github/workflows/build_master.yml
box_version = 4.7.0
box_image = registry.lab.plat.farm/platformsh/observability/blackfire/subtree-docker/php-internal:8.5-2026.7.0

BOX_BIN=bin/tools/box-$(box_version).phar
PHAR_DIST=bin/blackfire-player.phar
BASE_PHP=@docker run --rm -u `id -u`:`id -g` -v "$(HOME)/.composer:/.composer" -v "$(HOME)/.phive:/.phive" -v "$(PWD):/app" -e HOME=/ -i
BOX=@docker run --rm -v $(PWD):/app -w /app $(box_image)
ifdef CI
	PHP= $(BASE_PHP) $(php_image)
else
	PHP= $(BASE_PHP) -t $(php_image)
endif

# Lint tools (phpstan, php-cs-fixer) are baked into the ci-phive image.
# Locally we run them through a nested `docker run` on that image; on CI the job
# already runs inside the ci-phive image, so we invoke the baked binaries directly.
CI_PHIVE_IMAGE=registry.lab.plat.farm/platformsh/observability/blackfire/subtree-docker/ci-phive:latest
ON_PHIVE=docker run --rm -i -v "$(PWD):/app" -w /app $(CI_PHIVE_IMAGE)
ifdef CI
ON_PHIVE=
endif

ifdef GITLAB_CI
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

php-cs: ## Just analyze PHP code with php-cs-fixer
	@$(call section_start, $@, "Running PHP-CS-Fixer", false)

	@$(ON_PHIVE) php -dmemory_limit=-1 /usr/local/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

	@$(call section_end, $@)
.PHONY: php-cs

php-cs-fix: ## Analyze and fix PHP code with php-cs-fixer
	@$(ON_PHIVE) php -dmemory_limit=-1 /usr/local/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
.PHONY: php-cs-fix

rector-fix: vendor/bin/rector vendor/autoload.php ## Analyze and fix PHP code with rector
	@$(PHP) php -dmemory_limit=-1 ./vendor/bin/rector
.PHONY: rector-fix

vendor/bin/rector: vendor/autoload.php

phpstan: ## Analyze PHP code with phpstan
# vendor/ must already be present (composer cache on CI, local install otherwise);
# no make prerequisite so the bare ci-phive image (no composer) never rebuilds it.
	@$(call section_start, $@, "Running PHPStan", false)

	@$(ON_PHIVE) php -dmemory_limit=-1 /usr/local/bin/phpstan analyse Player -c phpstan.neon -l 1

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

help:
	@grep -hE '(^[a-zA-Z_-]+:.*?##.*$$)|(^###)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m\n/'
.PHONY: help

$(BOX_BIN):
	@$(call section_start, $@, "Downloading box")

	@mkdir -p bin/tools
	curl --fail --location -o $(BOX_BIN) https://github.com/box-project/box/releases/download/$(box_version)/box.phar && chmod +x $(BOX_BIN)

	@$(call section_end, $@)
