export APP_USER_UID := $(shell id -u 2>/dev/null || echo 1000) # Use 2>/dev/null to suppress errors if id -u fails
export APP_USER_GID := $(shell id -g 2>/dev/null || echo 1000)

# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec -u appuser php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Executables: vendors
# PHPUNIT       = ./vendor/bin/phpunit
# PHPSTAN       = ./vendor/bin/phpstan
# PHP_CS_FIXER  = ./vendor/bin/php-cs-fixer
# PHPMETRICS    = ./vendor/bin/phpmetrics

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build-clean: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

build: ## Build and start the containers
	@$(DOCKER_COMP) build

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --wait

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=10 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: ## Start tests with phpunit, pass the parameter "c=" to add options to phpunit, example: make test c="--group e2e --stop-on-failure"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c)



## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

fix-perms: ## Fix permissions of all files
	@$(DOCKER_COMP) exec -e --rm php chown -R 1000:1000 /app

# ## —— Coding standards ✨ ——————————————————————————————————————————————————————
# lint-php: ## Lint files with php-cs-fixer
# 	@$(PHP_CS_FIXER) fix --allow-risky=yes --dry-run --config=php-cs-fixer.php

# fix-php: ## Fix files with php-cs-fixer
# 	@PHP_CS_FIXER_IGNORE_ENV=1 $(PHP_CS_FIXER) fix --allow-risky=yes --config=php-cs-fixer.php


# ## —— Code Quality reports 📊 ——————————————————————————————————————————————————
# report-metrics: ## Run the phpmetrics report
# 	@$(PHPMETRICS) --report-html=var/phpmetrics/ src/