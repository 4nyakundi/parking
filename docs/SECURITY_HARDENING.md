# Security changes: users, credentials and public access

The local fixes cover the five urgent findings. Cloud enforcement must be deployed to the matching Firebase project; editing the rules file alone does not change deployed rules.

## Sign-in and PINs

- Guard PIN sign-in accepts guards only. Supervisors and administrators use their username and password for dashboard access. Their existing approval PINs still authorize recorded handovers, incidents and other guard operations.
- Five unsuccessful attempts per PIN station or username within fifteen minutes temporarily block that sign-in route. A separate twenty-attempt limit applies to a client address. Successful authentication removes only its own attempt, preserving earlier failures.
- Throttling uses the actual connection address, persists across browser sessions and locks files while updating counters. Unavailable or corrupt throttle storage fails closed. Keep `storage/security` writable by the PHP service and inaccessible over HTTP.
- Failed and throttled local logins are audited without entered passwords or PINs. Cloud management login also has persistent attempt limits.
- Account administration and self-service PIN changes check all existing PIN hashes, including inactive accounts, under the same database locking order. Existing duplicate PINs cannot create a guard session or authorize a supervisor action. An administrator must assign unique replacement PINs.
- PIN changes require POST and a valid CSRF token.

## Private deployment configuration

`config/config.php` contains no working passwords or integration secrets. This installation's existing database and device access is preserved in ignored `config/secrets.local.php`. Do not publish, commit or attach that file to support requests. Protect it with operating system permissions for the PHP/worker service account and authorized administrators. Include it only in protected recovery backups.

The local ALPR API key and cloud API/HMAC keys were replaced with randomly generated values. PHP readers load private configuration consistently. The Python worker reads `alpr/config.env.local` after the non-secret defaults in `alpr/config.env`, with environment values taking precedence. Its local API key matches the PHP receiver. Restart running workers after configuration changes.

Environment overrides include `PARKING_DB_PASSWORD`, `PARKING_ALPR_API_KEY`, `PARKING_CLOUD_API_KEY`, `PARKING_CLOUD_HMAC_SECRET`, and `PARKING_{NVR|ENTRANCE|EXIT}_{USERNAME|PASSWORD}`.

Actual database and camera passwords were preserved to avoid disconnecting the devices. Since old revisions contained these credentials, moving them out of current source does not invalidate prior copies. Replace them on the corresponding database/devices and update private configuration together during a supervised maintenance window. Use separate limited device accounts where the hardware supports them. Keep edge and remote cloud API/HMAC values aligned before enabling cloud synchronization; an existing dedicated cloud override takes precedence.

Apache rules now block configuration, environment files, database exports, backups, diagnostic scripts, worker sources and security counter files. Apply equivalent deny rules if deploying behind a server that does not support `.htaccess`. System settings responses omit credential-related keys.

Fresh SQL imports contain inactive account placeholders with no password/PIN hashes. They do not ship usable default credentials. For a new installation, set `PARKING_INITIAL_ADMIN_PASSWORD` to a unique 12–72 character value and run `php scripts/provision_admin.php`, then create staff accounts from management. The utility refuses to reset an existing active administrator. Reimporting the canonical schema preserves existing account credentials instead of resetting them to seed values. This source change does not alter accounts in an existing database.

## Public driver and camera workflows

- Driver vehicle selection shows recent entrance plates and availability, without names, phone numbers, destinations, photographs or ticket identifiers. Drivers enter their own details. Authenticated guard lookup continues to provide the existing operational workflow.
- Existing camera image URLs are routed through a staff-authenticated image handler. Guessing a stored image path does not grant public access; staff can still open images in their existing screens.
- Camera ingestion accepts POST with the configured key in `X-API-Key` or a submitted body field. Local/private addresses and forwarding headers never bypass authentication. Query-string keys are rejected. Empty configured keys fail closed.
- Existing PHP/Python workers already submit keys. Native camera pushes that cannot supply a key must go through the authenticated worker/relay.

## Firebase deployment

The bridge keeps public, consented driver intake. Reading visitor records, acknowledging admission/rejection and publishing occupancy require short-lived staff claims issued only after checking the PHP session and CSRF token. Firebase credentials remain in browser memory; the bridge revalidates its server session every four minutes, and rules refuse staff access after five minutes without renewed authorization. Deactivation can therefore take up to five minutes to remove existing cloud access; local access is checked on each authenticated request.

Set `PARKING_FIREBASE_SERVICE_ACCOUNT` to a protected service account JSON file, or use the ignored `config/firebase-service-account.local.json`. Its project must match `mombasa-mall-parking`. Use a dedicated signing identity with no project roles. The signing key can issue staff tokens and must be treated as an authentication secret. The endpoint never returns the signing key to browsers.

Deploy the updated public bridge and rules together after configuring the signer:

```powershell
firebase deploy --only hosting,firestore:rules --project mombasa-mall-parking
```

The unused SQL Connect operations that exposed parking records, changed sessions or let users choose their own role are now marked `NO_ACCESS`. If the SQL Connect connector is deployed, deploy that connector's authorization changes as well. Normal PHP and Firestore workflows do not call those operations.

On 20 November 2026, the project inspection found the `parking-konnect` SQL Connect service with no deployed connectors. Standard Firebase Authentication was activated on the existing project; no billing account was enabled. The dedicated `parking-staff-signin` signing account has no project roles, and its credential is stored only in the protected local configuration file. Hosting and Firestore rules were deployed successfully to `mombasa-mall-parking`.

Live verification confirmed that the dedicated signer is accepted, anonymous visitor-record access returns HTTP 403, and authenticated staff access returns HTTP 200. Only aggregate counts were requested; no visitor identities were read. Each temporary cloud authentication test identity was removed after verification. The hosted driver page and updated bridge were fetched successfully. The live local database port and the three configured camera/NVR addresses were unreachable during verification, so hardware operation and coordinated password replacement remain to be checked when those systems are available.

## Verification

- `npm test`: existing dashboard and workflow checks plus the authenticated Firebase bridge checks.
- `C:/xampp/php/php.exe scripts/test_security.php`: throttling, PIN uniqueness, machine keys and RSA token checks.
- `scripts/test_security_api.php` runs through the existing isolated test runtime, using temporary tables for sign-in, PIN changes, privacy, settings and camera endpoints. Never run an isolated-test setup against the live database.
- `npm run test:firestore` expects the Firestore emulator at `127.0.0.1:8089` and uses the `demo-parking-security` project. It checks public intake and staff operations, rejects unauthorized/expired access, and never writes to the live Firebase database.
- Apache checks verify sensitive paths return HTTP 403 while public JavaScript remains available.

These changes do not implement the separately identified forced-password-change and session-revocation improvements.
