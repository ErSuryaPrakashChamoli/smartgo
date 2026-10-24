# ADR-0007 — Redis in production; database drivers locally until Redis is installed

* **Status:** ACCEPTED (project owner, 2026-10-24: "Approve all as recommended", D-34)
* **Date:** 2026-10-24

## Context
Redis is requested. Locally, Redis server is not installed and the `phpredis` extension is not loaded. `.env` sets `REDIS_CLIENT=phpredis` but uses `database` drivers for cache, queue and session, which works today.

## Decision
* Production and staging: Redis for cache, sessions, queues (Horizon) and locks, using the **phpredis** extension (installed on the host).
* Local: keep `database` drivers until `redis-server` and `php8.5-redis` are installed (requires the owner's or administrator's system access). `predis/predis` 3.x (pure PHP) is the fallback if the extension cannot be installed.
* Correctness never depends on Redis: booking integrity uses DB locks (ADR-0004).

## Consequences
* This is an environment gap, not a change to the requested architecture.
