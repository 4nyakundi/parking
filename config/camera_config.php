<?php

declare(strict_types=1);

function lock_parking_camera_config(array $config): array
{
    $cameras = [
        'entrance' => ['ip' => '192.168.1.230', 'channel' => 1],
        'exit'     => ['ip' => '192.168.1.210', 'channel' => 1],
    ];

    foreach ($cameras as $name => $camera) {
        $cameraConfig = $config['alpr'][$name] ?? [];
        $username = rawurlencode((string)($cameraConfig['username'] ?? ''));
        $password = rawurlencode((string)($cameraConfig['password'] ?? ''));
        $ip = $camera['ip'];
        $channel = $camera['channel'];
        $credentials = $username . ':' . $password . '@';

        $config['network']['cam_' . $name . '_ip'] = $ip;
        $config['alpr'][$name]['ip'] = $ip;
        $config['alpr'][$name]['snapshot_url'] = "http://{$ip}/cgi-bin/snapshot.cgi?channel={$channel}";
        $config['alpr'][$name]['event_url'] = "http://{$ip}/cgi-bin/eventManager.cgi?action=attach&codes=[Traffic,TrafficTollGate,PlateDetection,All]";
        $config['alpr'][$name]['rtsp_main'] = "rtsp://{$credentials}{$ip}:554/cam/realmonitor?channel={$channel}&subtype=0";
        $config['alpr'][$name]['rtsp_sub'] = "rtsp://{$credentials}{$ip}:554/cam/realmonitor?channel={$channel}&subtype=1";
        $nvr = $config['alpr']['nvr'] ?? [];
        $nvrCredentials = rawurlencode((string)($nvr['username'] ?? '')) . ':' . rawurlencode((string)($nvr['password'] ?? '')) . '@';
        $nvrIp = (string)($nvr['ip'] ?? '192.168.1.3');
        $nvrChannel = (int)($cameraConfig['nvr_channel'] ?? 1);
        $config['alpr'][$name]['nvr_rtsp'] = "rtsp://{$nvrCredentials}{$nvrIp}:554/cam/realmonitor?channel={$nvrChannel}&subtype=0";
    }

    return $config;
}
