<?php
/**
 * Apply template seed cards to a board (shared by API and freelance_projects).
 */
require_once __DIR__ . '/kanban_board_templates.php';

function kanban_template_ensure_links_attachments_columns(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'links'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec('ALTER TABLE kanban_cards ADD COLUMN links TEXT NULL DEFAULT NULL AFTER description');
        }
    } catch (Throwable $e) {
        error_log('kanban_template_ensure_links: ' . $e->getMessage());
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'attachments'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec('ALTER TABLE kanban_cards ADD COLUMN attachments TEXT NULL DEFAULT NULL AFTER links');
        }
    } catch (Throwable $e) {
        error_log('kanban_template_ensure_attachments: ' . $e->getMessage());
    }
}

function kanban_template_ensure_datetime_times(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'start_time'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec('ALTER TABLE kanban_cards ADD COLUMN start_time TIME NULL DEFAULT NULL AFTER start_date');
        }
    } catch (Throwable $e) {
        error_log('kanban_template_ensure start_time: ' . $e->getMessage());
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'due_time'");
        if ($stmt && $stmt->rowCount() == 0) {
            $pdo->exec('ALTER TABLE kanban_cards ADD COLUMN due_time TIME NULL DEFAULT NULL AFTER due_date');
        }
    } catch (Throwable $e) {
        error_log('kanban_template_ensure due_time: ' . $e->getMessage());
    }
}

/**
 * @param list<int> $columnIdsOrdered Column IDs in template column order (index 0 = first column).
 * @return list<int> New card IDs (for Google sync, etc.).
 */
function kanban_seed_template_cards(PDO $pdo, array $columnIdsOrdered, string $slug): array {
    $slug = kanban_board_template_resolve_slug($slug);
    $seeds = kanban_board_template_seed_cards($slug);
    if ($seeds === [] || $columnIdsOrdered === []) {
        return [];
    }

    kanban_template_ensure_datetime_times($pdo);
    kanban_template_ensure_links_attachments_columns($pdo);

    $n = count($columnIdsOrdered);
    $nextPos = array_fill(0, $n, 0);
    $newIds = [];

    $stmt = $pdo->prepare(
        'INSERT INTO kanban_cards (column_id, title, description, start_date, start_time, due_date, due_time, position, priority, links, attachments) VALUES (?, ?, ?, NULL, NULL, NULL, NULL, ?, NULL, ?, ?)'
    );

    foreach ($seeds as $row) {
        if (!is_array($row)) {
            continue;
        }
        $colIdx = (int)($row['column_index'] ?? -1);
        if ($colIdx < 0 || $colIdx >= $n) {
            continue;
        }
        $column_id = (int)$columnIdsOrdered[$colIdx];
        if ($column_id <= 0) {
            continue;
        }
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $description = isset($row['description']) ? trim((string)$row['description']) : '';
        $description = $description !== '' ? $description : null;

        $links_arr = [];
        if (!empty($row['links']) && is_array($row['links'])) {
            foreach ($row['links'] as $url) {
                if (is_string($url) && trim($url) !== '') {
                    $links_arr[] = trim($url);
                }
            }
        }
        $links_json = $links_arr === [] ? null : json_encode($links_arr);

        $attachments_arr = [];
        if (!empty($row['attachments']) && is_array($row['attachments'])) {
            foreach ($row['attachments'] as $att) {
                if (is_array($att) && !empty($att['path'])) {
                    $attachments_arr[] = [
                        'path' => trim((string)$att['path']),
                        'name' => trim((string)($att['name'] ?? basename((string)$att['path']))),
                    ];
                }
            }
        }
        $attachments_json = $attachments_arr === [] ? null : json_encode($attachments_arr);

        $pos = $nextPos[$colIdx]++;
        $stmt->execute([$column_id, $title, $description, $pos, $links_json, $attachments_json]);
        $newIds[] = (int)$pdo->lastInsertId();
    }

    return $newIds;
}
