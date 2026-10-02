<?php
/**
 * Portal-only: match session cookie settings with includes/auth.php so
 * kanban.cinegrid.net shares PHP sessions with cinegrid.net.
 */
require_once __DIR__ . '/_session_https.php';

$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');

ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

$cookieSecure = function_exists('cg_kanban_request_is_https')
    ? cg_kanban_request_is_https()
    : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');

if ($host !== '' && preg_match('/(^|\\.)cinegrid\\.net$/i', $host)) {
    ini_set('session.cookie_domain', '.cinegrid.net');
}

$cg_session_ttl = 30 * 24 * 60 * 60;
ini_set('session.gc_maxlifetime', (string)$cg_session_ttl);
ini_set('session.cookie_lifetime', (string)$cg_session_ttl);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$debugSessionHeaders = getenv('CG_KANBAN_SESSION_DEBUG_HEADERS');
if ($debugSessionHeaders === '1' || strtolower((string)$debugSessionHeaders) === 'true') {
    header('X-Debug-Session-ID: ' . session_id());
    header('X-Debug-Session-Status: ' . session_status());
}
