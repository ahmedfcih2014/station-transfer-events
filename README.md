# station-transfer-events

This repository to demonstrate a simple task as a hiring step in PetroApp company

## Decisions

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
