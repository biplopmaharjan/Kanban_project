<?php
/**
 * Shared helpers for no-login Google Calendar invite linking.
 * OAuth callback runs on https://kanban.cinegrid.net/api/freelance_kanban.php (board-owner + client invite flows).
 */

if (!function_exists('cg_google_sync_exchange_code')) {
    require_once __DIR__ . '/google_calendar_sync.php';
}

function cg_client_google_oauth_state_ensure_table(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kanban_google_client_oauth_states (
                state VARCHAR(64) NOT NULL,
                board_id INT UNSIGNED NOT NULL,
                invite_token VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (state),
                KEY idx_gco_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        error_log('cg_client_google_oauth_state_ensure_table: ' . $e->getMessage());
    }
    $done = true;
}

function cg_client_google_oauth_state_save(PDO $pdo, string $state, int $boardId, string $inviteToken): void {
    cg_client_google_oauth_state_ensure_table($pdo);
    try {
        $pdo->exec('DELETE FROM kanban_google_client_oauth_states WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    } catch (Throwable $e) {
        /* ignore */
    }
    $stmt = $pdo->prepare('
        INSERT INTO kanban_google_client_oauth_states (state, board_id, invite_token)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE board_id = VALUES(board_id), invite_token = VALUES(invite_token), created_at = NOW()
    ');
    $stmt->execute([$state, $boardId, $inviteToken]);
}

/**
 * @return array{board_id: int, invite_token: string, created_at: string}|null
 */
function cg_client_google_oauth_state_peek(PDO $pdo, string $state): ?array {
    if ($state === '') {
        return null;
    }
    cg_client_google_oauth_state_ensure_table($pdo);
    $stmt = $pdo->prepare('
        SELECT board_id, invite_token, created_at
        FROM kanban_google_client_oauth_states
        WHERE state = ? AND created_at > DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        LIMIT 1
    ');
    $stmt->execute([$state]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    return [
        'board_id' => (int)$r['board_id'],
        'invite_token' => (string)$r['invite_token'],
        'created_at' => (string)$r['created_at'],
    ];
}

function cg_client_google_oauth_state_delete(PDO $pdo, string $state): void {
    if ($state === '') {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM kanban_google_client_oauth_states WHERE state = ?')->execute([$state]);
    } catch (Throwable $e) {
        error_log('cg_client_google_oauth_state_delete: ' . $e->getMessage());
    }
}

/**
 * @return array{0: string, 1: int} [JSON column list, default column id]
 */
function cg_client_google_normalize_invite_columns(PDO $pdo, int $boardId, string $syncColumnIdsJson): array {
    $decoded = json_decode($syncColumnIdsJson, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    $columnIds = [];
    foreach ($decoded as $v) {
        $cid = (int)$v;
        if ($cid <= 0) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM kanban_columns WHERE id = ? AND board_id = ? LIMIT 1');
        $stmt->execute([$cid, $boardId]);
        if ($stmt->fetchColumn()) {
            $columnIds[$cid] = true;
        }
    }
    if ($columnIds === []) {
        $stmt = $pdo->prepare('SELECT id FROM kanban_columns WHERE board_id = ? ORDER BY position ASC, id ASC LIMIT 1');
        $stmt->execute([$boardId]);
        $first = (int)$stmt->fetchColumn();
        if ($first <= 0) {
            throw new RuntimeException('This board has no columns yet.');
        }
        $columnIds = [$first => true];
    }
    $ids = array_keys($columnIds);
    sort($ids, SORT_NUMERIC);
    $syncJson = json_encode($ids);
    $in = implode(',', array_map('intval', $ids));
    $stmtOrder = $pdo->prepare("SELECT id FROM kanban_columns WHERE board_id = ? AND id IN ($in) ORDER BY position ASC, id ASC LIMIT 1");
    $stmtOrder->execute([$boardId]);
    $default = (int)($stmtOrder->fetchColumn() ?: $ids[0]);
    return [$syncJson, $default];
}

function cg_client_google_board_owner_user_id(PDO $pdo, int $boardId): int {
    $stmt = $pdo->prepare('
        SELECT p.user_id
        FROM kanban_boards b
        INNER JOIN freelance_projects p ON p.id = b.project_id
        WHERE b.id = ?
        LIMIT 1
    ');
    $stmt->execute([$boardId]);
    return (int)$stmt->fetchColumn();
}

function cg_client_google_load_valid_invite(PDO $pdo, int $boardId, string $token): ?array {
    if ($boardId <= 0 || strlen($token) !== 64) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM kanban_google_sync_invites WHERE invite_token = ? AND board_id = ? LIMIT 1');
    $stmt->execute([$token, $boardId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inv) {
        return null;
    }
    if (!empty($inv['used_at'])) {
        return null;
    }
    if (strtotime((string)$inv['expires_at']) < time()) {
        return null;
    }
    return $inv;
}

function cg_client_google_html_shell(string $title, string $bodyHtml): void {
    header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $t . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">';
    echo '<style>body{background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;font-family:system-ui,-apple-system,sans-serif;}.card{max-width:520px;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 12px 40px rgba(15,23,42,.08);}</style>';
    echo '</head><body><div class="card p-4 p-md-5 bg-white"><h1 class="h4 fw-bold mb-3" style="color:#111827;">' . $t . '</h1>';
    echo $bodyHtml;
    echo '</div></body></html>';
}

function cg_client_google_oauth_state_key(string $state): string {
    return 'cg_client_gcal_oauth_' . $state;
}

/**
 * If this request is Google OAuth return for a no-login client invite, complete linking.
 * OAuth state is stored in DB so the callback works on whichever host is registered in Google (e.g. cinegrid.net).
 *
 * @return bool true if this request was fully handled (caller should exit)
 */
function cg_client_google_maybe_finish_oauth_callback(PDO $pdo): bool {
    $action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
    if ($action !== 'google_connect_callback') {
        return false;
    }
    $state = preg_replace('/[^a-f0-9]/', '', strtolower(trim((string)($_GET['state'] ?? ''))));
    if ($state === '' || strlen($state) < 16) {
        return false;
    }
    $stateKey = cg_client_google_oauth_state_key($state);
    $dbRow = cg_client_google_oauth_state_peek($pdo, $state);
    $sessionCtx = (isset($_SESSION[$stateKey]) && is_array($_SESSION[$stateKey])) ? $_SESSION[$stateKey] : null;
    if ($dbRow === null && $sessionCtx === null) {
        return false;
    }
    $fromDb = $dbRow !== null;
    if ($fromDb) {
        $ts = strtotime($dbRow['created_at']);
        $ctx = [
            'board_id' => (int)$dbRow['board_id'],
            'invite_token' => preg_replace('/[^a-f0-9]/', '', strtolower($dbRow['invite_token'])),
            'created_at' => $ts !== false ? $ts : time(),
        ];
    } else {
        $ctx = $sessionCtx;
    }
    if (isset($_SESSION[$stateKey])) {
        unset($_SESSION[$stateKey]);
    }

    try {
        cg_google_sync_ensure_schema($pdo);
    } catch (Throwable $e) {
        error_log('cg_client_google_maybe_finish_oauth_callback schema: ' . $e->getMessage());
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Unavailable', '<p class="text-muted mb-0">Calendar linking is temporarily unavailable.</p>');
        return true;
    }

    $oauthError = trim((string)($_GET['error'] ?? ''));
    if ($oauthError !== '') {
        $oauthDesc = trim((string)($_GET['error_description'] ?? ''));
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        $msg = $oauthDesc !== '' ? $oauthDesc : $oauthError;
        cg_client_google_html_shell('Google sign-in cancelled', '<p class="text-muted mb-0">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>');
        return true;
    }

    $code = trim((string)($_GET['code'] ?? ''));
    if ($code === '') {
        return false;
    }

    if (!is_array($ctx) || (int)($ctx['board_id'] ?? 0) <= 0) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Session expired', '<p class="text-muted mb-0">Please return to the invite page and try connecting again.</p>');
        return true;
    }
    $cbBoard = (int)$ctx['board_id'];
    $cbToken = preg_replace('/[^a-f0-9]/', '', strtolower((string)($ctx['invite_token'] ?? '')));
    if (strlen($cbToken) !== 64) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Invalid session', '<p class="text-muted mb-0">Please use the link from your invitation email.</p>');
        return true;
    }
    if ((int)($ctx['created_at'] ?? 0) < (time() - 900)) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Link timed out', '<p class="text-muted mb-0">Open the invitation email again for a fresh link.</p>');
        return true;
    }

    $invRow = cg_client_google_load_valid_invite($pdo, $cbBoard, $cbToken);
    if (!$invRow) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Invite not valid', '<p class="text-muted mb-0">This invite was already used, expired, or is invalid.</p>');
        return true;
    }
    $ownerUserId = cg_client_google_board_owner_user_id($pdo, $cbBoard);
    if ($ownerUserId <= 0) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Board not found', '<p class="text-muted mb-0">This board no longer exists.</p>');
        return true;
    }
    try {
        [$syncColsJson, $defaultSyncColumnId] = cg_client_google_normalize_invite_columns($pdo, $cbBoard, (string)($invRow['sync_column_ids'] ?? '[]'));
    } catch (RuntimeException $e) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Cannot connect', '<p class="text-muted mb-0">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>');
        return true;
    }

    try {
        $tokenData = cg_google_sync_exchange_code($pdo, $code);
    } catch (Throwable $e) {
        error_log('cg_client_google oauth token: ' . $e->getMessage());
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Google sign-in failed', '<p class="text-muted mb-0">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>');
        return true;
    }

    $access = (string)($tokenData['access_token'] ?? '');
    $refresh = (string)($tokenData['refresh_token'] ?? '');
    $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
    $scope = (string)($tokenData['scope'] ?? '');
    if ($access === '') {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Google sign-in failed', '<p class="text-muted mb-0">No access token returned.</p>');
        return true;
    }
    $profileResp = cg_google_sync_http_json('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', ['Authorization: Bearer ' . $access]);
    if ($profileResp['status'] >= 300 || empty($profileResp['data']['email'])) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Profile error', '<p class="text-muted mb-0">Could not read your Google account email.</p>');
        return true;
    }
    $email = strtolower(trim((string)$profileResp['data']['email']));
    $sub = trim((string)($profileResp['data']['sub'] ?? ''));
    $name = trim((string)($profileResp['data']['name'] ?? ''));
    $want = strtolower(trim((string)$invRow['email']));
    if ($email !== $want) {
        if ($fromDb) {
            cg_client_google_oauth_state_delete($pdo, $state);
        }
        cg_client_google_html_shell('Wrong Google account', '<p class="text-muted mb-0">Sign in with <strong>' . htmlspecialchars($want, ENT_QUOTES, 'UTF-8') . '</strong> — the address this invite was sent to.</p>');
        return true;
    }
    $stmt = $pdo->prepare('
        INSERT INTO kanban_google_accounts (board_id, owner_user_id, google_email, google_sub, display_name, access_token, refresh_token, token_expires_at, scope, allow_member_link, is_active, default_sync_column_id, sync_column_ids)
        VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, 1, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            owner_user_id = VALUES(owner_user_id),
            google_sub = VALUES(google_sub),
            display_name = VALUES(display_name),
            access_token = VALUES(access_token),
            refresh_token = IF(VALUES(refresh_token) IS NULL OR VALUES(refresh_token) = \'\', refresh_token, VALUES(refresh_token)),
            token_expires_at = VALUES(token_expires_at),
            scope = VALUES(scope),
            is_active = 1,
            default_sync_column_id = VALUES(default_sync_column_id),
            sync_column_ids = VALUES(sync_column_ids)
    ');
    $stmt->execute([
        $cbBoard,
        $ownerUserId,
        $email,
        $sub !== '' ? $sub : null,
        $name !== '' ? $name : null,
        $access,
        $refresh !== '' ? $refresh : null,
        max(60, $expiresIn),
        $scope !== '' ? $scope : null,
        $defaultSyncColumnId,
        $syncColsJson,
    ]);
    $gaid = (int)$pdo->lastInsertId();
    if ($gaid <= 0) {
        $stmtId = $pdo->prepare('SELECT id FROM kanban_google_accounts WHERE board_id = ? AND google_email = ? ORDER BY id DESC LIMIT 1');
        $stmtId->execute([$cbBoard, $email]);
        $gaid = (int)$stmtId->fetchColumn();
    }
    if ($gaid > 0) {
        cg_google_sync_select_primary_calendar_for_account($pdo, $cbBoard, $gaid, $access);
    }
    $pdo->prepare('UPDATE kanban_google_sync_invites SET used_at = NOW() WHERE id = ? LIMIT 1')->execute([(int)$invRow['id']]);
    if ($fromDb) {
        cg_client_google_oauth_state_delete($pdo, $state);
    }
    cg_client_google_html_shell(
        'Calendar connected',
        '<p class="text-muted">Your Google Calendar is linked to this board. Events will stay in sync automatically — you do not need a CineGrid Kanban login.</p>'
        . '<p class="small text-muted mb-0">You can close this window.</p>'
    );
    return true;
}
