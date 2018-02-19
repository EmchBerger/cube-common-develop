
.DEFAULT_GOAL := help

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*?## "}; /^[a-zA-Z-]+:.*?## .*$$/ {printf "\033[32m%-15s\033[0m %s\n", $$1, $$2}' Makefile | sort

# specific to cube-common-develop
thisFilesDir = $(dir $(lastword $(MAKEFILE_LIST)))
cubeDevDir = $(thisFilesDir)
checkScript = $(cubeDevDir)/src/CodeStyle/check-commit-cube.sh

###> cubetools/cube-common-develop ###

# development helpers
# ===================

checkScript ?= vendor/bin/check-commit-cube.sh
composer ?= $(shell which composer || echo composer.phar)
syConsole ?= bin/console
cubeDevDir ?= vendor/cubetools/cube-common-develop/

# commit checks

check-lastCommit: ## check last done (HEAD) commit
	$(checkScript) HEAD
.PHONY: check-lastCommit

check-changes: ## check changed files
	$(checkScript) --changed
.PHONY: check-changes

check-precommit: ## check all added files (after git add)
	$(checkScript)
.PHONY: check-precommit

check-branch: ## checks all files changed since origin/development
	$(checkScript) origin/development..
.PHONY: check-branch

# validate targets

validate-stan: ## runs phpstan (missing variables, wrong case, ...)
	./vendor/bin/phpstan analyse -c .phpstan.neon src/
.PHONY: validate-stan

validate-codestyle: ## runs phpcs (code style)
	./vendor/bin/phpcs --colors src/
.PHONY: validate-codestyle

validate-cs-fixer: ## runs php-cs-fixer (code style)
	./vendor/bin/php-cs-fixer fix -v --ansi --dry-run --diff
.PHONY: validate-cs-fixer

validate-all: validate-codestyle validate-cs-fixer validate-stan ## runs all validation-* commands
.PHONY: validate-all

# general

update-makefile-from-cube-common-bundle: ## update the makefile section from cube-common-bundle

include $(cubeDevDir)/src/Workplace/Makefile.include.cube

###< cubetools/cube-common-develop ###
