<?php
/**
 * Soft-delete / restore / permanent purge for kanban_boards.
 * Soft-trashed boards are invisible to users; only admin can restore.
 */

if (!function_exists('kanban_mysql_show_has_row_for_trash')) {
    function kanban_mysql_show_has_row_for_trash(PDO $pdo, string $sql): bool
    {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return false;
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}

if (!function_exists('ensure_kanban_board_trash_schema')) {
    function ensure_kanban_board_trash_schema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            if (!kanban_mysql_show_has_row_for_trash($pdo, "SHOW COLUMNS FROM kanban_boards LIKE 'is_trashed'")) {
                $pdo->exec('ALTER TABLE kanban_boards ADD COLUMN is_trashed TINYINT(1) NOT NULL DEFAULT 0 AFTER name');
            }
            if (!kanban_mysql_show_has_row_for_trash($pdo, "SHOW COLUMNS FROM kanban_boards LIKE 'trashed_at'")) {
                $pdo->exec('ALTER TABLE kanban_boards ADD COLUMN trashed_at DATETIME NULL DEFAULT NULL AFTER is_trashed');
            }
            if (!kanban_mysql_show_has_row_for_trash($pdo, "SHOW INDEX FROM kanban_boards WHERE Key_name = 'idx_kanban_boards_is_trashed'")) {
                $pdo->exec('ALTER TABLE kanban_boards ADD INDEX idx_kanban_boards_is_trashed (is_trashed)');
            }
        } catch (Throwable $e) {
            error_log('ensure_kanban_board_trash_schema: ' . $e->getMessage());
        }
        $done = true;
    }
}

if (!function_exists('kanban_board_is_trashed')) {
    /** @return bool True if board missing or soft-trashed (treat as inaccessible to users). */
    function kanban_board_is_trashed(PDO $pdo, int $board_id): bool
    {
        if ($board_id <= 0) {
            return true;
        }
        ensure_kanban_board_trash_schema($pdo);
        $stmt = $pdo->prepare('SELECT COALESCE(is_trashed, 0) FROM kanban_boards WHERE id = ? LIMIT 1');
        $stmt->execute([$board_id]);
        $val = $stmt->fetchColumn();
        if ($val === false) {
            return true;
        }
        return (int)$val === 1;
    }
}

if (!function_exists('kanban_soft_delete_board')) {
    function kanban_soft_delete_board(PDO $pdo, int $board_id): bool
    {
        if ($board_id <= 0) {
            return false;
        }
        ensure_kanban_board_trash_schema($pdo);
        $stmt = $pdo->prepare('
            UPDATE kanban_boards
            SET is_trashed = 1, trashed_at = NOW()
            WHERE id = ? AND COALESCE(is_trashed, 0) = 0
        ');
        $stmt->execute([$board_id]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('kanban_board_active_filter_sql')) {
    /** SQL fragment: active (non-trashed) boards. Falls back to always-true if column missing. */
    function kanban_board_active_filter_sql(PDO $pdo, string $tableAlias = 'b'): string
    {
        ensure_kanban_board_trash_schema($pdo);
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM kanban_boards LIKE 'is_trashed'");
            if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
                return 'COALESCE(' . $tableAlias . '.is_trashed, 0) = 0';
            }
        } catch (Throwable $e) {
            error_log('kanban_board_active_filter_sql: ' . $e->getMessage());
        }
        return '1=1';
    }
}

if (!function_exists('kanban_restore_board')) {
    function kanban_restore_board(PDO $pdo, int $board_id): bool
    {
        if ($board_id <= 0) {
            return false;
        }
        ensure_kanban_board_trash_schema($pdo);
        $stmt = $pdo->prepare('
            UPDATE kanban_boards
            SET is_trashed = 0, trashed_at = NULL
            WHERE id = ? AND COALESCE(is_trashed, 0) = 1
        ');
        $stmt->execute([$board_id]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('kanban_permanently_delete_board')) {
    /**
     * Hard-cascade delete a board and related rows.
     *
     * @param list<string> $docRoots Absolute paths used to resolve workspace image files for unlink.
     */
    function kanban_permanently_delete_board(PDO $pdo, int $board_id, array $docRoots = []): void
    {
        if ($board_id <= 0) {
            throw new InvalidArgumentException('Invalid board_id');
        }

        $stmt = $pdo->prepare('SELECT id FROM kanban_columns WHERE board_id = ?');
        $stmt->execute([$board_id]);
        $columnIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $cardIds = [];
        if (!empty($columnIds)) {
            $placeholders = implode(',', array_fill(0, count($columnIds), '?'));
            $stmt = $pdo->prepare("SELECT id FROM kanban_cards WHERE column_id IN ($placeholders)");
            $stmt->execute($columnIds);
            $cardIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }

        if (!empty($cardIds) && function_exists('ensure_kanban_card_assignments_schema')) {
            ensure_kanban_card_assignments_schema($pdo);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM gantt_charts WHERE source_board_id = ?');
            $stmt->execute([$board_id]);
            $chartIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if (!empty($chartIds)) {
                $chPh = implode(',', array_fill(0, count($chartIds), '?'));
                try {
                    $pdo->prepare("DELETE FROM freelance_project_comments WHERE chart_id IN ($chPh)")->execute($chartIds);
                } catch (Throwable $e) {
                    error_log('purge_board freelance_project_comments: ' . $e->getMessage());
                }
                try {
                    $pdo->prepare("DELETE FROM timeline_views WHERE chart_id IN ($chPh)")->execute($chartIds);
                } catch (Throwable $e) {
                    error_log('purge_board timeline_views (chart): ' . $e->getMessage());
                }
            }
            try {
                $pdo->prepare('DELETE FROM timeline_views WHERE board_id = ?')->execute([$board_id]);
            } catch (Throwable $e) {
                error_log('purge_board timeline_views (board): ' . $e->getMessage());
            }
            $pdo->prepare('DELETE FROM gantt_charts WHERE source_board_id = ?')->execute([$board_id]);

            try {
                $wsStmt = $pdo->prepare("SELECT image_path FROM kanban_workspace_items WHERE board_id = ? AND image_path IS NOT NULL AND image_path <> ''");
                $wsStmt->execute([$board_id]);
                $resolvedRoots = [];
                foreach ($docRoots as $base) {
                    $r = realpath($base);
                    if ($r !== false && !in_array($r, $resolvedRoots, true)) {
                        $resolvedRoots[] = $r;
                    }
                }
                while ($ip = $wsStmt->fetchColumn()) {
                    $rel = ltrim(str_replace(["\0", '\\'], '', (string)$ip), '/');
                    if ($rel === '' || strpos($rel, '..') !== false) {
                        continue;
                    }
                    foreach ($resolvedRoots as $docRoot) {
                        $full = realpath($docRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
                        $prefix = $docRoot . DIRECTORY_SEPARATOR;
                        if ($full !== false && strpos($full, $prefix) === 0 && is_file($full)) {
                            @unlink($full);
                            break;
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('purge_board workspace image cleanup: ' . $e->getMessage());
            }

            try {
                $pdo->prepare('DELETE FROM kanban_workspace_connectors WHERE board_id = ?')->execute([$board_id]);
            } catch (Throwable $e) {
                error_log('purge_board connectors: ' . $e->getMessage());
            }
            try {
                $pdo->prepare('DELETE FROM kanban_workspace_strokes WHERE board_id = ?')->execute([$board_id]);
            } catch (Throwable $e) {
                error_log('purge_board strokes: ' . $e->getMessage());
            }
            try {
                $pdo->prepare('DELETE FROM kanban_workspace_items WHERE board_id = ?')->execute([$board_id]);
            } catch (Throwable $e) {
                error_log('purge_board workspace_items: ' . $e->getMessage());
            }

            foreach ([
                'kanban_board_digest_events',
                'kanban_board_activities',
                'kanban_google_sync_invites',
                'kanban_google_deleted_import_events',
                'kanban_google_sync_log',
            ] as $tbl) {
                try {
                    $pdo->prepare("DELETE FROM {$tbl} WHERE board_id = ?")->execute([$board_id]);
                } catch (Throwable $e) {
                    error_log("purge_board {$tbl}: " . $e->getMessage());
                }
            }

            if (!empty($cardIds)) {
                try {
                    $cPh = implode(',', array_fill(0, count($cardIds), '?'));
                    $pdo->prepare("DELETE FROM kanban_card_assignments WHERE card_id IN ($cPh)")->execute($cardIds);
                } catch (Throwable $e) {
                    error_log('purge_board kanban_card_assignments: ' . $e->getMessage());
                }
                try {
                    $cPh = implode(',', array_fill(0, count($cardIds), '?'));
                    $pdo->prepare("DELETE FROM kanban_card_comments WHERE card_id IN ($cPh)")->execute($cardIds);
                } catch (Throwable $e) {
                    // optional table
                }
            }

            if (!empty($columnIds)) {
                $placeholders = implode(',', array_fill(0, count($columnIds), '?'));
                $pdo->prepare("DELETE FROM kanban_cards WHERE column_id IN ($placeholders)")->execute($columnIds);
            }

            $pdo->prepare('DELETE FROM kanban_columns WHERE board_id = ?')->execute([$board_id]);

            foreach ([
                'kanban_board_emails',
                'kanban_board_collaborators',
                'kanban_board_invites',
                'kanban_google_event_links',
                'kanban_google_sync_cursors',
                'kanban_google_calendars',
                'kanban_google_account_members',
                'kanban_google_accounts',
                'kanban_board_pins',
                'kanban_board_recent',
            ] as $tbl) {
                try {
                    $pdo->prepare("DELETE FROM {$tbl} WHERE board_id = ?")->execute([$board_id]);
                } catch (Throwable $e) {
                    error_log("purge_board {$tbl}: " . $e->getMessage());
                }
            }

            if (function_exists('kanban_clear_board_pins')) {
                kanban_clear_board_pins($pdo, $board_id);
            }

            $pdo->prepare('DELETE FROM kanban_boards WHERE id = ?')->execute([$board_id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable $e2) {
                    error_log('purge_board rollback: ' . $e2->getMessage());
                }
            }
            throw $e;
        }
    }
}
