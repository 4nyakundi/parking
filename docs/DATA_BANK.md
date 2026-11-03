# Returning visitors and the Data Bank

Each vehicle has one reusable profile. Each accepted arrival still creates a new parking session and ticket. Driver profiles never overwrite vehicle ownership, owner contact details, or VIP/staff/tenant/blacklist categories. Frequent means three valid visits; it is not an access privilege.

## Guard workflow

Open an arrival or enter a plate in manual registration. A returning-vehicle card shows the saved driver, masked phone, last vehicle visit, and destination suggestion. Choose Use These Details, Edit Details, or Different Driver. Always confirm the destination. Fields remain editable. Different Driver clears the identity and destination and leaves Remember Details off; enable it explicitly to replace the saved driver for future visits. If no useful identity is supplied, the existing profile is kept.

## Administration

Vehicles → Profile / History opens the saved driver and paginated visits. Save Driver Details corrects the profile only. Archive Profile disables reuse without deleting sessions or altering the vehicle's access category; Restore re-enables it. New Owner / Clear Saved Driver clears reusable identity and blocks fallback to older visits. Update the separate owner/access fields through Edit when an ownership change warrants it.

Data Bank record review lists uncertain plate links. These are never automatically merged. Historical sessions remain unchanged. Archived profiles still occupy storage; archiving is a reuse control, not a deletion or retention policy.

## Installation and existing records

Run `php scripts/databank_migrate.php` for a summary preview. `--apply` first copies the vehicle registry to a dated backup table, then adds missing profile columns and a lookup index. Exact valid historical plates receive a regular profile if absent. Visit totals are rebuilt from ACTIVE/COMPLETED sessions, excluding uncollected departures. Driver identities are not bulk-copied into ownership fields. Uncertain plates are written to protected `storage/backups/databank_review_*.json` for review. The script is repeatable; it never removes history, changes access categories, or merges uncertain identities.

For phpMyAdmin on an existing installation, select the parking database and import `database/upgrade_databank_20261103.sql`. It creates a dated registry backup, adds missing columns/index, and rebuilds visit summaries for existing profiles. It can be imported again without resetting saved identities. It does not bulk-create historical registry profiles or generate the CLI review report; the PHP migration above handles those optional steps. The import leaves session history and ownership/access fields unchanged.

Fresh installs use database/schema.sql. Alternative legacy schema imports must run the additive migration afterwards. No runtime request or camera worker performs schema changes.

## Checks

- `php scripts/test_databank.php`: regression checks use temporary MySQL tables that shadow the live tables on a separate CLI connection; no live parking records are changed.
- `node --test --test-isolation=none scripts/test_guard_profiles.cjs scripts/test_guard_dashboard.cjs`: profile confirmation, stale lookup protection, and live dashboard checks.

Camera detection, camera ingestion, and camera workers are unchanged. Their existing Data Bank lookup reads now return accurate suggestions; the guard confirms reuse. Printing and messaging transports are unchanged.

Administrative mutation checks: `php scripts/test_databank_api.php save`, `csrf`, and `reset` use temporary tables, including the audit table. The local upgrade created nine exact-match profiles and left one uncertain historical plate for review. The new screens still need a visual walkthrough on the target tablets.
