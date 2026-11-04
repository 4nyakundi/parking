# Guard dashboard display and live refresh

The blue station layout is retained. `guard-dashboard.css` refines display; `guard-live.js` coordinates dashboard reads; `guard-live-model.js` provides reconciliation rules.

## Behavior

- Authenticated, read-only `api/guard/snapshot.php` supplies a consistent initial queue, roster, device reports, print failures, server time, and event cursor.
- One dashboard refresh cycle runs at a time. Reads time out after 12 seconds, retry with capped delays, and ignore prior-session or pre-mutation snapshot responses.
- Local and Firestore queues merge before rendering. Local records retain authority; active vehicles suppress old pending copies.
- Session expiry stops refreshes, closes private dialogs, conceals data, and requests a PIN. PINs use explicit Enter for 4–6 digits.
- Sidebar status shows Unknown for missing reports and Stale for old camera/worker heartbeats. Printer status describes the last reported job, not paper delivery.
- Server-reported failed tickets remain in a persistent panel across reloads (latest 100 failures). Reprints use the existing endpoint.
- Filters and counts follow the existing 90-minute warning and 2-hour limit; policy is unchanged.
- Older databases without optional Data Bank columns still load the queue; no migration is performed.

## Verification

Run `node --test --test-isolation=none scripts/test_guard_dashboard.cjs` and PHP syntax checks. Tests simulate responses without registering vehicles, printing, sending messages, or contacting cameras.

In staging, check desktop/tablet sizes, local/cloud arrivals, slow responses, lost connection, expired sessions, print failures, threshold transitions, and reloads with historical events. Native browser preview was unavailable during implementation; visual verification on target tablets remains necessary.

Camera ingestion, recognition, matching, workers, printer transport, gate mutations, and hardware control are unchanged. Physical barrier movement and paper delivery require hardware acknowledgement.

The main roster shows newest arrivals first and longest parked last. Active Cars lists all currently parked vehicles; Overstayed Cars isolates those at or beyond two hours. Connection details and collapsible print issues are kept in the sidebar.


## Exited cars and daily traffic

The roster has four tabs: Active Cars, Overstayed Cars, Exited Cars, and Daily Traffic. Exited Cars shows completed visits with an exit timestamp and captured driver details, latest departure first. It opens on all dates, with optional departure-date filtering. Departures recorded as Uncollected Driver are excluded here and remain visible in Daily Traffic.

Daily Traffic defaults to today in Africa/Nairobi. The calendar shows recorded visits present during the selected date, including overnight stays. Repeat visits are separate rows. Cards show distinct plates, visits present, carry-in, arrivals, exits, movements (arrivals plus exits), remaining at day close (inside now for today), and visits without driver details. Cancelled sessions are excluded. An exit at exactly midnight counts toward the new day's exits; carry-in is measured immediately before that day's movements, so carry-in plus arrivals minus exits balances closing occupancy. Such a midnight departure does not count as a visit present later in the day. Totals reflect recorded sessions, not uncaptured physical camera traffic.

Search narrows the record list, while daily cards retain whole-day totals. Results are paged at 50 visits. History rows have no operational action buttons. All times shown are Nairobi time. The report uses existing fields and requires no additional database migration.

`api/guard/history.php` requires authentication and uses a consistent read transaction. History refreshes run sequentially through GuardLive, with stale-response rejection, capped retry backoff, session-expiry clearing, and ten-second refreshes while visible. A history failure preserves existing results and labels them accordingly without changing camera or gate operations.

Checks: `php scripts/test_guard_history.php` exercises temporary tables only, including overnight occupancy, midnight boundaries, repeat visits, cancellation exclusions, missing details, search and pagination. `node --test --test-isolation=none scripts/test_guard_history.cjs scripts/test_guard_dashboard.cjs scripts/test_guard_profiles.cjs` checks display safety, outdated responses, session expiry, retry behavior, and sequential coordination. Visual verification on the target browser/tablet remains outstanding.


## Today's overview and queue simplification

Alongside the capacity cards in the same top strip, the same metric styling now shows Recorded Arrivals Today, Registered Today, Unregistered Today, and Exited Today. Dates use Africa/Nairobi. Registered arrivals are ACTIVE/COMPLETED sessions entered today with captured driver details. Unregistered arrivals combine today's saved visits marked Uncollected Driver and distinct unresolved PENDING plates captured today. Requests covered by a saved visit or active session are excluded to prevent duplicate counts. Cancelled, rejected, expired and approved requests do not add to the unresolved total. A later repeat arrival after a completed visit can count separately. These are recorded local arrivals, not a physical vehicle count inferred from every camera frame or cloud-only pending request.

The unregistered card shows its awaiting/left-without-details breakdown. Exits count all completed departures today, including overnight arrivals and departures without details. Thus daily arrivals and exits need not match. Totals refresh inside the existing consistent snapshot, without another polling loop. Saved totals stay visible and are marked as last known during connection failures, and are cleared when the session expires. No database migration is required.

The Gate Camera Entries sidebar search box, its input listener, and its text-matching work have been removed. Existing All / Needs Data / Pre-Filled category tabs and Rows / Cards controls remain. The removed search ran locally and did not issue database searches on each keystroke.

Checks: `php scripts/test_guard_overview.php` uses temporary tables to verify duplicate suppression, registration transitions, repeat visits, overnight exits, empty days and timezone boundaries. The dashboard JavaScript regression suite also covers overview recovery and expiry clearing.
