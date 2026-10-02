<?php
/**
 * Resolve a public IP to a human-readable location label (city, country).
 * Uses ip-api.com with in-request caching — same approach as admin activity views.
 */
declare(strict_types=1);

function cg_ip_location_is_private(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '' || $ip === 'unknown' || $ip === 'N/A' || $ip === 'localhost' || $ip === '::1' || $ip === '127.0.0.1') {
        return true;
    }
    if (strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return true;
    }
    if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)) {
        return true;
    }
    if (preg_match('/^(fc|fd)[0-9a-f]{0,2}:/i', $ip) || stripos($ip, 'fe80:') === 0) {
        return true;
    }
    return false;
}

function cg_ip_location_label(string $ip): string
{
    static $cache = [];

    $ip = trim($ip);
    if ($ip === '') {
        return '';
    }
    if (isset($cache[$ip])) {
        return $cache[$ip];
    }
    if (cg_ip_location_is_private($ip)) {
        return $cache[$ip] = 'Local network';
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return $cache[$ip] = '';
    }

    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,message,country,regionName,city';
    $label = '';

    if (function_exists('curl_init')) {
        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_USERAGENT => 'CineGrid Kanban',
                CURLOPT_FAILONERROR => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                $data = json_decode($response, true);
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    $parts = array_filter([
                        trim((string) ($data['city'] ?? '')),
                        trim((string) ($data['regionName'] ?? '')),
                        trim((string) ($data['country'] ?? '')),
                    ], static fn($v) => $v !== '');
                    $parts = array_values(array_unique($parts));
                    $label = implode(', ', $parts);
                }
            }
        }
    }

    return $cache[$ip] = $label;
}

function cg_ip_location_labels_for_ips(array $ips): array
{
    $out = [];
    foreach ($ips as $ip) {
        $key = trim((string) $ip);
        if ($key === '') {
            continue;
        }
        $out[$key] = cg_ip_location_label($key);
    }
    return $out;
}
