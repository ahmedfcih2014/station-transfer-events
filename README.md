# station-transfer-events

This repository demonstrates a small hiring-task API.
It implements a small HTTP API that ingests **station transfer events** from an external system and exposes **per-station reconciliation**: totals sum only **approved** events, while ingestion stays **idempotent** on `event_id` and **safe under concurrent** duplicate or overlapping requests, backed by a swappable store (here, PostgreSQL with unique constraints and transactions).

## Tech stack

- **Laravel (PHP)** for the HTTP API—built-in validation, routing, and testing fit the assignment’s JSON contract, with more framework overhead than a minimal script.
- **PostgreSQL** provides ACID transactions and unique constraints on `event_id` for concurrency-safe idempotent inserts; it is heavier to run than SQLite or an in-memory store but closer to how we’d operate this in production.
- **Nginx + PHP-FPM** matches a typical deploy layout; for local development, `php artisan serve` is enough with less setup at the cost of parity with prod.

## [Requirements](docs/assignment.md)

## How to run project locally

The Laravel app lives in **`station-events/`** (not the repo root).

1. Install **PHP 8.3+**, **Composer**, and **PostgreSQL**. Enable PHP’s **PostgreSQL PDO** extension (`pdo_pgsql`—on macOS with Homebrew PHP, it is usually built in; otherwise install/enable it so Laravel can connect).
2. Clone this repository.
3. `cd station-events` and run `composer install`.
4. Start PostgreSQL.
5. Create an empty database (the default in `.env.example` is `station_events`; use that name or change `DB_DATABASE` in `.env` to match what you created).
6. Run `cp .env.example .env`.
7. Edit `.env` and set the database credentials (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
8. Run `php artisan key:generate`.
9. Run `php artisan migrate`.
10. Run `php artisan serve` (default URL `http://127.0.0.1:8000`).
11. Try the [curl examples](#api-examples) below. You do **not** need Node/npm for the HTTP API alone; they are only used if you work on the bundled front-end assets.

Redis and a queue worker are **not** required for local API use (`.env.example` uses the database for cache, sessions, and queues, and migrations create those tables).

## How to run with Docker

The repo root contains **`docker-compose.yml`**: **Nginx**, **PHP-FPM** (Laravel in `station-events/`), and **PostgreSQL**. You need **Docker** and **Docker Compose**.

From the **repository root** (not `station-events/`):

1. `docker compose up -d --build`
2. `docker compose exec app php artisan migrate --force`
3. Open the API at **`http://localhost:8080`** (Nginx maps host port **`8080`** to the app by default).

Compose sets `DB_HOST=postgres` and database credentials for you. To use another host port, set **`HTTP_PORT`** (for example `HTTP_PORT=3000 docker compose up -d`). You can override `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`, and related variables the same way; keep app and Postgres values in sync.

To stop containers: `docker compose down`. To remove the Postgres data volume as well: `docker compose down -v`.

The HTTP API paths match the Laravel app (there is **no** `/api` prefix—see `bootstrap/app.php`). Example: `GET http://localhost:8080/stations/S1/summary` and `POST http://localhost:8080/transfers`.

The app image is built with **PHP 8.4** to match the current `composer.lock`. The `app` service bind-mounts `./station-events`; on first run, if `vendor/` is missing, the container installs Composer dependencies (including a usable autoloader for the API).

## How to run tests

- Use **PHP 8.3+** (or run tests **in Docker**—see below). From the `station-events` project directory, run `php artisan test`.
- **Test vs runtime database:** `phpunit.xml` uses **in-memory SQLite** so CI and local runs need no Postgres instance. **Application defaults** target **PostgreSQL** (see `.env.example`). The ingest SQL uses PostgreSQL-style **`ON CONFLICT … RETURNING`**, which the SQLite version used in tests also accepts; if you point tests at another engine, confirm upsert support matches.
- **Concurrent ingestion test:** `tests/Feature/ConcurrentIngestionTest.php` runs two workers via Laravel’s **Concurrency** process driver. Workers do not share `:memory:` SQLite, so that test switches to a **temporary file-backed SQLite** database for its duration only.

### Running tests in Docker

From the **repository root**, with the **`app`** container running after `docker compose up -d`:

```bash
docker compose exec app php artisan test
```

Tests still use **`phpunit.xml`** (SQLite in memory); you do **not** need Postgres for the test suite itself.

To run tests **without** starting Postgres or Nginx (only the `app` image and bind mount):

```bash
docker compose run --rm --no-deps app php artisan test
```

**Dev dependencies (Pest, PHPUnit, and so on):** the production-oriented Docker **build** runs `composer install --no-dev`. After `docker compose up`, your bind-mounted `station-events` directory is what the container sees. If `vendor/` is empty, the entrypoint runs a full `composer install` (including dev). If you already have a `vendor/` tree produced with **`composer install --no-dev`** on the host, run **`docker compose exec app composer install`** once so test tooling is present before `php artisan test`.

**Compose `DB_*` vs tests:** `docker-compose.yml` exports PostgreSQL settings into the container. `phpunit.xml` forces SQLite for the test run (`force="true"` on the relevant `<env>` entries) and mirrors the database settings into `<server>` so subprocess env (used by Laravel’s **Concurrency** workers) still sees `DB_CONNECTION=sqlite`. Without that, workers could ignore PHPUnit’s SQLite config and try Postgres using a temp file path as the database name.

## API Examples

Examples assume the HTTP server is at `http://localhost:8000` (adjust host and port to match how you run the app).

### 1. Store transfers — `POST /transfers`

Batch ingest of transfer events (idempotent by `event_id`; see [assignment](docs/assignment.md#accept-a-json-body)).

**Request**

```bash
curl -sS -X POST 'http://localhost:8000/transfers' \
  -H 'Content-Type: application/json' \
  -d '{
    "events": [
      {
        "event_id": "evt-001",
        "station_id": "S1",
        "amount": 100.5,
        "status": "approved",
        "created_at": "2026-02-19T10:00:00Z"
      }
    ]
  }'
```

**Response** `200 OK` — [documented return shape](docs/assignment.md#return):

```json
{
  "inserted": 7,
  "duplicates": 3
}
```

Invalid or failing validation → **`400`** with a helpful error ([error handling](docs/assignment.md#error-handling)).

### 2. Summarize station transfers — `GET /api/stations/{station_id}/summary`

**Request**

```bash
curl -sS 'http://localhost:8000/api/stations/S1/summary'
```

**Response** `200 OK` — [documented return shape](docs/assignment.md#return-1):

```json
{
  "station_id": "S1",
  "total_approved_amount": 450.25,
  "events_count": 12
}
```

---

## Design Notes

1. **Batch handling:** we use **fail-fast**—if the payload shape is invalid or any event fails [validation](docs/assignment.md#validation-expectations), we reject the **entire** batch (no partial accept), as allowed in the [assignment error-handling notes](docs/assignment.md#error-handling). The API returns **400** with a helpful error so callers know what to fix before retrying.
   - Fail-fast keeps server behavior and stored data easy to reason about and to extend later (no mixed “some rows saved” states for the same request).
   - For integrating systems, a single success/failure outcome per request simplifies retries and reconciliation: either the whole batch applied or nothing did, and error bodies point at the problem.
2. **Concurrency:** per the [assignment concurrency requirements](docs/assignment.md#concurrency-requirements).
   - Concurrent POSTs that reuse the same `event_id` must not insert a second row; **station summaries** must stay aligned with stored events.
   - We use a **unique constraint on `event_id`** and a **transactional bulk `INSERT`** with **`ON CONFLICT DO NOTHING`**: conflicts are handled **inside the database**—the existing row stays as-is, no duplicate row is written, and the request can still complete successfully (see point 5 for how **`duplicates`** is reported).
   - **Validation failures** (malformed payload, invalid fields) return **400** and run **no insert**—that is **fail-fast in the app layer before the database**. That is separate from idempotency: a **valid** batch may include `event_id`s already stored; those are not HTTP errors; they increment **`duplicates`** instead of **`inserted`**.
3. **Database choice:** MySQL and PostgreSQL both provide ACID transactions and are typical choices for scaling this kind of service. **This project defaults to PostgreSQL** (see `.env.example`). The repository uses PostgreSQL-compatible **`ON CONFLICT … DO NOTHING`** and **`RETURNING`**; swapping engines requires equivalent upsert behavior in the repository layer.
4. **Summaries and what we persist:** the [GET summary rules](docs/assignment.md#rules-1) require `total_approved_amount` to sum **only** `approved` events.
   - For `events_count`, the brief allows either all stored rows per station or approved-only; **we count only stored rows**, and because we **persist only `approved` events**, `events_count` matches approved events for that station.
   - Ingest accepts other statuses for validation, but **non-approved events are not stored**—they do not affect totals or counts—so we never need to change a row’s status later.
   - There is **no update API** for events; POST remains idempotent on `event_id` (no overwrite), which fits “write once” approved-only storage.
5. **POST response counts (`inserted` / `duplicates`) for mixed batches:** validation applies to **every** event in the batch, but only **`approved`** events are written. Counters refer to that write path only:
   - **`inserted`** — how many **approved** events became new rows in this request (after `ON CONFLICT` / existing-key handling).
   - **`duplicates`** — duplicate `event_id` values **among approved events in the same request**, plus approved `event_id`s that **already existed** in the store (idempotent replay).
   - **Non-approved** events (any status other than `approved`) are **skipped** for persistence: they do **not** increase `inserted` and are **not** counted as `duplicates`, because nothing is inserted for them and they are not failed duplicate-key writes.
   - A batch that is **entirely** non-approved still returns **`200`** with `inserted: 0` and `duplicates: 0` (no insert statement runs).
6. **Idempotency (mechanics):** Behavior is described in point 2. The implementation is a transactional **bulk insert** with **`ON CONFLICT (event_id) DO NOTHING`**, counting rows returned by **`RETURNING`**. Other databases express the same idea with different syntax (for example MySQL’s upsert variants); keep semantics aligned if you change the driver.
