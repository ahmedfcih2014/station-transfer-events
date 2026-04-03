# station-transfer-events

This repository to demonstrate a simple task as a hiring step in PetroApp company

## Decisions

1. **Batch handling:** we use **fail-fast**—if the payload shape is invalid or any event fails [validation](artifacts/assignment.md#L58), we reject the **entire** batch (no partial accept), as allowed in the [assignment error-handling notes](artifacts/assignment.md#L63). The API returns **400** with a helpful error so callers know what to fix before retrying.
   - Fail-fast keeps server behavior and stored data easy to reason about and to extend later (no mixed “some rows saved” states for the same request).
   - For integrating systems, a single success/failure outcome per request simplifies retries and reconciliation: either the whole batch applied or nothing did, and error bodies point at the problem.
