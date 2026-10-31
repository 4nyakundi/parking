<?php
/**
 * Test Dahua event stream and config endpoints
 */
declare(strict_types=1);

$nvrIp = '192.168.0.3';
$cred = 'admin:Mall@2024';

$testEndpoints = [
    '/cgi-bin/configManager.cgi?action=getConfig&name=TrafficSnapshot',
    '/cgi-bin/configManager.cgi?action=getConfig&name=SnapConfig',
    '/cgi-bin/magicBox.cgi?action=getSystemInfo',
    '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=Traffic',
];

foreach ($testEndpoints as $ep) {
    $url = "https://{$nvrIp}{$ep}";
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
    curl_setopt($c, CURLOPT_USERPWD, $cred);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($c, CURLOPT_TIMEOUT, 3);
    $data = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);

    echo "Endpoint {$ep} => HTTP {$code}\n";
    if ($code === 200) {
        echo substr((string)$data, 0, 300) . "\n---\n";
    }
}
