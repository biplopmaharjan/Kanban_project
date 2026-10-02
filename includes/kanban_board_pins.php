<?php
/**
 * Per-user pinned boards on the boards home page.
 */
declare(strict_types=1);

function ensure_kanban_board_pins_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS kanban_board_pins (
                user_id INT NOT NULL,
                board_id INT NOT NULL,
                pinned_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, board_id),
                KEY idx_kanban_board_pins_user (user_id, pinned_at),
                KEY idx_kanban_board_pins_board (board_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_kanban_board_pins_schema: ' . $e->getMessage());
    }
}

function kanban_set_board_pinned(PDO $pdo, int $user_id, int $board_id, bool $pinned): void
{
    if ($user_id <= 0 || $board_id <= 0) {
        return;
    }
    ensure_kanban_board_pins_schema($pdo);
    try {
        if ($pinned) {
            $stmt = $pdo->prepare('
                INSERT INTO kanban_board_pins (user_id, board_id, pinned_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE pinned_at = NOW()
            ');
            $stmt->execute([$user_id, $board_id]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM kanban_board_pins WHERE user_id = ? AND board_id = ?');
            $stmt->execute([$user_id, $board_id]);
        }
    } catch (Throwable $e) {
        error_log('kanban_set_board_pinned: ' . $e->getMessage());
    }
}

function kanban_clear_board_pins(PDO $pdo, int $board_id): void
{
    if ($board_id <= 0) {
        return;
    }
    ensure_kanban_board_pins_schema($pdo);
    try {
        $pdo->prepare('DELETE FROM kanban_board_pins WHERE board_id = ?')->execute([$board_id]);
    } catch (Throwable $e) {
        error_log('kanban_clear_board_pins: ' . $e->getMessage());
    }
}
