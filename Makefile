.PHONY: *

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Build em
	docker build --target worker -t ha-php-worker:latest .
	docker build --target web -t ha-php-web:latest .

run-worker: build ## Run the worker component
	docker run -ti --init --rm --env-file .env ha-php-worker:latest

run-web: build ## Run the web UI on port 8080
	docker run --rm -p 8080:8080 --env-file .env ha-php-web:latest

push: check-docker-registry-defined build ## Push the images to the specified Docker registry (requires DOCKER_REGISTRY argument)
	docker tag ha-php-worker:latest $(DOCKER_REGISTRY)/ha-php-worker:latest
	docker tag ha-php-web:latest $(DOCKER_REGISTRY)/ha-php-web:latest
	docker push $(DOCKER_REGISTRY)/ha-php-worker:latest
	docker push $(DOCKER_REGISTRY)/ha-php-web:latest

check-docker-registry-defined:
ifndef DOCKER_REGISTRY
	$(error DOCKER_REGISTRY is undefined)
endif
