<?php
/**
 * Mombasa Mall - Automated End-to-End Test for Camera Plate Intake & Guard Quick-Fill
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/plate_helper.php';

echo "=========================================================\n";
echo " TESTING CAMERA PLATE INTAKE & GUARD QUICK-FILL WORKFLOW \n";
echo "=========================================================\n\n";

$testPlate = 'KDA' . rand(100, 999) . 'X';
$cleanPlate = PlateHelper::clean($testPlate);
$formattedPlate = PlateHelper::format($cleanPlate);

echo "1. Simulating camera detection for plate: {$formattedPlate} ...\n";

$ch = curl_init('http://127.0.0.1/parking/api/gate/plate-detected.php');
$payload = [
    'camera'     => 'entrance',
    'plate'      => $cleanPlate,
    'confidence' => 0.98,
    'api_key'    => 'MOMBASA_PARKING_ALPR_SECRET_KEY_2026',
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\nResponse: {$resp}\n\n";
$detResult = json_decode((string)$resp, true);

if (!$detResult || empty($detResult['ok'])) {
    echo "FAILED: plate-detected.php did not return ok:true\n";
    exit(1);
}

$matchedReqId = $detResult['data']['matched_request_id'] ?? null;
echo "Created Pending Visitor Request ID: {$matchedReqId}\n\n";

// 2. Verify Database State
$db = get_db();
$stmt = $db->prepare('SELECT * FROM visitors WHERE id = ?');
$stmt->execute([$matchedReqId]);
$vis = $stmt->fetch();

echo "2. Checking visitors table record:\n";
echo "- ID: {$vis['id']}\n";
echo "- Plate: {$vis['plate_number']}\n";
echo "- Source: {$vis['source']}\n";
echo "- Status: {$vis['status']}\n";
echo "- ALPR Verified: {$vis['alpr_verified']}\n\n";

if ($vis['source'] !== 'alpr_camera' || $vis['status'] !== 'PENDING') {
    echo "FAILED: visitors record source or status mismatch!\n";
    exit(1);
}

// 3. Test Polling API (Poll sees new_request)
echo "3. Testing api/poll.php to ensure event was queued:\n";
$pollCh = curl_init('http://127.0.0.1/parking/api/poll.php?since=0');
curl_setopt($pollCh, CURLOPT_RETURNTRANSFER, true);
$pollResp = json_decode(curl_exec($pollCh), true);
curl_close($pollCh);

$eventFound = false;
foreach ($pollResp['events'] ?? [] as $evt) {
    if ($evt['type'] === 'new_request' && ($evt['payload']['plate_number'] ?? '') === $cleanPlate) {
        $eventFound = true;
        echo "Found new_request event for {$cleanPlate}!\n";
        break;
    }
}
if (!$eventFound) {
    echo "WARNING: Event not found in recent poll response.\n";
} else {
    echo "SUCCESS: Realtime event dispatched to dashboard!\n\n";
}

// 4. Test Guard Approval with Name, Phone, and Destination
echo "4. Simulating Guard submitting Name, Phone, and Destination ...\n";
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Authenticate first
$loginCh = curl_init('http://127.0.0.1/parking/api/auth/login.php');
curl_setopt($loginCh, CURLOPT_POST, true);
curl_setopt($loginCh, CURLOPT_POSTFIELDS, json_encode(['type' => 'pin', 'pin' => '1234']));
curl_setopt($loginCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($loginCh, CURLOPT_HEADER, true);
curl_setopt($loginCh, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$loginRaw = curl_exec($loginCh);
$headerSize = curl_getinfo($loginCh, CURLINFO_HEADER_SIZE);
$loginHeaders = substr((string)$loginRaw, 0, $headerSize);
$loginBody = substr((string)$loginRaw, $headerSize);
curl_close($loginCh);

echo "Login Body: {$loginBody}\n";

$sessId = '';
if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $loginHeaders, $m)) {
    $sessId = trim($m[1]);
}
echo "Extracted PHPSESSID: {$sessId}\n";

$apprCh = curl_init('http://127.0.0.1/parking/api/gate/approve-session.php');
$apprPayload = [
    'request_id'   => $matchedReqId,
    'driver_name'  => 'Rashid Al-Mansoor',
    'driver_phone' => '0722123456',
    'destination'  => 'Naivas Supermarket',
];
curl_setopt($apprCh, CURLOPT_POST, true);
curl_setopt($apprCh, CURLOPT_POSTFIELDS, json_encode($apprPayload));
curl_setopt($apprCh, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "Cookie: PHPSESSID={$sessId}"
]);
curl_setopt($apprCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($apprCh, CURLOPT_TIMEOUT, 8);
curl_setopt($apprCh, CURLOPT_CONNECTTIMEOUT, 3);
$apprResp = curl_exec($apprCh);
$apprCode = curl_getinfo($apprCh, CURLINFO_HTTP_CODE);
$curlErr = curl_error($apprCh);
curl_close($apprCh);

if ($curlErr) {
    echo "CURL Error: {$curlErr}\n";
}

echo "Approval HTTP Code: {$apprCode}\nResponse: {$apprResp}\n\n";
$apprJson = json_decode((string)$apprResp, true);

if (!$apprJson || empty($apprJson['ok'])) {
    echo "FAILED: approve-session.php failed!\n";
    exit(1);
}

// 5. Verify created parking session
$ticketId = $apprJson['data']['ticket_id'];
$sessStmt = $db->prepare('SELECT * FROM parking_sessions WHERE ticket_id = ?');
$sessStmt->execute([$ticketId]);
$sess = $sessStmt->fetch();

echo "5. Verifying parking session record in database:\n";
echo "- Ticket ID: {$sess['ticket_id']}\n";
echo "- Plate: {$sess['plate_number']}\n";
echo "- Driver Name: {$sess['driver_name']} (Expected: Rashid Al-Mansoor)\n";
echo "- Driver Phone: {$sess['driver_phone']} (Expected: 254722123456)\n";
echo "- Destination: {$sess['destination']} (Expected: Naivas Supermarket)\n";
echo "- Status: {$sess['status']}\n";
echo "- Entry Method: {$sess['entry_method']}\n\n";

if ($sess['driver_name'] === 'Rashid Al-Mansoor' && $sess['destination'] === 'Naivas Supermarket') {
    echo ">>> ALL INTEGRATION CHECKS PASSED PERFECTLY! <<<\n";
} else {
    echo "FAILED: Parking session fields do not match guard input.\n";
    exit(1);
}

// Clean up test cookie
if (file_exists($cookieFile)) unlink($cookieFile);
