<?php
// Kanban app: lives under kanban.cinegrid.net; database connection only from sibling public_html/includes/db.php.

if (!function_exists('getDB')) {
    $dbCandidates = [
        dirname(__DIR__, 2) . '/public_html/includes/db.php',
        dirname(__DIR__) . '/../public_html/includes/db.php',
        dirname(__DIR__, 2) . '/httpdocs/includes/db.php',
    ];
    $dbLoaded = false;
    foreach ($dbCandidates as $dbPath) {
        $dir = dirname($dbPath);
        $real = realpath($dir);
        $check = ($real !== false ? $real : $dir) . '/' . basename($dbPath);
        if (is_file($check)) {
            require_once $check;
            $dbLoaded = true;
            break;
        }
    }
    if (!$dbLoaded) {
        throw new RuntimeException('db.php not found for freelance_projects.php');
    }
}

if (!defined('CG_MAIN_SITE_INCLUDES')) {
    define('CG_MAIN_SITE_INCLUDES', dirname(__DIR__, 2) . '/public_html/includes');
}

function cg_is_freelance_role($role): bool {
    return in_array($role, ['freelance', 'freelancer'], true);
}

/**
 * Create required tables if they do not exist.
 * Safe to call on every request (uses CREATE TABLE IF NOT EXISTS).
 */
function cg_ensure_freelance_tables(): void {
    $pdo = getDB();

    // Projects
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS freelance_projects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            client_name VARCHAR(255) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            start_date DATE NULL,
            end_date DATE NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Kanban boards per project (multiple)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_boards (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT 'Board',
            is_trashed TINYINT(1) NOT NULL DEFAULT 0,
            trashed_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project_id (project_id),
            KEY idx_kanban_boards_is_trashed (is_trashed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    if (!function_exists('ensure_kanban_board_trash_schema')) {
        require_once __DIR__ . '/kanban_board_trash.php';
    }
    ensure_kanban_board_trash_schema($pdo);

    // Kanban columns
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_columns (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_board_id (board_id),
            KEY idx_position (position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Kanban cards
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_cards (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            column_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            start_date DATE NULL,
            due_date DATE NULL,
            progress INT NOT NULL DEFAULT 0,
            priority VARCHAR(10) NULL DEFAULT NULL,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_column_id (column_id),
            KEY idx_position (position),
            KEY idx_due_date (due_date),
            KEY idx_priority (priority),
            KEY idx_is_pinned (is_pinned)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add progress and priority columns if they don't exist (for existing tables)
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'progress'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN progress INT NOT NULL DEFAULT 0 AFTER due_date");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding progress column: ' . $e->getMessage());
    }
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'priority'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN priority VARCHAR(10) NULL DEFAULT NULL AFTER progress");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding priority column: ' . $e->getMessage());
    }
    
    // Add priority index if it doesn't exist
    try {
        $stmt = $pdo->query("SHOW INDEX FROM kanban_cards WHERE Key_name = 'idx_priority'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD INDEX idx_priority (priority)");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding priority index: ' . $e->getMessage());
    }
    
    // Add progress, priority, and pinned columns if they don't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN IF NOT EXISTS progress INT NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        // Column might already exist, ignore
    }
    try {
        $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN IF NOT EXISTS priority VARCHAR(10) NULL DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist, ignore
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'is_pinned'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER priority");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding is_pinned column: ' . $e->getMessage());
    }
    try {
        $stmt = $pdo->query("SHOW INDEX FROM kanban_cards WHERE Key_name = 'idx_is_pinned'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD INDEX idx_is_pinned (is_pinned)");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding is_pinned index: ' . $e->getMessage());
    }

    // Links and attachments (JSON) for card modal
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'links'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN links TEXT NULL DEFAULT NULL AFTER priority");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding links column: ' . $e->getMessage());
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'attachments'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN attachments TEXT NULL DEFAULT NULL AFTER links");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding attachments column: ' . $e->getMessage());
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_card_assignments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_card_user (card_id, user_id),
            KEY idx_card_id (card_id),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Gantt charts per project (multiple); each chart can be “linked” to a board as source
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gantt_charts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT 'Timeline',
            source_board_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project_id (project_id),
            KEY idx_source_board_id (source_board_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Share tokens for client view
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS freelance_project_shares (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            token VARCHAR(80) NOT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_token (token),
            KEY idx_project_id (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Timeline saved views
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timeline_views (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            project_id INT UNSIGNED NOT NULL,
            chart_id INT UNSIGNED NOT NULL,
            board_id INT UNSIGNED NOT NULL,
            column_id INT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_project (user_id, project_id),
            KEY idx_chart_id (chart_id),
            KEY idx_board_id (board_id),
            KEY idx_column_id (column_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Notification emails per board (multiple per board)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_emails (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            last_digest_sent_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_board_email (board_id, email),
            KEY idx_board_id (board_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_board_emails LIKE 'last_digest_sent_at'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE kanban_board_emails ADD COLUMN last_digest_sent_at DATETIME NULL DEFAULT NULL AFTER email");
        }
    } catch (PDOException $e) {
        error_log('Error checking/adding kanban_board_emails.last_digest_sent_at: ' . $e->getMessage());
    }

    // Digest events queued for daily board notification emails
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_digest_events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            payload JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_board_created (board_id, created_at),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Board collaborators (users with access; owner is via project)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_collaborators (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'editor',
            invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            invited_by INT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_board_user (board_id, user_id),
            KEY idx_board_id (board_id),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Pending board invites (email not yet a user; redeemed on login/signup)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_invites (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_board_email (board_id, email),
            KEY idx_board_id (board_id),
            KEY idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Board activity log (per-board; for activity panel)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_activities (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL DEFAULT NULL,
            user_name VARCHAR(255) NOT NULL DEFAULT '',
            action_type VARCHAR(64) NOT NULL,
            payload JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_board_id (board_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Workspace (infinite canvas) per Kanban board
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_workspace_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            item_type VARCHAR(64) NOT NULL DEFAULT 'sticky_note',
            title TEXT NULL,
            content MEDIUMTEXT NULL,
            source_url TEXT NULL,
            embed_url TEXT NULL,
            image_path VARCHAR(512) NULL DEFAULT NULL,
            x DOUBLE NOT NULL DEFAULT 0,
            y DOUBLE NOT NULL DEFAULT 0,
            width DOUBLE NOT NULL DEFAULT 280,
            height DOUBLE NOT NULL DEFAULT 180,
            rotation DOUBLE NOT NULL DEFAULT 0,
            z_index INT NOT NULL DEFAULT 0,
            style_json MEDIUMTEXT NULL,
            meta_json MEDIUMTEXT NULL,
            is_trashed TINYINT(1) NOT NULL DEFAULT 0,
            trashed_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_kws_items_board (board_id),
            KEY idx_kws_items_board_z (board_id, z_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Soft-delete support (Trash): add columns for existing installations.
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_workspace_items LIKE 'is_trashed'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE kanban_workspace_items ADD COLUMN is_trashed TINYINT(1) NOT NULL DEFAULT 0 AFTER meta_json");
        }
    } catch (Exception $e) {}
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_workspace_items LIKE 'trashed_at'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE kanban_workspace_items ADD COLUMN trashed_at DATETIME NULL DEFAULT NULL AFTER is_trashed");
        }
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_kws_items_board_trashed ON kanban_workspace_items (board_id, is_trashed)");
    } catch (Exception $e) {}
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_workspace_strokes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NULL DEFAULT NULL,
            tool VARCHAR(32) NOT NULL DEFAULT 'brush',
            stroke_data MEDIUMTEXT NOT NULL,
            bounds_json TEXT NULL,
            style_json MEDIUMTEXT NULL,
            z_index INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_kws_strokes_board (board_id),
            KEY idx_kws_strokes_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_workspace_connectors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            from_item_id INT UNSIGNED NULL DEFAULT NULL,
            to_item_id INT UNSIGNED NULL DEFAULT NULL,
            start_x DOUBLE NULL DEFAULT NULL,
            start_y DOUBLE NULL DEFAULT NULL,
            end_x DOUBLE NULL DEFAULT NULL,
            end_y DOUBLE NULL DEFAULT NULL,
            label VARCHAR(512) NOT NULL DEFAULT '',
            style_json MEDIUMTEXT NULL,
            z_index INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_kws_conn_board (board_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Per-board collaborator chat (messages, presence, typing)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_chat_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            card_context JSON NULL,
            mentions_json JSON NULL,
            edit_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            client_uuid VARCHAR(40) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_kbc_msg_board_id (board_id, id),
            UNIQUE KEY uq_kbc_msg_board_client (board_id, client_uuid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_chat_presence (
            board_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            last_seen_at DATETIME NOT NULL,
            PRIMARY KEY (board_id, user_id),
            KEY idx_kbc_pres_board_seen (board_id, last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_board_chat_typing (
            board_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (board_id, user_id),
            KEY idx_kbc_typ_board_upd (board_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    try {
        $pdo->exec('ALTER TABLE kanban_board_chat_messages ADD COLUMN mentions_json JSON NULL DEFAULT NULL AFTER card_context');
    } catch (Throwable $e) {
        /* column already present */
    }
    try {
        $pdo->exec('ALTER TABLE kanban_board_chat_messages ADD COLUMN edit_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER mentions_json');
    } catch (Throwable $e) {
        /* column already present */
    }
    try {
        $pdo->exec('ALTER TABLE kanban_board_chat_messages ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER edit_count');
    } catch (Throwable $e) {
        /* column already present */
    }
    try {
        $pdo->exec('ALTER TABLE kanban_board_chat_messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER updated_at');
    } catch (Throwable $e) {
        /* column already present */
    }
}

/**
 * Base URL for public client timeline links (/client_timeline?token=… on kanban host, /client?token=… on main site).
 * Kanban portal pages call APIs on cinegrid.net; HTTP_HOST is wrong there — use Origin (and Referer fallback).
 */
function cg_freelance_client_portal_base_url(): string {
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '' && preg_match('#\Ahttps://kanban\.cinegrid\.net/?\z#i', $origin)) {
        return 'https://kanban.cinegrid.net';
    }
    $ref = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref !== '' && preg_match('#\Ahttps://kanban\.cinegrid\.net(?:[/?#]|$)#i', $ref)) {
        return 'https://kanban.cinegrid.net';
    }
    return 'https://cinegrid.net';
}

/**
 * Guest/client workspace links (?share=guest|client&board_id=…): store access in session (24h) so API works without login.
 * Call after getDB() + cg_ensure_freelance_tables(). Updates session when share+board_id are present in GET/POST.
 *
 * @return array{board_id:int, share:string}|null
 */
function cg_workspace_share_access_refresh(PDO $pdo): ?array {
    require_once CG_MAIN_SITE_INCLUDES . '/auth.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    $shareParam = strtolower(trim((string)($_GET['share'] ?? $_POST['share'] ?? '')));
    $bidParam = (int)($_GET['board_id'] ?? $_POST['board_id'] ?? 0);
    $wsParam = trim((string)($_GET['ws'] ?? $_POST['ws'] ?? ''));
    // Logged-in users ignore a stale session share unless the URL explicitly requests guest/client access.
    if ((int)($_SESSION['user_id'] ?? 0) > 0) {
        $urlRequestsShare = ($wsParam !== '' || in_array($shareParam, ['guest', 'client'], true));
        if (!$urlRequestsShare) {
            unset($_SESSION['cg_workspace_share_access']);
            return null;
        }
    }
    $wsLinkExpiresAt = 0;
    if ($wsParam !== '') {
        $norm = strtr($wsParam, '-_', '+/');
        $pad = strlen($norm) % 4;
        if ($pad > 0) {
            $norm .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($norm, true);
        if (is_string($decoded) && $decoded !== '') {
            $payload = json_decode($decoded, true);
            if (is_array($payload)) {
                $wsBoardId = (int)($payload['b'] ?? 0);
                $wsShare = strtolower(trim((string)($payload['s'] ?? '')));
                $wsLinkExpiresAt = (int)($payload['exp'] ?? 0);
                if ($wsBoardId > 0 && in_array($wsShare, ['guest', 'client'], true)) {
                    if ($wsLinkExpiresAt > 0 && time() > $wsLinkExpiresAt) {
                        unset($_SESSION['cg_workspace_share_access']);
                        return null;
                    }
                    $bidParam = $wsBoardId;
                    $shareParam = $wsShare;
                }
            }
        }
    }
    if ($bidParam > 0 && in_array($shareParam, ['guest', 'client'], true)) {
        if (!function_exists('ensure_kanban_board_trash_schema')) {
            require_once __DIR__ . '/kanban_board_trash.php';
        }
        ensure_kanban_board_trash_schema($pdo);
        $stmt = $pdo->prepare('SELECT id FROM kanban_boards WHERE id = ? AND COALESCE(is_trashed, 0) = 0 LIMIT 1');
        $stmt->execute([$bidParam]);
        if ($stmt->fetchColumn()) {
            $prev = $_SESSION['cg_workspace_share_access'] ?? null;
            $sameBoard = is_array($prev)
                && (int)($prev['board_id'] ?? 0) === $bidParam
                && strtolower((string)($prev['share'] ?? '')) === $shareParam;
            if ($wsParam !== '') {
                $_SESSION['cg_workspace_share_access'] = [
                    'board_id' => $bidParam,
                    'share' => $shareParam,
                    // 0 = never expire. Optional exp is baked into the share token at link creation.
                    'expires_at' => $wsLinkExpiresAt > 0 ? $wsLinkExpiresAt : 0,
                ];
            } elseif (!$sameBoard) {
                $_SESSION['cg_workspace_share_access'] = [
                    'board_id' => $bidParam,
                    'share' => $shareParam,
                    'expires_at' => 0,
                ];
            }
        }
    }
    $acc = $_SESSION['cg_workspace_share_access'] ?? null;
    if (!is_array($acc)) {
        return null;
    }
    $sbid = (int)($acc['board_id'] ?? 0);
    $sshare = strtolower((string)($acc['share'] ?? ''));
    $exp = (int)($acc['expires_at'] ?? 0);
    if ($sbid <= 0 || !in_array($sshare, ['guest', 'client'], true)) {
        return null;
    }
    if ($exp > 0 && time() > $exp) {
        unset($_SESSION['cg_workspace_share_access']);
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM kanban_boards WHERE id = ? AND COALESCE(is_trashed, 0) = 0 LIMIT 1');
    $stmt->execute([$sbid]);
    if (!$stmt->fetchColumn()) {
        unset($_SESSION['cg_workspace_share_access']);
        return null;
    }
    return ['board_id' => $sbid, 'share' => $sshare];
}

/** True for guest link only (temporary edit); client links are read-only on the server. */
function cg_workspace_share_allows_write(?array $share): bool {
    return $share !== null && ($share['share'] ?? '') === 'guest';
}

function cg_require_freelancer(): void {
    require_once CG_MAIN_SITE_INCLUDES . '/auth.php';
    if (!isset($_SESSION['user_id'])) {
        $to = getLoginRedirectUrl();
        if (function_exists('cg_kanban_log_redirect')) {
            cg_kanban_log_redirect($to, 'cg_require_freelancer: no session');
        }
        header('Location: ' . $to, true, 302);
        exit();
    }
    $role = $_SESSION['user_role'] ?? 'customer';
    if (!cg_is_freelance_role($role)) {
        $to = cg_kanban_portal_url('/login', ['error' => 'not_freelancer']);
        if (function_exists('cg_kanban_log_redirect')) {
            cg_kanban_log_redirect($to, 'cg_require_freelancer: wrong role');
        }
        header('Location: ' . $to, true, 302);
        exit();
    }
}

/**
 * True if the current session may use freelance Kanban (freelancer role or linked to one).
 * Used by image preview and other non-redirecting checks.
 */
function cg_user_can_access_freelance_kanban(): bool {
    require_once CG_MAIN_SITE_INCLUDES . '/auth.php';
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $role = $_SESSION['user_role'] ?? 'customer';
    if (cg_is_freelance_role($role)) {
        return true;
    }
    $linkedFile = CG_MAIN_SITE_INCLUDES . '/linked_users.php';
    if (!is_readable($linkedFile)) {
        return false;
    }
    if (!function_exists('getPrimaryUserId') || !function_exists('getLinkedUserIds')) {
        require_once $linkedFile;
    }
    try {
        $userId = (int)$_SESSION['user_id'];
        $primary = getPrimaryUserId($userId);
        $linkedIds = getLinkedUserIds($primary);
        if (empty($linkedIds)) {
            return false;
        }
        $pdo = getDB();
        $ph = implode(',', array_fill(0, count($linkedIds), '?'));
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id IN ($ph) AND role IN ('freelance', 'freelancer') LIMIT 1");
        $stmt->execute(array_map('intval', $linkedIds));
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('cg_user_can_access_freelance_kanban: ' . $e->getMessage());
        return false;
    }
}

/**
 * Require freelancer role OR allow if current user has a linked account with freelance role (e.g. for Kanban shared to linked accounts).
 */
function cg_require_freelancer_or_linked(): void {
    if (cg_user_can_access_freelance_kanban()) {
        return;
    }
    require_once CG_MAIN_SITE_INCLUDES . '/auth.php';
    if (!isset($_SESSION['user_id'])) {
        $to = getLoginRedirectUrl();
        if (function_exists('cg_kanban_log_redirect')) {
            cg_kanban_log_redirect($to, 'cg_require_freelancer_or_linked: no session');
        }
        header('Location: ' . $to, true, 302);
        exit();
    }
    $to = cg_kanban_portal_url('/login', ['error' => 'not_freelancer']);
    if (function_exists('cg_kanban_log_redirect')) {
        cg_kanban_log_redirect($to, 'cg_require_freelancer_or_linked: not freelance');
    }
    header('Location: ' . $to, true, 302);
    exit();
}

function cg_get_or_create_default_project(int $user_id): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM freelance_projects WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($project) return $project;

    $stmt = $pdo->prepare("INSERT INTO freelance_projects (user_id, title, status) VALUES (?, 'My First Project', 'active')");
    $stmt->execute([$user_id]);
    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM freelance_projects WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id];
}

function cg_get_or_create_default_board(int $project_id): array {
    $pdo = getDB();
    if (!function_exists('ensure_kanban_board_trash_schema')) {
        require_once __DIR__ . '/kanban_board_trash.php';
    }
    ensure_kanban_board_trash_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM kanban_boards WHERE project_id = ? AND COALESCE(is_trashed, 0) = 0 ORDER BY updated_at DESC, id DESC LIMIT 1");
    $stmt->execute([$project_id]);
    $board = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($board) return $board;

    $tplFile = __DIR__ . '/kanban_board_templates.php';
    $applyFile = __DIR__ . '/kanban_template_apply.php';
    $default_slug = 'commercial_ads';
    $board_title = 'Main Board';
    if (is_file($tplFile)) {
        require_once $tplFile;
        if (defined('CG_KANBAN_DEFAULT_FIRST_BOARD_TEMPLATE_SLUG')) {
            $default_slug = CG_KANBAN_DEFAULT_FIRST_BOARD_TEMPLATE_SLUG;
        }
        $board_title = kanban_board_template_default_board_name($default_slug);
    }

    $stmt = $pdo->prepare("INSERT INTO kanban_boards (project_id, name) VALUES (?, ?)");
    $stmt->execute([$project_id, $board_title]);
    $board_id = (int)$pdo->lastInsertId();

    $cols = [['To Do', 0], ['Doing', 1], ['Done', 2]];
    if (is_file($tplFile)) {
        $cols = kanban_board_template_columns_with_positions($default_slug);
    }
    $stmtCol = $pdo->prepare("INSERT INTO kanban_columns (board_id, name, position) VALUES (?, ?, ?)");
    $column_ids_ordered = [];
    foreach ($cols as [$name, $pos]) {
        $stmtCol->execute([$board_id, $name, $pos]);
        $column_ids_ordered[] = (int)$pdo->lastInsertId();
    }

    if (is_file($applyFile) && is_file($tplFile)) {
        require_once $applyFile;
        kanban_seed_template_cards($pdo, $column_ids_ordered, $default_slug);
    }

    $stmt = $pdo->prepare("SELECT * FROM kanban_boards WHERE id = ?");
    $stmt->execute([$board_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $board_id];
}

function cg_get_or_create_default_gantt(int $project_id, ?int $source_board_id): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM gantt_charts WHERE project_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
    $stmt->execute([$project_id]);
    $chart = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($chart) return $chart;

    $stmt = $pdo->prepare("INSERT INTO gantt_charts (project_id, name, source_board_id) VALUES (?, 'Main Timeline', ?)");
    $stmt->execute([$project_id, $source_board_id]);
    $chart_id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM gantt_charts WHERE id = ?");
    $stmt->execute([$chart_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $chart_id];
}

function cg_share_token_new(int $project_id): string {
    $pdo = getDB();
    $token = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare("INSERT INTO freelance_project_shares (project_id, token) VALUES (?, ?)");
    $stmt->execute([$project_id, $token]);
    return $token;
}

/**
 * Redeem pending Kanban board invites for a user (by email).
 * Call after login or signup so invited users get board access.
 */
function cg_redeem_kanban_invites(int $user_id, string $email): void {
    $pdo = getDB();
    $email = trim(strtolower($email));
    if ($email === '') return;
    // Match invite by email with same collation on both sides to avoid "Illegal mix of collations" and case/space differences
    $stmt = $pdo->prepare("SELECT id, board_id, created_at FROM kanban_board_invites WHERE LOWER(TRIM(email)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(?)) COLLATE utf8mb4_unicode_ci");
    $stmt->execute([$email]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        $board_id = (int)$row['board_id'];
        try {
            $ins = $pdo->prepare("INSERT IGNORE INTO kanban_board_collaborators (board_id, user_id, role, invited_at) VALUES (?, ?, 'editor', ?)");
            $ins->execute([$board_id, $user_id, $row['created_at'] ?? date('Y-m-d H:i:s')]);
            $del = $pdo->prepare("DELETE FROM kanban_board_invites WHERE id = ?");
            $del->execute([$row['id']]);
        } catch (PDOException $e) {
            error_log('cg_redeem_kanban_invites: ' . $e->getMessage());
        }
    }
}

