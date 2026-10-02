<?php
/**
 * Per-user "last opened" timestamps for Kanban boards (boards home).
 */
declare(strict_types=1);

function ensure_kanban_board_recent_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kanban_board_recent (
                user_id INT NOT NULL,
                board_id INT NOT NULL,
                last_opened_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, board_id),
                KEY idx_kanban_board_recent_user_opened (user_id, last_opened_at),
                KEY idx_kanban_board_recent_board (board_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_kanban_board_recent_schema: ' . $e->getMessage());
    }
}

function kanban_touch_board_opened(PDO $pdo, int $user_id, int $board_id): void
{
    if ($user_id <= 0 || $board_id <= 0) {
        return;
    }
    ensure_kanban_board_recent_schema($pdo);
    try {
        $stmt = $pdo->prepare('
            INSERT INTO kanban_board_recent (user_id, board_id, last_opened_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_opened_at = NOW()
        ');
        $stmt->execute([$user_id, $board_id]);
    } catch (Throwable $e) {
        error_log('kanban_touch_board_opened: ' . $e->getMessage());
    }
}
