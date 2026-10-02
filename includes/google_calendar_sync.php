<?php

require_once dirname(__DIR__, 2) . '/public_html/includes/db.php';

/**
 * Public host for building a relative GOOGLE_REDIRECT_URI (HTTP_HOST, then proxy headers, then SERVER_NAME).
 */
function cg_google_sync_request_public_host(): string {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        return $host;
    }
    $xfwd = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    if ($xfwd !== '') {
        $host = trim(explode(',', $xfwd, 2)[0]);
        if ($host !== '') {
            return $host;
        }
    }
    return trim((string)($_SERVER['SERVER_NAME'] ?? ''));
}

/**
 * Google OAuth redirect URLs only need http(s) + host; PHP's FILTER_VALIDATE_URL rejects many valid hosts (e.g. underscores).
 */
function cg_google_sync_is_valid_oauth_redirect_url(string $url): bool {
    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }
    return trim((string)($parts['host'] ?? '')) !== '';
}

/**
 * Kanban Google OAuth must complete on this subdomain API (not apex cinegrid.net).
 * When CG_KANBAN_APP_ORIGIN is set, rewrite freelance_kanban.php callback URLs to that origin.
 */
function cg_google_sync_normalize_oauth_redirect_to_kanban_host(string $url): string {
    if (!defined('CG_KANBAN_APP_ORIGIN')) {
        return $url;
    }
    $origin = rtrim(trim((string) CG_KANBAN_APP_ORIGIN), '/');
    if ($origin === '' || !preg_match('#\Ahttps?://#i', $origin)) {
        return $url;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['path'])) {
        return $url;
    }
    if (stripos((string) $parts['path'], 'freelance_kanban.php') === false) {
        return $url;
    }
    $o = parse_url($origin);
    if ($o === false || empty($o['scheme']) || empty($o['host'])) {
        return $url;
    }
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    unset($query['action']);
    $rebuilt = ($o['scheme'] ?? 'https') . '://' . ($o['host'] ?? '');
    if (!empty($o['port'])) {
        $rebuilt .= ':' . (int) $o['port'];
    }
    $rebuilt .= $parts['path'] ?? '';
    if ($query !== []) {
        $rebuilt .= '?' . http_build_query($query);
    }
    return $rebuilt;
}

function cg_google_sync_resolve_redirect_uri(array $creds): string {
    $redirectUri = trim((string)($creds['redirect_uri'] ?? ''));
    if ($redirectUri === '') {
        throw new RuntimeException('Google redirect URI is empty');
    }
    if (strpos($redirectUri, '://') === false) {
        $host = cg_google_sync_request_public_host();
        if ($host === '') {
            throw new RuntimeException(
                'Google redirect URI is a path only (no scheme) but the request host is unknown. ' .
                'Set GOOGLE_REDIRECT_URI (or site_settings google_redirect_uri) to a full URL, e.g. https://yourdomain.com/api/freelance_kanban.php'
            );
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $xfwdProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($xfwdProto === 'https' || $xfwdProto === 'http') {
            $scheme = $xfwdProto;
        } else {
            $scheme = $https ? 'https' : 'http';
        }
        if ($redirectUri[0] !== '/') {
            $redirectUri = '/' . $redirectUri;
        }
        $redirectUri = $scheme . '://' . $host . $redirectUri;
    }
    if (!cg_google_sync_is_valid_oauth_redirect_url($redirectUri)) {
        throw new RuntimeException('Google redirect URI is invalid (use https://... with a valid host, or a path like /api/freelance_kanban.php when the site is loaded in a browser)');
    }
    if (strpos($redirectUri, 'action=google_connect_callback') !== false) {
        $p = parse_url($redirectUri);
        if ($p !== false && !empty($p['scheme']) && !empty($p['host'])) {
            $q = [];
            if (!empty($p['query'])) {
                parse_str((string) $p['query'], $q);
            }
            unset($q['action']);
            $rebuilt = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
            if (!empty($p['port'])) {
                $rebuilt .= ':' . (int) $p['port'];
            }
            $rebuilt .= $p['path'] ?? '';
            if ($q !== []) {
                $rebuilt .= '?' . http_build_query($q);
            }
            return cg_google_sync_normalize_oauth_redirect_to_kanban_host($rebuilt);
        }
        return cg_google_sync_normalize_oauth_redirect_to_kanban_host($redirectUri);
    }
    $parts = parse_url($redirectUri);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        throw new RuntimeException('Google redirect URI is malformed');
    }
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
    }
    unset($query['action']);
    $rebuilt = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    if (!empty($parts['port'])) {
        $rebuilt .= ':' . (int) $parts['port'];
    }
    $rebuilt .= $parts['path'] ?? '';
    if ($query !== []) {
        $rebuilt .= '?' . http_build_query($query);
    }
    return cg_google_sync_normalize_oauth_redirect_to_kanban_host($rebuilt);
}

function cg_google_sync_ensure_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            owner_user_id INT UNSIGNED NOT NULL,
            google_email VARCHAR(255) NOT NULL,
            google_sub VARCHAR(255) NULL,
            display_name VARCHAR(255) NULL,
            access_token TEXT NULL,
            refresh_token TEXT NULL,
            token_expires_at DATETIME NULL,
            scope TEXT NULL,
            allow_member_link TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_board_google_email (board_id, google_email),
            KEY idx_board_id (board_id),
            KEY idx_owner_user_id (owner_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $pdo->exec("ALTER TABLE kanban_google_accounts ADD COLUMN default_sync_column_id INT UNSIGNED NULL DEFAULT NULL AFTER is_active");
    } catch (Throwable $e) {
        // Column already exists
    }
    try {
        $pdo->exec("ALTER TABLE kanban_google_accounts ADD COLUMN google_tasks_list_id VARCHAR(255) NULL DEFAULT NULL AFTER default_sync_column_id");
    } catch (Throwable $e) {
        // Column already exists
    }
    try {
        $pdo->exec("ALTER TABLE kanban_google_accounts ADD COLUMN sync_column_ids TEXT NULL DEFAULT NULL AFTER google_tasks_list_id");
    } catch (Throwable $e) {
        // Column already exists
    }
    try {
        $pdo->exec("ALTER TABLE kanban_google_event_links ADD COLUMN link_target VARCHAR(24) NOT NULL DEFAULT 'calendar_event' AFTER source_direction");
    } catch (Throwable $e) {
        // Column already exists
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_account_members (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            google_account_id INT UNSIGNED NOT NULL,
            member_user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_account_member (google_account_id, member_user_id),
            KEY idx_board_member (board_id, member_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_calendars (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            google_account_id INT UNSIGNED NOT NULL,
            calendar_id VARCHAR(255) NOT NULL,
            calendar_summary VARCHAR(255) NULL,
            time_zone VARCHAR(128) NULL,
            is_selected TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_account_calendar (google_account_id, calendar_id),
            KEY idx_board_id (board_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_event_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            card_id INT UNSIGNED NOT NULL,
            google_account_id INT UNSIGNED NOT NULL,
            calendar_id VARCHAR(255) NOT NULL,
            google_event_id VARCHAR(255) NOT NULL,
            google_etag VARCHAR(255) NULL,
            source_direction VARCHAR(16) NOT NULL DEFAULT 'board',
            sync_lock_token VARCHAR(80) NULL,
            last_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_card_calendar_account (card_id, google_account_id, calendar_id),
            UNIQUE KEY uq_google_event (google_account_id, calendar_id, google_event_id),
            KEY idx_board_id (board_id),
            KEY idx_card_id (card_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kanban_google_deleted_import_events (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                board_id INT UNSIGNED NOT NULL,
                google_account_id INT UNSIGNED NOT NULL,
                calendar_id VARCHAR(255) NOT NULL,
                google_event_id VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_deleted_import_event (google_account_id, calendar_id, google_event_id(191)),
                KEY idx_board_id (board_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $e) {
        error_log('cg_google_sync_ensure_schema kanban_google_deleted_import_events: ' . $e->getMessage());
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_sync_cursors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            google_account_id INT UNSIGNED NOT NULL,
            calendar_id VARCHAR(255) NOT NULL,
            sync_token TEXT NULL,
            page_token TEXT NULL,
            last_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sync_cursor (google_account_id, calendar_id),
            KEY idx_board_id (board_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_sync_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            google_account_id INT UNSIGNED NULL,
            card_id INT UNSIGNED NULL,
            direction VARCHAR(16) NOT NULL,
            action_name VARCHAR(48) NOT NULL,
            status VARCHAR(16) NOT NULL,
            message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_board_created (board_id, created_at),
            KEY idx_account_created (google_account_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kanban_google_sync_invites (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                board_id INT UNSIGNED NOT NULL,
                invite_token VARCHAR(64) NOT NULL,
                email VARCHAR(255) NOT NULL,
                sync_column_ids TEXT NOT NULL,
                invited_by_user_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_google_sync_invite_token (invite_token),
                KEY idx_google_sync_invite_board_email (board_id, email(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $e) {
        error_log('cg_google_sync_ensure_schema kanban_google_sync_invites: ' . $e->getMessage());
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
        error_log('cg_google_sync_ensure_schema kanban_google_client_oauth_states: ' . $e->getMessage());
    }
}

/**
 * When a card is deleted, Google Calendar may still hold the event (API delete can fail).
 * Record the event identity so incremental sync does not insert a new card for it again.
 */
function cg_google_sync_suppress_deleted_import_event(PDO $pdo, int $boardId, int $googleAccountId, string $calendarId, string $googleEventId): void {
    $cal = trim($calendarId);
    $ev = trim($googleEventId);
    if ($googleAccountId <= 0 || $cal === '' || $ev === '') {
        return;
    }
    try {
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO kanban_google_deleted_import_events (board_id, google_account_id, calendar_id, google_event_id)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([max(0, $boardId), $googleAccountId, $cal, $ev]);
    } catch (Throwable $e) {
        error_log('cg_google_sync_suppress_deleted_import_event: ' . $e->getMessage());
    }
}

function cg_google_sync_is_deleted_import_event_suppressed(PDO $pdo, int $googleAccountId, string $calendarId, string $googleEventId): bool {
    $cal = trim($calendarId);
    $ev = trim($googleEventId);
    if ($googleAccountId <= 0 || $cal === '' || $ev === '') {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM kanban_google_deleted_import_events WHERE google_account_id = ? AND calendar_id = ? AND google_event_id = ? LIMIT 1');
        $stmt->execute([$googleAccountId, $cal, $ev]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Load Kanban Google OAuth client from a Google-downloaded JSON file (web client).
 * Path: getenv('GOOGLE_CALENDAR_OAUTH_JSON') or config/google_calendar_oauth.json next to public_html.
 * When redirect_uris lists several entries, picks the one whose host matches the current request host
 * (so production is not stuck with localhost as [0]).
 */
function cg_google_sync_load_oauth_json_file(?string $path = null): ?array {
    if ($path === null || $path === '') {
        $path = trim((string)getenv('GOOGLE_CALENDAR_OAUTH_JSON'));
    }
    if ($path === '') {
        $path = dirname(__DIR__, 2) . '/config/google_calendar_oauth.json';
    }
    if ($path === '' || !is_readable($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['web']) || !is_array($data['web'])) {
        return null;
    }
    $web = $data['web'];
    $clientId = trim((string)($web['client_id'] ?? ''));
    $clientSecret = trim((string)($web['client_secret'] ?? ''));
    $redirectUri = '';
    if (!empty($web['redirect_uris']) && is_array($web['redirect_uris'])) {
        $candidates = [];
        foreach ($web['redirect_uris'] as $u) {
            $u = trim((string)$u);
            if ($u !== '') {
                $candidates[] = $u;
            }
        }
        if ($candidates !== []) {
            $requestHost = strtolower(cg_google_sync_request_public_host());
            if ($requestHost !== '') {
                foreach ($candidates as $u) {
                    $parts = parse_url($u);
                    $h = strtolower(trim((string)($parts['host'] ?? '')));
                    if ($h !== '' && $h === $requestHost) {
                        $redirectUri = $u;
                        break;
                    }
                }
            }
            if ($redirectUri === '' && defined('CG_KANBAN_APP_ORIGIN')) {
                $originRaw = rtrim(trim((string) CG_KANBAN_APP_ORIGIN), '/');
                $wantHost = '';
                if ($originRaw !== '' && preg_match('#\Ahttps?://#i', $originRaw)) {
                    $oh = parse_url($originRaw);
                    if ($oh !== false) {
                        $wantHost = strtolower(trim((string) ($oh['host'] ?? '')));
                    }
                }
                if ($wantHost !== '') {
                    foreach ($candidates as $u) {
                        $parts = parse_url($u);
                        $h = strtolower(trim((string) ($parts['host'] ?? '')));
                        if ($h !== '' && $h === $wantHost) {
                            $redirectUri = $u;
                            break;
                        }
                    }
                }
            }
            if ($redirectUri === '') {
                $redirectUri = $candidates[0];
            }
        }
    }
    if ($clientId === '' || $clientSecret === '') {
        return null;
    }
    return ['client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirectUri];
}

/**
 * After linking Google, turn on the user's primary calendar (so background sync can import events).
 */
function cg_google_sync_select_primary_calendar_for_account(PDO $pdo, int $boardId, int $googleAccountId, string $accessToken): void {
    $res = cg_google_sync_http_json('GET', 'https://www.googleapis.com/calendar/v3/users/me/calendarList', ['Authorization: Bearer ' . $accessToken]);
    if ($res['status'] >= 300) {
        return;
    }
    $items = $res['data']['items'] ?? [];
    if (!is_array($items) || $items === []) {
        return;
    }
    $pick = null;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        if (!empty($it['primary'])) {
            $pick = $it;
            break;
        }
    }
    if ($pick === null) {
        $pick = $items[0];
    }
    if (!is_array($pick)) {
        return;
    }
    $cid = trim((string)($pick['id'] ?? ''));
    if ($cid === '') {
        return;
    }
    try {
        $pdo->prepare('UPDATE kanban_google_calendars SET is_selected = 0 WHERE google_account_id = ?')->execute([$googleAccountId]);
        $pdo->prepare('
            INSERT INTO kanban_google_calendars (board_id, google_account_id, calendar_id, calendar_summary, time_zone, is_selected)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                calendar_summary = VALUES(calendar_summary),
                time_zone = VALUES(time_zone),
                is_selected = 1
        ')->execute([
            $boardId,
            $googleAccountId,
            $cid,
            trim((string)($pick['summary'] ?? '')) !== '' ? trim((string)$pick['summary']) : null,
            trim((string)($pick['timeZone'] ?? '')) !== '' ? trim((string)$pick['timeZone']) : null,
        ]);
    } catch (Throwable $e) {
        error_log('cg_google_sync_select_primary_calendar_for_account: ' . $e->getMessage());
    }
}

function cg_google_sync_get_credentials(PDO $pdo): array {
    $clientId = trim((string)getenv('GOOGLE_CLIENT_ID'));
    $clientSecret = trim((string)getenv('GOOGLE_CLIENT_SECRET'));
    $redirectUri = trim((string)getenv('GOOGLE_REDIRECT_URI'));
    if ($clientId !== '' && $clientSecret !== '' && $redirectUri !== '') {
        return ['client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirectUri];
    }

    $fromJson = cg_google_sync_load_oauth_json_file();
    if (is_array($fromJson) && $fromJson['client_id'] !== '' && $fromJson['client_secret'] !== '') {
        if ($fromJson['redirect_uri'] === '') {
            $fromJson['redirect_uri'] = trim((string)getenv('GOOGLE_REDIRECT_URI'));
        }
        if ($fromJson['redirect_uri'] !== '') {
            return $fromJson;
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(191) NOT NULL,
            setting_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('google_client_id','google_client_secret','google_redirect_uri')");
    $stmt->execute();
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(string)$row['setting_key']] = trim((string)($row['setting_value'] ?? ''));
    }
    return [
        'client_id' => $map['google_client_id'] ?? '',
        'client_secret' => $map['google_client_secret'] ?? '',
        'redirect_uri' => $map['google_redirect_uri'] ?? '',
    ];
}

function cg_google_sync_has_credentials(PDO $pdo): bool {
    $c = cg_google_sync_get_credentials($pdo);
    return $c['client_id'] !== '' && $c['client_secret'] !== '' && $c['redirect_uri'] !== '';
}

function cg_google_sync_log(PDO $pdo, int $boardId, ?int $accountId, ?int $cardId, string $direction, string $action, string $status, string $message = ''): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO kanban_google_sync_log (board_id, google_account_id, card_id, direction, action_name, status, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$boardId, $accountId, $cardId, $direction, $action, $status, $message !== '' ? $message : null]);
    } catch (Throwable $e) {
        error_log('cg_google_sync_log: ' . $e->getMessage());
    }
}

function cg_google_sync_http_json(string $method, string $url, array $headers = [], ?array $payload = null): array {
    $ch = curl_init();
    $allHeaders = array_merge(['Accept: application/json'], $headers);
    if ($payload !== null) {
        $allHeaders[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException($err !== '' ? $err : 'HTTP request failed');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = ['raw' => $raw];
    }
    return ['status' => $code, 'data' => $data, 'raw' => $raw];
}

function cg_google_sync_exchange_code(PDO $pdo, string $authCode): array {
    $creds = cg_google_sync_get_credentials($pdo);
    if ($creds['client_id'] === '' || $creds['client_secret'] === '' || $creds['redirect_uri'] === '') {
        throw new RuntimeException('Google OAuth credentials are not configured');
    }
    $redirectUri = cg_google_sync_resolve_redirect_uri($creds);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://oauth2.googleapis.com/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $authCode,
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException($err !== '' ? $err : 'Token exchange failed');
    $data = json_decode($raw, true);
    if (!is_array($data) || $code >= 300) {
        throw new RuntimeException(is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'Token exchange failed') : 'Token exchange failed');
    }
    return $data;
}

function cg_google_sync_refresh_access_token(PDO $pdo, array $account): ?string {
    $refresh = trim((string)($account['refresh_token'] ?? ''));
    if ($refresh === '') return null;
    $creds = cg_google_sync_get_credentials($pdo);
    if ($creds['client_id'] === '' || $creds['client_secret'] === '') return null;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://oauth2.googleapis.com/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException($err !== '' ? $err : 'Token refresh failed');
    $data = json_decode($raw, true);
    if (!is_array($data) || $code >= 300 || empty($data['access_token'])) {
        return null;
    }
    $expiresIn = (int)($data['expires_in'] ?? 3600);
    $stmt = $pdo->prepare("UPDATE kanban_google_accounts SET access_token = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW() WHERE id = ?");
    $stmt->execute([(string)$data['access_token'], max(60, $expiresIn), (int)$account['id']]);
    return (string)$data['access_token'];
}

function cg_google_sync_account_token(PDO $pdo, array $account): ?string {
    $token = trim((string)($account['access_token'] ?? ''));
    $exp = trim((string)($account['token_expires_at'] ?? ''));
    if ($token !== '' && $exp !== '' && strtotime($exp) !== false && strtotime($exp) > (time() + 60)) {
        return $token;
    }
    return cg_google_sync_refresh_access_token($pdo, $account);
}

function cg_google_sync_build_event_payload(array $card, int $boardId): array {
    $title = trim((string)($card['title'] ?? 'Untitled'));
    $description = trim((string)($card['description'] ?? ''));
    $startDate = trim((string)($card['start_date'] ?? ''));
    $dueDate = trim((string)($card['due_date'] ?? ''));
    if ($startDate === '' && $dueDate !== '') $startDate = $dueDate;
    if ($dueDate === '' && $startDate !== '') $dueDate = $startDate;
    if ($startDate === '' || $dueDate === '') {
        return [];
    }
    $endDate = date('Y-m-d', strtotime($dueDate . ' +1 day'));
    return [
        'summary' => $title,
        'description' => $description,
        'start' => ['date' => $startDate],
        'end' => ['date' => $endDate],
        'extendedProperties' => [
            'private' => [
                'cg_source' => 'kanban',
                'cg_board_id' => (string)$boardId,
                'cg_card_id' => (string)((int)($card['id'] ?? 0)),
            ],
        ],
    ];
}

/**
 * RFC3339 datetime with explicit offset for Google Tasks `due`.
 * Offset form avoids Calendar treating the task as an all-day item (midnight UTC issues).
 * Uses server default timezone for wall time when the card has no due time (default 6 PM local).
 */
function cg_google_sync_task_due_rfc3339(string $dueDate, ?string $dueTime): string {
    $timeStr = trim((string)$dueTime);
    if ($timeStr === '') {
        $timeStr = '18:00:00';
    }
    if (strlen($timeStr) === 5 && strpos($timeStr, ':') === 2) {
        $timeStr .= ':00';
    }
    $tzName = @date_default_timezone_get() ?: 'UTC';
    try {
        $tz = new DateTimeZone($tzName);
        $dt = new DateTimeImmutable($dueDate . ' ' . $timeStr, $tz);
        return $dt->format(DateTimeInterface::ATOM);
    } catch (Throwable $e) {
        try {
            $tz = new DateTimeZone($tzName);
            $dt = new DateTimeImmutable($dueDate . ' 18:00:00', $tz);
            return $dt->format(DateTimeInterface::ATOM);
        } catch (Throwable $e2) {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
        }
    }
}

/** True when the card has a wall-clock time (Tasks often render as all-day in Google Calendar). */
function cg_google_sync_card_has_explicit_wall_time(array $card): bool {
    return trim((string)($card['due_time'] ?? '')) !== '' || trim((string)($card['start_time'] ?? '')) !== '';
}

/**
 * Primary calendar timed event so Google Calendar shows a clock time (not Task “Add time” / all-day).
 * End = start + 1 hour.
 */
function cg_google_sync_build_calendar_timed_event_payload(array $card, int $boardId): ?array {
    $startDate = trim((string)($card['start_date'] ?? ''));
    $dueDate = trim((string)($card['due_date'] ?? ''));
    if ($startDate === '' && $dueDate !== '') {
        $startDate = $dueDate;
    }
    if ($dueDate === '' && $startDate !== '') {
        $dueDate = $startDate;
    }
    if ($startDate === '' || $dueDate === '') {
        return null;
    }
    $dateStr = '';
    $timeRaw = '';
    if (trim((string)($card['due_time'] ?? '')) !== '') {
        $timeRaw = trim((string) $card['due_time']);
        $dateStr = $dueDate !== '' ? $dueDate : $startDate;
    } elseif (trim((string)($card['start_time'] ?? '')) !== '') {
        $timeRaw = trim((string) $card['start_time']);
        $dateStr = $startDate !== '' ? $startDate : $dueDate;
    } else {
        return null;
    }
    if (strlen($timeRaw) === 5 && strpos($timeRaw, ':') === 2) {
        $timeRaw .= ':00';
    }
    $tzName = @date_default_timezone_get() ?: 'UTC';
    try {
        $tz = new DateTimeZone($tzName);
        $startDt = new DateTimeImmutable($dateStr . ' ' . $timeRaw, $tz);
        $endDt = $startDt->modify('+1 hour');
    } catch (Throwable $e) {
        return null;
    }
    $title = trim((string)($card['title'] ?? 'Untitled'));
    $description = trim((string)($card['description'] ?? ''));
    $notes = $description;
    if ($notes !== '') {
        $notes .= "\n\n";
    }
    $notes .= 'CineGrid Kanban · board #' . $boardId . ' · card #' . (int)($card['id'] ?? 0);
    $startAtom = $startDt->format(DateTimeInterface::ATOM);
    $endAtom = $endDt->format(DateTimeInterface::ATOM);
    return [
        'summary' => $title !== '' ? $title : 'Untitled',
        'description' => $notes,
        'start' => ['dateTime' => $startAtom, 'timeZone' => $tzName],
        'end' => ['dateTime' => $endAtom, 'timeZone' => $tzName],
        'extendedProperties' => [
            'private' => [
                'cg_source' => 'kanban',
                'cg_board_id' => (string) $boardId,
                'cg_card_id' => (string) (int) ($card['id'] ?? 0),
            ],
        ],
    ];
}

const CG_GOOGLE_SYNC_BOARD_PUSH_CALENDAR_ID = 'primary';

/** Board → Google: one Google Task per card (not Calendar events — avoids duplicate event+task clutter). */
function cg_google_sync_build_task_payload(array $card, int $boardId): ?array {
    $title = trim((string)($card['title'] ?? 'Untitled'));
    $description = trim((string)($card['description'] ?? ''));
    $startDate = trim((string)($card['start_date'] ?? ''));
    $dueDate = trim((string)($card['due_date'] ?? ''));
    if ($startDate === '' && $dueDate !== '') {
        $startDate = $dueDate;
    }
    if ($dueDate === '' && $startDate !== '') {
        $dueDate = $startDate;
    }
    if ($startDate === '' || $dueDate === '') {
        return null;
    }
    $dueTimeRaw = trim((string)($card['due_time'] ?? '')) !== '' ? trim((string)($card['due_time'] ?? '')) : null;
    if ($dueTimeRaw === null || $dueTimeRaw === '') {
        $stWall = trim((string)($card['start_time'] ?? ''));
        if ($stWall !== '') {
            $dueTimeRaw = $stWall;
        }
    }
    $dueRfc = cg_google_sync_task_due_rfc3339($dueDate, $dueTimeRaw);
    $notes = $description;
    if ($notes !== '') {
        $notes .= "\n\n";
    }
    $notes .= 'CineGrid Kanban · board #' . $boardId . ' · card #' . (int)($card['id'] ?? 0);
    return [
        'title' => $title !== '' ? $title : 'Untitled',
        'notes' => $notes,
        'due' => $dueRfc,
        'status' => 'needsAction',
    ];
}

const CG_GOOGLE_SYNC_TASK_LIST_TITLE = 'CineGrid Kanban';

function cg_google_sync_ensure_task_list(PDO $pdo, int $googleAccountId, string $token): ?string {
    $stmt = $pdo->prepare('SELECT google_tasks_list_id FROM kanban_google_accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$googleAccountId]);
    $existing = trim((string)$stmt->fetchColumn());
    if ($existing !== '') {
        return $existing;
    }
    $res = cg_google_sync_http_json('GET', 'https://tasks.googleapis.com/tasks/v1/users/@me/lists', ['Authorization: Bearer ' . $token]);
    if ($res['status'] >= 300) {
        return null;
    }
    $items = $res['data']['items'] ?? [];
    if (is_array($items)) {
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            if (trim((string)($it['title'] ?? '')) === CG_GOOGLE_SYNC_TASK_LIST_TITLE) {
                $lid = trim((string)($it['id'] ?? ''));
                if ($lid !== '') {
                    $pdo->prepare('UPDATE kanban_google_accounts SET google_tasks_list_id = ? WHERE id = ?')->execute([$lid, $googleAccountId]);
                    return $lid;
                }
            }
        }
    }
    $res2 = cg_google_sync_http_json('POST', 'https://tasks.googleapis.com/tasks/v1/users/@me/lists', ['Authorization: Bearer ' . $token], [
        'title' => CG_GOOGLE_SYNC_TASK_LIST_TITLE,
    ]);
    if ($res2['status'] >= 300) {
        return null;
    }
    $lid = trim((string)($res2['data']['id'] ?? ''));
    if ($lid === '') {
        return null;
    }
    $pdo->prepare('UPDATE kanban_google_accounts SET google_tasks_list_id = ? WHERE id = ?')->execute([$lid, $googleAccountId]);
    return $lid;
}

/**
 * Validated import column ids for this account. Empty array = no push filter (all columns) and pull uses first board column only (legacy).
 */
function cg_google_sync_account_resolved_import_column_ids(PDO $pdo, int $boardId, array $acc): array {
    $raw = trim((string)($acc['sync_column_ids'] ?? ''));
    $ids = [];
    if ($raw !== '') {
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            foreach ($dec as $v) {
                $i = (int)$v;
                if ($i <= 0) {
                    continue;
                }
                $chk = $pdo->prepare('SELECT 1 FROM kanban_columns WHERE id = ? AND board_id = ? LIMIT 1');
                $chk->execute([$i, $boardId]);
                if ($chk->fetchColumn()) {
                    $ids[$i] = true;
                }
            }
        }
    }
    if ($ids !== []) {
        return array_keys($ids);
    }
    $cid = (int)($acc['default_sync_column_id'] ?? 0);
    if ($cid > 0) {
        $chk = $pdo->prepare('SELECT 1 FROM kanban_columns WHERE id = ? AND board_id = ? LIMIT 1');
        $chk->execute([$cid, $boardId]);
        if ($chk->fetchColumn()) {
            return [$cid];
        }
    }
    return [];
}

function cg_google_sync_board_first_column_id(PDO $pdo, int $boardId): int {
    $stmt = $pdo->prepare('SELECT id FROM kanban_columns WHERE board_id = ? ORDER BY position ASC, id ASC LIMIT 1');
    $stmt->execute([$boardId]);
    return (int)($stmt->fetchColumn() ?: 0);
}

/** First column by board order among the import set; if set empty, first column on the board. */
function cg_google_sync_account_primary_import_column_id(PDO $pdo, int $boardId, array $acc): int {
    $ids = cg_google_sync_account_resolved_import_column_ids($pdo, $boardId, $acc);
    if ($ids === []) {
        return cg_google_sync_board_first_column_id($pdo, $boardId);
    }
    $in = implode(',', array_map('intval', $ids));
    if ($in === '') {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT id FROM kanban_columns WHERE board_id = ? AND id IN ($in) ORDER BY position ASC, id ASC LIMIT 1");
    $stmt->execute([$boardId]);
    return (int)($stmt->fetchColumn() ?: 0);
}

/** Remove board-originated Task + Calendar links for this card/account (Google API + DB). */
function cg_google_sync_clear_board_push_links(PDO $pdo, int $boardId, int $cardId, int $googleAccountId, string $token): void {
    $stmtT = $pdo->prepare("
        SELECT id, calendar_id, google_event_id FROM kanban_google_event_links
        WHERE card_id = ? AND google_account_id = ? AND source_direction = 'board' AND link_target = 'task'
        LIMIT 1
    ");
    $stmtT->execute([$cardId, $googleAccountId]);
    $taskLink = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($taskLink && !empty($taskLink['google_event_id'])) {
        $tl = (string)$taskLink['calendar_id'];
        $tid = (string)$taskLink['google_event_id'];
        try {
            cg_google_sync_http_json('DELETE', 'https://tasks.googleapis.com/tasks/v1/lists/' . rawurlencode($tl) . '/tasks/' . rawurlencode($tid), [
                'Authorization: Bearer ' . $token,
            ]);
        } catch (Throwable $e) {
            cg_google_sync_log($pdo, $boardId, $googleAccountId, $cardId, 'board_to_google', 'cleanup', 'error', $e->getMessage());
        }
        $pdo->prepare('DELETE FROM kanban_google_event_links WHERE id = ?')->execute([(int)$taskLink['id']]);
    }
    $stmtC = $pdo->prepare("
        SELECT id, calendar_id, google_event_id FROM kanban_google_event_links
        WHERE card_id = ? AND google_account_id = ? AND source_direction = 'board'
          AND (link_target IS NULL OR link_target = '' OR link_target = 'calendar_event')
    ");
    $stmtC->execute([$cardId, $googleAccountId]);
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [] as $leg) {
        try {
            cg_google_sync_http_json('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$leg['calendar_id']) . '/events/' . rawurlencode((string)$leg['google_event_id']), [
                'Authorization: Bearer ' . $token,
            ]);
        } catch (Throwable $e) {
            cg_google_sync_log($pdo, $boardId, $googleAccountId, $cardId, 'board_to_google', 'cleanup', 'error', $e->getMessage());
        }
        $pdo->prepare('DELETE FROM kanban_google_event_links WHERE id = ?')->execute([(int)$leg['id']]);
    }
}

function cg_google_sync_upsert_card_events(PDO $pdo, int $boardId, int $cardId, int $actorUserId = 0, ?int $onlyGoogleAccountId = null): void {
    $stmtCard = $pdo->prepare("
        SELECT k.id, k.column_id, k.title, k.description, k.start_date, k.start_time, k.due_date, k.due_time
        FROM kanban_cards k
        JOIN kanban_columns c ON c.id = k.column_id
        WHERE k.id = ? AND c.board_id = ?
        LIMIT 1
    ");
    $stmtCard->execute([$cardId, $boardId]);
    $card = $stmtCard->fetch(PDO::FETCH_ASSOC);
    if (!$card) {
        return;
    }
    $taskPayload = cg_google_sync_build_task_payload($card, $boardId);
    $calTimedPayload = null;
    if ($taskPayload !== null && cg_google_sync_card_has_explicit_wall_time($card)) {
        $calTimedPayload = cg_google_sync_build_calendar_timed_event_payload($card, $boardId);
    }
    $useCalTimed = $calTimedPayload !== null;

    if ($actorUserId > 0) {
        $stmtAcc = $pdo->prepare("
            SELECT DISTINCT a.*
            FROM kanban_google_accounts a
            LEFT JOIN kanban_google_account_members m ON m.google_account_id = a.id
            WHERE a.board_id = ? AND a.is_active = 1
              AND (a.owner_user_id = ? OR m.member_user_id = ? OR a.allow_member_link = 1)
        ");
        $stmtAcc->execute([$boardId, $actorUserId, $actorUserId]);
    } else {
        $stmtAcc = $pdo->prepare('SELECT * FROM kanban_google_accounts WHERE board_id = ? AND is_active = 1');
        $stmtAcc->execute([$boardId]);
    }
    $accounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($onlyGoogleAccountId !== null && $onlyGoogleAccountId > 0) {
        $accounts = array_values(array_filter($accounts, static fn($a) => (int)($a['id'] ?? 0) === $onlyGoogleAccountId));
    }

    $stmtTaskLink = $pdo->prepare("
        SELECT id, calendar_id, google_event_id FROM kanban_google_event_links
        WHERE card_id = ? AND google_account_id = ? AND source_direction = 'board' AND link_target = 'task'
        LIMIT 1
    ");
    $stmtCalBoardLink = $pdo->prepare("
        SELECT id, calendar_id, google_event_id FROM kanban_google_event_links
        WHERE card_id = ? AND google_account_id = ? AND source_direction = 'board' AND link_target = 'calendar_event'
        LIMIT 1
    ");
    $stmtCalBoardLinksAll = $pdo->prepare("
        SELECT id, calendar_id, google_event_id FROM kanban_google_event_links
        WHERE card_id = ? AND google_account_id = ? AND source_direction = 'board'
          AND (link_target IS NULL OR link_target = '' OR link_target = 'calendar_event')
    ");

    foreach ($accounts as $acc) {
        $accId = (int)$acc['id'];
        $token = cg_google_sync_account_token($pdo, $acc);
        if (!$token) {
            cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'upsert', 'error', 'Missing/expired token');
            continue;
        }
        try {
            $importCols = cg_google_sync_account_resolved_import_column_ids($pdo, $boardId, $acc);
            if ($importCols !== [] && !in_array((int)($card['column_id'] ?? 0), $importCols, true)) {
                cg_google_sync_clear_board_push_links($pdo, $boardId, $cardId, $accId, $token);
                continue;
            }

            $primaryCal = CG_GOOGLE_SYNC_BOARD_PUSH_CALENDAR_ID;

            $stmtTaskLink->execute([$cardId, $accId]);
            $taskLink = $stmtTaskLink->fetch(PDO::FETCH_ASSOC);

            $stmtCalBoardLink->execute([$cardId, $accId]);
            $calBoardLink = $stmtCalBoardLink->fetch(PDO::FETCH_ASSOC);

            if ($taskPayload === null) {
                cg_google_sync_clear_board_push_links($pdo, $boardId, $cardId, $accId, $token);
                continue;
            }

            if ($useCalTimed) {
                if ($taskLink && !empty($taskLink['google_event_id'])) {
                    $tl = (string)$taskLink['calendar_id'];
                    $tid = (string)$taskLink['google_event_id'];
                    cg_google_sync_http_json('DELETE', 'https://tasks.googleapis.com/tasks/v1/lists/' . rawurlencode($tl) . '/tasks/' . rawurlencode($tid), [
                        'Authorization: Bearer ' . $token,
                    ]);
                    $pdo->prepare('DELETE FROM kanban_google_event_links WHERE id = ?')->execute([(int)$taskLink['id']]);
                }

                $calLink = $calBoardLink;
                if ($calLink && !empty($calLink['google_event_id']) && (string)$calLink['calendar_id'] !== $primaryCal) {
                    try {
                        cg_google_sync_http_json('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$calLink['calendar_id']) . '/events/' . rawurlencode((string)$calLink['google_event_id']), [
                            'Authorization: Bearer ' . $token,
                        ]);
                    } catch (Throwable $e) {
                        cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'cleanup', 'error', $e->getMessage());
                    }
                    $pdo->prepare('DELETE FROM kanban_google_event_links WHERE id = ?')->execute([(int)$calLink['id']]);
                    $calLink = false;
                }

                if ($calLink && !empty($calLink['google_event_id']) && (string)$calLink['calendar_id'] === $primaryCal) {
                    $eid = (string)$calLink['google_event_id'];
                    $res = cg_google_sync_http_json('PATCH', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($primaryCal) . '/events/' . rawurlencode($eid), [
                        'Authorization: Bearer ' . $token,
                    ], $calTimedPayload);
                    if ($res['status'] >= 300) {
                        throw new RuntimeException('Update Google Calendar event failed HTTP ' . (int)$res['status']);
                    }
                    $etag = (string)($res['data']['etag'] ?? '');
                    $pdo->prepare("UPDATE kanban_google_event_links SET google_etag = ?, source_direction = 'board', last_synced_at = NOW() WHERE id = ?")
                        ->execute([$etag !== '' ? $etag : null, (int)$calLink['id']]);
                } else {
                    $res = cg_google_sync_http_json('POST', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($primaryCal) . '/events', [
                        'Authorization: Bearer ' . $token,
                    ], $calTimedPayload);
                    if ($res['status'] >= 300 || empty($res['data']['id'])) {
                        throw new RuntimeException('Create Google Calendar event failed HTTP ' . (int)$res['status']);
                    }
                    $newEid = (string)$res['data']['id'];
                    $etag = (string)($res['data']['etag'] ?? '');
                    $pdo->prepare("INSERT INTO kanban_google_event_links (board_id, card_id, google_account_id, calendar_id, google_event_id, google_etag, source_direction, last_synced_at, link_target) VALUES (?, ?, ?, ?, ?, ?, 'board', NOW(), 'calendar_event')")
                        ->execute([$boardId, $cardId, $accId, $primaryCal, $newEid, $etag !== '' ? $etag : null]);
                }
                cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'upsert', 'ok');
                continue;
            }

            $taskListId = cg_google_sync_ensure_task_list($pdo, $accId, $token);
            if ($taskListId === null || $taskListId === '') {
                cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'upsert', 'error', 'Could not create or load Google Task list');
                continue;
            }

            $stmtCalBoardLinksAll->execute([$cardId, $accId]);
            foreach ($stmtCalBoardLinksAll->fetchAll(PDO::FETCH_ASSOC) ?: [] as $leg) {
                try {
                    cg_google_sync_http_json('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$leg['calendar_id']) . '/events/' . rawurlencode((string)$leg['google_event_id']), [
                        'Authorization: Bearer ' . $token,
                    ]);
                } catch (Throwable $e) {
                    cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'cleanup', 'error', $e->getMessage());
                }
                $pdo->prepare('DELETE FROM kanban_google_event_links WHERE id = ?')->execute([(int)$leg['id']]);
            }

            $stmtTaskLink->execute([$cardId, $accId]);
            $taskLink = $stmtTaskLink->fetch(PDO::FETCH_ASSOC);

            if ($taskLink && !empty($taskLink['google_event_id'])) {
                $tl = (string)$taskLink['calendar_id'];
                $tid = (string)$taskLink['google_event_id'];
                $res = cg_google_sync_http_json('PATCH', 'https://tasks.googleapis.com/tasks/v1/lists/' . rawurlencode($tl) . '/tasks/' . rawurlencode($tid), [
                    'Authorization: Bearer ' . $token,
                ], $taskPayload);
                if ($res['status'] >= 300) {
                    throw new RuntimeException('Update Google Task failed HTTP ' . (int)$res['status']);
                }
                $etag = (string)($res['data']['etag'] ?? '');
                $pdo->prepare("UPDATE kanban_google_event_links SET google_etag = ?, source_direction = 'board', last_synced_at = NOW() WHERE id = ?")
                    ->execute([$etag !== '' ? $etag : null, (int)$taskLink['id']]);
            } else {
                $res = cg_google_sync_http_json('POST', 'https://tasks.googleapis.com/tasks/v1/lists/' . rawurlencode($taskListId) . '/tasks', [
                    'Authorization: Bearer ' . $token,
                ], $taskPayload);
                if ($res['status'] >= 300 || empty($res['data']['id'])) {
                    throw new RuntimeException('Create Google Task failed HTTP ' . (int)$res['status']);
                }
                $newId = (string)$res['data']['id'];
                $etag = (string)($res['data']['etag'] ?? '');
                $pdo->prepare("INSERT INTO kanban_google_event_links (board_id, card_id, google_account_id, calendar_id, google_event_id, google_etag, source_direction, last_synced_at, link_target) VALUES (?, ?, ?, ?, ?, ?, 'board', NOW(), 'task')")
                    ->execute([$boardId, $cardId, $accId, $taskListId, $newId, $etag !== '' ? $etag : null]);
            }
            cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'upsert', 'ok');
        } catch (Throwable $e) {
            cg_google_sync_log($pdo, $boardId, $accId, $cardId, 'board_to_google', 'upsert', 'error', $e->getMessage());
        }
    }
}

/**
 * Remove every Google Calendar event linked to this board + Google account (API delete), before DB cleanup on disconnect.
 * 404/410 = already deleted; still OK. Logs failures when token missing or API errors.
 */
function cg_google_sync_delete_google_calendar_events_for_account(PDO $pdo, int $boardId, int $googleAccountId): void {
    $stmtLinks = $pdo->prepare("
        SELECT id, calendar_id, google_event_id, card_id, link_target
        FROM kanban_google_event_links
        WHERE board_id = ? AND google_account_id = ?
        ORDER BY id ASC
    ");
    $stmtLinks->execute([$boardId, $googleAccountId]);
    $rows = $stmtLinks->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtAcc = $pdo->prepare("SELECT * FROM kanban_google_accounts WHERE id = ? AND board_id = ? LIMIT 1");

    foreach ($rows as $row) {
        $stmtAcc->execute([$googleAccountId, $boardId]);
        $accFresh = $stmtAcc->fetch(PDO::FETCH_ASSOC);
        if (!$accFresh) {
            break;
        }
        $token = cg_google_sync_account_token($pdo, $accFresh);
        if (!$token) {
            cg_google_sync_log($pdo, $boardId, $googleAccountId, (int)$row['card_id'], 'board_to_google', 'disconnect', 'error', 'No token; event left in Google Calendar');
            continue;
        }
        $target = trim((string)($row['link_target'] ?? 'calendar_event'));
        try {
            if ($token) {
                if ($target === 'task') {
                    $res = cg_google_sync_http_json('DELETE', 'https://tasks.googleapis.com/tasks/v1/lists/' . rawurlencode((string)$row['calendar_id']) . '/tasks/' . rawurlencode((string)$row['google_event_id']), [
                        'Authorization: Bearer ' . $token,
                    ]);
                    $st = (int)($res['status'] ?? 500);
                    if ($st >= 300 && $st !== 404) {
                        cg_google_sync_log($pdo, $boardId, $googleAccountId, (int)$row['card_id'], 'board_to_google', 'disconnect', 'error', 'DELETE task HTTP ' . $st);
                    }
                } else {
                    $res = cg_google_sync_http_json('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$row['calendar_id']) . '/events/' . rawurlencode((string)$row['google_event_id']), [
                        'Authorization: Bearer ' . $token,
                    ]);
                    $st = (int)($res['status'] ?? 500);
                    if ($st >= 300 && $st !== 404 && $st !== 410) {
                        cg_google_sync_log($pdo, $boardId, $googleAccountId, (int)$row['card_id'], 'board_to_google', 'disconnect', 'error', 'DELETE event HTTP ' . $st);
                    }
                }
            }
        } catch (Throwable $e) {
            cg_google_sync_log($pdo, $boardId, $googleAccountId, (int)$row['card_id'], 'board_to_google', 'disconnect', 'error', $e->getMessage());
        }
    }
}

function cg_google_sync_delete_card_events(PDO $pdo, int $boardId, int $cardId): void {
    $stmt = $pdo->prepare("
        SELECT l.id AS link_pk, l.google_account_id, l.calendar_id, l.google_event_id, l.link_target,
               a.id, a.access_token, a.refresh_token, a.token_expires_at
        FROM kanban_google_event_links l
        JOIN kanban_google_accounts a ON a.id = l.google_account_id
        WHERE l.board_id = ? AND l.card_id = ?
    ");
    $stmt->execute([$boardId, $cardId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        try {
            $token = cg_google_sync_account_token($pdo, $row);
            if ($token) {
                $target = trim((string)($row['link_target'] ?? 'calendar_event'));
                if ($target === 'task') {
                    cg_google_sync_http_json('DELETE', 'https://tasks.googleapis.com/tasks/v1/lists/' . rawurlencode((string)$row['calendar_id']) . '/tasks/' . rawurlencode((string)$row['google_event_id']), [
                        'Authorization: Bearer ' . $token,
                    ]);
                } else {
                    cg_google_sync_http_json('DELETE', 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$row['calendar_id']) . '/events/' . rawurlencode((string)$row['google_event_id']), [
                        'Authorization: Bearer ' . $token,
                    ]);
                }
            }
        } catch (Throwable $e) {
            cg_google_sync_log($pdo, $boardId, (int)$row['google_account_id'], $cardId, 'board_to_google', 'delete', 'error', $e->getMessage());
        }
        $pdo->prepare('DELETE FROM kanban_google_event_links WHERE id = ?')->execute([(int)$row['link_pk']]);
    }
}

