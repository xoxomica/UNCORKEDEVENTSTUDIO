# Uncorked thesis readiness audit

Audit date: 21 October 2026 (Asia/Manila).

Follow-up: F1 and F2 have been addressed in code. Status changes to Pending, Confirmed, or Completed now recheck retained staff assignments inside the transaction. Sales record collection timestamps separately from order dates; repeated settlement preserves the original collection timestamp. Legacy paid sales keep unknown collection dates and are disclosed separately in period reports. Regression checks cover these scenarios, and a legacy-schema migration test checks preservation on repeated opens. Remaining findings below still apply; the original audit findings are retained as the rationale for the fixes.

## Scope and conclusion

Reviewed README, architecture/operator documentation, database schema, service rules, reservation endpoint, reports, CSV export, backup and restoration implementation, and existing tests. This is a functional/code audit, not a visual accessibility review or a production certification.

No approved thesis proposal/objectives were found in README or docs. The coverage below uses the documented purpose, "Online Reservation with Integrated Business Operations Management," as a provisional baseline. The adviser-approved objectives and actual client policies still need to be mapped before claiming thesis compliance.

The project already implements the main operational workflow. Prioritize the correctness gaps below before adding external automation or AI.

## Verification at original audit

- 43 existing integration checks passed using an isolated temporary database.
- 44 existing HTTP checks passed using a temporary PHP server and database. Local socket restrictions required running this suite outside the sandbox. The actual business database was not used.
- All PHP files in app, public, database, and tests passed PHP syntax checks.
- Two additional temporary database probes reproduced findings F1 and F2 below. Probe data was removed.
- Simultaneous multi-process booking/stock/payment requests were not exercised. BEGIN IMMEDIATE transactions are present, but sequential tests alone do not prove concurrent behavior.
- The backup download was tested; a restore-and-reopen round trip was not tested.

## Follow-up verification

- 74 integration checks and two legacy migration checks passed after F1/F2 fixes.
- Expanded HTTP checks cover all-table comparison after restoring a downloaded backup, application reopening, preservation of a pre-restore safety copy, and rejection of corrupt/incomplete backups without changing the destination.
- All Manager and Staff module GET permissions and restricted-module POST permissions are exercised, along with direct backup/export access and immediate account deactivation. Allowed write actions are not exhaustively tested for every role.
- 17 multi-process concurrency checks passed: exactly one competing overlapping booking, last-unit sale, or full-balance payment succeeds. Losing requests fail for the expected business rule, and resulting records and database integrity reconcile.
- Restore now honors the same `UNCORKED_DB_PATH` override as the application so recovery tests cannot target business data accidentally.
- These checks address the targeted F4 evidence gaps; they do not replace client acceptance, production load testing, or validation of whole-venue/table rules (F3) and approved objectives (F5).

## Provisional module coverage

| Area | Evidence | Remaining work / limit |
|---|---|---|
| Online reservations | HTTP submission, tracking, confirmation, availability checks passed | Opening hours, setup/cleanup buffers, and client cancellation rules require validation |
| Venue capacity and conflicts | Capacity, overlap rejection, adjacent events, independent venues covered by integration tests | Whole-venue/table relationships are not modeled; concurrency still needs testing |
| Customers and packages | Customer creation, selected package total, price snapshots implemented | Validate actual package pricing and business terminology with client |
| Inventory, purchases, sales | Receipt adds stock; sale deducts it; overselling and negative adjustment rejected | Returns, reversals, recipe/package consumption, and multi-item checkout are outside current implementation |
| Event collections | Deposit, remaining balance, overpayment rejection tested | Refund/cancellation settlement rules absent; no online transfer processing |
| Employees and duties | Creation and overlapping assignment rejection tested | Event reactivation can bypass staff conflict checks (F1) |
| Reports and export | Reports render, CSV exports, event statement balance tested | Bar collection period is inaccurate for late settlement (F2); report totals need broader assertions |
| Access and audit | Role helper checks, CSRF rejection, signed-out backup denial passed | Full HTTP permission matrix across all roles/actions not covered; audit detail needs review |
| Backup/recovery | Admin backup produces SQLite file; restore utility exists | Actual restoration and data reconciliation need a dedicated isolated test |
| Alerts | Alert module renders; docs describe pending/upcoming/stock/balance lists | Email/SMS delivery is not implemented |

## Findings and implementation order

### F1 — High: reactivating an event can double-book an employee

Locations: app/services/Operations.php (change_status), app/services/AdminActions.php (assignment).

Reproduced sequence: assign an employee to event A; cancel A; assign the same employee to overlapping event B at another venue; confirm B; confirm A. Both events become Confirmed with overlapping assignments for that employee.

The assignment action excludes Cancelled/Archived events from conflict checks, but change_status validates only the venue/capacity when restoring an event. The existing overlap test covers assignment creation, not reactivation.

Recommended fix: revalidate retained staff assignments whenever a status transition makes them blocking again, including a return to Pending. Keep validation and status mutation in the same transaction, and reject conflicts without partial changes. Add regression coverage for Cancelled/Archived reactivation and independent employees.

### F2 — High: late bar-sale settlement changes historical collection figures

Locations: app/services/AdminActions.php (settle), app/views-reports.php (paid bar sales), database/schema.sql (sales).

Reproduced: an Unpaid sale with created_at in September was settled later; settlement changed only status to Paid and retained September's created_at. The report filters Paid rows by created_at, so the collection is attributed to September, not the payment date. Documentation describes these figures as collections by transaction date.

Recommended fix: persist settlement time or a dedicated collection record, update settlement atomically, and define separate sale-date and collection-date reports. Decide how legacy Paid rows with unknown collection dates should be represented; do not invent historical dates. Test sales created and paid in different periods, initial Paid sales, and repeated settlement.

### F3 — Conditional high: whole-venue bookings do not block separate table records

Locations: app/services/Operations.php and public/api.php (same venue_id overlap checks); docs/USER-GUIDE.md explicitly documents this boundary.

Two independent venue records may overlap. If the client models both the entire venue and its tables as separate records, a whole-venue booking will not automatically block its tables. This is a documented limitation, not a reproduced failure of the intended independent-resource behavior.

Validate whether Uncorked actually sells those overlapping resources. If yes, define parent/child or resource-blocking rules before accepting such bookings. If no, keep the simpler independent-resource model.

### F4 — Medium: recovery and concurrent operation lack verification evidence

Add isolated backup -> restore -> reopen tests and compare reservations, payments, movements, users, and schema. Exercise real competing database connections for the last stock unit, overlapping confirmation, and remaining event balance. These are evidence gaps, not observed data corruption.

### F5 — Medium: approved objectives and client acceptance evidence are missing

Create a traceability matrix after obtaining the approved objectives: objective -> module -> acceptance scenario -> test/result -> client validation. Confirm hours, buffers, table/venue relationships, cancellation/refund policies, prices, and operator roles. No finding here establishes whether the thesis currently meets school requirements.

## Recommended next batch

1. Fix F1 and F2 with regression coverage.
2. Resolve venue/table rules with the client, then implement F3 only if applicable.
3. Verify recovery, concurrent transactions, report reconciliation, and role restrictions.
4. Map approved thesis objectives and run client acceptance scenarios.

No additional runtime library is required for F1/F2. System Design Primer can remain an architecture reference and Gitleaks a development tool. Defer Windmill and Transformers until a confirmed requirement justifies their integration and evaluation effort. No new library was installed during this audit.

Suggested evaluation measures: booking conflict rejection correctness, task completion rate, booking processing time versus the client's baseline, inventory reconciliation accuracy, collection report accuracy, and successful backup recovery. These are proposed measures, not measured improvements.
