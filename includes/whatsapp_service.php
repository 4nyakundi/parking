<?php
/**
 * Mombasa Mall Basement Parking - Meta WhatsApp Business Cloud API Service
 * Handles queueing and sending of automated parking ticket and exit confirmations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/phone_helper.php';
require_once __DIR__ . '/plate_helper.php';

class WhatsAppService
{
    /**
     * Queue an Entry Ticket WhatsApp notification into whatsapp_queue table
     */
    public static function queueEntry(int $sessionId, string $phone, array $data): bool
    {
        $normalizedPhone = PhoneHelper::normalize($phone);
        if (!PhoneHelper::validate($normalizedPhone)) {
            return false;
        }

        $config = require __DIR__ . '/../config/config.php';
        $template = $config['whatsapp']['template_entry'] ?? 'mombasa_parking_ticket';

        // Variables for template:
        // {{1}}: Driver Name
        // {{2}}: Formatted Plate (e.g. KDA 123A)
        // {{3}}: Ticket ID (e.g. MM-20261003-0001)
        // {{4}}: Entry Time (e.g. 10:45 AM)
        // {{5}}: Destination
        $params = [
            'driver_name'  => $data['driver_name'],
            'plate_number' => PlateHelper::format($data['plate_number']),
            'ticket_id'    => $data['ticket_id'],
            'entry_time'   => date('d/m/Y H:i', strtotime($data['entry_time'])),
            'destination'  => $data['destination'],
        ];

        try {
            $db = get_db();
            $stmt = $db->prepare('
                INSERT INTO whatsapp_queue (session_id, phone, template_name, params, status, created_at)
                VALUES (:session_id, :phone, :template, :params, "pending", NOW())
            ');
            $stmt->execute([
                ':session_id' => $sessionId,
                ':phone'      => $normalizedPhone,
                ':template'   => $template,
                ':params'     => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]);

            // If WhatsApp is disabled in config, mark status queued without error
            $db->prepare('UPDATE parking_sessions SET whatsapp_status = "queued" WHERE id = ?')
               ->execute([$sessionId]);

            return true;
        } catch (Throwable $e) {
            error_log('WHATSAPP QUEUE ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue an Exit Ticket WhatsApp notification
     */
    public static function queueExit(int $sessionId, string $phone, array $data): bool
    {
        $normalizedPhone = PhoneHelper::normalize($phone);
        if (!PhoneHelper::validate($normalizedPhone)) {
            return false;
        }

        $config = require __DIR__ . '/../config/config.php';
        $template = $config['whatsapp']['template_exit'] ?? 'mombasa_parking_exit';

        $durationHrs = floor($data['duration_minutes'] / 60);
        $durationMins = $data['duration_minutes'] % 60;
        $durationStr = $durationHrs > 0 ? "{$durationHrs}h {$durationMins}m" : "{$durationMins} mins";

        $params = [
            'driver_name'      => $data['driver_name'],
            'plate_number'     => PlateHelper::format($data['plate_number']),
            'ticket_id'        => $data['ticket_id'],
            'exit_time'        => date('d/m/Y H:i', strtotime($data['exit_time'])),
            'duration'         => $durationStr,
        ];

        try {
            $db = get_db();
            $stmt = $db->prepare('
                INSERT INTO whatsapp_queue (session_id, phone, template_name, params, status, created_at)
                VALUES (:session_id, :phone, :template, :params, "pending", NOW())
            ');
            $stmt->execute([
                ':session_id' => $sessionId,
                ':phone'      => $normalizedPhone,
                ':template'   => $template,
                ':params'     => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('WHATSAPP EXIT QUEUE ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch a single message to Meta Cloud API via cURL
     */
    public static function sendViaMeta(string $phone, string $templateName, array $params): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $wCfg = $config['whatsapp'];

        if (empty($wCfg['enabled'])) {
            return ['ok' => false, 'error' => 'WhatsApp messaging is disabled in config.php'];
        }

        $token        = $wCfg['token'] ?? '';
        $phoneNumId   = $wCfg['phone_number_id'] ?? '';
        $apiVersion   = $wCfg['api_version'] ?? 'v19.0';

        if (empty($token) || empty($phoneNumId) || $token === 'YOUR_META_PERMANENT_ACCESS_TOKEN') {
            return ['ok' => false, 'error' => 'Meta API credentials not configured'];
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumId}/messages";

        // Convert parameters to Meta components format
        $bodyParameters = [];
        foreach ($params as $val) {
            $bodyParameters[] = [
                'type' => 'text',
                'text' => (string)$val,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => $bodyParameters,
                    ],
                ],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['ok' => false, 'error' => 'cURL Network Error: ' . $curlError];
        }

        $resJson = json_decode($response, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'meta_response' => $resJson];
        }

        $errorMsg = $resJson['error']['message'] ?? "HTTP {$httpCode}: {$response}";
        return ['ok' => false, 'error' => $errorMsg];
    }
}
