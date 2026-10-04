<?php
$camIp = '192.168.1.230';
$user = 'admin';
$pass = 'Mall@2024';

$testEndpoints = [
    // Hikvision ISAPI
    '/ISAPI/System/deviceInfo',
    '/ISAPI/Streaming/channels/101/picture',
    '/ISAPI/Streaming/channels/1/picture',
    // Dahua / Amcrest CGI
    '/cgi-bin/snapshot.cgi',
    '/cgi-bin/magicBox.cgi?action=getSystemInfo',
    // Uniview (UNV)
    '/LAPI/V1.0/System/DeviceInfo',
    '/LAPI/V1.0/Channels/0/Media/Video/Streams/0/Snapshot',
    '/LAPI/V1.0/Channels/1/Media/Video/Streams/1/Snapshot',
    // Generic / ONVIF / Others
    '/onvif-http/snapshot',
    '/snapshot.jpg',
    '/image.jpg',
    '/jpg/image.jpg',
    '/api/v1/snapshot',
];

echo "Testing camera endpoints on http://$camIp with admin / Mall@2024 ...\n\n";

foreach ($testEndpoints as $ep) {
    $url = "http://{$camIp}{$ep}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPAUTH => CURLAUTH_ANY,
        CURLOPT_USERPWD => "{$user}:{$pass}",
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $size = strlen((string)$body);
    $err  = curl_error($ch);
    curl_close($ch);

    printf("%-55s => Code: %3d | Type: %-20s | Size: %6d bytes | Err: %s\n", $ep, $code, substr((string)$type, 0, 20), $size, $err ?: 'None');
    
    if ($code === 200 && (str_contains($type, 'image') || str_contains($type, 'xml') || str_contains($type, 'json'))) {
        echo "   >>> SAMPLE CONTENT:\n";
        echo "   " . substr(trim(strip_tags((string)$body)), 0, 150) . "\n\n";
    }
}
