# station-transfer-events

This repository demonstrates a small hiring-task API.
It implements a small HTTP API that ingests **station transfer events** from an external system and exposes **per-station reconciliation**: totals sum only **approved** events, while ingestion stays **idempotent** on `event_id` and **safe under concurrent** duplicate or overlapping requests, backed by a swappable store (here, PostgreSQL with unique constraints and transactions).

## How to run tests

- Use **PHP 8.3+**. From the `station-events` project directory, run `php artisan test`.
- **Test vs runtime database:** `phpunit.xml` uses **in-memory SQLite** so CI and local runs need no Postgres instance. **Application defaults** target **PostgreSQL** (see `.env.example`). The ingest SQL uses PostgreSQL-style **`ON CONFLICT … RETURNING`**, which the SQLite version used in tests also accepts; if you point tests at another engine, confirm upsert support matches.

## Tech stack

- **Laravel (PHP)** for the HTTP API—built-in validation, routing, and testing fit the assignment’s JSON contract, with more framework overhead than a minimal script.
- **PostgreSQL** provides ACID transactions and unique constraints on `event_id` for concurrency-safe idempotent inserts; it is heavier to run than SQLite or an in-memory store but closer to how we’d operate this in production.
- **Nginx + PHP-FPM** matches a typical deploy layout; for local development, `php artisan serve` is enough with less setup at the cost of parity with prod.

## [Requirements](docs/assignment.md)

## API Examples

Examples assume the HTTP server is at `http://localhost:8000` (adjust host and port to match how you run the app).

### 1. Store transfers — `POST /api/transfers`

Batch ingest of transfer events (idempotent by `event_id`; see [assignment](docs/assignment.md#accept-a-json-body)).

**Request**

```bash
curl -sS -X POST 'http://localhost:8000/api/transfers' \
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
