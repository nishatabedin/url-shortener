COMPOSE ?= docker compose
K6_SCRIPT ?= smoke.js
K6_BASE_URL ?= http://shortener-dev
K6_API_KEY ?= local-dev-key

.PHONY: up-dev up-prod down logs test load-test

up-dev:
	$(COMPOSE) --profile dev --profile observability up -d

up-prod:
	$(COMPOSE) --profile prod --profile observability up -d

down:
	$(COMPOSE) --profile dev --profile prod --profile observability --profile loadtest down

logs:
	$(COMPOSE) logs -f --tail=100

test:
	$(COMPOSE) --profile dev exec shortener-dev php artisan test

load-test:
	$(COMPOSE) --profile loadtest run --rm \
		-e K6_BASE_URL=$(K6_BASE_URL) \
		-e K6_API_KEY=$(K6_API_KEY) \
		k6 run /scripts/$(K6_SCRIPT)
