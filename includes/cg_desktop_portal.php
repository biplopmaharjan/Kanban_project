<?php
/**
 * One-time SSO tokens so the desktop app can open calendar / workspace / timeline in an embedded iframe.
 */

if (!function_exists('cg_desktop_portal_ensure_schema')) {
    function cg_desktop_portal_ensure_schema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS desktop_portal_sso_tokens (
                token_hash CHAR(64) NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (token_hash),
                KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

if (!function_exists('cg_desktop_portal_site_origin')) {
    function cg_desktop_portal_site_origin(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'kanban.cinegrid.net';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)$host);
    }
}

if (!function_exists('cg_desktop_portal_get_or_create_chart_id')) {
    function cg_desktop_portal_get_or_create_chart_id(PDO $pdo, int $board_id, int $user_id): int
    {
        if (!function_exists('user_can_access_board')) {
            require_once __DIR__ . '/kanban_board_access.php';
        }
        if (!user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $stmt = $pdo->prepare('SELECT id FROM gantt_charts WHERE source_board_id = ? LIMIT 1');
        $stmt->execute([$board_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
        $stmt = $pdo->prepare('SELECT project_id, name FROM kanban_boards WHERE id = ? LIMIT 1');
        $stmt->execute([$board_id]);
        $board = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$board) {
            throw new RuntimeException('Board not found', 404);
        }
        $project_id = (int)($board['project_id'] ?? 0);
        $name = trim((string)($board['name'] ?? 'Timeline'));
        $stmt = $pdo->prepare('INSERT INTO gantt_charts (project_id, name, source_board_id) VALUES (?, ?, ?)');
        $stmt->execute([$project_id, $name !== '' ? $name : 'Timeline', $board_id]);
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('cg_desktop_portal_issue_sso_url')) {
    /**
     * @param 'calendar'|'workspace'|'timeline' $view
     */
    function cg_desktop_portal_issue_sso_url(PDO $pdo, int $user_id, string $view, int $board_id): string
    {
        cg_desktop_portal_ensure_schema($pdo);
        $view = strtolower(trim($view));
        if ($board_id <= 0 && !in_array($view, ['switch_account', 'wallet'], true)) {
            throw new InvalidArgumentException('board_id required');
        }
        if (!in_array($view, ['calendar', 'workspace', 'timeline', 'wallet', 'project_manager', 'switch_account'], true)) {
            throw new InvalidArgumentException('Invalid portal view');
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $pdo->prepare('
            INSERT INTO desktop_portal_sso_tokens (token_hash, user_id, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))
        ')->execute([$hash, $user_id]);

        $origin = cg_desktop_portal_site_origin();
        $params = [
            't' => $plain,
            'view' => $view,
        ];
        if ($board_id > 0) {
            $params['board_id'] = (string)$board_id;
        }
        if ($view === 'timeline') {
            if ($board_id <= 0) {
                throw new InvalidArgumentException('board_id required for timeline');
            }
            $params['chart_id'] = (string)cg_desktop_portal_get_or_create_chart_id($pdo, $board_id, $user_id);
        } elseif ($view === 'calendar') {
            if ($board_id <= 0) {
                throw new InvalidArgumentException('board_id required for calendar');
            }
            $params['month'] = gmdate('Y-m');
        } elseif ($view === 'workspace' || $view === 'project_manager') {
            if ($board_id <= 0) {
                throw new InvalidArgumentException('board_id required for ' . $view);
            }
        } elseif ($view === 'switch_account') {
            // board_id not required
        }
        return $origin . '/cg_desktop_portal_sso.php?' . http_build_query($params);
    }
}

if (!function_exists('cg_desktop_portal_consume_sso')) {
    /** @return array{id:int,name:string,email:string,role:string}|null */
    function cg_desktop_portal_consume_sso(PDO $pdo, string $plain_token): ?array
    {
        cg_desktop_portal_ensure_schema($pdo);
        $plain = trim($plain_token);
        if ($plain === '') {
            return null;
        }
        $hash = hash('sha256', $plain);
        $stmt = $pdo->prepare('
            SELECT user_id FROM desktop_portal_sso_tokens
            WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        $user_id = (int)($stmt->fetchColumn() ?: 0);
        if ($user_id <= 0) {
            return null;
        }
        $pdo->prepare('UPDATE desktop_portal_sso_tokens SET used_at = NOW() WHERE token_hash = ?')
            ->execute([$hash]);

        $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        return [
            'id' => (int)$user['id'],
            'name' => (string)($user['name'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? 'customer'),
        ];
    }
}
