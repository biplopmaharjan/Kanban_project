<?php
/**
 * Board collaborator chat (sync + send) for freelance_kanban.php.
 * Tables: kanban_board_chat_messages, kanban_board_chat_presence, kanban_board_chat_typing
 * (created by cg_ensure_freelance_tables in freelance_projects.php).
 */

/**
 * @param 'chat_sync'|'chat_send'|'chat_edit'|'chat_unsend' $action
 * @param array<string,mixed>|null $workspace_share
 */
function cg_kanban_board_chat_handle(string $action, PDO $pdo, int $user_id, ?array $workspace_share): void {
    if (!function_exists('cg_ensure_freelance_tables')) {
        json_fail('Chat unavailable.', 500);
    }
    cg_ensure_freelance_tables();

    if ($user_id <= 0) {
        json_fail('Please sign in to use board chat.', 401);
    }

    if ($action === 'chat_sync') {
        cg_kanban_board_chat_sync($pdo, $user_id, $workspace_share);
        return;
    }
    if ($action === 'chat_send') {
        cg_kanban_board_chat_send($pdo, $user_id, $workspace_share);
        return;
    }
    if ($action === 'chat_edit') {
        cg_kanban_board_chat_edit($pdo, $user_id, $workspace_share);
        return;
    }
    if ($action === 'chat_unsend') {
        cg_kanban_board_chat_unsend($pdo, $user_id, $workspace_share);
        return;
    }
}

function cg_kanban_chat_board_public_url(int $board_id): string {
    $base = defined('CG_KANBAN_APP_ORIGIN') ? (string) constant('CG_KANBAN_APP_ORIGIN') : 'https://kanban.cinegrid.net';
    return rtrim($base, '/') . '/kanban.php?board_id=' . $board_id;
}

function cg_kanban_board_chat_column_exists(PDO $pdo, string $column): bool {
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM kanban_board_chat_messages');
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
                $name = (string) ($row['Field'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('cg_kanban_board_chat_column_exists: ' . $e->getMessage());
        }
    }
    return isset($columns[$column]);
}

/**
 * @return list<int>
 */
function cg_kanban_board_chat_decode_mentions($raw): array {
    if ($raw === null || $raw === '') {
        return [];
    }
    $d = null;
    if (is_array($raw)) {
        $d = $raw;
    } elseif (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $d = is_array($decoded) ? $decoded : null;
    }
    if ($d === null) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map(static function ($v): int {
        return (int) $v;
    }, $d), static function (int $id): bool {
        return $id > 0;
    })));
}

/**
 * @param list<int> $mentionedUserIds
 */
function cg_kanban_board_chat_send_mention_emails(
    PDO $pdo,
    KanbanBoardNotifier $notifier,
    int $board_id,
    string $boardName,
    int $authorUserId,
    string $authorName,
    string $bodySnippet,
    array $mentionedUserIds
): void {
    if ($mentionedUserIds === []) {
        return;
    }
    $allowed = [];
    foreach (get_board_members_for_board($pdo, $board_id) as $m) {
        $uid = (int) ($m['id'] ?? 0);
        if ($uid > 0) {
            $allowed[$uid] = true;
        }
    }
    $boardUrl = cg_kanban_chat_board_public_url($board_id);
    $authorLabel = $authorName !== '' ? $authorName : 'Someone';
    foreach ($mentionedUserIds as $targetId) {
        if ($targetId <= 0 || $targetId === $authorUserId) {
            continue;
        }
        if (!isset($allowed[$targetId])) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $email = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        try {
            $notifier->notifyBoardChatTagged($boardName, $email, $authorLabel, $bodySnippet, $boardUrl);
        } catch (Throwable $e) {
            error_log('cg_kanban_board_chat_send_mention_emails: ' . $e->getMessage());
        }
    }
}

/**
 * @param array<string,mixed>|null $workspace_share
 */
function cg_kanban_board_chat_sync(PDO $pdo, int $user_id, ?array $workspace_share, ?array $opts = null): void {
    if (is_array($opts)) {
        $board_id = (int) ($opts['board_id'] ?? 0);
        $after_id = (int) ($opts['after_id'] ?? 0);
        $since_upd = (float) ($opts['since_upd'] ?? 0);
        $typing = !empty($opts['typing']);
    } else {
        $board_id = (int) ($_GET['board_id'] ?? 0);
        $after_id = (int) ($_GET['after_id'] ?? 0);
        $since_upd = (float) ($_GET['since_upd'] ?? 0);
        $typing = trim((string) ($_GET['typing'] ?? '')) === '1';
    }
    if ($board_id <= 0) {
        json_fail('Missing board_id');
    }
    workspace_assert_board_access($pdo, $board_id, $user_id, $workspace_share);
    workspace_share_forbid_client($workspace_share);

    if ($since_upd < 0) {
        $since_upd = 0.0;
    }

    $now = $pdo->prepare('INSERT INTO kanban_board_chat_presence (board_id, user_id, last_seen_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at)');
    $now->execute([$board_id, $user_id]);

    if ($typing) {
        $t = $pdo->prepare('INSERT INTO kanban_board_chat_typing (board_id, user_id, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)');
        $t->execute([$board_id, $user_id]);
    } else {
        $d = $pdo->prepare('DELETE FROM kanban_board_chat_typing WHERE board_id = ? AND user_id = ?');
        $d->execute([$board_id, $user_id]);
    }

    $clientUuidSelect = cg_kanban_board_chat_column_exists($pdo, 'client_uuid') ? 'm.client_uuid' : 'NULL AS client_uuid';
    $stmt = $pdo->prepare('
        SELECT m.id, m.user_id, m.body, m.card_context, m.mentions_json, ' . $clientUuidSelect . ', m.created_at,
            m.edit_count, m.updated_at, m.deleted_at, u.name AS user_name, u.profile_pic
        FROM kanban_board_chat_messages m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.board_id = ? AND (
            (m.deleted_at IS NULL AND m.id > ?)
            OR (? > 0 AND m.updated_at > FROM_UNIXTIME(?))
        )
        ORDER BY m.updated_at ASC, m.id ASC
        LIMIT 250
    ');
    $sinceFlag = $since_upd > 0 ? 1 : 0;
    $stmt->execute([$board_id, $after_id, $sinceFlag, $since_upd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = cg_kanban_board_chat_message_public($row);
    }

    $onlineStmt = $pdo->prepare('
        SELECT p.user_id AS id, u.name, u.profile_pic
        FROM kanban_board_chat_presence p
        INNER JOIN users u ON u.id = p.user_id
        WHERE p.board_id = ? AND p.last_seen_at > DATE_SUB(NOW(), INTERVAL 50 SECOND)
        ORDER BY u.name ASC
    ');
    $onlineStmt->execute([$board_id]);
    $online = $onlineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $typStmt = $pdo->prepare('
        SELECT u.name
        FROM kanban_board_chat_typing t
        INNER JOIN users u ON u.id = t.user_id
        WHERE t.board_id = ? AND t.updated_at > DATE_SUB(NOW(), INTERVAL 6 SECOND) AND t.user_id <> ?
        ORDER BY u.name ASC
    ');
    $typStmt->execute([$board_id, $user_id]);
    $typingNames = $typStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $typing = [];
    foreach ($typingNames as $n) {
        $typing[] = ['name' => (string) $n];
    }

    json_ok([
        'messages' => $messages,
        'online' => $online,
        'typing' => $typing,
    ]);
}

/**
 * @param array<string,mixed>|null $workspace_share
 */
function cg_kanban_board_chat_send(PDO $pdo, int $user_id, ?array $workspace_share, ?array $payload_override = null): void {
    if ($payload_override !== null) {
        $payload = $payload_override;
    } else {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            json_fail('Invalid JSON body');
        }
    }

    $board_id = (int) ($payload['board_id'] ?? 0);
    if ($board_id <= 0) {
        json_fail('Missing board_id');
    }
    workspace_assert_board_access($pdo, $board_id, $user_id, $workspace_share);
    workspace_share_forbid_client($workspace_share);

    $body = trim((string) ($payload['body'] ?? ''));
    $client_msg_id = trim((string) ($payload['client_msg_id'] ?? ''));
    if (strlen($client_msg_id) > 40) {
        $client_msg_id = substr($client_msg_id, 0, 40);
    }

    $rawMentions = $payload['mentioned_user_ids'] ?? [];
    $mentionedIds = [];
    if (is_array($rawMentions)) {
        foreach ($rawMentions as $v) {
            $mid = (int) $v;
            if ($mid > 0 && $mid !== $user_id) {
                $mentionedIds[$mid] = $mid;
            }
        }
    }
    $mentionedIds = array_slice(array_values($mentionedIds), 0, 25);
    $allowedMemberIds = [];
    foreach (get_board_members_for_board($pdo, $board_id) as $m) {
        $uid = (int) ($m['id'] ?? 0);
        if ($uid > 0) {
            $allowedMemberIds[$uid] = true;
        }
    }
    $mentionedIds = array_values(array_filter($mentionedIds, static function (int $id) use ($allowedMemberIds): bool {
        return isset($allowedMemberIds[$id]);
    }));

    $card_raw = $payload['card_context'] ?? null;
    $card_json = null;
    if (is_array($card_raw)) {
        $cid = (int) ($card_raw['card_id'] ?? 0);
        if ($cid > 0 && card_accessible_by_user($pdo, $cid, $user_id)) {
            $cardBoard = get_card_board_id($pdo, $cid);
            if ($cardBoard === $board_id) {
                $card_json = json_encode([
                    'card_id' => $cid,
                    'board_id' => $board_id,
                    'title' => function_exists('mb_substr')
                        ? mb_substr(trim((string) ($card_raw['title'] ?? '')), 0, 500, 'UTF-8')
                        : substr(trim((string) ($card_raw['title'] ?? '')), 0, 500),
                    'column_name' => function_exists('mb_substr')
                        ? mb_substr(trim((string) ($card_raw['column_name'] ?? '')), 0, 200, 'UTF-8')
                        : substr(trim((string) ($card_raw['column_name'] ?? '')), 0, 200),
                ], JSON_UNESCAPED_UNICODE);
            }
        }
    }

    if ($body === '' && $card_json === null) {
        json_fail('Message is empty.');
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($body, 'UTF-8') > 4000) {
            $body = mb_substr($body, 0, 4000, 'UTF-8');
        }
    } elseif (strlen($body) > 12000) {
        $body = substr($body, 0, 12000);
    }

    if ($client_msg_id === '') {
        json_fail('Missing client_msg_id');
    }

    $mentions_json = $mentionedIds === [] ? null : json_encode($mentionedIds, JSON_UNESCAPED_UNICODE);

    $ins = $pdo->prepare('
        INSERT INTO kanban_board_chat_messages (board_id, user_id, body, card_context, mentions_json, client_uuid)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ');
    $ins->execute([$board_id, $user_id, $body, $card_json, $mentions_json, $client_msg_id]);
    $msg_id = (int) $pdo->lastInsertId();
    if ($msg_id <= 0) {
        json_fail('Could not save message.', 500);
    }

    $sel = $pdo->prepare('
        SELECT m.id, m.user_id, m.body, m.card_context, m.mentions_json, m.client_uuid, m.created_at,
            m.edit_count, m.updated_at, m.deleted_at, u.name AS user_name, u.profile_pic
        FROM kanban_board_chat_messages m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.id = ? LIMIT 1
    ');
    $sel->execute([$msg_id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_fail('Could not load message.', 500);
    }

    if ($mentionedIds !== []) {
        require_once __DIR__ . '/kanban_board_notifier.php';
        $bn = $pdo->prepare('SELECT name FROM kanban_boards WHERE id = ? LIMIT 1');
        $bn->execute([$board_id]);
        $boardName = (string) ($bn->fetchColumn() ?: 'Kanban');
        $an = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $an->execute([$user_id]);
        $authorName = trim((string) ($an->fetchColumn() ?: 'User'));
        $notifier = new KanbanBoardNotifier();
        cg_kanban_board_chat_send_mention_emails($pdo, $notifier, $board_id, $boardName, $user_id, $authorName, $body, $mentionedIds);
    }

    json_ok(['message' => cg_kanban_board_chat_message_public($row)]);
}

/**
 * @param array<string,mixed>|null $workspace_share
 */
function cg_kanban_board_chat_edit(PDO $pdo, int $user_id, ?array $workspace_share, ?array $payload_override = null): void {
    if ($payload_override !== null) {
        $payload = $payload_override;
    } else {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            json_fail('Invalid JSON body');
        }
    }

    $board_id = (int) ($payload['board_id'] ?? 0);
    $message_id = (int) ($payload['message_id'] ?? 0);
    if ($board_id <= 0 || $message_id <= 0) {
        json_fail('Missing board_id or message_id');
    }
    workspace_assert_board_access($pdo, $board_id, $user_id, $workspace_share);
    workspace_share_forbid_client($workspace_share);

    $body = trim((string) ($payload['body'] ?? ''));
    if (function_exists('mb_strlen')) {
        if (mb_strlen($body, 'UTF-8') > 4000) {
            $body = mb_substr($body, 0, 4000, 'UTF-8');
        }
    } elseif (strlen($body) > 12000) {
        $body = substr($body, 0, 12000);
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, user_id, board_id, edit_count, deleted_at, card_context FROM kanban_board_chat_messages WHERE id = ? AND board_id = ? FOR UPDATE');
        $lock->execute([$message_id, $board_id]);
        $cur = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$cur) {
            $pdo->rollBack();
            json_fail('Message not found.', 404);
        }
        if ((int) ($cur['user_id'] ?? 0) !== $user_id) {
            $pdo->rollBack();
            json_fail('You can only edit your own messages.', 403);
        }
        if (!empty($cur['deleted_at'])) {
            $pdo->rollBack();
            json_fail('Message was removed.');
        }
        if ((int) ($cur['edit_count'] ?? 0) >= 2) {
            $pdo->rollBack();
            json_fail('This message can no longer be edited.');
        }

        $ccRow = $cur['card_context'] ?? null;
        $hasCard = false;
        if ($ccRow !== null && $ccRow !== '') {
            $decCard = json_decode((string) $ccRow, true);
            $hasCard = is_array($decCard) && (int) ($decCard['card_id'] ?? 0) > 0;
        }
        if ($body === '' && !$hasCard) {
            $pdo->rollBack();
            json_fail('Message is empty.');
        }

        $upd = $pdo->prepare('UPDATE kanban_board_chat_messages SET body = ?, edit_count = edit_count + 1 WHERE id = ? AND board_id = ? AND user_id = ? AND deleted_at IS NULL AND edit_count < 2');
        $upd->execute([$body, $message_id, $board_id, $user_id]);
        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            json_fail('Could not update message.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $sel = $pdo->prepare('
        SELECT m.id, m.user_id, m.body, m.card_context, m.mentions_json, m.client_uuid, m.created_at,
            m.edit_count, m.updated_at, m.deleted_at, u.name AS user_name, u.profile_pic
        FROM kanban_board_chat_messages m
        INNER JOIN users u ON u.id = m.user_id
        WHERE m.id = ? LIMIT 1
    ');
    $sel->execute([$message_id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_fail('Could not load message.', 500);
    }
    json_ok(['message' => cg_kanban_board_chat_message_public($row)]);
}

/**
 * @param array<string,mixed>|null $workspace_share
 */
function cg_kanban_board_chat_unsend(PDO $pdo, int $user_id, ?array $workspace_share, ?array $payload_override = null): void {
    if ($payload_override !== null) {
        $payload = $payload_override;
    } else {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            json_fail('Invalid JSON body');
        }
    }

    $board_id = (int) ($payload['board_id'] ?? 0);
    $message_id = (int) ($payload['message_id'] ?? 0);
    if ($board_id <= 0 || $message_id <= 0) {
        json_fail('Missing board_id or message_id');
    }
    workspace_assert_board_access($pdo, $board_id, $user_id, $workspace_share);
    workspace_share_forbid_client($workspace_share);

    $upd = $pdo->prepare('UPDATE kanban_board_chat_messages SET deleted_at = NOW(), body = \'\', card_context = NULL, mentions_json = NULL WHERE id = ? AND board_id = ? AND user_id = ? AND deleted_at IS NULL');
    $upd->execute([$message_id, $board_id, $user_id]);
    if ($upd->rowCount() === 0) {
        json_fail('Message not found or already removed.', 404);
    }

    $sel = $pdo->prepare('
        SELECT m.id, m.user_id, m.deleted_at, m.updated_at
        FROM kanban_board_chat_messages m
        WHERE m.id = ? AND m.board_id = ? LIMIT 1
    ');
    $sel->execute([$message_id, $board_id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_fail('Could not load message.', 500);
    }
    json_ok(['message' => cg_kanban_board_chat_message_public($row + ['user_name' => ''])]);
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function cg_kanban_board_chat_message_public(array $row): array {
    $del = $row['deleted_at'] ?? null;
    if ($del !== null && $del !== '') {
        $updated = $row['updated_at'] ?? '';
        if ($updated instanceof DateTimeInterface) {
            $updated = $updated->format('Y-m-d H:i:s');
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'is_deleted' => true,
            'updated_at' => (string) $updated,
        ];
    }

    $card = null;
    $cc = $row['card_context'] ?? null;
    if ($cc !== null && $cc !== '') {
        $decoded = json_decode((string) $cc, true);
        if (is_array($decoded)) {
            $card = $decoded;
        }
    }
    $created = $row['created_at'] ?? '';
    if ($created instanceof DateTimeInterface) {
        $created = $created->format('Y-m-d H:i:s');
    }
    $updated = $row['updated_at'] ?? '';
    if ($updated instanceof DateTimeInterface) {
        $updated = $updated->format('Y-m-d H:i:s');
    }
    return [
        'id' => (int) ($row['id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'user_name' => (string) ($row['user_name'] ?? 'User'),
        'profile_pic' => (string) ($row['profile_pic'] ?? ''),
        'body' => (string) ($row['body'] ?? ''),
        'created_at' => (string) $created,
        'updated_at' => (string) $updated,
        'edit_count' => (int) ($row['edit_count'] ?? 0),
        'card_context' => $card,
        'mentioned_user_ids' => cg_kanban_board_chat_decode_mentions($row['mentions_json'] ?? null),
        'client_msg_id' => isset($row['client_uuid']) && $row['client_uuid'] !== null && $row['client_uuid'] !== ''
            ? (string) $row['client_uuid']
            : null,
    ];
}
