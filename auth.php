<?php
/**
 * Kanban subdomain: shared session with cinegrid.net (same pattern as transfer.cinegrid.net).
 * Loads public_html/includes/auth.php so "Remember me" (remember_token) restores sessions on
 * portal-only entry points (login.php, Google OAuth, etc.) that do not include auth.php directly.
 *
 * IMPORTANT: Any page that must share the same PHP session as login.php must load THIS file
 * before public_html/includes/auth.php so session.cookie_* matches (otherwise session_start()
 * in the main auth uses different ini → cookie mismatch → login ↔ app redirect loop).
 */
require_once __DIR__ . '/_session_https.php';

/** Set CG_KANBAN_SESSION_DIAG=1 to log why session cookies may be missing (headers_sent, ini, session_status). */
function cg_kanban_session_diag_enabled(): bool {
    $e = getenv('CG_KANBAN_SESSION_DIAG');
    return $e === '1' || strtolower((string)$e) === 'true';
}

function cg_kanban_session_diag_log(string $phase, array $extra = []): void {
    if (!cg_kanban_session_diag_enabled()) {
        return;
    }
    $hsFile = $hsLine = '';
    $hs = headers_sent($hsFile, $hsLine);
    $base = [
        'phase' => $phase,
        'session_status' => session_status(),
        'headers_sent' => $hs,
        'headers_sent_file' => $hs ? $hsFile : '',
        'headers_sent_line' => $hs ? $hsLine : 0,
        'use_cookies' => ini_get('session.use_cookies'),
        'use_only_cookies' => ini_get('session.use_only_cookies'),
        'save_handler' => ini_get('session.save_handler'),
        'save_path' => ini_get('session.save_path'),
        'cookie_params' => session_get_cookie_params(),
    ];
    error_log('cg_kanban_session_diag ' . json_encode($base + $extra, JSON_UNESCAPED_UNICODE));
}

function cg_kanban_debug_headers_enabled(): bool {
    $e = getenv('CG_KANBAN_SESSION_DEBUG_HEADERS');
    return $e === '1' || strtolower((string)$e) === 'true';
}

/** Set getenv('CG_KANBAN_AUTH_DEBUG')=1 or define CG_KANBAN_AUTH_DEBUG true to log auth/session. */
function cg_kanban_auth_debug_enabled(): bool {
    if (defined('CG_KANBAN_AUTH_DEBUG') && CG_KANBAN_AUTH_DEBUG) {
        return true;
    }
    $e = getenv('CG_KANBAN_AUTH_DEBUG');
    return $e === '1' || strtolower((string)$e) === 'true';
}

function cg_kanban_auth_debug_log(string $message, array $context = []): void {
    if (!cg_kanban_auth_debug_enabled()) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $line = 'cg_kanban_auth: ' . $message . ' host=' . $host . ' uri=' . $uri;
    if ($context !== []) {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $line .= ' ' . json_encode($context, $flags);
    }
    error_log($line);
}

/** Log outbound redirects when debug is on (reason + target URL). */
function cg_kanban_log_redirect(string $targetUrl, string $reason): void {
    cg_kanban_auth_debug_log('redirect', ['reason' => $reason, 'location' => $targetUrl]);
}

cg_kanban_session_diag_log('auth.php entry');

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

/** Server-side session file TTL; default PHP (~24m) caused frequent surprise logouts on Kanban. */
function cg_kanban_is_localhost(): bool {
    $h = (string)($_SERVER['HTTP_HOST'] ?? '');
    return $h === 'localhost'
        || strpos($h, '127.0.0.1') !== false
        || strpos($h, 'localhost:') !== false;
}

/** Kanban portal: match public_html persistent login TTL (sliding window on activity). */
function cg_kanban_session_ttl_seconds(): int {
    if (function_exists('cgPersistentSessionTtlSeconds')) {
        return (int) cgPersistentSessionTtlSeconds();
    }
    return 30 * 24 * 60 * 60;
}

function cg_kanban_configure_session_lifetime(): void {
    $ttl = (string)cg_kanban_session_ttl_seconds();
    ini_set('session.gc_maxlifetime', $ttl);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.cookie_lifetime', $ttl);
    }
}

function cg_kanban_ensure_remember_tokens_schema(): void {
    if (!function_exists('getDB')) {
        return;
    }
    try {
        $pdo = getDB();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        error_log('kanban remember_tokens schema: ' . $e->getMessage());
    }
}

/** Re-issue the PHP session cookie so the browser keeps sending it (sliding 30-day expiry). */
function cg_kanban_touch_session_cookie(): void {
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }
    $sid = session_id();
    if ($sid === '') {
        return;
    }
    $ttl = cg_kanban_session_ttl_seconds();
    $cookieParams = session_get_cookie_params();
    $cookieSecure = function_exists('cg_kanban_request_is_https')
        ? cg_kanban_request_is_https()
        : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookie = [
        'expires' => time() + $ttl,
        'path' => $cookieParams['path'] ?: '/',
        'secure' => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (!cg_kanban_is_localhost()) {
        $cookie['domain'] = '.cinegrid.net';
    }
    setcookie(session_name(), $sid, $cookie);
    $_SESSION['_kanban_session_touch_at'] = time();
}

/** 30-day remember token + session cookie for every Kanban sign-in. */
function cg_kanban_persist_login_for_days(int $userId, int $days = 30): void {
    if ($userId <= 0) {
        return;
    }
    cg_kanban_configure_session_lifetime();
    if (function_exists('createRememberToken')) {
        cg_kanban_ensure_remember_tokens_schema();
        createRememberToken($userId);
    }
    cg_kanban_touch_session_cookie();
}

/** Keep active sessions alive; backfill cg_session when missing (same as cinegrid.net). */
function cg_kanban_refresh_authenticated_session_if_needed(): void {
    if (!function_exists('cg_kanban_is_authenticated') || !cg_kanban_is_authenticated()) {
        return;
    }
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0 && function_exists('createRememberToken')) {
        $hasCgSession = defined('CG_SESSION_COOKIE') && !empty($_COOKIE[CG_SESSION_COOKIE]);
        $hasLegacyRemember = !empty($_COOKIE['remember_token']);
        if (!$hasCgSession && !$hasLegacyRemember) {
            if (function_exists('cgEnsureUserSessionsSchema')) {
                cgEnsureUserSessionsSchema();
            }
            createRememberToken($userId);
        }
    }
    cg_kanban_configure_session_lifetime();
    $touchAt = (int)($_SESSION['_kanban_session_touch_at'] ?? 0);
    if ($touchAt > 0 && (time() - $touchAt) < 3600) {
        return;
    }
    cg_kanban_touch_session_cookie();
}

if ($host !== '' && preg_match('/(^|\\.)cinegrid\\.net$/i', $host)) {
    cg_kanban_configure_session_lifetime();
}

cg_kanban_session_diag_log('before session_start');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (cg_kanban_debug_headers_enabled()) {
    header('X-Debug-Session-ID: ' . session_id());
    header('X-Debug-Session-Status: ' . session_status());
}

cg_kanban_session_diag_log('after session_start', [
    'session_id' => session_id(),
    'headers_list' => headers_list(),
]);

cg_kanban_auth_debug_log('session_start', [
    'cookie_domain' => ini_get('session.cookie_domain'),
    'cookie_secure' => ini_get('session.cookie_secure'),
    'session_id' => session_id(),
    'session_keys' => array_keys($_SESSION),
    'user_id_set' => isset($_SESSION['user_id']),
]);

$dbPath = dirname(__DIR__) . '/public_html/includes/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
}

$authCore = dirname(__DIR__) . '/public_html/includes/auth.php';
if (file_exists($authCore)) {
    require_once $authCore;
}

cg_kanban_refresh_authenticated_session_if_needed();

function cg_kanban_is_authenticated(): bool {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $uid = $_SESSION['user_id'];
    if ($uid === '' || $uid === null) {
        return false;
    }
    $id = filter_var($uid, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        return false;
    }
    return true;
}

/**
 * Normalize a redirect path/query for post-login; returns path starting with / or null if unsafe.
 */
function cg_kanban_normalize_redirect_path(string $redirect, ?string $expectedHost = null): ?string {
    $expectedHost = $expectedHost ?? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    $redirect_to = $redirect;
    if (preg_match('/^https?:\/\//', $redirect_to)) {
        $parsed = parse_url($redirect_to);
        $rh = isset($parsed['host']) ? preg_replace('/:\d+$/', '', $parsed['host']) : '';
        if ($expectedHost !== '' && $rh !== '' && strcasecmp($rh, $expectedHost) === 0) {
            $redirect_to = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        } else {
            return null;
        }
    } else {
        $redirect_to = str_replace(['http://', 'https://'], '', $redirect_to);
        if (strpos($redirect_to, '..') !== false) {
            return null;
        }
    }
    if ($redirect_to === '' || $redirect_to[0] !== '/') {
        $redirect_to = '/' . ltrim((string)$redirect_to, '/');
    }
    return $redirect_to;
}

function cg_kanban_is_login_url_path(string $path): bool {
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    $q = strpos($path, '?');
    $base = $q !== false ? substr($path, 0, $q) : $path;
    return (bool)preg_match('#^/(login\\.php|login)(/|\\?)?$#i', $base);
}
