# Usage: `make -C docker <target>` from the repo root or `make <target>` from docker/.
DOCKER_DIR := $(dir $(abspath $(lastword $(MAKEFILE_LIST))))
COMPOSE    := docker compose -f $(DOCKER_DIR)compose.yml
UID_GID    := $(shell id -u)
PHP        := $(COMPOSE) exec -T -u $(UID_GID) -e COMPOSER_HOME=/tmp/composer php

.PHONY: build up down console-backend console-frontend rebuild-frontend

build: ## Full backend build: keys, stack, composer, migrations, superadmin, sensor seed
	$(DOCKER_DIR)scripts/gen-keys.sh
	$(COMPOSE) up -d --build
	$(PHP) composer install
	$(PHP) vendor/bin/doctrine-migrations migrate --no-interaction
	$(PHP) bin/console app:superadmin:ensure
	$(PHP) bin/console app:sensors:seed

up: ## Build and start the stack (http://localhost:8080)
	$(COMPOSE) up -d --build

down: ## Stop the stack (DB data is kept)
	$(COMPOSE) down

console-backend: ## Shell in the php container as your uid
	$(COMPOSE) exec -u $(UID_GID) -e COMPOSER_HOME=/tmp/composer php sh

console-frontend: ## Shell in the node container (tools profile)
	$(COMPOSE) run --rm -u $(UID_GID) -e HOME=/tmp -p 5173:5173 node sh

rebuild-frontend: ## tsc + vite build into frontend/dist
	$(COMPOSE) run --rm -T -u $(UID_GID) -e HOME=/tmp node npm run build
