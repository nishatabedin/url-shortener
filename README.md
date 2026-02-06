# URL Shortener Microservices (Laravel)

This repo runs two Laravel services with Docker Compose:

- **shortener-api**: public API for creating short URLs and redirecting.
- **kgs-service**: key-generation service for unique short codes.

It also includes **observability** (OpenTelemetry Collector, Prometheus, Grafana, Loki/Promtail, Jaeger) and **load testing** (k6) with a DEV vs PROD-like setup.

## Quick start

### DEV mode (hot-reload & debug)
```bash
make up-dev
```

If you haven't installed PHP dependencies locally yet, run:
```bash
cd shortener-api && composer install
cd ../kgs-service && composer install
```

### PROD-like mode (cached + opcache enabled)
```bash
make up-prod
```

### Stop everything
```bash
make down
```

### Follow logs
```bash
make logs
```

### Run tests (shortener-api)
```bash
make test
```

### Run k6 load tests
```bash
# smoke test (default)
make load-test

# ramping load test
make load-test K6_SCRIPT=load-ramping.js

# spike test
make load-test K6_SCRIPT=spike.js
```

> **Note:** `make load-test` uses Docker Compose networking, so it defaults to `K6_BASE_URL=http://shortener-dev` (or `shortener-prod` if you override it).

## Services & ports

| Service | Dev Port | Notes |
| --- | --- | --- |
| shortener-api | 8082 | API + redirects |
| kgs-service | 8081 | internal service |
| mysql | 3307 | shared DB |
| redis | 6379 | shared cache |
| grafana | 3000 | dashboards |
| prometheus | 9090 | metrics |
| jaeger | 16686 | traces |
| otel-collector | 4318 | OTLP/HTTP ingest (gRPC is internal) |
| loki | 3100 | logs |
| phpMyAdmin (dev only) | 8083 | DB UI |

## Endpoints

- `GET /healthz` – liveness
- `GET /readyz` – readiness (checks MySQL + Redis)
- `GET /metrics` – Prometheus scrape endpoint
- `POST /api/v1/shorten` – create short URL (requires `X-API-Key` + `Idempotency-Key`)

## Environment modes

The repository uses Compose profiles and separate env files:

- **DEV:** `APP_DEBUG=true`, Telescope enabled, verbose logging
- **PROD:** `APP_DEBUG=false`, caches + optimized autoloader + opcache enabled at container start

Env files (do **not** bake secrets into images):

- `shortener-api/.env.dev`, `shortener-api/.env.prod`
- `kgs-service/.env.dev`, `kgs-service/.env.prod`

## Folder structure

```
infra/observability/     # Prometheus, Grafana, Loki, Promtail configs
k6/                      # load test scripts
kgs-service/             # Laravel service for key generation
shortener-api/           # Laravel service for short URLs
mysql-init/              # DB init SQL
```

## Documentation

See `architecture-doc/README.md` for architecture overview, diagrams, and data flow details.

## OpenTelemetry tracing notes

- Both Laravel services use the OpenTelemetry PHP auto-instrumentation package and emit traces to the OpenTelemetry Collector over OTLP gRPC.
- The collector forwards traces to Jaeger for storage and visualization in Grafana.
- Metrics remain exported via the existing Prometheus middleware, and logs remain shipped via Promtail to Loki.

Key environment variables (already set in `.env.dev` / `.env.prod`):

- `OTEL_PHP_AUTOLOAD_ENABLED=true`
- `OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317`
- `OTEL_EXPORTER_OTLP_PROTOCOL=grpc`
- `OTEL_TRACES_EXPORTER=otlp`
