<?php
/** Additive Data Bank migration. Called explicitly by the CLI installer, never by camera requests. */
declare(strict_types=1);
function databank_migrate(PDO $db): array {
    $definitions = [
        'visit_count' => 'INT NOT NULL DEFAULT 0',
        'is_frequent' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'default_destination' => 'VARCHAR(150) NULL',
        'last_visit_at' => 'DATETIME NULL',
        'driver_name' => 'VARCHAR(100) NULL',
        'driver_phone' => 'VARCHAR(20) NULL',
        'last_confirmed_session_id' => 'INT NULL',
        'profile_confirmed_by' => 'INT NULL',
        'profile_confirmed_at' => 'DATETIME NULL',
        'profile_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'profile_reset_at' => 'DATETIME NULL',
    ];
    $columns = array_column($db->query('SHOW COLUMNS FROM registered_vehicles')->fetchAll(), 'Field');
    $added = [];
    foreach ($definitions as $name => $definition) {
        if (!in_array($name, $columns, true)) {
            $db->exec("ALTER TABLE registered_vehicles ADD COLUMN `{$name}` {$definition}");
            $added[] = $name;
        }
    }
    $indexes = array_column($db->query('SHOW INDEX FROM parking_sessions')->fetchAll(), 'Key_name');
    if (!in_array('idx_sess_plate_entry', $indexes, true)) {
        $db->exec('CREATE INDEX idx_sess_plate_entry ON parking_sessions (plate_number, entry_time, id)');
    }
    return $added;
}
