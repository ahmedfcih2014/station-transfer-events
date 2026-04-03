## Station transfer events from an external system. Need to:

- Ingest events safely (idempotent + concurrency-safe).
- Expose an endpoint that returns a reconciliation summary per station.

## Domain entities

TransferEvent

- event_id
- station_id
- amount
- status [approved & other values]
- created_at

## Rules:

- Only status == "approved" counts toward totals.
- Events can arrive duplicated (same event_id ), out of order, and concurrently.
- Assume event_id is globally unique per event.

---

## API requirements

1. POST /transfers

- Accept a JSON, body:

  ```
   {
       "events": [
           {
               "event_id": "...",
               "station_id": "...",
               "amount": 100.5,
               "status": "approved",
               "created_at": "2026-02-19T10:00:00Z"
           }
       ]
   }
  ```

- Behavior:
  - Must be idempotent by event_id :
    - If an event with the same event_id already exists, do not store/overwrite it.
  - Must be concurrency-safe:
    - Concurrent requests containing overlapping event_id s must not double insert.
- Return:

  ```
  {
    "inserted": 7,
    "duplicates": 3
  }
  ```

- Validation expectations:
  - event_id , station_id , status , created_at are required.
  - amount must be a non-negative number.
  - created_at must be parseable as ISO8601.
  - Unknown statuses are allowed but do not count unless "approved" .
- Error handling:
  - If the payload shape is invalid → 400 with a helpful error.
  - You may choose “fail-fast” (reject whole batch) or “partial accept”; document your choice in README.

---

2. GET /stations/{station\*id}/summary

- Return:

  ```
  {
   "station_id": "S1",
   "total_approved_amount": 450.25,
   "events_count": 12
  }
  ```

- Rules:
  - events_count = count of stored events for that station (all statuses), unless you choose “approved only”; again, document your decision.
  - Totals must only sum approved events.

---

## Storage / persistence constraints

- Implement using a store that can be swapped (interface/port).
- For the take-home you can use:
  - In- memory store (thread-safe), OR
  - A lightweight DB (SQLite/Postgres) if you prefer.
- Even if in-memory, design as if it will be persisted:
  - Unique constraint concept on event_id
  - Deterministic reconciliation queries

---

## Concurrency requirements

Your solution must demonstrate that:

- Two concurrent POSTs with the same event_id do not double-insert
- Summary totals are consistent.

You may solve via:

- DB unique constraint + transactional insert, OR
- In-memory locking strategy, OR
- Compare-and-set / atomic primitives, but justify the approach briefly.

---

## Testing requirements (minimum)

Provide automated tests (suggested: 6–10 small tests):

1. Batch insert returns correct inserted/duplicates
2. Duplicate event doesn’t change totals
3. Out-of-order arrival still produces same totals
4. Concurrent ingestion of same IDs doesn’t double count
5. Summary endpoint correctness per station
6. Validation failure behavior (your chosen strategy)
