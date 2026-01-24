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

## Detailed request/maintenance flows

The sections below expand each text diagram into step-by-step explanations of **what happens**, **why it exists**, and **how it avoids race conditions or performance pitfalls**. These are intended to be read alongside the code paths in the `shortener-api` and `kgs-service` apps.

### A) Shorten request — normal fast path (Redis has keys)

**Text diagram**

```
Client
  |
  | 1) POST /api/v1/shorten (original_url, expires_in_days)
  |    Headers: X-API-Key, Content-Type
  v
Shortener API
  |
  | 2) Rate limit check (throttle:shorten) by API key id
  | 3) Validate URL + TTL
  |
  | 4) Request a key from KGS
  v
KGS Service
  |
  | 5) Redis RPOP kgs:pool   (atomic pop)
  |    -> returns "Ab3kZ9"
  |
  | 6) Mark key as USED in KGS DB (short_keys.status=1, used_at=now)
  |    (DB = source of truth)
  v
Shortener API
  |
  | 7) Choose shard: shard = crc32(hash) % N
  | 8) INSERT mapping into shard DB:
  |    urls(hash, original_url, api_key_id, expires_at, clicks=0)
  |
  | 9) Warm cache:
  |    Redis SET url:Ab3kZ9 -> {original_url, expires_at} with TTL
  |
  | 10) Return 201 JSON: short_url = SHORT_DOMAIN/Ab3kZ9
  v
Client
```

**Why this is fast**

- The KGS Redis pop is **O(1) and atomic**, so every API server gets a unique key with no collisions or retries.
- The shortener writes a **single row** to the chosen shard and performs a **single cache write**.
- There is **no “check-then-insert” race**, because the KGS already reserved the key.

**Key implementation links**

- `shortener-api` requests keys from `KgsClient`, then persists to `UrlRepository` and warms cache. The request is orchestrated in `ShortenController`.【F:shortener-api/app/Http/Controllers/ShortenController.php†L1-L33】
- The KGS reserve endpoint is exposed in `kgs-service/routes/api.php` and uses `KgsService::reserveOne()`.【F:kgs-service/routes/api.php†L1-L15】

---

### B) Shorten request — Redis empty (KGS fallback + refill)

**Text diagram**

```
Client -> Shortener API -> KGS
                         |
                         | 1) Redis RPOP kgs:pool  -> NULL (empty)
                         |
                         | 2) Fallback: reserve from DB (transaction)
                         |    SELECT unused key FOR UPDATE
                         |    -> If found, reserve it
                         |
                         | 3) If DB also low/empty:
                         |    ensurePool():
                         |      - lock counter row
                         |      - allocate range [start..end]
                         |      - Base62 encode each number
                         |      - insert keys into short_keys (status=unused)
                         |      - push into Redis list
                         |
                         | 4) Return one key
                         v
Shortener API continues as normal:
  - shard select
  - insert into shard DB
  - cache warm
  - return response
```

**Why this exists**

- **Redis is a volatile cache.** It can be flushed, restarted, or drained during spikes.
- The KGS database is the **source of truth** for which keys exist and whether they are used.
- When Redis is empty, KGS **pulls directly from the DB** so key generation remains correct.

**How the refill stays safe**

- A **counter row lock** prevents two refillers from allocating the same numeric range.
- Base62 encoding turns numeric ranges into short codes without collisions.
- Once inserted to DB, those keys are pushed into Redis to restore fast-path performance.

**Key implementation links**

- `KgsService::ensurePool()` is scheduled to run and can be called on demand via admin route.【F:kgs-service/app/Console/Commands/KgsEnsurePool.php†L1-L20】【F:kgs-service/routes/api.php†L1-L15】
- Pool configuration (Redis key, min/target sizes) lives in `config/kgs.php`.【F:kgs-service/config/kgs.php†L1-L10】

---

### C) Shorten request — 10 API servers at the same time (no race condition)

**Text diagram**

```
Shortener API #1 ----\
Shortener API #2 -----\
Shortener API #3 ------> KGS -> Redis RPOP (atomic) -> unique key each time
...
Shortener API #10 ----/
```

**Why it is race-free**

- Redis `RPOP` is **atomic**, so each concurrent request receives a distinct key.
- The KGS DB is updated to mark keys as used **after** pop, preserving consistency.
- The shortener **never generates keys itself**; it only consumes the KGS output.

---

### D) Redirect request — fast path (cache hit)

**Text diagram**

```
Client
  |
  | 1) GET /Ab3kZ9
  v
Shortener API
  |
  | 2) Rate limit redirect (optional, by IP)
  |
  | 3) Cache lookup:
  |    Redis GET url:Ab3kZ9
  |    -> {original_url, expires_at}
  |
  | 4) If expired -> 410 + delete cache (and optional passive DB delete)
  |
  | 5) Increment clicks (async best; sync shown in our code)
  |
  | 6) 301 Redirect to original_url
  v
Client browser goes to long URL
```

**Why it is fast**

- A cache hit avoids **any DB read**, so latency stays low at high QPS.
- Redis TTL ensures expired URLs are **evicted automatically**.

**Key implementation links**

- Redirect flow is handled in the `shortener-api` controller for redirects and the repository cache helpers.【F:shortener-api/app/Http/Controllers/RedirectController.php†L1-L37】【F:shortener-api/app/Repositories/UrlRepository.php†L1-L78】

---

### E) Redirect request — cache miss (DB read + cache fill)

**Text diagram**

```
Client -> Shortener API
          |
          | 1) Redis GET url:hash -> MISS
          |
          | 2) Choose shard by hash (crc32(hash)%N)
          | 3) SELECT original_url, expires_at FROM urls WHERE hash=...
          |
          | 4) If not found -> 404
          | 5) If expired -> 410 + optional passive delete
          |
          | 6) Cache SET url:hash with TTL until expires_at
          | 7) 301 Redirect
          v
Client
```

**Why shard selection matters**

- Sharding distributes data **horizontally** across multiple MySQL instances.
- Hash-based routing ensures **even distribution** without a central lookup.
- Each redirect only hits **one shard**, keeping reads predictable under load.

---

### F) Expiry cleanup — active job (hourly purge across shards)

**Text diagram**

```
Scheduler (shortener)
  |
  | urls:purge-expired
  v
For each shard connection:
  |
  | 1) SELECT hashes WHERE expires_at < now() LIMIT chunk
  | 2) DELETE those rows
  | 3) Redis DEL url:hash (best effort)
  |
  | Repeat until none left
```

**Why we do this**

- Keeps shard tables **bounded in size**, which keeps indexes fast.
- Prevents old URLs from consuming cache entries.

**Key implementation links**

- The scheduled command is registered in `shortener-api/routes/console.php`.【F:shortener-api/routes/console.php†L1-L13】
- The purge logic lives in `UrlsPurgeExpired`.【F:shortener-api/app/Console/Commands/UrlsPurgeExpired.php†L1-L64】

---

### G) KGS pool maintenance — scheduled job (every minute)

**Text diagram**

```
Scheduler (kgs)
  |
  | kgs:ensure-pool
  v
KGS:
  |
  | 1) Redis LLEN kgs:pool
  | 2) If below min:
  |    - lock counter row
  |    - allocate [start..end]
  |    - Base62 encode
  |    - insert into DB (unused)
  |    - push into Redis pool
```

**Why KGS needs this**

- Guarantees the **fast path** remains available even if traffic spikes.
- Keeps Redis stocked so the shortener never needs to generate keys itself.

**Key implementation links**

- The scheduled command is registered in `kgs-service/routes/console.php`.【F:kgs-service/routes/console.php†L1-L13】
- The command invokes `KgsService::ensurePool()`.【F:kgs-service/app/Console/Commands/KgsEnsurePool.php†L1-L20】

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
