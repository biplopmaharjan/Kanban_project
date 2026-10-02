<?php
/**
 * Connect Google Calendar for a board using only the email invite link — no Kanban login.
 *
 * OAuth redirect targets kanban.cinegrid.net/api/freelance_kanban.php (see CG_KANBAN_APP_ORIGIN + site_settings).
 * Pending state is stored in the database so the callback works without a prior Kanban session.
 */
declare(strict_types=1);

if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL')) {
    define('CG_KANBAN_SUBDOMAIN_PORTAL', true);
}

if (!defined('CG_KANBAN_APP_ORIGIN')) {
    define('CG_KANBAN_APP_ORIGIN', 'https://kanban.cinegrid.net');
}

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/public_html/includes/db.php';
require_once __DIR__ . '/includes/google_calendar_sync.php';
require_once __DIR__ . '/includes/cg_client_google_calendar_common.php';

$pdo = getDB();
try {
    cg_google_sync_ensure_schema($pdo);
} catch (Throwable $e) {
    error_log('client_google_sync schema: ' . $e->getMessage());
    cg_client_google_html_shell('Unavailable', '<p class="text-muted mb-0">Calendar linking is temporarily unavailable.</p>');
    exit;
}

$boardId = (int)($_GET['board_id'] ?? $_POST['board_id'] ?? 0);
$token = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['google_sync_invite'] ?? $_POST['google_sync_invite'] ?? '')));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($boardId <= 0 || strlen($token) !== 64) {
    cg_client_google_html_shell(
        'Invalid link',
        '<p class="text-muted mb-0">Use the full link from your invitation email, or ask the board owner to send a new Google Calendar invite.</p>'
    );
    exit;
}

$inv = cg_client_google_load_valid_invite($pdo, $boardId, $token);
if (!$inv) {
    cg_client_google_html_shell('Invite not valid', '<p class="text-muted mb-0">This invite was already used, has expired, or the link is incorrect.</p>');
    exit;
}

$creds = cg_google_sync_get_credentials($pdo);
if ($creds['client_id'] === '' || $creds['client_secret'] === '') {
    cg_client_google_html_shell('Not configured', '<p class="text-muted mb-0">Google Calendar integration is not configured on this server.</p>');
    exit;
}

if (isset($_GET['start']) && (string)$_GET['start'] === '1') {
    try {
        $redirectUri = cg_google_sync_resolve_redirect_uri($creds);
    } catch (Throwable $e) {
        cg_client_google_html_shell('Configuration error', '<p class="text-muted mb-0">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>');
        exit;
    }
    $stateToken = bin2hex(random_bytes(16));
    cg_client_google_oauth_state_save($pdo, $stateToken, $boardId, $token);
    $_SESSION[cg_client_google_oauth_state_key($stateToken)] = [
        'board_id' => $boardId,
        'invite_token' => $token,
        'created_at' => time(),
    ];
    $loginHint = strtolower(trim((string)$inv['email']));
    $query = [
        'client_id' => $creds['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'scope' => 'openid email profile https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/tasks',
        'state' => $stateToken,
    ];
    if ($loginHint !== '' && filter_var($loginHint, FILTER_VALIDATE_EMAIL)) {
        $query['login_hint'] = $loginHint;
    }
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    header('Location: ' . $authUrl, true, 302);
    exit;
}

$stmt = $pdo->prepare('SELECT name FROM kanban_boards WHERE id = ? LIMIT 1');
$stmt->execute([$boardId]);
$boardName = (string)($stmt->fetchColumn() ?: 'Board');
$invEmail = htmlspecialchars((string)$inv['email'], ENT_QUOTES, 'UTF-8');
$bn = htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8');
$startUrl = '?board_id=' . (int)$boardId . '&google_sync_invite=' . rawurlencode($token) . '&start=1';

cg_client_google_html_shell(
    'Connect Google Calendar',
    '<p class="text-muted">Link Google Calendar for <strong>' . $bn . '</strong> without signing in to Kanban.</p>'
    . '<p class="small text-muted">Use the Google account for <strong>' . $invEmail . '</strong> when prompted.</p>'
    . '<a class="btn btn-danger w-100 py-2 fw-semibold mt-2" href="' . htmlspecialchars($startUrl, ENT_QUOTES, 'UTF-8') . '">Continue with Google</a>'
    . '<p class="small text-muted mt-3 mb-0">If the button fails, copy this page’s address from the invitation email and open it again.</p>'
);
