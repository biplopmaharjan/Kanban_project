<?php
// Log a kanban board activity for the activity panel.
// $user_id can be null (e.g. client comment); $user_name is displayed.
function log_kanban_activity(PDO $pdo, int $board_id, ?int $user_id, string $user_name, string $action_type, array $payload = []): void {
    $stmt = $pdo->prepare("
        INSERT INTO kanban_board_activities (board_id, user_id, user_name, action_type, payload)
        VALUES (?, ?, ?, ?, ?)
    ");
    $json = $payload === [] ? null : json_encode($payload);
    $stmt->execute([$board_id, $user_id ?: null, $user_name, $action_type, $json]);
}

// Return true if we should skip inserting (recent duplicate exists). Use for progress_changed etc. to avoid double-save duplicates.
function kanban_activity_recent_duplicate(PDO $pdo, int $board_id, ?int $user_id, string $action_type, array $payload, int $within_seconds = 60): bool {
    $stmt = $pdo->prepare("
        SELECT payload, created_at FROM kanban_board_activities
        WHERE board_id = ? AND user_id <=> ? AND action_type = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$board_id, $user_id ?: null, $action_type, $within_seconds]);
    $card = trim((string)($payload['card_title'] ?? ''));
    $progress = isset($payload['progress']) ? (int)$payload['progress'] : null;
    $cid = isset($payload['card_id']) ? (int)$payload['card_id'] : 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $p = $row['payload'] ? (json_decode($row['payload'], true) ?: []) : [];
        $pcid = isset($p['card_id']) ? (int)$p['card_id'] : 0;
        if ($cid > 0 && $pcid === $cid && $action_type === 'progress_changed') {
            $pr2 = isset($p['progress']) ? (int)$p['progress'] : null;
            if ($progress !== null && $pr2 === $progress) {
                return true;
            }
            continue;
        }
        $c = trim((string)($p['card_title'] ?? ''));
        $pr = isset($p['progress']) ? (int)$p['progress'] : null;
        if ($c === $card && $pr === $progress) {
            return true;
        }
    }
    return false;
}
