<?php
/**
 * Google OAuth on the Kanban host (redirect_uri = this site's google_callback.php).
 * Add that exact URL in Google Cloud Console → OAuth client → Authorized redirect URIs.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_portal_config.php';
require_once __DIR__ . '/_google_oauth.php';

try {
    $creds = cg_kanban_google_credentials();
} catch (Throwable $e) {
    error_log('kanban google_login: ' . $e->getMessage());
    header('Location: /login?error=oauth_failed&message=' . urlencode('Google sign-in is not configured (kanban.json).'), true, 302);
    exit;
}
$client_id = $creds['client_id'];
$scope = 'email profile';
$redirect_uri = CG_PORTAL_GOOGLE_REDIRECT_URI;

$redirect_param = $_GET['redirect'] ?? '';
$expected_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
if ($redirect_param !== '' && preg_match('#^https?://#i', $redirect_param)) {
    $pu = parse_url($redirect_param);
    $rh = isset($pu['host']) ? preg_replace('/:\d+$/', '', $pu['host']) : '';
    if ($rh !== '' && strcasecmp($rh, $expected_host) === 0) {
        $_SESSION['google_oauth_redirect'] = $redirect_param;
    } else {
        $_SESSION['google_oauth_redirect'] = CG_PORTAL_BASE_URL;
    }
} else {
    $_SESSION['google_oauth_redirect'] = CG_PORTAL_BASE_URL;
}
$_SESSION['google_oauth_kanban_portal'] = true;

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$isLocalhost = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
    || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') !== false;
$cookieDomain = $isLocalhost ? '' : '.cinegrid.net';
$cookieSecure = !$isLocalhost;
$cookieOpts = [
    'expires' => time() + 600,
    'path' => '/',
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax',
];
if (!$isLocalhost) {
    $cookieOpts['domain'] = $cookieDomain;
}
setcookie('oauth_state_backup', $state, $cookieOpts);
setcookie('oauth_redirect_backup', $_SESSION['google_oauth_redirect'], $cookieOpts);

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => $scope,
    'state' => $state,
    'access_type' => 'online',
]);

session_write_close();
header('Location: ' . $auth_url, true, 302);
exit;
