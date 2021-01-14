.PHONY: *

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

run-worker: ## Run the worker component
	docker build --target worker -t ha-php-worker:latest .
	docker run -ti --init --rm --env-file .env ha-php-worker:latest

run-web: ## Run the web UI on port 8080
	docker build --target web -t ha-php-web:latest .
	docker run --rm -p 8080:8080 --env-file .env ha-php-web:latest
