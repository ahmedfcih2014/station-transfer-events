# station-transfer-events

This repository to demonstrate a simple task as a hiring step.
It implements a small HTTP API that ingests **station transfer events** from an external system and exposes **per-station reconciliation**: totals sum only **approved** events, while ingestion stays **idempotent** on `event_id` and **safe under concurrent** duplicate or overlapping requests, backed by a swappable store (here, PostgreSQL with unique constraints and transactions).

## How to run tests

- Locally, make sure you have php 8.3 at least installed on your machine and make sure your terminal is this path: /path-to-root/station-events then execute this command `php artisan test`

## Tech stack

- **Laravel (PHP)** for the HTTP API—built-in validation, routing, and testing fit the assignment’s JSON contract, with more framework overhead than a minimal script.
- **PostgreSQL** provides ACID transactions and unique constraints on `event_id` for concurrency-safe idempotent inserts; it is heavier to run than SQLite or an in-memory store but closer to how we’d operate this in production.
- **Nginx + PHP-FPM** matches a typical deploy layout; for local development, `php artisan serve` is enough with less setup at the cost of parity with prod.

## [Requirements](artifacts/assignment.md)

## API Examples

Examples assume the HTTP server is at `http://localhost:8000` (adjust host and port to match how you run the app).

### 1. Store transfers — `POST /transfers`

Batch ingest of transfer events (idempotent by `event_id`; see [assignment](artifacts/assignment.md#accept-a-json-body)).

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

**Response** `200 OK` — [documented return shape](artifacts/assignment.md#return):

```json
{
  "inserted": 7,
  "duplicates": 3
}
```

Invalid or failing validation → **`400`** with a helpful error ([error handling](artifacts/assignment.md#error-handling)).

### 2. Summarize station transfers — `GET /stations/{station_id}/summary`

**Request**

```bash
curl -sS 'http://localhost:8000/stations/S1/summary'
```

**Response** `200 OK` — [documented return shape](artifacts/assignment.md#return-1):

```json
{
  "station_id": "S1",
  "total_approved_amount": 450.25,
  "events_count": 12
}
```

---

## Design Notes

1. **Batch handling:** we use **fail-fast**—if the payload shape is invalid or any event fails [validation](artifacts/assignment.md#validation-expectations), we reject the **entire** batch (no partial accept), as allowed in the [assignment error-handling notes](artifacts/assignment.md#error-handling). The API returns **400** with a helpful error so callers know what to fix before retrying.
   - Fail-fast keeps server behavior and stored data easy to reason about and to extend later (no mixed “some rows saved” states for the same request).
   - For integrating systems, a single success/failure outcome per request simplifies retries and reconciliation: either the whole batch applied or nothing did, and error bodies point at the problem.
2. **Concurrency:** per the [assignment concurrency requirements](artifacts/assignment.md#concurrency-requirements).
   - we must ensure concurrent POSTs with the same `event_id` do not double-insert and that **station summary totals stay consistent**.
   - We implement this with a **database unique constraint on `event_id` plus transactional inserts**—one of the approaches the brief allows.
   - Duplicates surface as constraint violations inside the transaction, so we never persist two rows for the same event and downstream totals remain aligned with stored events
   - This keeps the system state consistent and will fail fast in the application layer without persist the data at the database.
3. Will use a database storage like `mysql`/`postgres` both support ACID both has a powerful features for scaling up the system.
4. **Summaries and what we persist:** the [GET summary rules](artifacts/assignment.md#rules-1) require `total_approved_amount` to sum **only** `approved` events.
   - For `events_count`, the brief allows either all stored rows per station or approved-only; **we count only stored rows**, and because we **persist only `approved` events**, `events_count` matches approved events for that station.
   - Ingest accepts other statuses for validation, but **non-approved events are not stored**—they do not affect totals or counts—so we never need to change a row’s status later.
   - There is **no update API** for events; POST remains idempotent on `event_id` (no overwrite), which fits “write once” approved-only storage.
5. **Idempotency** as mentioned in point 2 above, I'll handle the Idempotency depending on the DB unique constraint plus transaction inserts by using `INSERT OR IGNORE` / `ON CONFLICT DO NOTHING` depends on our final database storage decision.
