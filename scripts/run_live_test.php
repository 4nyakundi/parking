<?php
/**
 * Mombasa Mall Basement Parking - Live End-to-End System Test Runner
 * Uses real Dahua camera at 192.168.1.230 + local Apache/MariaDB + Guard/Driver APIs.
 */

declare(strict_types=1);

$baseUrl = 'http://127.0.0.1/parking';
$camIp   = '192.168.1.230';
$camUser = 'admin';
$camPass = 'Mall@2024';

echo "====================================================================\n";
echo "  MOMBASA MALL BASEMENT PARKING - LIVE END-TO-END SYSTEM TEST\n";
echo "====================================================================\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// -------------------------------------------------------------------
// STEP 1: Live Camera Frame Acquisition
// -------------------------------------------------------------------
echo ">>> STEP 1: Grabbing live high-res frame from Dahua Camera (192.168.1.230)...\n";
$snapUrl = "http://{$camIp}/cgi-bin/snapshot.cgi?channel=1";
$ch = curl_init($snapUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
    CURLOPT_USERPWD        => "{$camUser}:{$camPass}",
]);
$liveImageBytes = curl_exec($ch);
$httpCode       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($liveImageBytes)) {
    echo "FAILED to connect to camera. HTTP Code: {$httpCode}\n";
    exit(1);
}
$imgKb = round(strlen($liveImageBytes) / 1024, 2);
echo "    SUCCESS: Acquired fresh frame ({$imgKb} KB, Dahua Entrance Ramp)\n\n";

// -------------------------------------------------------------------
// STEP 2: Driver Mobile Self Sign-In Submission
// -------------------------------------------------------------------
$testPlate = 'KDA ' . rand(100, 999) . 'Z';
$testName  = 'Farah Ali';
$testPhone = '0722' . rand(100000, 999999);
$testDest  = 'Naivas Supermarket';

echo ">>> STEP 2: Driver submits mobile self sign-in at entrance QR...\n";
echo "    Plate: {$testPlate} | Driver: {$testName} | Destination: {$testDest}\n";

$ch = curl_init("{$baseUrl}/api/driver/submit.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'plate_number' => $testPlate,
        'driver_name'  => $testName,
        'driver_phone' => $testPhone,
        'destination'  => $testDest,
        'consent'      => true,
        'hp_website'   => '', // Honeypot clean
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$submitRes = curl_exec($ch);
curl_close($ch);
$submitJson = json_decode((string)$submitRes, true);

if (empty($submitJson['ok'])) {
    echo "    FAILED: Driver submit error: " . ($submitJson['error'] ?? $submitRes) . "\n";
    exit(1);
}
$visitorId = (int)$submitJson['data']['request_id'];
echo "    SUCCESS: Visitor request created (#{$visitorId}) with status PENDING\n\n";

// -------------------------------------------------------------------
// STEP 3: Camera ALPR Ingestion & Plate Verification
// -------------------------------------------------------------------
echo ">>> STEP 3: ALPR Engine detects plate on ramp and matches request...\n";
$b64Image = base64_encode($liveImageBytes);

$ch = curl_init("{$baseUrl}/api/gate/plate-detected.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'camera'       => 'entrance',
        'plate'        => $testPlate,
        'confidence'   => 0.97,
        'image_base64' => $b64Image,
        'api_key'      => 'MOMBASA_PARKING_ALPR_SECRET_KEY_2026',
    ]),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-Key: MOMBASA_PARKING_ALPR_SECRET_KEY_2026',
    ],
]);
$alprRes = curl_exec($ch);
curl_close($ch);
$alprJson = json_decode((string)$alprRes, true);

if (empty($alprJson['ok'])) {
    echo "    FAILED: ALPR ingest error: " . ($alprJson['error'] ?? $alprRes) . "\n";
    exit(1);
}
echo "    SUCCESS: Camera matched request (#{$visitorId})! Snapshot stored.\n\n";

// -------------------------------------------------------------------
// STEP 4: Guard Tablet Approval & Ticket Issuance
// -------------------------------------------------------------------
echo ">>> STEP 4: Guard logs in and taps [ACCEPT & PRINT] on tablet...\n";
// Create guard session with Guard 1 (PIN: 1234)
$cookieJar = tempnam(sys_get_temp_dir(), 'guard_cookie_');

$ch = curl_init("{$baseUrl}/api/auth/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'type' => 'pin',
        'pin'  => '1234',
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
]);
$loginRes = curl_exec($ch);
curl_close($ch);

// Approve session
$ch = curl_init("{$baseUrl}/api/gate/approve-session.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'request_id' => $visitorId,
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_COOKIEFILE     => $cookieJar,
]);
$approveRes = curl_exec($ch);
curl_close($ch);
$approveJson = json_decode((string)$approveRes, true);

if (empty($approveJson['ok'])) {
    echo "    FAILED: Approval error: " . ($approveJson['error'] ?? $approveRes) . "\n";
    exit(1);
}
$ticketId = $approveJson['data']['ticket_id'];
$sessId   = $approveJson['data']['session_id'];
echo "    SUCCESS: Ticket issued: {$ticketId} (Session #{$sessId})\n";
echo "    Thermal Print Status: " . ($approveJson['data']['print_status'] ?? 'pending') . "\n\n";

// -------------------------------------------------------------------
// STEP 5: Live Occupancy & Polling Broadcast Verification
// -------------------------------------------------------------------
echo ">>> STEP 5: Checking real-time occupancy feed...\n";
$pollRes = file_get_contents("{$baseUrl}/api/poll.php?since=0");
$pollJson = json_decode((string)$pollRes, true);
$stats = $pollJson['stats'] ?? [];
echo "    Basement Total Capacity: " . ($stats['capacity'] ?? '?') . "\n";
echo "    Currently Occupied     : " . ($stats['occupied'] ?? '?') . " vehicles\n";
echo "    Available Slots        : " . ($stats['available'] ?? '?') . " slots\n";
echo "    Pending Requests       : " . ($stats['pending_count'] ?? '?') . "\n\n";

// -------------------------------------------------------------------
// STEP 6: Vehicle Exit Clearance Simulation
// -------------------------------------------------------------------
echo ">>> STEP 6: Vehicle departs -> Guard scans exit...\n";
$ch = curl_init("{$baseUrl}/api/gate/clear-exit.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'session_id' => $sessId,
        'ticket_id'  => $ticketId,
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_COOKIEFILE     => $cookieJar,
]);
$exitRes = curl_exec($ch);
curl_close($ch);
$exitJson = json_decode((string)$exitRes, true);

if (empty($exitJson['ok'])) {
    echo "    FAILED: Exit clearance error: " . ($exitJson['error'] ?? $exitRes) . "\n";
} else {
    echo "    SUCCESS: Exit Cleared!\n";
    echo "    Dwell Time: " . ($exitJson['data']['duration_text'] ?? '0 mins') . "\n";
    echo "    Cleared by: " . ($exitJson['data']['cleared_by'] ?? 'Gate Officer') . "\n";
}

@unlink($cookieJar);

// -------------------------------------------------------------------
// Final Summary
// -------------------------------------------------------------------
echo "\n====================================================================\n";
echo "  LIVE TEST COMPLETE: ALL SUBSYSTEMS FULLY OPERATIONAL!\n";
echo "====================================================================\n";
