<?php
/**
 * Local Development Cloud Configuration Override (XAMPP)
 * This allows localhost/parking/cloud/ to run seamlessly alongside the Edge PC.
 */
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'mombasam_parking',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];
