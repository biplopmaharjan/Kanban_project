<?php
/**
 * Detect whether the current HTTP request is served over HTTPS (including behind proxies).
 * Used for session.cookie_secure — must match real transport; forcing Secure on plain HTTP
 * prevents the browser from storing/sending the session cookie (redirect loops with login).
 */
function cg_kanban_request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $xfp = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($xfp !== '') {
        foreach (preg_split('/\s*,\s*/', $xfp) as $p) {
            if ($p === 'https') {
                return true;
            }
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
        $cf = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
        if (is_array($cf) && (($cf['scheme'] ?? '') === 'https')) {
            return true;
        }
    }
    if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    if (!empty($_SERVER['REQUEST_SCHEME'])
        && strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https') {
        return true;
    }
    return false;
}
