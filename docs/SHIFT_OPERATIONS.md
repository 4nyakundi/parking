# Shift operations, guard accounts and multi-store visits

## Install

Existing installations must run `C:\xampp\php\php.exe scripts\operations_migrate.php` from the project directory **before deploying the updated PHP files**. Alternatively, import `database/upgrade_operations_20261109.sql` followed by `database/upgrade_shift_followup_20261109.sql` into the configured parking database. The initial upgrade adds four tables. The follow-up upgrade adds nullable shift and approving-supervisor references to action records. Existing parking visits, accounts and entry times are preserved. Fresh installations include these tables in `database/schema.sql`.

The upgrade was initially tested on an isolated MariaDB instance. On November 9, 2026, the local MySQL startup failure was repaired and the upgrade was applied to the existing `mombasa_parking` database. Verification confirmed unchanged values in all 15 parking visits, 4 application accounts, 14 visitor requests, 14 vehicle profiles and 48 destinations. Operations overview, roster, shift exit history and management reports read successfully. See MYSQL_RECOVERY_20261109.md for the repair and backup record.

## Guard workflow

- Sign in normally and open **Shift & Follow-ups**. The existing open shift is reused. If no shift has been recorded yet, the current sign-in supplies its start time. There is no second Start Shift button. A shift stays open across reloads or sign-outs until its handover is saved.
- The opening count includes cars already inside. Vehicle timers always retain the original entry time.
- Use **Follow-up / Incident** beside a roster car, or select an active vehicle in the operations panel. Record driver contact, supervisor notification, incidents, or a general follow-up. Actions retain the recording guard, current shift, approving supervisor and timestamp. Enter the existing active supervisor/admin PIN to save an action. Resolve completed actions with **Mark resolved** and a fresh supervisor-code approval. Unresolved incidents remain in operations after a car departs, until explicitly resolved.
- A supervisor or administrator can enter their existing code on the guard tablet to approve 1–1,440 extra minutes, with a required reason. The guard remains signed in. Minutes are added after the existing deadline or the approval time, whichever is later. The base allowance remains two hours; original entry times and earlier approval audits remain available.
- At handover, enter the physical count, notes and supervisor code. A difference from the system count requires an explanation. Ending a shift does not clear cars or close their follow-ups. After handover, the incoming guard signs in to continue; refreshing the panel does not silently open another shift.
- Exited Cars defaults to the signed-in account's current recorded shift, or today when no shift is open. Today, a selected departure date, and All dates remain available. Departures without captured driver details are included and labelled.

Movement totals cover all basement movements during the recorded shift, rather than only movements performed by that guard. Overlapping guards see shared movement totals. Individual admissions and exits retain their original acting guard. Recent handovers show the latest 20 recorded shifts, with notes and physical/system differences. Older audits remain in Audit Log. Historical action records without shift attribution remain labelled as historical; they are not assigned an invented shift.

## Management

**Users & Guards** allows administrators to manage all accounts and supervisors to create, edit, reset the PIN of, activate, and deactivate guards only. A new guard requires a unique 4–6 digit PIN. PINs and passwords are hashed; blank credential fields preserve existing values. Account changes are written directly to `users`, with an audit record. Name, role, phone and account activation changes take effect on the next authenticated request. Deactivating an account retains its historical activity.

**Shifts & Follow-ups → Open operations** shows overdue cars, cars with unresolved actions, deadlines, approved extension reasons, and recent count discrepancies. Links lead to existing missing-exit reconciliation, missing driver details, and failed-message filters. Missing-exit corrections require an explicit verification reason. Existing reconciliation records the correction time as the exit time; it does not reconstruct an unknown historical departure time.

## Multiple stores

Driver self-registration, manual guard registration and camera intake support **Visit multiple stores**. Select up to 20 stores, including stores on different floors. The selection persists across floor/search filters. Turning multi-store mode off retains the first store.

Each entry remains one parking visit, one ticket, one arrival and one parking timer. `visit_destinations` stores the separate intended destinations. The original `destination` field retains the first store for compatibility with existing profiles and cloud services. Roster, history, receipts, session lists and CSV exports show all selected stores. A guard can update intended stores through the operations panel while the visit is active; the edit is audited.

Store reports count each intended store once per visit. These totals may exceed parking arrivals and do not prove a customer entered a store. Legacy single-store visits continue to contribute one store. An approved extension is independent of how many stores are selected.

## Data boundaries

The new shifts, action history, extensions and destination selections live in the configured local MySQL/MariaDB database and are included by whole-database backups. The existing cloud mirror remains a legacy session mirror using the first destination; this change does not add cloud replication of operational tables or guard accounts. Multi-store driver submissions require the local API; they do not use the old single-store Firestore fallback. This avoids dropping selected stores while claiming a successful submission.

No driver reminders, store validation or parking zones were added. Existing arrival/exit messaging behaviour is unchanged.

## Verification

- `scripts/test_operations.php` uses connection-local temporary tables to verify shift inheritance, duplicate starts, permission checks, deadline changes, incident resolution, destination validation, multi-store reporting and handover preservation.
- `scripts/test_admin_api.php` includes supervisor guard creation/editing, management privilege rejection, PIN requirements, missing accounts, CSRF, revoked sessions and account role refresh.
- Guard history and management reports retain their overnight, midnight, repeat-visit and pagination regression checks.
- The browser was checked against an isolated database: supervisor account controls, operations panel, extension form and two-store guard selection rendered successfully. Hardware printing and gate movement were not exercised.

An optional CLI-only harness, `scripts/operations_test_runtime.php`, routes these tests to an isolated test database on port 3308. Its `setup` command is only for an already provisioned disposable MariaDB instance on that port; it must not point at a working parking database.


## Supervisor-code approval

Use the PIN belonging to an existing active supervisor or administrator. Administrators can set or reset it in **Users & Guards → Edit / PIN**. No shared master code or new default code is created. Saving follow-ups/incidents, resolving actions, approving extensions and completing handover each require a fresh code. Approval works from the signed-in guard’s station as well as management.

The server verifies the code against hashed account credentials, checks that the account is still active and privileged, and records the operator and approving supervisor separately. Codes are cleared from the form after submission and are never included in action/audit records. Five incorrect attempts by one guard across their sessions block further attempts for five minutes. Historical parked-car entry times continue unchanged.

The nullable `parking_actions.shift_id` and `authorized_by` columns were installed on the existing database through `database/upgrade_shift_followup_20261109.sql`. The main migration script includes this idempotent upgrade. Checks use temporary tables; no sample incidents, approvals, shifts or credentials are inserted into the working database.

Run `node --test --test-isolation=none scripts/test_shift_followup.cjs` for current-shift reuse, one-time attachment of older sign-ins, code-required handover submission and prevention of accidental new shifts after handover. PHP operation/login checks cover shift attribution, incorrect or inactive codes, retry limits, actor/supervisor attribution, and credential exclusion from audit data.
