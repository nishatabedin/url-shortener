# Architecture Documentation

## Overview

This repository contains a Laravel-based microservice architecture that provides URL shortening with production-ready observability and load testing.

### Services

- **shortener-api**: Public API for creating short URLs. Writes to MySQL and caches in Redis. Handles redirects.
- **kgs-service**: Internal key generation service to reserve unique short codes.
- **MySQL**: Primary persistence for URLs, API keys, and idempotency records.
- **Redis**: Cache for hot URL lookups and idempotency locks.
- **Observability stack**: Prometheus, Grafana, Loki + Promtail, Jaeger.
- **k6**: Load testing runner.

## High-level architecture (text diagram)

```
                        +-------------------+
                        |   Grafana UI      |
                        |  (Dashboards)     |
                        +---------+---------+
                                  |
                                  | reads
                                  v
+-----------+   /metrics   +---------------+     traces     +------------------+
| shortener |------------->|  Prometheus   |<-------------->|      Jaeger      |
|  service  |              +---------------+                +------------------+
|           |   logs               ^
|           |----------------------|
+-----------+                      |
                                  |
+-----------+   /metrics   +---------------+     logs        +------------------+
|   kgs     |------------->|  Prometheus   |<--------------- |   Loki/Promtail  |
|  service  |              +---------------+                +------------------+
+-----------+

        +-----------------+       +----------------+
        |     MySQL       |<----->|    Redis        |
        +-----------------+       +----------------+
```

## Request flow (data flow diagram)

```
Client
  |
  | POST /api/v1/shorten (X-API-Key + Idempotency-Key)
  v
shortener-api
  |-- validates API key
  |-- idempotency middleware
  |-- reserves key from kgs-service
  |-- writes URL record to MySQL
  |-- caches URL in Redis
  v
Response (short URL)
```

## Idempotency flow (data preparation)

```
Idempotency-Key (header)
  |
  v
Hash(method + path + query + body)
  |
  v
Lookup in idempotency_keys table
  |-- same key + same hash -> return stored response
  |-- same key + different hash -> 409 conflict
  |-- no record -> lock in Redis + process + persist response
```

## Observability flow

```
Request
  |-> RequestIdMiddleware generates X-Request-Id
  |-> logs include request_id + trace_id (if available)
  |-> PrometheusMetricsMiddleware records latency + status
  |-> DB listener records query durations
  v
/metrics endpoint exposes Prometheus format
```

## Docker profiles

- **dev**: bind mounts, Telescope enabled, APP_DEBUG=true
- **prod**: opcache + caches enabled at container start, APP_DEBUG=false
- **observability**: Prometheus, Grafana, Loki/Promtail, Jaeger
- **loadtest**: k6 runner container

## Files created for this architecture (not Laravel defaults)

### Root
- `docker-compose.yml` — Profiles for dev/prod/observability/loadtest.
- `Makefile` — Common commands (`up-dev`, `up-prod`, `down`, `logs`, `test`, `load-test`).
- `infra/observability/*` — Prometheus, Grafana provisioning + dashboards, Loki, Promtail.
- `k6/*` — smoke/ramping/spike load test scripts.
- `architecture-doc/README.md` — this document.

### shortener-api
- `Dockerfile.dev` — Dev image (no composer install, relies on bind mount).
- `app/Http/Middleware/RequestIdMiddleware.php` — generates/propagates `X-Request-Id`.
- `app/Http/Middleware/PrometheusMetricsMiddleware.php` — captures HTTP metrics.
- `app/Http/Middleware/IdempotencyMiddleware.php` — wraps POST requests with idempotency.
- `app/Observability/TraceContext.php` — pulls trace id from OTel spans.
- `app/Observability/Metrics/PrometheusMetrics.php` — Prometheus registry + metric helpers.
- `app/Http/Controllers/Observability/MetricsController.php` — `/metrics` endpoint.
- `app/Providers/ObservabilityServiceProvider.php` — registers metrics + DB query listener.
- `app/Providers/TelescopeServiceProvider.php` + `config/telescope.php` — Telescope integration.
- `config/observability.php` — service name config for metrics/tracing.
- `config/idempotency.php` — TTL and lock settings.
- `database/migrations/2025_01_01_000001_create_idempotency_keys_table.php` — idempotency table.
- `app/Models/IdempotencyKey.php` — Eloquent model for idempotency records.
- `docker/entrypoint.sh` — enables caches/opcache in production.
- `docker/opcache.ini` — opcache tuning.
- `.dockerignore` — keeps secrets/artefacts out of images.
- `.env.dev` / `.env.prod` — environment-specific configs.

### kgs-service
- `Dockerfile.dev` — Dev image (no composer install, relies on bind mount).
- `app/Http/Middleware/RequestIdMiddleware.php` — generates/propagates `X-Request-Id`.
- `app/Http/Middleware/PrometheusMetricsMiddleware.php` — captures HTTP metrics.
- `app/Observability/TraceContext.php` — pulls trace id from OTel spans.
- `app/Observability/Metrics/PrometheusMetrics.php` — Prometheus registry + metric helpers.
- `app/Http/Controllers/Observability/MetricsController.php` — `/metrics` endpoint.
- `app/Providers/ObservabilityServiceProvider.php` — registers metrics + DB query listener.
- `app/Providers/TelescopeServiceProvider.php` + `config/telescope.php` — Telescope integration.
- `config/observability.php` — service name config for metrics/tracing.
- `docker/entrypoint.sh` — enables caches/opcache in production.
- `docker/opcache.ini` — opcache tuning.
- `.dockerignore` — keeps secrets/artefacts out of images.
- `.env.dev` / `.env.prod` — environment-specific configs.

## Recommended operational notes

- Use `.env.prod` for production-like runs locally. Keep secrets out of images and supply them via environment variables or a secrets manager in real deployments.
- Ensure migrations are executed for idempotency support (`php artisan migrate`).
- Metrics endpoints should be protected in real production networks (e.g., internal-only ingress).
