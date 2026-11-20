<?php
/**
 * Test Dahua IVSS event stream
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
$nvr = get_config()['alpr']['nvr'];
$nvrIp = $nvr['ip'];
$cred = $nvr['username'] . ':' . $nvr['password'];

$codes = ['All', 'Traffic', 'TrafficSnap', 'TrafficSnapshot', 'NewFile', 'CrossLineDetection'];

foreach ($codes as $code) {
    echo "Testing event code [{$code}]...\n";
    $url = "https://{$nvrIp}/cgi-bin/eventManager.cgi?action=attach&codes=[{$code}]";
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
    curl_setopt($c, CURLOPT_USERPWD, $cred);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($c, CURLOPT_TIMEOUT, 2);
    
    $buffer = '';
    curl_setopt($c, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer) {
        $buffer .= $data;
        if (strlen($buffer) > 200) {
            return 0; // stop after reading some data
        }
        return strlen($data);
    });
    
    curl_exec($c);
    $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    
    echo "Code [{$code}] => HTTP {$httpCode}, Received: " . strlen($buffer) . " bytes\n";
    if (strlen($buffer) > 0) {
        echo "Sample: " . substr($buffer, 0, 150) . "\n";
    }
}
