# URL Shortener Microservices (Laravel)

This repo runs two Laravel services with Docker Compose:

- **shortener-api**: public API for creating short URLs and redirecting.
- **kgs-service**: key-generation service for unique short codes.

It also includes **observability** (Prometheus, Grafana, Loki/Promtail, Jaeger) and **load testing** (k6) with a DEV vs PROD-like setup.

## Quick start

### DEV mode (hot-reload & debug)
```bash
make up-dev
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
