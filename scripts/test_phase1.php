<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/plate_helper.php';
require_once __DIR__ . '/../includes/phone_helper.php';

echo "Database Test: ";
$db = get_db();
$cnt = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "Users count = {$cnt}\n";

echo "Plate Normalization Test:\n";
$p1 = PlateHelper::clean('kda 123a');
$p2 = PlateHelper::clean('K0A 1Z3A'); // OCR confusion test: 0 in letter spot, Z in digit spot
$p3 = PlateHelper::clean('kmda-456-b');
echo "kda 123a -> {$p1} (format: " . PlateHelper::format($p1) . ", valid: " . (PlateHelper::validate($p1) ? 'YES' : 'NO') . ")\n";
echo "K0A 1Z3A -> {$p2} (format: " . PlateHelper::format($p2) . ", valid: " . (PlateHelper::validate($p2) ? 'YES' : 'NO') . ")\n";
echo "kmda-456-b -> {$p3} (format: " . PlateHelper::format($p3) . ", valid: " . (PlateHelper::validate($p3) ? 'YES' : 'NO') . ")\n";

echo "Phone Normalization Test:\n";
$ph1 = PhoneHelper::normalize('0712345678');
$ph2 = PhoneHelper::normalize('+254 799 887 766');
echo "0712345678 -> {$ph1} (valid: " . (PhoneHelper::validate($ph1) ? 'YES' : 'NO') . ")\n";
echo "+254 799 887 766 -> {$ph2} (valid: " . (PhoneHelper::validate($ph2) ? 'YES' : 'NO') . ")\n";
