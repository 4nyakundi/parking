<?php
/** Compatibility entry point. Uses the same validation as the management panel. */
$_GET['action'] = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') ? 'create' : 'list';
require __DIR__ . '/backups.php';
