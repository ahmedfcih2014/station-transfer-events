# station-transfer-events

This repository to demonstrate a simple task as a hiring step in PetroApp company

## Decisions

1. **Batch handling:** we use **fail-fast**—if the payload shape is invalid or any event fails [validation](artifacts/assignment.md#L58), we reject the **entire** batch (no partial accept), as allowed in the [assignment error-handling notes](artifacts/assignment.md#L63). The API returns **400** with a helpful error so callers know what to fix before retrying.
   - Fail-fast keeps server behavior and stored data easy to reason about and to extend later (no mixed “some rows saved” states for the same request).
   - For integrating systems, a single success/failure outcome per request simplifies retries and reconciliation: either the whole batch applied or nothing did, and error bodies point at the problem.
2. **Concurrency:** per the [assignment concurrency requirements](artifacts/assignment.md#L99).
   - we must ensure concurrent POSTs with the same `event_id` do not double-insert and that **station summary totals stay consistent**.
   - We implement this with a **database unique constraint on `event_id` plus transactional inserts**—one of the approaches the brief allows.
   - Duplicates surface as constraint violations inside the transaction, so we never persist two rows for the same event and downstream totals remain aligned with stored events
   - This keeps the system state consistent and will fail fast in the application layer without persist the data at the database.
