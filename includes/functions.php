<?php
/**
 * includes/functions.php — Shared utility functions
 * Used by all API endpoints for responses, validation, sanitisation.
 */

/** Emit a JSON response and exit */
function json_ok(mixed $data = null, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Sanitise a string input */
function clean_str(mixed $v, int $max = 255): string {
    return mb_substr(trim((string)($v ?? '')), 0, $max);
}

/** Normalise a Kenyan number plate: strip spaces/dashes, uppercase */
function normalise_plate(string $plate): string {
    $plate = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plate));
    return $plate;
}

/** Format plate for display with a space (e.g. KDA 123A) */
function format_plate(string $clean): string {
    // Insert space before the numeric block
    if (preg_match('/^([A-Z]{2,4})(\d{3}[A-Z]?)$/', $clean, $m)) {
        return $m[1] . ' ' . $m[2];
    }
    return $clean;
}

/** Normalise a Kenyan phone: 07… → +254… */
function normalise_phone(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        return '+254' . $digits;
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
        return '+254' . substr($digits, 1);
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '254')) {
        return '+' . $digits;
    }
    return $phone; // return as-is if unrecognised
}

/** Generate a ticket ID like MM-20260115-0047 */
function generate_ticket_id(PDO $pdo): string {
    $date = date('Ymd');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM parking_sessions WHERE DATE(created_at) = CURDATE()"
    );
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('MM-%s-%04d', $date, $count);
}

/** Get the client's real IP address */
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return explode(',', $_SERVER[$key])[0];
        }
    }
    return '0.0.0.0';
}

/** Return current session user_id, or null if not logged in */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Format duration in minutes to human string (e.g. "1h 23m") */
function format_duration(int $minutes): string {
    if ($minutes < 60) return "{$minutes}m";
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
}
