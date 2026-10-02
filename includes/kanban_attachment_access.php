<?php
/**
 * Shared helpers for password-protected Kanban card attachments.
 */

if (!function_exists('cg_kanban_normalize_attachment_path')) {
    function cg_kanban_normalize_attachment_path(string $path): string {
        return ltrim(str_replace("\0", '', trim($path)), '/');
    }
}

if (!function_exists('cg_kanban_attachment_access_secret')) {
    function cg_kanban_attachment_access_secret(): string {
        static $secret = null;
        if ($secret !== null) {
            return $secret;
        }
        $env = getenv('CG_KANBAN_ATTACHMENT_SECRET');
        if (is_string($env) && $env !== '') {
            $secret = $env;
            return $secret;
        }
        $root = defined('CG_KANBAN_ROOT') ? (string) CG_KANBAN_ROOT : dirname(__DIR__);
        $secret = hash('sha256', 'cg_kanban_attachment|' . $root);
        return $secret;
    }
}

if (!function_exists('cg_kanban_attachment_issue_access_token')) {
    function cg_kanban_attachment_issue_access_token(int $card_id, string $path, int $user_id, int $ttlSeconds = 600): string {
        $path = cg_kanban_normalize_attachment_path($path);
        $payload = json_encode([
            'c' => $card_id,
            'p' => $path,
            'u' => $user_id,
            'e' => time() + max(60, $ttlSeconds),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return '';
        }
        $sig = hash_hmac('sha256', $payload, cg_kanban_attachment_access_secret(), true);
        return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
    }
}

if (!function_exists('cg_kanban_attachment_validate_access_token')) {
    function cg_kanban_attachment_validate_access_token(string $token, int $card_id, string $path, int $user_id): bool {
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || !str_contains($raw, '.')) {
            return false;
        }
        $dot = strrpos($raw, '.');
        if ($dot === false) {
            return false;
        }
        $payload = substr($raw, 0, $dot);
        $sig = substr($raw, $dot + 1);
        if ($payload === '' || $sig === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, cg_kanban_attachment_access_secret(), true);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return false;
        }
        $exp = (int)($data['e'] ?? 0);
        if ($exp <= 0 || time() > $exp) {
            return false;
        }
        $normPath = cg_kanban_normalize_attachment_path($path);
        return (int)($data['c'] ?? 0) === $card_id
            && (int)($data['u'] ?? 0) === $user_id
            && cg_kanban_normalize_attachment_path((string)($data['p'] ?? '')) === $normPath;
    }
}

if (!function_exists('cg_kanban_attachment_session_unlock')) {
    function cg_kanban_attachment_session_unlock(int $card_id, string $path): void {
        $path = cg_kanban_normalize_attachment_path($path);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION['cg_kanban_att_unlock']) || !is_array($_SESSION['cg_kanban_att_unlock'])) {
            $_SESSION['cg_kanban_att_unlock'] = [];
        }
        $_SESSION['cg_kanban_att_unlock'][$card_id . ':' . $path] = time();
        if (function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}

if (!function_exists('cg_kanban_attachment_session_is_unlocked')) {
    function cg_kanban_attachment_session_is_unlocked(int $card_id, string $path): bool {
        $path = cg_kanban_normalize_attachment_path($path);
        $key = $card_id . ':' . $path;
        $map = $_SESSION['cg_kanban_att_unlock'] ?? null;
        if (!is_array($map) || !isset($map[$key])) {
            return false;
        }
        $ts = (int)$map[$key];
        return $ts > 0 && (time() - $ts) < 86400;
    }
}

if (!function_exists('cg_kanban_find_attachment_by_path')) {
    function cg_kanban_find_attachment_by_path(array $attachments, string $path): ?array {
        $want = cg_kanban_normalize_attachment_path($path);
        foreach ($attachments as $att) {
            if (!is_array($att)) {
                continue;
            }
            $p = isset($att['path']) ? cg_kanban_normalize_attachment_path((string)$att['path']) : '';
            if ($p !== '' && $p === $want) {
                return $att;
            }
        }
        return null;
    }
}

if (!function_exists('cg_kanban_share_resolve_project_id')) {
    function cg_kanban_share_resolve_project_id(PDO $pdo, string $share_token): int {
        $share_token = trim($share_token);
        if ($share_token === '') {
            return 0;
        }
        $stmt = $pdo->prepare('
            SELECT project_id
            FROM freelance_project_shares
            WHERE token = ?
            LIMIT 1
        ');
        $stmt->execute([$share_token]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('cg_kanban_share_can_access_card')) {
    function cg_kanban_share_can_access_card(PDO $pdo, string $share_token, int $card_id): bool {
        if ($card_id <= 0) {
            return false;
        }
        $project_id = cg_kanban_share_resolve_project_id($pdo, $share_token);
        if ($project_id <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('
            SELECT b.id
            FROM kanban_cards k
            JOIN kanban_columns c ON c.id = k.column_id
            JOIN kanban_boards b ON b.id = c.board_id
            WHERE k.id = ?
              AND b.project_id = ?
            LIMIT 1
        ');
        $stmt->execute([$card_id, $project_id]);
        return (int)($stmt->fetchColumn() ?: 0) > 0;
    }
}

if (!function_exists('cg_kanban_attachment_share_token_hash')) {
    function cg_kanban_attachment_share_token_hash(string $share_token): string {
        return hash('sha256', trim($share_token));
    }
}

if (!function_exists('cg_kanban_attachment_issue_share_access_token')) {
    function cg_kanban_attachment_issue_share_access_token(int $card_id, string $path, string $share_token, int $ttlSeconds = 600): string {
        $path = cg_kanban_normalize_attachment_path($path);
        $payload = json_encode([
            'c' => $card_id,
            'p' => $path,
            'sh' => cg_kanban_attachment_share_token_hash($share_token),
            'e' => time() + max(60, $ttlSeconds),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return '';
        }
        $sig = hash_hmac('sha256', $payload, cg_kanban_attachment_access_secret(), true);
        return rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');
    }
}

if (!function_exists('cg_kanban_attachment_validate_share_access_token')) {
    function cg_kanban_attachment_validate_share_access_token(string $token, int $card_id, string $path, string $share_token): bool {
        $token = trim($token);
        $share_token = trim($share_token);
        if ($token === '' || $share_token === '') {
            return false;
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || !str_contains($raw, '.')) {
            return false;
        }
        $dot = strrpos($raw, '.');
        if ($dot === false) {
            return false;
        }
        $payload = substr($raw, 0, $dot);
        $sig = substr($raw, $dot + 1);
        if ($payload === '' || $sig === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, cg_kanban_attachment_access_secret(), true);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return false;
        }
        $exp = (int)($data['e'] ?? 0);
        if ($exp <= 0 || time() > $exp) {
            return false;
        }
        $normPath = cg_kanban_normalize_attachment_path($path);
        return (int)($data['c'] ?? 0) === $card_id
            && hash_equals(cg_kanban_attachment_share_token_hash($share_token), (string)($data['sh'] ?? ''))
            && cg_kanban_normalize_attachment_path((string)($data['p'] ?? '')) === $normPath;
    }
}
