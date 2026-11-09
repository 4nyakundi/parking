<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../config/database.php';
get_db()->exec(file_get_contents(__DIR__ . '/../database/upgrade_operations_20261109.sql'));
get_db()->exec(file_get_contents(__DIR__ . '/../database/upgrade_shift_followup_20261109.sql'));
echo "Operational tables ready. Existing parking records preserved.\n";
