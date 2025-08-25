.PHONY: help start
.DEFAULT_GOAL := help

.ONESHELL:
SHELL := bash

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| sort \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

start: ## Start the development server with Octane and Chrome DevTools support
	(
	  CADDY_SERVER_EXTRA_DIRECTIVES='@devtoolsjson path /.well-known/appspecific/com.chrome.devtools.json reverse_proxy @devtoolsjson localhost:5173' frankenphp php-cli artisan octane:start --watch --host=localhost &
	  bun run dev &
	  wait
	)
