<?php
/**
 * Google OAuth callback for kanban.cinegrid.net (URI must be listed in Google Cloud Console).
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log');

require_once __DIR__ . '/auth.php';

$dbPath = dirname(__DIR__) . '/public_html/includes/db.php';
if (!file_exists($dbPath)) {
    die('Database configuration not found');
}
require_once $dbPath;

require_once __DIR__ . '/_portal_config.php';
require_once __DIR__ . '/_google_oauth.php';

try {
    $creds = cg_kanban_google_credentials();
} catch (Throwable $e) {
    error_log('kanban google_callback: ' . $e->getMessage());
    header('Location: /login?error=oauth_failed&message=' . urlencode('Google sign-in is not configured (kanban.json).'), true, 302);
    exit;
}
$client_id = $creds['client_id'];
$client_secret = $creds['client_secret'];
$redirect_uri = CG_PORTAL_GOOGLE_REDIRECT_URI;

if (isset($_GET['error'])) {
    $msg = $_GET['error_description'] ?? ($_GET['error'] === 'access_denied' ? 'Access was denied.' : 'OAuth error');
    header('Location: /login?error=oauth_failed&message=' . urlencode($msg));
    exit;
}

if (!isset($_GET['code'], $_GET['state'])) {
    header('Location: /login?error=oauth_missing&message=' . urlencode('Missing authentication parameters.'));
    exit;
}

$sessionState = $_SESSION['google_oauth_state'] ?? null;
$cookieState = $_COOKIE['oauth_state_backup'] ?? null;
$receivedState = $_GET['state'];
$expectedState = $sessionState ?? $cookieState;

if ($receivedState !== $expectedState) {
    unset($_SESSION['google_oauth_state']);
    header('Location: /login?error=oauth_invalid_state&message=' . urlencode('Session expired. Please try signing in again.'));
    exit;
}

if ($sessionState === null && $cookieState !== null && $cookieState === $receivedState) {
    $_SESSION['google_oauth_state'] = $cookieState;
    if (isset($_COOKIE['oauth_redirect_backup'])) {
        $_SESSION['google_oauth_redirect'] = $_COOKIE['oauth_redirect_backup'];
    }
}

unset($_SESSION['google_oauth_state']);

$isLocalhost = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
    || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') !== false;
$cookieDomain = $isLocalhost ? '' : '.cinegrid.net';
$cookieSecure = !$isLocalhost;
$clearCookie = [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax',
];
if (!$isLocalhost) {
    $clearCookie['domain'] = $cookieDomain;
}
setcookie('oauth_state_backup', '', $clearCookie);
setcookie('oauth_redirect_backup', '', $clearCookie);

try {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $_GET['code'],
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        header('Location: /login?error=oauth_failed&message=' . urlencode($data['error_description'] ?? 'Token exchange failed'));
        exit;
    }

    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $data['access_token']],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $userinfo = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($userinfo['email'])) {
        header('Location: /login?error=oauth_failed&message=' . urlencode('Could not read email from Google.'));
        exit;
    }

    $email = $userinfo['email'];
    $name = $userinfo['name'] ?? '';
    $picture = $userinfo['picture'] ?? '';

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
            $reason = $user['suspension_reason'] ?? 'No reason provided.';
            header('Location: /login?error=suspended&reason=' . urlencode($reason));
            exit;
        }
        if (!empty($user['merged_into_user_id']) || (!empty($user['is_merged']) && (int)$user['is_merged'] === 1)) {
            header('Location: /login?error=account_merged');
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'] ?? 'customer';

        if ($picture !== '') {
            try {
                $c = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
                if ($c->rowCount() > 0 && empty($user['profile_pic'])) {
                    $u = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
                    $u->execute([$picture, $user['id']]);
                }
            } catch (Throwable $e) {
                error_log('kanban google_callback profile_pic: ' . $e->getMessage());
            }
        }

        session_regenerate_id(true);
        if (function_exists('cg_kanban_persist_login_for_days')) {
            cg_kanban_persist_login_for_days((int)$user['id']);
        }

        require_once __DIR__ . '/includes/freelance_projects.php';
        if (!cg_user_can_access_freelance_kanban()) {
            session_write_close();
            header('Location: /login?error=not_freelancer', true, 302);
            exit;
        }

        $redirect_to = $_SESSION['google_oauth_redirect'] ?? $_COOKIE['oauth_redirect_backup'] ?? CG_PORTAL_BASE_URL;
        unset($_SESSION['google_oauth_redirect'], $_SESSION['google_oauth_kanban_portal']);

        if (!preg_match('#^https?://#i', $redirect_to)) {
            $redirect_to = CG_PORTAL_BASE_URL;
        } else {
            $expectedHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
            if (preg_match('#^https?://([^/]+)#i', $redirect_to, $m)) {
                $rh = preg_replace('/:\d+$/', '', $m[1]);
                if ($expectedHost === '' || strcasecmp($rh, $expectedHost) !== 0) {
                    $redirect_to = CG_PORTAL_BASE_URL;
                }
            } else {
                $redirect_to = CG_PORTAL_BASE_URL;
            }
        }

        session_write_close();
        header('Location: ' . $redirect_to);
        exit;
    }

    $_SESSION['google_name'] = $name;
    $_SESSION['google_email'] = $email;
    $_SESSION['google_picture'] = $picture;
    $_SESSION['google_phone'] = '';
    $_SESSION['google_oauth_kanban_portal'] = true;
    if (empty($_SESSION['google_oauth_redirect'])) {
        $_SESSION['google_oauth_redirect'] = CG_PORTAL_BASE_URL;
    }

    session_write_close();
    header('Location: ' . rtrim(CG_PORTAL_SELF_ORIGIN, '/') . '/google_signup_finish', true, 302);
    exit;
} catch (Throwable $e) {
    error_log('kanban google_callback: ' . $e->getMessage());
    header('Location: /login?error=oauth_failed&message=' . urlencode('Something went wrong. Please try again.'));
    exit;
}
