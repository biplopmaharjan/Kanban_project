<?php
/**
 * Kanban portal: self-contained subdomain (shared DB/session only).
 *
 * Google OAuth: add CG_PORTAL_GOOGLE_REDIRECT_URI (exact string) under Authorized redirect URIs.
 * Optional: set env CG_KANBAN_GOOGLE_REDIRECT_URI to that same URL if auto-detection ever mismatches.
 */
$cg_portal_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');

$cg_portal_https = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $cg_portal_https = true;
} else {
    $xfp = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($xfp !== '') {
        foreach (preg_split('/\s*,\s*/', $xfp) as $p) {
            if ($p === 'https') {
                $cg_portal_https = true;
                break;
            }
        }
    }
}
if (!$cg_portal_https && !empty($_SERVER['HTTP_X_FORWARDED_SSL'])
    && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
    $cg_portal_https = true;
}
if (!$cg_portal_https && !empty($_SERVER['HTTP_CF_VISITOR'])) {
    $cf = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($cf) && (($cf['scheme'] ?? '') === 'https')) {
        $cg_portal_https = true;
    }
}
if (!$cg_portal_https && isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
    $cg_portal_https = true;
}
if (!$cg_portal_https && !empty($_SERVER['REQUEST_SCHEME'])
    && strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https') {
    $cg_portal_https = true;
}

$cg_portal_local = $cg_portal_host === 'localhost'
    || strpos($cg_portal_host, '127.0.0.1') === 0
    || strpos($cg_portal_host, 'localhost:') === 0;
if (!$cg_portal_local && preg_match('/\.cinegrid\.net$/i', $cg_portal_host)) {
    $cg_portal_https = true;
}

$cg_portal_scheme = $cg_portal_https ? 'https' : 'http';
define('CG_PORTAL_SELF_ORIGIN', $cg_portal_scheme . '://' . $cg_portal_host);

$cg_google_redirect = getenv('CG_KANBAN_GOOGLE_REDIRECT_URI');
$cg_google_redirect = is_string($cg_google_redirect) ? trim($cg_google_redirect) : '';
if ($cg_google_redirect === '') {
    // Exact string must appear in Google Console (Authorized redirect URIs). No guessing scheme/host.
    if (preg_match('/^kanban\.cinegrid\.net$/i', $cg_portal_host)) {
        $cg_google_redirect = 'https://kanban.cinegrid.net/google_callback';
    } elseif (preg_match('/^www\.kanban\.cinegrid\.net$/i', $cg_portal_host)) {
        $cg_google_redirect = 'https://www.kanban.cinegrid.net/google_callback';
    } else {
        $cg_google_redirect = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . '/google_callback';
    }
}
define('CG_PORTAL_GOOGLE_REDIRECT_URI', $cg_google_redirect);

$__scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$__scriptDir = ($__scriptDir === '/' || $__scriptDir === '\\' || $__scriptDir === '.') ? '' : rtrim($__scriptDir, '/');
define('CG_PORTAL_BASE_URL', rtrim(CG_PORTAL_SELF_ORIGIN, '/') . ($__scriptDir === '' ? '/' : $__scriptDir . '/'));
unset($__scriptDir);
