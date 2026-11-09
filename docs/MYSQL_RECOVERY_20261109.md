# MySQL connection recovery — November 9, 2026

phpMyAdmin reported connection refusal, and application PHP connections stalled or returned `MySQL server has gone away`. The MySQL process stopped before reaching the normal ready-for-connections startup state. phpMyAdmin and MySQL already used the same IPv4 address and port, 127.0.0.1:3306.

## Backup

With MySQL stopped, a complete copy of `C:/xampp/mysql/data` and the existing MySQL/phpMyAdmin configuration files was saved outside the web root:

`C:/Users/emman/.codex/visualizations/2026/11/04/01a1a8a9-248c-7023-9774-af7282366758/mysql-recovery-20261109/`

The original database backup is `data-backup/`. All 929 files were verified against the stopped source using SHA-256 before any original system-table files were replaced. Keep this backup private; it contains database data and account information.

## Findings and repair

- Offline checks found that `mysql/db.MAI` was not recognised as an Aria table. `mysql/roles_mapping` also required repair; the other checked Aria system tables passed.
- Repairs were first performed on separate working copies. The `db.frm` definition matched the stock XAMPP backup definition exactly. A matching index template was used to rebuild the damaged index from the existing `db.MAD` data; four permission rows were recovered. The original permission data was not replaced with stock defaults.
- Repaired `db` and `roles_mapping` copies passed offline checks. Only their four `.MAI`/`.MAD` files were restored into the stopped server.
- MySQL was started with its existing configuration. Online `REPAIR TABLE` finalised both repaired tables for the server, and `FLUSH PRIVILEGES` reloaded the existing permissions.
- `CHECK TABLE mysql.db`, `mysql.roles_mapping` and `mysql.global_priv` all returned OK. No password reset or authentication bypass was used.
- phpMyAdmin was opened in the browser and its normal server dashboard loaded successfully. A separate optional configuration-storage notice remains; the original connection error is resolved.

Startup also reported existing auxiliary InnoDB metadata warnings for MySQL replication/statistics tables. Those tables were not dropped or recreated as part of this targeted permissions repair.

## Parking database activation

After restoring connectivity, `scripts/apply_operations_upgrade.php` applied the additive operational migration to the configured `mombasa_parking` database. Required columns were verified in `parking_shifts`, `parking_actions`, `parking_extensions` and `visit_destinations`.

Before/after logical fingerprints confirmed unchanged values in:

| Existing table | Records preserved |
|---|---:|
| parking_sessions | 15 |
| users | 4 |
| visitors | 14 |
| registered_vehicles | 14 |
| destinations | 48 |

Read-only checks passed for the operations overview, active vehicle roster, current-shift exit history and management reports. No sample shifts, incidents, extensions or guard accounts were added to the working database.
