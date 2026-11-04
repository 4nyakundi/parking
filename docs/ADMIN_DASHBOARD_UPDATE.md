# Admin dashboard repairs and operational overview

Implemented 4 November 2026. The existing PHP/JavaScript dashboard and blue styling are retained. No additional database migration is required for an installation already running the Data Bank update.

## What changed

- Settings uses the actual form IDs and the API's `update` action with a nested `settings` object. Missing display defaults are populated, numeric values are validated before any write, and updates are committed together. Capacity affects the live overview. The saved long-stay review threshold supplies the default reconciliation filter. The live free-parking overstay limit remains two hours, matching the guard roster. Hardware/service connection values are saved and read back for reference; this change does not make camera, printer or cloud workers consume new configuration sources.
- Reports reads the visible date inputs and renders the API's summary, destination visits and guard activity. Exits and average dwell use the departure date, so overnight departures count in the correct period. Cancelled arrivals are excluded. Hourly traffic includes all 24 hours. CSV and session lists share one validated filter builder, including issue, plate, ticket, status, destination, dates and guard. Formula-like spreadsheet cells are escaped.
- Overview uses a consistent database snapshot, the same daily arrival helper as the guard dashboard, registered/unregistered counts, links to exceptions, recorded device freshness, latest recorded backup and latest shift sign-off. The calendar lists visits present during a date, including overnight stays, and distinguishes visits from unique plates and movements.
- Session issue filters show overstays, print failures, message failures, missing details and unsynced sessions. Driver Requests has a status filter and paging. Audit Log has action/entity filters and paging. Vehicle registry responses are bounded to 50 entries per page. Destinations can be added, edited and deactivated through the modal.
- Admin requests are serialized, obsolete reads are ignored, identical pending changes are coalesced, reads time out, refreshes back off during failures and pause while hidden. Failed writes are not automatically replayed. A failed list shows a loading error instead of claiming there are no records. A permission denial does not sign out a valid user; an expired session stops queued requests and redirects to login.
- Administrative changes require POST plus a session CSRF token. Only administrators can activate/deactivate user accounts. Current account role and active status are checked on authenticated requests, revoking disabled accounts and applying role changes. Login rotates the session ID. Existing Data Bank changes continue to use CSRF protection.
- Shift sign-off calculates its own system count and reports success only after the audit insert succeeds. Physical counts must be explicitly supplied. Reconciliation closes only a still-active session, preventing a competing close from overwriting a completed departure. Batch reconciliation publishes a refresh event. An empty review filter does not claim that physical discrepancies have been resolved.
- Missing hardware reports start at Unknown. Camera/worker heartbeat freshness uses 120 seconds consistently. Printer success is labelled as the last recorded job, not proof of current printer availability. Cloud status does not invent a last-sync timestamp or claim database parity after one batch.
- Privacy lookup is wired to display/download JSON, supports saved driver phones, and prevents a plate search from matching unrelated records with blank phones. The camera simulation control was removed from the normal admin screen; camera ingestion and workers remain unchanged.
- Backup creation checks the dump exit code and file size, uses separate process arguments and unique filenames, verifies an external copy by checksum when available, and records the copy result/checksum in its audit entry. The duplicate backup endpoint delegates to the same implementation.

## Validation

- `node --test --test-isolation=none scripts/test_admin_dashboard.cjs scripts/test_guard_dashboard.cjs scripts/test_guard_profiles.cjs scripts/test_guard_history.cjs`
- `php scripts/test_admin_reports.php`
- `php scripts/test_admin_api.php CASE`, where CASE is `settings`, `invalid_settings`, `csrf`, `get_mutation`, `supervisor_activation`, `role_refresh`, `revoked`, `signoff`, `invalid_signoff`, `destination`, `reports`, `overview`, `hourly`, `privacy_plate`, or `privacy_phone`.
- `php scripts/test_admin_backup.php` creates two isolated sample databases, invokes the backup API against the sample configuration, restores the dump into the other sample database, checks the recovered value and removes its test databases, dump and session file. It does not dump or restore live parking data.
- Existing Data Bank and guard regression suites pass with connection-local temporary tables. Administrative endpoint fixtures shadow all tables they modify, including users and audit records.
- Changed PHP/JavaScript files pass syntax checks. Admin element references and inline handlers resolve, and admin element IDs are unique.

No production settings, accounts, destinations, visit records or camera actions were changed by the tests. The backup/recovery result verifies the sample workflow, not a recovery of a particular production backup.

## Deployment and remaining verification

Deploy the matching admin pages, scripts, helpers and APIs together, then reload the admin page so it receives the current scripts and CSRF token. Existing guard changes in the working tree are separate from the admin update. No Git push was performed for this work.

A visual walkthrough on the target browser/tablet remains outstanding. Verify an actual installed printer/camera only through the normal installation checks. Scheduled backup configuration, disk capacity and recovery of a real operational backup have not been certified by the sample test. The overview deliberately displays unconfirmed recovery readiness rather than inventing a successful test date. Hardware service configuration and traffic capture remain outside this update.
