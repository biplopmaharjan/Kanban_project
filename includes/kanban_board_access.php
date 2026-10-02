<?php
/**
 * Board access checks shared by freelance_kanban.php and workspace_character_describe.php.
 */

require_once __DIR__ . '/kanban_board_trash.php';

if (!function_exists('board_belongs_to_user')) {
    function board_belongs_to_user(PDO $pdo, int $board_id, int $user_id): bool {
        ensure_kanban_board_trash_schema($pdo);
        $stmt = $pdo->prepare("
            SELECT 1
            FROM kanban_boards b
            JOIN freelance_projects p ON p.id = b.project_id
            WHERE b.id = ? AND p.user_id = ? AND COALESCE(b.is_trashed, 0) = 0
            LIMIT 1
        ");
        $stmt->execute([$board_id, $user_id]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('user_owns_board')) {
    /** True when the board project belongs to the user or any linked account. */
    function user_owns_board(PDO $pdo, int $board_id, int $user_id): bool {
        foreach (kanban_linked_user_ids($user_id) as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && board_belongs_to_user($pdo, $board_id, $uid)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('kanban_linked_user_ids')) {
    function kanban_linked_user_ids(int $user_id): array {
        $ids = [$user_id];
        if (!function_exists('cg_kanban_ensure_linked_users_loaded')) {
            $loader = dirname(__FILE__) . '/cg_kanban_linked_users_loader.php';
            if (is_readable($loader)) {
                require_once $loader;
            }
        }
        if (!function_exists('cg_kanban_ensure_linked_users_loaded') || !cg_kanban_ensure_linked_users_loaded()) {
            return $ids;
        }
        if (!function_exists('getPrimaryUserId') || !function_exists('getLinkedUserIds')) {
            return $ids;
        }
        try {
            $primary = getPrimaryUserId($user_id);
            $linked = getLinkedUserIds($primary);
            return is_array($linked) ? array_values(array_unique(array_map('intval', $linked))) : $ids;
        } catch (Throwable $e) {
            error_log('kanban_linked_user_ids: ' . $e->getMessage());
            return $ids;
        }
    }
}

if (!function_exists('user_can_access_board')) {
    function user_can_access_board(PDO $pdo, int $board_id, int $user_id): bool {
        if ($board_id <= 0 || kanban_board_is_trashed($pdo, $board_id)) {
            return false;
        }
        $idsToCheck = kanban_linked_user_ids($user_id);
        foreach ($idsToCheck as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) {
                continue;
            }
            if (board_belongs_to_user($pdo, $board_id, $uid)) {
                return true;
            }
            $stmt = $pdo->prepare('SELECT 1 FROM kanban_board_collaborators WHERE board_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$board_id, $uid]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }
        $stmt = $pdo->prepare("
            SELECT 1 FROM kanban_board_invites bi
            INNER JOIN users u ON u.id = ? AND LOWER(TRIM(u.email)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(bi.email)) COLLATE utf8mb4_unicode_ci
            WHERE bi.board_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $board_id]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('workspace_share_can_access_board')) {
    /** Guest/client anonymous link: session must match board_id. Soft-trashed boards are never accessible. */
    function workspace_share_can_access_board(?array $workspace_share, int $board_id, ?PDO $pdo = null): bool {
        if ($workspace_share === null || (int)($workspace_share['board_id'] ?? 0) !== $board_id) {
            return false;
        }
        if ($pdo instanceof PDO && kanban_board_is_trashed($pdo, $board_id)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('workspace_assert_board_access')) {
    function workspace_assert_board_access(PDO $pdo, int $board_id, int $user_id, ?array $workspace_share): void {
        if (kanban_board_is_trashed($pdo, $board_id)) {
            json_fail('Board not found', 404);
        }
        if (workspace_share_can_access_board($workspace_share, $board_id, $pdo)) {
            return;
        }
        if (!user_can_access_board($pdo, $board_id, $user_id)) {
            json_fail('Board not found', 404);
        }
    }
}

if (!function_exists('workspace_share_forbid_client')) {
    function workspace_share_forbid_client(?array $workspace_share): void {
        if ($workspace_share !== null && ($workspace_share['share'] ?? '') === 'client') {
            json_fail('This link is view-only.', 403);
        }
    }
}

if (!function_exists('get_board_members_for_board')) {
    function get_board_members_for_board(PDO $pdo, int $board_id): array {
        $stmt = $pdo->prepare("
            SELECT p.id AS project_id, p.user_id AS owner_id, u.name, u.profile_pic
            FROM kanban_boards b
            JOIN freelance_projects p ON p.id = b.project_id
            JOIN users u ON u.id = p.user_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$board_id]);
        $projectRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$projectRow) {
            return [];
        }

        $members = [];
        $ownerId = (int)$projectRow['owner_id'];
        if ($ownerId > 0) {
            $members[$ownerId] = [
                'id' => $ownerId,
                'name' => $projectRow['name'] ?? '',
                'profile_pic' => $projectRow['profile_pic'] ?? '',
                'role' => 'owner',
            ];
        }

        $stmtCollab = $pdo->prepare("
            SELECT bc.user_id AS id, u.name, u.profile_pic
            FROM kanban_board_collaborators bc
            JOIN users u ON u.id = bc.user_id
            WHERE bc.board_id = ?
        ");
        $stmtCollab->execute([$board_id]);
        while ($row = $stmtCollab->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int)($row['id'] ?? 0);
            if ($uid <= 0 || isset($members[$uid])) {
                continue;
            }
            $row['role'] = 'editor';
            $members[$uid] = $row;
        }

        return array_values($members);
    }
}

if (!function_exists('cg_user_has_freelance_access')) {
    function cg_user_has_freelance_access(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $role = $_SESSION['user_role'] ?? 'customer';
        if (function_exists('cg_is_freelance_role') && cg_is_freelance_role($role)) {
            return true;
        }
        if (in_array($role, ['freelance', 'freelancer'], true)) {
            return true;
        }
        if (!function_exists('cg_kanban_ensure_linked_users_loaded')) {
            $loader = dirname(__FILE__) . '/cg_kanban_linked_users_loader.php';
            if (is_readable($loader)) {
                require_once $loader;
            }
        }
        if (!function_exists('cg_kanban_ensure_linked_users_loaded') || !cg_kanban_ensure_linked_users_loaded()) {
            return false;
        }
        if (!function_exists('getPrimaryUserId') || !function_exists('getLinkedUserIds')) {
            return false;
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
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('cg_user_has_freelance_access: ' . $e->getMessage());
            return false;
        }
    }
}
