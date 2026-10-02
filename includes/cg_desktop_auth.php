<?php
/**
 * Bearer device-token auth for CineGrid Kanban Desktop.
 */

if (!function_exists('cg_desktop_ensure_tables')) {
    function cg_desktop_ensure_tables(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS desktop_device_tokens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                device_id VARCHAR(64) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                device_name VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_device (user_id, device_id),
                KEY idx_token_hash (token_hash),
                KEY idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

if (!function_exists('cg_desktop_user_has_freelance_access')) {
    function cg_desktop_user_has_freelance_access(PDO $pdo, int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $role = (string)($stmt->fetchColumn() ?: '');
        if (function_exists('cg_is_freelance_role') && cg_is_freelance_role($role)) {
            return true;
        }
        if (in_array($role, ['freelance', 'freelancer'], true)) {
            return true;
        }
        $linkedFile = (defined('CG_MAIN_INCLUDES') ? CG_MAIN_INCLUDES : (defined('CG_MAIN_SITE_INCLUDES') ? CG_MAIN_SITE_INCLUDES : dirname(__DIR__, 2) . '/public_html/includes')) . '/linked_users.php';
        if (!is_readable($linkedFile)) {
            return false;
        }
        if (!function_exists('getPrimaryUserId') || !function_exists('getLinkedUserIds')) {
            require_once $linkedFile;
        }
        try {
            $primary = getPrimaryUserId($user_id);
            $linkedIds = getLinkedUserIds($primary);
            if (empty($linkedIds)) {
                return false;
            }
            $ph = implode(',', array_fill(0, count($linkedIds), '?'));
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id IN ($ph) AND role IN ('freelance', 'freelancer') LIMIT 1");
            $stmt->execute(array_map('intval', $linkedIds));
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('cg_desktop_user_has_freelance_access: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('cg_desktop_issue_token')) {
    function cg_desktop_issue_token(PDO $pdo, int $user_id, string $device_id, string $device_name): string
    {
        $device_id = substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', $device_id) ?: '', 0, 64);
        if ($device_id === '') {
            $device_id = bin2hex(random_bytes(16));
        }
        $device_name = substr(trim($device_name), 0, 255);
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);

        $stmt = $pdo->prepare('
            INSERT INTO desktop_device_tokens (user_id, device_id, token_hash, device_name, last_seen_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                device_name = VALUES(device_name),
                revoked_at = NULL,
                last_seen_at = NOW()
        ');
        $stmt->execute([$user_id, $device_id, $hash, $device_name]);

        return $plain;
    }
}

if (!function_exists('cg_desktop_revoke_token')) {
    function cg_desktop_revoke_token(PDO $pdo, int $user_id, string $token_hash): void
    {
        $stmt = $pdo->prepare('
            UPDATE desktop_device_tokens
            SET revoked_at = NOW()
            WHERE user_id = ? AND token_hash = ? AND revoked_at IS NULL
        ');
        $stmt->execute([$user_id, $token_hash]);
    }
}

if (!function_exists('cg_desktop_parse_bearer')) {
    function cg_desktop_parse_bearer(): ?string
    {
        $header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp((string)$k, 'Authorization') === 0) {
                    $header = trim((string)$v);
                    break;
                }
            }
        }
        if ($header === '' || !preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }
        return $m[1];
    }
}

if (!function_exists('cg_desktop_authenticate_request')) {
    /**
     * @return array{user_id:int,token_hash:string,device_id:string}|null
     */
    function cg_desktop_authenticate_request(PDO $pdo): ?array
    {
        $plain = cg_desktop_parse_bearer();
        if ($plain === null || $plain === '') {
            return null;
        }
        $hash = hash('sha256', $plain);
        $stmt = $pdo->prepare('
            SELECT user_id, device_id, token_hash
            FROM desktop_device_tokens
            WHERE token_hash = ? AND revoked_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $pdo->prepare('UPDATE desktop_device_tokens SET last_seen_at = NOW() WHERE token_hash = ?')
            ->execute([$hash]);
        return [
            'user_id' => (int)$row['user_id'],
            'token_hash' => (string)$row['token_hash'],
            'device_id' => (string)$row['device_id'],
        ];
    }
}

if (!function_exists('cg_desktop_list_boards_for_user')) {
    function cg_desktop_list_boards_for_user(PDO $pdo, int $user_id): array
    {
        $linkedIds = kanban_linked_user_ids($user_id);
        if ($linkedIds === []) {
            $linkedIds = [$user_id];
        }
        $ph = implode(',', array_fill(0, count($linkedIds), '?'));
        $stmt = $pdo->prepare("
            SELECT b.id, b.name, b.created_at, b.updated_at,
                   p.id AS project_id, p.title AS project_title,
                   (p.user_id IN ($ph)) AS is_owner,
                   (SELECT COUNT(*) FROM kanban_columns WHERE board_id = b.id) AS column_count,
                   (SELECT COUNT(*) FROM kanban_cards kc
                    JOIN kanban_columns kcol ON kcol.id = kc.column_id
                    WHERE kcol.board_id = b.id) AS card_count
            FROM kanban_boards b
            JOIN freelance_projects p ON p.id = b.project_id
            LEFT JOIN kanban_board_collaborators bc ON bc.board_id = b.id AND bc.user_id IN ($ph)
            WHERE (p.user_id IN ($ph) OR bc.user_id IS NOT NULL)
            ORDER BY b.updated_at DESC, b.id DESC
        ");
        $stmt->execute(array_merge($linkedIds, $linkedIds, $linkedIds));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
