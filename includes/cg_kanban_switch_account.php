<?php
/**
 * Linked-account switch for kanban.cinegrid.net (direct, no modal).
 */

require_once __DIR__ . '/cg_kanban_linked_users_loader.php';

function cg_kanban_portal_has_linked_accounts(): bool
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !function_exists('getDB')) {
        return false;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT 1
            FROM linked_users
            WHERE status = 'active'
              AND (primary_user_id = ? OR linked_user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('cg_kanban_portal_has_linked_accounts: ' . $e->getMessage());
        return false;
    }
}

function cg_kanban_portal_linked_user_ids(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    if (cg_kanban_ensure_linked_users_loaded()) {
        try {
            $primary = (int) getPrimaryUserId($userId);
            $linkedIds = getLinkedUserIds($primary);
            if (is_array($linkedIds) && count($linkedIds) > 0) {
                return array_values(array_unique(array_map('intval', $linkedIds)));
            }
        } catch (Throwable $e) {
            error_log('cg_kanban_portal_linked_user_ids helpers: ' . $e->getMessage());
        }
    }

    if (!function_exists('getDB')) {
        return [$userId];
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT primary_user_id, linked_user_id
            FROM linked_users
            WHERE status = 'active'
              AND (primary_user_id = ? OR linked_user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [$userId];
        }
        return array_values(array_unique([
            (int) ($row['primary_user_id'] ?? $userId),
            (int) ($row['linked_user_id'] ?? $userId),
        ]));
    } catch (Throwable $e) {
        error_log('cg_kanban_portal_linked_user_ids sql: ' . $e->getMessage());
        return [$userId];
    }
}

function cg_kanban_portal_switch_target(int $userId, ?string $currentRole = null): ?int
{
    $linkedIds = cg_kanban_portal_linked_user_ids($userId);
    if (count($linkedIds) <= 1) {
        return null;
    }

    $otherIds = array_values(array_filter($linkedIds, static function ($id) use ($userId) {
        return (int) $id !== $userId;
    }));
    if ($otherIds === []) {
        return null;
    }

    try {
        $pdo = getDB();
        $placeholders = implode(',', array_fill(0, count($otherIds), '?'));
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id IN ($placeholders)");
        $stmt->execute($otherIds);
        $others = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$others) {
            return null;
        }

        if ($currentRole === null) {
            $currentRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
        } else {
            $currentRole = strtolower($currentRole);
        }
        if ($currentRole === 'vendor') {
            foreach ($others as $row) {
                if (in_array(strtolower((string) ($row['role'] ?? '')), ['freelance', 'freelancer'], true)) {
                    return (int) $row['id'];
                }
            }
        } elseif (in_array($currentRole, ['freelance', 'freelancer'], true)) {
            foreach ($others as $row) {
                if (strtolower((string) ($row['role'] ?? '')) === 'vendor') {
                    return (int) $row['id'];
                }
            }
        }

        return (int) ($others[0]['id'] ?? 0) ?: null;
    } catch (Throwable $e) {
        error_log('cg_kanban_portal_switch_target: ' . $e->getMessage());
        return null;
    }
}

function cg_kanban_portal_redirect_back(string $fallback = '/'): void
{
    $return = $_GET['return'] ?? '';
    if (is_string($return) && $return !== '' && strpos($return, '/') === 0 && strpos($return, '//') !== 0) {
        header('Location: ' . $return, true, 302);
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (is_string($referer) && $referer !== '') {
        $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
        $refHost = parse_url($referer, PHP_URL_HOST);
        if (is_string($refHost) && strcasecmp($refHost, $host) === 0) {
            header('Location: ' . $referer, true, 302);
            exit;
        }
    }

    header('Location: ' . $fallback, true, 302);
    exit;
}

function cg_kanban_portal_switch_account_and_redirect(string $fallback = '/'): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        header('Location: /login', true, 302);
        exit;
    }

    $targetId = cg_kanban_portal_switch_target($userId);
    if ($targetId === null || $targetId <= 0) {
        error_log('kanban switch_account: no linked target for user ' . $userId);
        cg_kanban_portal_redirect_back($fallback);
    }

    if (!cg_kanban_ensure_linked_users_loaded() || !function_exists('switchToLinkedUser')) {
        error_log('kanban switch_account: linked_users helpers unavailable');
        cg_kanban_portal_redirect_back($fallback);
    }

    $result = switchToLinkedUser($userId, $targetId);
    if (empty($result['success'])) {
        error_log('kanban switch_account: ' . ($result['message'] ?? 'switchToLinkedUser failed'));
    }

    cg_kanban_portal_redirect_back($fallback);
}
