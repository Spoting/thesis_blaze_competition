export APP_USER_UID := $(shell id -u 2>/dev/null || echo 1000) # Use 2>/dev/null to suppress errors if id -u fails
export APP_USER_GID := $(shell id -g 2>/dev/null || echo 1000)

# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec -u appuser php
# WORKER_CONT = $(DOCKER_COMP) exec worker

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
.PHONY        : help build up start down logs composer vendor sf cc test worker-logs shell

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

php-bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

logs: ## Show live logs. You can add argument(s) for specific container(s)
	@$(DOCKER_COMP) logs --tail=30 --follow $(filter-out $@,$(MAKECMDGOALS))

worker-logs: ## Show live logs for workers
	@$(DOCKER_COMP) logs --tail=30 --follow php_worker_submission_low_priority php_worker_submission_high_priority php_worker_competition_status php_worker_email

shell: ## Connect to specified container ( bash )
	@$(eval CONTAINER_TO_CONNECT = $(filter-out $@,$(MAKECMDGOALS)))
	@if [ "$(CONTAINER_TO_CONNECT)" = "php" ]; then \
		$(DOCKER_COMP) exec -u appuser $(CONTAINER_TO_CONNECT) /bin/bash; \
	elif [ "$(CONTAINER_TO_CONNECT)" = "database" ]; then \
		$(DOCKER_COMP) exec $(CONTAINER_TO_CONNECT) psql -h localhost -p 5432 -U app -d app; \
	else \
		$(DOCKER_COMP) exec $(CONTAINER_TO_CONNECT) /bin/bash; \
	fi


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

fix-perms: ## Fix permissions of all files in php
	@$(DOCKER_COMP) exec -e --rm php chown -R 1000:1000 /app

# ## —— Coding standards ✨ ——————————————————————————————————————————————————————
# lint-php: ## Lint files with php-cs-fixer
# 	@$(PHP_CS_FIXER) fix --allow-risky=yes --dry-run --config=php-cs-fixer.php

# fix-php: ## Fix files with php-cs-fixer
# 	@PHP_CS_FIXER_IGNORE_ENV=1 $(PHP_CS_FIXER) fix --allow-risky=yes --config=php-cs-fixer.php


# ## —— Code Quality reports 📊 ——————————————————————————————————————————————————
# report-metrics: ## Run the phpmetrics report
# 	@$(PHPMETRICS) --report-html=var/phpmetrics/ src/