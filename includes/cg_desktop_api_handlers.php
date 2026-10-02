<?php
/**
 * Desktop native app handlers — called from api/freelance_kanban.php (action=desktop_*).
 * No session cookie required; uses Bearer device tokens.
 */

if (!function_exists('cg_desktop_api_read_json')) {
    function cg_desktop_api_read_json(): array
    {
        if (function_exists('cg_freelance_kanban_peek_json_body')) {
            return cg_freelance_kanban_peek_json_body();
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('cg_desktop_api_handle')) {
    function cg_desktop_api_handle(string $action, PDO $pdo): void
    {
        $apiDir = __DIR__;
        require_once $apiDir . '/cg_desktop_auth.php';
        require_once $apiDir . '/cg_desktop_sync_service.php';

        cg_desktop_ensure_tables($pdo);

        $body = cg_desktop_api_read_json();

        if ($action === 'desktop_ping') {
            json_ok(['api' => 'desktop', 'version' => 1, 'ping' => true, 'build' => '2026-06-29']);
        }

        if ($action === 'desktop_login') {
            $email = trim((string)($body['email'] ?? ''));
            $password = (string)($body['password'] ?? '');
            $device_id = trim((string)($body['device_id'] ?? ''));
            $device_name = trim((string)($body['device_name'] ?? 'CineGrid Kanban Desktop'));

            if ($email === '' || $password === '') {
                json_fail('Email and password required', 400);
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                json_fail('Invalid email or password', 401);
            }
            if (isset($user['merged_into_user_id']) && $user['merged_into_user_id'] !== null) {
                json_fail('Account merged. Use your primary account.', 403);
            }
            if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
                json_fail('Account suspended.', 403);
            }
            if (empty($user['password'])) {
                json_fail('Invalid email or password', 401);
            }
            if (!password_verify($password, $user['password'])) {
                json_fail('Invalid email or password', 401);
            }

            $user_id = (int)$user['id'];
            if (!cg_desktop_user_has_freelance_access($pdo, $user_id)) {
                json_fail('Kanban is available to freelancer accounts.', 403);
            }

            $token = cg_desktop_issue_token($pdo, $user_id, $device_id, $device_name);
            json_ok([
                'device_token' => $token,
                'user' => [
                    'id' => $user_id,
                    'name' => (string)($user['name'] ?? ''),
                    'email' => (string)($user['email'] ?? ''),
                ],
                'boards' => cg_desktop_list_boards_for_user($pdo, $user_id),
            ]);
        }

        if ($action === 'desktop_logout') {
            $auth = cg_desktop_authenticate_request($pdo);
            if ($auth) {
                cg_desktop_revoke_token($pdo, $auth['user_id'], $auth['token_hash']);
            }
            json_ok();
        }

        $auth = cg_desktop_authenticate_request($pdo);
        if (!$auth) {
            json_fail('Unauthorized', 401);
        }
        $user_id = $auth['user_id'];
        if (!cg_desktop_user_has_freelance_access($pdo, $user_id)) {
            json_fail('Kanban access revoked', 403);
        }

        if ($action === 'desktop_me') {
            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            json_ok([
                'user' => $user,
                'boards' => cg_desktop_list_boards_for_user($pdo, $user_id),
                'device_id' => $auth['device_id'],
            ]);
        }

        if ($action === 'desktop_portal_url') {
            $board_id = (int)($body['board_id'] ?? 0);
            $view = strtolower(trim((string)($body['view'] ?? '')));
            if (!in_array($view, ['wallet', 'switch_account'], true) && $board_id <= 0) {
                json_fail('board_id required', 400);
            }
            if ($board_id > 0 && !user_can_access_board($pdo, $board_id, $user_id)) {
                json_fail('Board not found', 404);
            }
            require_once __DIR__ . '/cg_desktop_portal.php';
            try {
                $url = cg_desktop_portal_issue_sso_url($pdo, $user_id, $view, $board_id);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            } catch (Throwable $e) {
                error_log('desktop_portal_url: ' . $e->getMessage());
                json_fail('Portal URL failed', 500);
            }
            json_ok(['url' => $url]);
        }

        if ($action === 'desktop_sync_pull') {
            $board_ids = $body['board_ids'] ?? [];
            if (!is_array($board_ids)) {
                $board_ids = [];
            }
            $cursors = is_array($body['cursors'] ?? null) ? $body['cursors'] : [];
            $pull = cg_desktop_pull_boards($pdo, $user_id, $board_ids, $cursors);
            json_ok([
                'boards' => cg_desktop_list_boards_for_user($pdo, $user_id),
                'columns' => $pull['columns'],
                'cards' => $pull['cards'],
                'cursors' => $pull['cursors'],
            ]);
        }

        if ($action === 'desktop_sync_push') {
            $mutations = is_array($body['mutations'] ?? null) ? $body['mutations'] : [];
            $client_id = trim((string)($body['client_id'] ?? ''));
            $pdo->beginTransaction();
            try {
                $result = cg_desktop_apply_push_mutations($pdo, $user_id, $mutations);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('desktop_sync_push: ' . $e->getMessage());
                json_fail('Push failed', 500);
            }
            json_ok([
                'client_id' => $client_id,
                'id_map' => $result['id_map'],
                'applied' => $result['applied'],
                'errors' => $result['errors'],
            ]);
        }

        if ($action === 'desktop_trash_list') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            $cards = cg_desktop_list_trashed_cards($pdo, $user_id, $board_id);
            json_ok(['cards' => $cards]);
        }

        if ($action === 'desktop_list_activities') {
            $board_id = (int)($body['board_id'] ?? $_GET['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            $limit = min(150, max(10, (int)($body['limit'] ?? $_GET['limit'] ?? 50)));
            $activities = cg_desktop_list_activities($pdo, $user_id, $board_id, $limit);
            json_ok(['activities' => $activities]);
        }

        if ($action === 'desktop_list_card_comments') {
            $card_id = (int)($body['card_id'] ?? $_GET['card_id'] ?? 0);
            if ($card_id <= 0) {
                json_fail('card_id required', 400);
            }
            $comments = cg_desktop_list_card_comments($pdo, $user_id, $card_id);
            json_ok(['comments' => $comments]);
        }

        if ($action === 'desktop_add_card_comment') {
            $card_id = (int)($body['card_id'] ?? 0);
            $comment_body = trim((string)($body['body'] ?? ''));
            if ($card_id <= 0) {
                json_fail('card_id required', 400);
            }
            try {
                $comment = cg_desktop_add_card_comment($pdo, $user_id, $card_id, $comment_body);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = (int)$e->getCode();
                json_fail($e->getMessage(), $code >= 400 && $code < 600 ? $code : 400);
            }
            json_ok(['comment' => $comment]);
        }

        if ($action === 'desktop_list_card_activities') {
            $board_id = (int)($body['board_id'] ?? 0);
            $card_id = (int)($body['card_id'] ?? 0);
            if ($board_id <= 0 || $card_id <= 0) {
                json_fail('board_id and card_id required', 400);
            }
            $limit = min(150, max(10, (int)($body['limit'] ?? 80)));
            $activities = cg_desktop_list_card_activities($pdo, $user_id, $board_id, $card_id, $limit);
            json_ok(['activities' => $activities]);
        }

        if ($action === 'desktop_chat_sync') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            if (!user_can_access_board($pdo, $board_id, $user_id)) {
                json_fail('Board not found', 404);
            }
            cg_ensure_freelance_tables();
            $chatApi = __DIR__ . '/kanban_board_chat_api.php';
            if (!is_file($chatApi)) {
                json_fail('Chat unavailable — upload includes/kanban_board_chat_api.php', 500);
            }
            require_once $chatApi;
            try {
                cg_kanban_board_chat_sync($pdo, $user_id, null, [
                    'board_id' => $board_id,
                    'after_id' => (int)($body['after_id'] ?? 0),
                    'since_upd' => (float)($body['since_upd'] ?? 0),
                    'typing' => !empty($body['typing']),
                ]);
            } catch (Throwable $e) {
                error_log('desktop_chat_sync: ' . $e->getMessage());
                json_fail('Chat sync failed', 500);
            }
        }

        if ($action === 'desktop_chat_send') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            if (!user_can_access_board($pdo, $board_id, $user_id)) {
                json_fail('Board not found', 404);
            }
            cg_ensure_freelance_tables();
            $chatApi = __DIR__ . '/kanban_board_chat_api.php';
            if (!is_file($chatApi)) {
                json_fail('Chat unavailable — upload includes/kanban_board_chat_api.php', 500);
            }
            require_once $chatApi;
            try {
                cg_kanban_board_chat_send($pdo, $user_id, null, $body);
            } catch (Throwable $e) {
                error_log('desktop_chat_send: ' . $e->getMessage());
                json_fail('Chat send failed', 500);
            }
        }

        if ($action === 'desktop_chat_edit') {
            $board_id = (int)($body['board_id'] ?? 0);
            $message_id = (int)($body['message_id'] ?? 0);
            if ($board_id <= 0 || $message_id <= 0) {
                json_fail('board_id and message_id required', 400);
            }
            if (!user_can_access_board($pdo, $board_id, $user_id)) {
                json_fail('Board not found', 404);
            }
            cg_ensure_freelance_tables();
            $chatApi = __DIR__ . '/kanban_board_chat_api.php';
            if (!is_file($chatApi)) {
                json_fail('Chat unavailable — upload includes/kanban_board_chat_api.php', 500);
            }
            require_once $chatApi;
            try {
                cg_kanban_board_chat_edit($pdo, $user_id, null, $body);
            } catch (Throwable $e) {
                error_log('desktop_chat_edit: ' . $e->getMessage());
                json_fail('Chat edit failed', 500);
            }
        }

        if ($action === 'desktop_chat_unsend') {
            $board_id = (int)($body['board_id'] ?? 0);
            $message_id = (int)($body['message_id'] ?? 0);
            if ($board_id <= 0 || $message_id <= 0) {
                json_fail('board_id and message_id required', 400);
            }
            if (!user_can_access_board($pdo, $board_id, $user_id)) {
                json_fail('Board not found', 404);
            }
            cg_ensure_freelance_tables();
            $chatApi = __DIR__ . '/kanban_board_chat_api.php';
            if (!is_file($chatApi)) {
                json_fail('Chat unavailable — upload includes/kanban_board_chat_api.php', 500);
            }
            require_once $chatApi;
            try {
                cg_kanban_board_chat_unsend($pdo, $user_id, null, $body);
            } catch (Throwable $e) {
                error_log('desktop_chat_unsend: ' . $e->getMessage());
                json_fail('Chat unsend failed', 500);
            }
        }

        if ($action === 'desktop_list_board_members') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            $members = cg_desktop_list_board_members($pdo, $user_id, $board_id);
            json_ok(['members' => $members]);
        }

        if ($action === 'desktop_list_board_assignees') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            $assignees_by_card = cg_desktop_list_board_card_assignees($pdo, $user_id, $board_id);
            $hidden_viewers_by_card = cg_desktop_list_board_hidden_viewers_by_card($pdo, $user_id, $board_id);
            $hidden_viewers_by_column = cg_desktop_list_board_hidden_viewers_by_column($pdo, $user_id, $board_id);
            json_ok([
                'assignees_by_card' => $assignees_by_card,
                'hidden_viewers_by_card' => $hidden_viewers_by_card,
                'hidden_viewers_by_column' => $hidden_viewers_by_column,
            ]);
        }

        if ($action === 'desktop_set_column_hidden_viewers') {
            $column_id = (int)($body['column_id'] ?? 0);
            if ($column_id <= 0) {
                json_fail('column_id required', 400);
            }
            $raw = $body['user_ids'] ?? [];
            if (!is_array($raw)) {
                $raw = [];
            }
            $user_ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn($id) => $id > 0)));
            try {
                $valid = cg_desktop_set_column_hidden_viewers($pdo, $user_id, $column_id, $user_ids);
            } catch (RuntimeException $e) {
                $code = (int)$e->getCode();
                json_fail($e->getMessage(), $code >= 400 && $code < 600 ? $code : 400);
            } catch (Throwable $e) {
                error_log('desktop_set_column_hidden_viewers: ' . $e->getMessage());
                json_fail('Failed to update column hidden viewers', 500);
            }
            json_ok(['user_ids' => $valid]);
        }

        if ($action === 'desktop_set_card_hidden_viewers') {
            $card_id = (int)($body['card_id'] ?? 0);
            if ($card_id <= 0) {
                json_fail('card_id required', 400);
            }
            $raw = $body['user_ids'] ?? [];
            if (!is_array($raw)) {
                $raw = [];
            }
            $user_ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn($id) => $id > 0)));
            try {
                $valid = cg_desktop_set_card_hidden_viewers($pdo, $user_id, $card_id, $user_ids);
            } catch (RuntimeException $e) {
                $code = (int)$e->getCode();
                json_fail($e->getMessage(), $code >= 400 && $code < 600 ? $code : 400);
            } catch (Throwable $e) {
                error_log('desktop_set_card_hidden_viewers: ' . $e->getMessage());
                json_fail('Failed to update hidden viewers', 500);
            }
            json_ok(['user_ids' => $valid]);
        }

        if ($action === 'desktop_assign_card_members') {
            $card_id = (int)($body['card_id'] ?? 0);
            if ($card_id <= 0) {
                json_fail('card_id required', 400);
            }
            $raw = $body['user_ids'] ?? [];
            if (!is_array($raw)) {
                $raw = [];
            }
            $user_ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn($id) => $id > 0)));
            try {
                $valid = cg_desktop_assign_card_members($pdo, $user_id, $card_id, $user_ids);
            } catch (RuntimeException $e) {
                $code = (int)$e->getCode();
                json_fail($e->getMessage(), $code >= 400 && $code < 600 ? $code : 400);
            } catch (Throwable $e) {
                error_log('desktop_assign_card_members: ' . $e->getMessage());
                json_fail('Failed to update assignments', 500);
            }
            json_ok(['card_id' => $card_id, 'user_ids' => $valid]);
        }

        if ($action === 'desktop_list_board_templates') {
            $templatesFile = __DIR__ . '/kanban_board_templates.php';
            if (!is_file($templatesFile)) {
                json_fail('Board templates unavailable on server', 500);
            }
            require_once $templatesFile;
            json_ok(['templates' => kanban_board_templates_list_for_api()]);
        }

        if ($action === 'desktop_create_board') {
            $project_id = (int)($body['project_id'] ?? 0);
            $name = trim((string)($body['name'] ?? ''));
            $template = trim((string)($body['template'] ?? 'blank'));
            if ($project_id <= 0) {
                json_fail('project_id required', 400);
            }
            if ($name === '') {
                json_fail('name required', 400);
            }
            $stmt = $pdo->prepare('SELECT 1 FROM freelance_projects WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$project_id, $user_id]);
            if (!$stmt->fetchColumn()) {
                json_fail('Project not found', 404);
            }
            $templatesFile = __DIR__ . '/kanban_board_templates.php';
            $applyFile = __DIR__ . '/kanban_template_apply.php';
            if (!is_file($templatesFile) || !is_file($applyFile)) {
                json_fail('Board templates unavailable on server', 500);
            }
            require_once $templatesFile;
            require_once $applyFile;
            $template_slug = kanban_board_template_resolve_slug($template);
            $board_name = $name;
            $stmt = $pdo->prepare('INSERT INTO kanban_boards (project_id, name) VALUES (?, ?)');
            $stmt->execute([$project_id, $board_name]);
            $board_id = (int)$pdo->lastInsertId();
            $cols = kanban_board_template_columns_with_positions($template_slug);
            $stmtCol = $pdo->prepare('INSERT INTO kanban_columns (board_id, name, position) VALUES (?, ?, ?)');
            $column_ids_ordered = [];
            foreach ($cols as [$colName, $pos]) {
                $stmtCol->execute([$board_id, $colName, $pos]);
                $column_ids_ordered[] = (int)$pdo->lastInsertId();
            }
            kanban_seed_template_cards($pdo, $column_ids_ordered, $template_slug);
            try {
                $stmtChart = $pdo->prepare('INSERT INTO gantt_charts (project_id, name, source_board_id) VALUES (?, ?, ?)');
                $stmtChart->execute([$project_id, $board_name, $board_id]);
            } catch (Throwable $e) {
                error_log('desktop_create_board chart: ' . $e->getMessage());
            }
            json_ok(['board_id' => $board_id]);
        }

        if ($action === 'desktop_delete_board') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            if (!cg_desktop_user_owns_board($pdo, $board_id, $user_id)) {
                json_fail('Only the board owner can delete this board', 403);
            }
            try {
                cg_desktop_delete_board($pdo, $board_id);
            } catch (Throwable $e) {
                error_log('desktop_delete_board: ' . $e->getMessage());
                json_fail('Failed to delete board', 500);
            }
            json_ok();
        }

        if ($action === 'desktop_list_board_emails') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            $emails = cg_desktop_list_board_emails($pdo, $user_id, $board_id);
            json_ok(['emails' => $emails]);
        }

        if ($action === 'desktop_add_board_email') {
            $board_id = (int)($body['board_id'] ?? 0);
            $email = trim((string)($body['email'] ?? ''));
            if ($board_id <= 0 || $email === '') {
                json_fail('board_id and email required', 400);
            }
            try {
                $saved = cg_desktop_add_board_email($pdo, $user_id, $board_id, $email);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 400;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['email' => $saved]);
        }

        if ($action === 'desktop_remove_board_email') {
            $board_id = (int)($body['board_id'] ?? 0);
            $email = trim((string)($body['email'] ?? ''));
            if ($board_id <= 0 || $email === '') {
                json_fail('board_id and email required', 400);
            }
            try {
                cg_desktop_remove_board_email($pdo, $user_id, $board_id, $email);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_list_collaborators') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            try {
                $data = cg_desktop_list_collaborators($pdo, $user_id, $board_id);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok($data);
        }

        if ($action === 'desktop_add_collaborator') {
            $board_id = (int)($body['board_id'] ?? 0);
            $email = trim((string)($body['email'] ?? ''));
            if ($board_id <= 0 || $email === '') {
                json_fail('board_id and email required', 400);
            }
            try {
                $result = cg_desktop_add_collaborator($pdo, $user_id, $board_id, $email);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 400;
                json_fail($e->getMessage(), $code);
            }
            json_ok($result);
        }

        if ($action === 'desktop_remove_collaborator') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            $remove_user_id = (int)($body['user_id'] ?? 0);
            $remove_email = trim((string)($body['email'] ?? ''));
            try {
                cg_desktop_remove_collaborator($pdo, $user_id, $board_id, $remove_user_id, $remove_email);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_leave_board') {
            $board_id = (int)($body['board_id'] ?? 0);
            if ($board_id <= 0) {
                json_fail('board_id required', 400);
            }
            try {
                cg_desktop_leave_board($pdo, $user_id, $board_id);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 400;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_cinepoints_balance') {
            $stmt = $pdo->prepare('SELECT COALESCE(cine_points, 0) FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $balance = (int)($stmt->fetchColumn() ?: 0);
            json_ok(['balance' => $balance]);
        }

        if ($action === 'desktop_has_linked_accounts') {
            $stmt = $pdo->prepare("
                SELECT 1 FROM linked_users
                WHERE status = 'active' AND (primary_user_id = ? OR linked_user_id = ?)
                LIMIT 1
            ");
            $stmt->execute([$user_id, $user_id]);
            json_ok(['has_linked_accounts' => (bool)$stmt->fetchColumn()]);
        }

        if ($action === 'desktop_switch_account') {
            require_once __DIR__ . '/cg_kanban_switch_account.php';
            if (!cg_kanban_ensure_linked_users_loaded() || !function_exists('switchToLinkedUser')) {
                json_fail('Linked accounts unavailable', 503);
            }
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $currentRole = strtolower((string)($stmt->fetchColumn() ?: ''));
            $targetId = cg_kanban_portal_switch_target($user_id, $currentRole);
            if ($targetId === null || $targetId <= 0) {
                json_fail('No linked account to switch to', 400);
            }
            $result = switchToLinkedUser($user_id, $targetId);
            if (empty($result['success'])) {
                json_fail($result['message'] ?? 'Failed to switch account', 400);
            }
            $deviceStmt = $pdo->prepare('
                SELECT device_name FROM desktop_device_tokens
                WHERE user_id = ? AND device_id = ? LIMIT 1
            ');
            $deviceStmt->execute([$user_id, $auth['device_id']]);
            $device_name = (string)($deviceStmt->fetchColumn() ?: 'CineGrid Kanban Desktop');
            cg_desktop_revoke_token($pdo, $user_id, $auth['token_hash']);
            $new_token = cg_desktop_issue_token($pdo, $targetId, $auth['device_id'], $device_name);
            $userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
            $userStmt->execute([$targetId]);
            $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            json_ok([
                'device_token' => $new_token,
                'user' => [
                    'id' => (int)($targetUser['id'] ?? $targetId),
                    'name' => (string)($targetUser['name'] ?? ''),
                    'email' => (string)($targetUser['email'] ?? ''),
                ],
                'boards' => cg_desktop_list_boards_for_user($pdo, $targetId),
            ]);
        }

        if ($action === 'desktop_fetch_page_title') {
            $url = trim((string)($body['url'] ?? ''));
            if ($url === '' || strlen($url) > 2048) {
                json_fail('Invalid URL', 400);
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                json_fail('Invalid URL', 400);
            }
            if (!preg_match('#\Ahttps?://#i', $url)) {
                json_fail('Only http(s) URLs are allowed', 400);
            }
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');
            if ($host === '' || preg_match('/^(127\.0\.0\.1|localhost|0\.0\.0\.0|\[::1\])$/i', $host)) {
                json_fail('URL not allowed', 400);
            }
            if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
                json_fail('URL not allowed', 400);
            }
            require_once __DIR__ . '/kanban_card_description.php';
            $title = kanban_fetch_page_title($url);
            json_ok(['title' => $title !== '' ? $title : '']);
        }

        if ($action === 'desktop_queue_digest') {
            $board_id = (int)($body['board_id'] ?? 0);
            $event_type = trim((string)($body['event_type'] ?? ''));
            $payload = $body['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }
            try {
                cg_desktop_queue_digest($pdo, $user_id, $board_id, $event_type, $payload);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_workspace_bootstrap') {
            $board_id = (int)($body['board_id'] ?? 0);
            $import = !empty($body['import_kanban_cards']);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $payload = cg_desktop_workspace_bootstrap($pdo, $user_id, $board_id, $import);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok($payload);
        }

        if ($action === 'desktop_workspace_sync') {
            $board_id = (int)($body['board_id'] ?? 0);
            $since = trim((string)($body['since'] ?? ''));
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                if ($board_id <= 0) {
                    throw new InvalidArgumentException('Missing board_id');
                }
                if (!user_can_access_board($pdo, $board_id, $user_id)) {
                    throw new RuntimeException('Board not found', 404);
                }
                $workspace = cg_desktop_workspace_load_payload($pdo, $board_id);
                $stmt = $pdo->prepare("
                    SELECT MAX(ts) AS updated_at FROM (
                        SELECT MAX(updated_at) AS ts FROM kanban_workspace_items WHERE board_id = ?
                        UNION ALL SELECT MAX(updated_at) AS ts FROM kanban_workspace_strokes WHERE board_id = ?
                        UNION ALL SELECT MAX(updated_at) AS ts FROM kanban_workspace_connectors WHERE board_id = ?
                    ) t
                ");
                $stmt->execute([$board_id, $board_id, $board_id]);
                $updated_at = (string)($stmt->fetchColumn() ?: '');
                if ($since !== '' && $since === $updated_at) {
                    $device_id = trim((string)($body['device_id'] ?? ''));
                    $presence = $device_id !== ''
                        ? cg_desktop_workspace_presence_payload($pdo, $board_id, $user_id, $device_id)
                        : ['workspace_presence_self_actor' => '', 'workspace_presence' => cg_desktop_workspace_presence_list_active($pdo, $board_id)];
                    json_ok(array_merge([
                        'board_id' => $board_id,
                        'updated_at' => $updated_at,
                        'unchanged' => true,
                    ], $presence));
                }
                $device_id = trim((string)($body['device_id'] ?? ''));
                $presence = $device_id !== ''
                    ? cg_desktop_workspace_presence_payload($pdo, $board_id, $user_id, $device_id)
                    : ['workspace_presence_self_actor' => '', 'workspace_presence' => cg_desktop_workspace_presence_list_active($pdo, $board_id)];
                json_ok(array_merge([
                    'board_id' => $board_id,
                    'updated_at' => $updated_at,
                    'workspace' => $workspace,
                ], $presence));
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
        }

        if ($action === 'desktop_workspace_create_item') {
            $board_id = (int)($body['board_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $item = cg_desktop_workspace_create_item($pdo, $user_id, $board_id, $body);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['item' => $item]);
        }

        if ($action === 'desktop_workspace_update_item') {
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $item = cg_desktop_workspace_update_item($pdo, $user_id, $body);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['item' => $item]);
        }

        if ($action === 'desktop_workspace_delete_item') {
            $item_id = (int)($body['item_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                cg_desktop_workspace_delete_item($pdo, $user_id, $item_id);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_workspace_restore_item') {
            $item_id = (int)($body['item_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                cg_desktop_workspace_restore_item($pdo, $user_id, $item_id);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_workspace_trash_bootstrap') {
            $board_id = (int)($body['board_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $payload = cg_desktop_workspace_trash_bootstrap($pdo, $user_id, $board_id);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok($payload);
        }

        if ($action === 'desktop_workspace_delete_item_forever') {
            $item_id = (int)($body['item_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                cg_desktop_workspace_delete_item_forever($pdo, $user_id, $item_id);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok();
        }

        if ($action === 'desktop_workspace_empty_trash_forever') {
            $board_id = (int)($body['board_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $deleted = cg_desktop_workspace_empty_trash_forever($pdo, $user_id, $board_id);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['deleted_items' => $deleted]);
        }

        if ($action === 'desktop_workspace_save_stroke') {
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $stroke = cg_desktop_workspace_save_stroke($pdo, $user_id, $body);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['stroke' => $stroke]);
        }

        if ($action === 'desktop_workspace_delete_stroke') {
            $stroke_id = (int)($body['stroke_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                cg_desktop_workspace_delete_stroke($pdo, $user_id, $stroke_id);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok([]);
        }

        if ($action === 'desktop_workspace_save_connector') {
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $connector = cg_desktop_workspace_save_connector($pdo, $user_id, $body);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['connector' => $connector]);
        }

        if ($action === 'desktop_workspace_upload_image') {
            $board_id = (int)($body['board_id'] ?? 0);
            $image_base64 = (string)($body['image_base64'] ?? '');
            $mime_type = (string)($body['mime_type'] ?? 'image/png');
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                $image_path = cg_desktop_workspace_upload_image($pdo, $user_id, $board_id, $image_base64, $mime_type);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok(['image_path' => $image_path]);
        }

        if ($action === 'desktop_workspace_delete_connector') {
            $connector_id = (int)($body['connector_id'] ?? 0);
            require_once __DIR__ . '/cg_workspace_desktop.php';
            try {
                cg_desktop_workspace_delete_connector($pdo, $user_id, $connector_id);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok([]);
        }

        if ($action === 'desktop_workspace_generate_ai_image') {
            require_once __DIR__ . '/cg_workspace_ai_generate.php';
            try {
                $result = cg_desktop_workspace_generate_ai_image($pdo, $user_id, $body);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok($result);
        }

        if ($action === 'desktop_workspace_describe_character') {
            require_once __DIR__ . '/workspace_character_describe_api.php';
            try {
                $result = cg_desktop_workspace_describe_character($pdo, $user_id, $body);
            } catch (InvalidArgumentException $e) {
                json_fail($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
                json_fail($e->getMessage(), $code);
            }
            json_ok($result);
        }

        if ($action === 'desktop_workspace_presence_ping') {
            require_once __DIR__ . '/cg_workspace_desktop.php';
            $board_id = (int)($body['board_id'] ?? 0);
            $device_id = trim((string)($body['device_id'] ?? ''));
            if ($device_id === '') {
                json_fail('Missing device_id', 400);
            }
            try {
                $payload = cg_desktop_workspace_presence_ping($pdo, $user_id, $board_id, $device_id, $body);
            } catch (RuntimeException $e) {
                $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 404;
                json_fail($e->getMessage(), $code);
            }
            json_ok($payload);
        }

        json_fail('Unknown desktop action', 404);
    }
}
