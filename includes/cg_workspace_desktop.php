<?php
/**
 * Workspace bootstrap for CineGrid Kanban Desktop (Bearer auth, no PHP session).
 */

if (!function_exists('cg_desktop_normalize_ws_image_path')) {
    function cg_desktop_normalize_ws_image_path(string $imagePath): string
    {
        $raw = trim(str_replace(["\0", '\\'], '', $imagePath));
        if ($raw === '') {
            return '';
        }
        if (preg_match('#\Ahttps?://#i', $raw)) {
            $parsed = parse_url($raw);
            if (!is_array($parsed) || !isset($parsed['path'])) {
                return '';
            }
            $raw = ltrim((string) $parsed['path'], '/');
        } else {
            $raw = ltrim($raw, '/');
        }
        if ($raw === '' || strpos($raw, '..') !== false) {
            return '';
        }
        if (strpos($raw, 'uploads/workspace_images/') === 0) {
            return $raw;
        }
        if (preg_match('#\Akanban/((?:ws|ws_ai)_[A-Za-z0-9._-]+\.(?:jpe?g|png|gif|webp))\z#i', $raw, $m)) {
            return 'uploads/workspace_images/' . $m[1];
        }
        if (preg_match('#\A(?:ws|ws_ai)_[A-Za-z0-9._-]+\.(?:jpe?g|png|gif|webp)\z#i', $raw)) {
            return 'uploads/workspace_images/' . $raw;
        }
        return '';
    }
}

if (!function_exists('cg_desktop_workspace_image_url')) {
    function cg_desktop_workspace_image_url(string $imagePath): string
    {
        $rel = cg_desktop_normalize_ws_image_path($imagePath);
        if ($rel === '') {
            return '';
        }
        $base = defined('CG_KANBAN_APP_ORIGIN') && CG_KANBAN_APP_ORIGIN !== ''
            ? rtrim((string) CG_KANBAN_APP_ORIGIN, '/')
            : 'https://kanban.cinegrid.net';
        return $base . '/api/workspace_image?path=' . rawurlencode($rel) . '&w=1024';
    }
}

if (!function_exists('cg_desktop_normalize_meta_json_image_paths')) {
    function cg_desktop_normalize_meta_json_image_paths(string $metaJson): string
    {
        if ($metaJson === '') {
            return '';
        }
        $meta = json_decode($metaJson, true);
        if (!is_array($meta)) {
            return $metaJson;
        }
        $changed = false;
        $resolve = static function (?array &$entry) use (&$changed): void {
            if (!is_array($entry)) {
                return;
            }
            $path = trim((string)($entry['image_path'] ?? ''));
            if ($path === '') {
                return;
            }
            $resolved = cg_desktop_normalize_ws_image_path($path);
            if ($resolved === '') {
                return;
            }
            if ($path !== $resolved) {
                $changed = true;
            }
            $entry['image_path'] = $resolved;
            $entry['image_display_url'] = cg_desktop_workspace_image_url($resolved);
        };
        if (isset($meta['cells']) && is_array($meta['cells'])) {
            foreach ($meta['cells'] as $i => $cell) {
                if (!is_array($cell)) {
                    continue;
                }
                $resolve($meta['cells'][$i]);
            }
        }
        if (isset($meta['characters']) && is_array($meta['characters'])) {
            foreach ($meta['characters'] as $i => $ch) {
                if (!is_array($ch)) {
                    continue;
                }
                $resolve($meta['characters'][$i]);
            }
        }
        if (!$changed) {
            return $metaJson;
        }
        $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : $metaJson;
    }
}

if (!function_exists('cg_desktop_normalize_workspace_item')) {
    function cg_desktop_normalize_workspace_item(array $r): array
    {
        $r['id'] = (int)($r['id'] ?? 0);
        $r['board_id'] = (int)($r['board_id'] ?? 0);
        foreach (['x', 'y', 'width', 'height', 'rotation'] as $k) {
            $r[$k] = (float)($r[$k] ?? 0);
        }
        $r['z_index'] = (int)($r['z_index'] ?? 0);
        $r['item_type'] = (string)($r['item_type'] ?? 'sticky_note');
        foreach (['title', 'content', 'source_url', 'embed_url', 'image_path', 'style_json', 'meta_json'] as $k) {
            $r[$k] = isset($r[$k]) && $r[$k] !== null ? (string)$r[$k] : '';
        }
        $r['image_display_url'] = '';
        if ($r['item_type'] === 'image' && $r['image_path'] !== '') {
            $norm = cg_desktop_normalize_ws_image_path($r['image_path']);
            if ($norm !== '') {
                $r['image_path'] = $norm;
            }
            $r['image_display_url'] = cg_desktop_workspace_image_url($r['image_path']);
        }
        if (in_array($r['item_type'], ['storyboard_sheet', 'character_bible', 'spreadsheet_card'], true) && $r['meta_json'] !== '') {
            $r['meta_json'] = cg_desktop_normalize_meta_json_image_paths($r['meta_json']);
        }
        return $r;
    }
}

if (!function_exists('cg_desktop_normalize_workspace_stroke')) {
    function cg_desktop_normalize_workspace_stroke(array $r): array
    {
        $r['id'] = (int)($r['id'] ?? 0);
        $r['board_id'] = (int)($r['board_id'] ?? 0);
        $r['item_id'] = isset($r['item_id']) && $r['item_id'] !== null && $r['item_id'] !== ''
            ? (int)$r['item_id'] : null;
        $r['tool'] = (string)($r['tool'] ?? 'brush');
        $r['stroke_data'] = (string)($r['stroke_data'] ?? '');
        $r['bounds_json'] = $r['bounds_json'] !== null ? (string)$r['bounds_json'] : '';
        $r['style_json'] = $r['style_json'] !== null ? (string)$r['style_json'] : '';
        $r['z_index'] = (int)($r['z_index'] ?? 0);
        return $r;
    }
}

if (!function_exists('cg_desktop_normalize_workspace_connector')) {
    function cg_desktop_normalize_workspace_connector(array $r): array
    {
        $r['id'] = (int)($r['id'] ?? 0);
        $r['board_id'] = (int)($r['board_id'] ?? 0);
        foreach (['from_item_id', 'to_item_id'] as $k) {
            $r[$k] = isset($r[$k]) && $r[$k] !== null && $r[$k] !== '' ? (int)$r[$k] : null;
        }
        foreach (['start_x', 'start_y', 'end_x', 'end_y'] as $k) {
            if (!array_key_exists($k, $r) || $r[$k] === null || $r[$k] === '') {
                $r[$k] = null;
            } else {
                $r[$k] = (float)$r[$k];
            }
        }
        $r['label'] = (string)($r['label'] ?? '');
        $r['style_json'] = $r['style_json'] !== null ? (string)$r['style_json'] : '';
        $r['z_index'] = (int)($r['z_index'] ?? 0);
        return $r;
    }
}

if (!function_exists('cg_desktop_workspace_bootstrap')) {
    function cg_desktop_workspace_bootstrap(
        PDO $pdo,
        int $user_id,
        int $board_id,
        bool $import_kanban_cards = false
    ): array {
        if ($board_id <= 0) {
            throw new InvalidArgumentException('Missing board_id');
        }
        if (!user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }

        require_once __DIR__ . '/kanban_board_access.php';
        require_once __DIR__ . '/cg_desktop_sync_service.php';

        if ($import_kanban_cards) {
            cg_desktop_workspace_import_kanban_cards($pdo, $user_id, $board_id);
        }

        $boards = cg_desktop_list_boards_for_user($pdo, $user_id);
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

        $cine_points = 0;
        $cStmt = $pdo->prepare('SELECT cine_points FROM users WHERE id = ? LIMIT 1');
        $cStmt->execute([$user_id]);
        $cine_points = (int)($cStmt->fetchColumn() ?: 0);

        return [
            'board_id' => $board_id,
            'boards' => $boards,
            'cine_points' => $cine_points,
            'board_members' => get_board_members_for_board($pdo, $board_id),
            'updated_at' => $updated_at,
            'workspace' => $workspace,
        ];
    }
}

if (!function_exists('cg_desktop_workspace_import_kanban_cards')) {
    function cg_desktop_workspace_import_kanban_cards(PDO $pdo, int $user_id, int $board_id): int
    {
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            return 0;
        }
        require_once __DIR__ . '/cg_desktop_sync_service.php';

        $stmt = $pdo->prepare('SELECT name FROM kanban_boards WHERE id = ? LIMIT 1');
        $stmt->execute([$board_id]);
        $board_name = (string)($stmt->fetchColumn() ?: 'Board');

        $stmt = $pdo->prepare("
            SELECT kc.id, kc.title, kc.progress, kc.priority, kc.start_date, kc.due_date, kc.due_time,
                   kc.is_pinned, kc.column_id, col.name AS column_name
            FROM kanban_cards kc
            INNER JOIN kanban_columns col ON col.id = kc.column_id
            WHERE col.board_id = ? AND COALESCE(kc.is_trashed, 0) = 0
            ORDER BY col.position ASC, kc.position ASC, kc.id ASC
        ");
        $stmt->execute([$board_id]);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$cards) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT id, meta_json FROM kanban_workspace_items
            WHERE board_id = ? AND item_type = 'kanban_card' AND COALESCE(is_trashed, 0) = 0
        ");
        $stmt->execute([$board_id]);
        $existing = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            if (!is_array($meta)) {
                continue;
            }
            $cid = (int)($meta['card_id'] ?? 0);
            if ($cid > 0) {
                $existing[$cid] = (int)$row['id'];
            }
        }

        $assignees_by_card = cg_desktop_list_board_card_assignees($pdo, $user_id, $board_id);
        $imported = 0;
        $col = 0;
        $row = 0;
        $card_w = 340.0;
        $card_h = 130.0;
        $gap = 28.0;
        $start_x = 120.0;
        $start_y = 120.0;
        $per_row = 4;
        $z = 1;

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(z_index), 0) FROM kanban_workspace_items WHERE board_id = ?");
        $stmt->execute([$board_id]);
        $z = (int)$stmt->fetchColumn() + 1;

        $insert = $pdo->prepare("
            INSERT INTO kanban_workspace_items
            (board_id, item_type, title, content, source_url, embed_url, image_path,
             x, y, width, height, rotation, z_index, style_json, meta_json)
            VALUES (?, 'kanban_card', '', '', ?, '', NULL, ?, ?, ?, ?, 0, ?, NULL, ?)
        ");

        foreach ($cards as $card) {
            $card_id = (int)($card['id'] ?? 0);
            if ($card_id <= 0 || isset($existing[$card_id])) {
                continue;
            }
            $x = $start_x + $col * ($card_w + $gap);
            $y = $start_y + $row * ($card_h + $gap);
            $col++;
            if ($col >= $per_row) {
                $col = 0;
                $row++;
            }
            $assignees = $assignees_by_card[(string)$card_id] ?? [];
            $meta = [
                'card_id' => $card_id,
                'board_id' => $board_id,
                'board_name' => $board_name,
                'column_id' => (int)($card['column_id'] ?? 0),
                'column_name' => (string)($card['column_name'] ?? ''),
                'title' => (string)($card['title'] ?? ''),
                'priority' => (string)($card['priority'] ?? ''),
                'progress' => (int)($card['progress'] ?? 0),
                'start_date' => (string)($card['start_date'] ?? ''),
                'due_date' => (string)($card['due_date'] ?? ''),
                'due_time' => (string)($card['due_time'] ?? ''),
                'is_pinned' => (int)($card['is_pinned'] ?? 0) ? 1 : 0,
                'assignees' => array_map(static function ($m) {
                    return [
                        'name' => (string)($m['name'] ?? ''),
                        'avatar_url' => (string)($m['profile_pic'] ?? ''),
                        'initials' => strtoupper(substr((string)($m['name'] ?? 'U'), 0, 2)),
                    ];
                }, array_slice($assignees, 0, 6)),
            ];
            $source = 'https://kanban.cinegrid.net/kanban?board_id=' . $board_id . '&card_id=' . $card_id;
            $insert->execute([
                $board_id,
                $source,
                $x,
                $y,
                $card_w,
                $card_h,
                $z++,
                json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);
            $imported++;
        }
        return $imported;
    }
}

if (!function_exists('cg_desktop_workspace_load_payload')) {
    function cg_desktop_workspace_load_payload(PDO $pdo, int $board_id): array
    {
        $stmt = $pdo->prepare("SELECT * FROM kanban_workspace_items WHERE board_id = ? AND COALESCE(is_trashed, 0) = 0 ORDER BY z_index ASC, id ASC");
        $stmt->execute([$board_id]);
        $items = array_map('cg_desktop_normalize_workspace_item', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $stmt = $pdo->prepare("
            SELECT s.*
            FROM kanban_workspace_strokes s
            LEFT JOIN kanban_workspace_items i ON i.id = s.item_id
            WHERE s.board_id = ?
              AND (s.item_id IS NULL OR (i.id IS NOT NULL AND COALESCE(i.is_trashed, 0) = 0))
            ORDER BY s.z_index ASC, s.id ASC
        ");
        $stmt->execute([$board_id]);
        $strokes = array_map('cg_desktop_normalize_workspace_stroke', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $stmt = $pdo->prepare("
            SELECT c.*
            FROM kanban_workspace_connectors c
            LEFT JOIN kanban_workspace_items a ON a.id = c.from_item_id
            LEFT JOIN kanban_workspace_items b ON b.id = c.to_item_id
            WHERE c.board_id = ?
              AND (c.from_item_id IS NULL OR (a.id IS NOT NULL AND COALESCE(a.is_trashed, 0) = 0))
              AND (c.to_item_id IS NULL OR (b.id IS NOT NULL AND COALESCE(b.is_trashed, 0) = 0))
            ORDER BY c.z_index ASC, c.id ASC
        ");
        $stmt->execute([$board_id]);
        $connectors = array_map('cg_desktop_normalize_workspace_connector', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return compact('items', 'strokes', 'connectors');
    }
}

if (!function_exists('cg_desktop_workspace_create_item')) {
    function cg_desktop_workspace_create_item(PDO $pdo, int $user_id, int $board_id, array $body): array
    {
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $item_type = trim((string)($body['item_type'] ?? 'sticky_note'));
        if ($item_type === '') {
            $item_type = 'sticky_note';
        }
        $stmt = $pdo->prepare("
            INSERT INTO kanban_workspace_items
            (board_id, item_type, title, content, source_url, embed_url, image_path,
             x, y, width, height, rotation, z_index, style_json, meta_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $image_path = trim((string)($body['image_path'] ?? ''));
        $stmt->execute([
            $board_id,
            $item_type,
            (string)($body['title'] ?? ''),
            (string)($body['content'] ?? ''),
            (string)($body['source_url'] ?? ''),
            (string)($body['embed_url'] ?? ''),
            $image_path !== '' ? $image_path : null,
            (float)($body['x'] ?? 0),
            (float)($body['y'] ?? 0),
            (float)($body['width'] ?? 280),
            (float)($body['height'] ?? 180),
            (float)($body['rotation'] ?? 0),
            (int)($body['z_index'] ?? 1),
            ($body['style_json'] ?? '') !== '' ? (string)$body['style_json'] : null,
            ($body['meta_json'] ?? '') !== '' ? (string)$body['meta_json'] : null,
        ]);
        $id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Failed to load new item', 500);
        }
        return cg_desktop_normalize_workspace_item($row);
    }
}

if (!function_exists('cg_desktop_workspace_update_item')) {
    function cg_desktop_workspace_update_item(PDO $pdo, int $user_id, array $body): array
    {
        $item_id = (int)($body['item_id'] ?? 0);
        if ($item_id <= 0) {
            throw new InvalidArgumentException('Missing item_id');
        }
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$item_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new RuntimeException('Workspace item not found', 404);
        }
        $board_id = (int)($existing['board_id'] ?? 0);
        if (!user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $item_type = trim((string)($body['item_type'] ?? $existing['item_type'] ?? 'sticky_note'));
        $stmt = $pdo->prepare("
            UPDATE kanban_workspace_items SET
                item_type = ?, title = ?, content = ?, source_url = ?, embed_url = ?,
                x = ?, y = ?, width = ?, height = ?, rotation = ?, z_index = ?,
                style_json = ?, meta_json = ?, is_trashed = 0, trashed_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([
            $item_type,
            (string)($body['title'] ?? $existing['title'] ?? ''),
            (string)($body['content'] ?? $existing['content'] ?? ''),
            (string)($body['source_url'] ?? $existing['source_url'] ?? ''),
            (string)($body['embed_url'] ?? $existing['embed_url'] ?? ''),
            (float)($body['x'] ?? $existing['x'] ?? 0),
            (float)($body['y'] ?? $existing['y'] ?? 0),
            (float)($body['width'] ?? $existing['width'] ?? 280),
            (float)($body['height'] ?? $existing['height'] ?? 180),
            (float)($body['rotation'] ?? $existing['rotation'] ?? 0),
            (int)($body['z_index'] ?? $existing['z_index'] ?? 0),
            array_key_exists('style_json', $body) ? (string)$body['style_json'] : ($existing['style_json'] ?? null),
            array_key_exists('meta_json', $body) ? (string)$body['meta_json'] : ($existing['meta_json'] ?? null),
            $item_id,
        ]);
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$item_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Failed to load item', 500);
        }
        return cg_desktop_normalize_workspace_item($row);
    }
}

if (!function_exists('cg_desktop_workspace_delete_item')) {
    function cg_desktop_workspace_delete_item(PDO $pdo, int $user_id, int $item_id): void
    {
        cg_desktop_workspace_trash_item($pdo, $user_id, $item_id);
    }
}

if (!function_exists('cg_desktop_workspace_trash_item')) {
    function cg_desktop_workspace_trash_item(PDO $pdo, int $user_id, int $item_id): void
    {
        $stmt = $pdo->prepare('SELECT board_id FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$item_id]);
        $board_id = (int)($stmt->fetchColumn() ?: 0);
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Workspace item not found', 404);
        }
        $pdo->prepare('
            UPDATE kanban_workspace_items
            SET is_trashed = 1, trashed_at = NOW()
            WHERE id = ? AND board_id = ?
        ')->execute([$item_id, $board_id]);
    }
}

if (!function_exists('cg_desktop_workspace_restore_item')) {
    function cg_desktop_workspace_restore_item(PDO $pdo, int $user_id, int $item_id): void
    {
        $stmt = $pdo->prepare('SELECT board_id FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$item_id]);
        $board_id = (int)($stmt->fetchColumn() ?: 0);
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Workspace item not found', 404);
        }
        $pdo->prepare('
            UPDATE kanban_workspace_items
            SET is_trashed = 0, trashed_at = NULL
            WHERE id = ? AND board_id = ?
        ')->execute([$item_id, $board_id]);
    }
}

if (!function_exists('cg_desktop_workspace_trash_bootstrap')) {
    function cg_desktop_workspace_trash_bootstrap(PDO $pdo, int $user_id, int $board_id): array
    {
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_items WHERE board_id = ? AND COALESCE(is_trashed, 0) = 1 ORDER BY trashed_at DESC, id DESC');
        $stmt->execute([$board_id]);
        $items = array_map('cg_desktop_normalize_workspace_item', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_strokes WHERE board_id = ? ORDER BY z_index ASC, id ASC');
        $stmt->execute([$board_id]);
        $strokes = array_map('cg_desktop_normalize_workspace_stroke', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $stmt = $pdo->prepare("
            SELECT c.*
            FROM kanban_workspace_connectors c
            JOIN kanban_workspace_items a ON a.id = c.from_item_id AND COALESCE(a.is_trashed, 0) = 1
            JOIN kanban_workspace_items b ON b.id = c.to_item_id AND COALESCE(b.is_trashed, 0) = 1
            WHERE c.board_id = ?
            ORDER BY c.z_index ASC, c.id ASC
        ");
        $stmt->execute([$board_id]);
        $connectors = array_map('cg_desktop_normalize_workspace_connector', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return [
            'board_id' => $board_id,
            'workspace' => compact('items', 'strokes', 'connectors'),
        ];
    }
}

if (!function_exists('cg_desktop_workspace_delete_item_forever')) {
    function cg_desktop_workspace_delete_item_forever(PDO $pdo, int $user_id, int $item_id): void
    {
        $stmt = $pdo->prepare('SELECT board_id FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$item_id]);
        $board_id = (int)($stmt->fetchColumn() ?: 0);
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Workspace item not found', 404);
        }
        $pdo->prepare('DELETE FROM kanban_workspace_strokes WHERE item_id = ?')->execute([$item_id]);
        $pdo->prepare('DELETE FROM kanban_workspace_connectors WHERE from_item_id = ? OR to_item_id = ?')->execute([$item_id, $item_id]);
        $pdo->prepare('DELETE FROM kanban_workspace_items WHERE id = ?')->execute([$item_id]);
    }
}

if (!function_exists('cg_desktop_workspace_empty_trash_forever')) {
    function cg_desktop_workspace_empty_trash_forever(PDO $pdo, int $user_id, int $board_id): int
    {
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $idStmt = $pdo->prepare('SELECT id FROM kanban_workspace_items WHERE board_id = ? AND COALESCE(is_trashed, 0) = 1');
        $idStmt->execute([$board_id]);
        $trashedIds = array_map('intval', $idStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($trashedIds as $item_id) {
            $pdo->prepare('DELETE FROM kanban_workspace_strokes WHERE item_id = ?')->execute([$item_id]);
            $pdo->prepare('DELETE FROM kanban_workspace_connectors WHERE from_item_id = ? OR to_item_id = ?')->execute([$item_id, $item_id]);
        }
        $pdo->prepare('DELETE FROM kanban_workspace_items WHERE board_id = ? AND COALESCE(is_trashed, 0) = 1')->execute([$board_id]);
        return count($trashedIds);
    }
}

if (!function_exists('cg_desktop_workspace_save_stroke')) {
    function cg_desktop_workspace_save_stroke(PDO $pdo, int $user_id, array $body): array
    {
        $board_id = (int)($body['board_id'] ?? 0);
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $stroke_id = (int)($body['stroke_id'] ?? 0);
        $item_id = isset($body['item_id']) && $body['item_id'] !== null && $body['item_id'] !== ''
            ? (int)$body['item_id'] : null;
        if ($stroke_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE kanban_workspace_strokes SET
                    item_id = ?, tool = ?, stroke_data = ?, bounds_json = ?, style_json = ?, z_index = ?
                WHERE id = ? AND board_id = ?
            ");
            $stmt->execute([
                $item_id,
                (string)($body['tool'] ?? 'brush'),
                (string)($body['stroke_data'] ?? ''),
                ($body['bounds_json'] ?? '') !== '' ? (string)$body['bounds_json'] : null,
                ($body['style_json'] ?? '') !== '' ? (string)$body['style_json'] : null,
                (int)($body['z_index'] ?? 0),
                $stroke_id,
                $board_id,
            ]);
            $final_id = $stroke_id;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO kanban_workspace_strokes
                (board_id, item_id, tool, stroke_data, bounds_json, style_json, z_index)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $board_id,
                $item_id,
                (string)($body['tool'] ?? 'brush'),
                (string)($body['stroke_data'] ?? ''),
                ($body['bounds_json'] ?? '') !== '' ? (string)$body['bounds_json'] : null,
                ($body['style_json'] ?? '') !== '' ? (string)$body['style_json'] : null,
                (int)($body['z_index'] ?? 0),
            ]);
            $final_id = (int)$pdo->lastInsertId();
        }
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_strokes WHERE id = ? LIMIT 1');
        $stmt->execute([$final_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Failed to load stroke', 500);
        }
        return cg_desktop_normalize_workspace_stroke($row);
    }
}

if (!function_exists('cg_desktop_workspace_delete_stroke')) {
    function cg_desktop_workspace_delete_stroke(PDO $pdo, int $user_id, int $stroke_id): void
    {
        if ($stroke_id <= 0) {
            throw new InvalidArgumentException('Missing stroke_id');
        }
        $stmt = $pdo->prepare('SELECT board_id FROM kanban_workspace_strokes WHERE id = ? LIMIT 1');
        $stmt->execute([$stroke_id]);
        $board_id = (int)($stmt->fetchColumn() ?: 0);
        if ($board_id <= 0) {
            throw new RuntimeException('Stroke not found', 404);
        }
        if (!user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $pdo->prepare('DELETE FROM kanban_workspace_strokes WHERE id = ?')->execute([$stroke_id]);
    }
}

if (!function_exists('cg_desktop_workspace_save_connector')) {
    function cg_desktop_workspace_save_connector(PDO $pdo, int $user_id, array $body): array
    {
        $board_id = (int)($body['board_id'] ?? 0);
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $connector_id = (int)($body['connector_id'] ?? 0);
        if ($connector_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE kanban_workspace_connectors SET
                    from_item_id = ?, to_item_id = ?, start_x = ?, start_y = ?, end_x = ?, end_y = ?,
                    label = ?, style_json = ?, z_index = ?
                WHERE id = ? AND board_id = ?
            ");
            $stmt->execute([
                isset($body['from_item_id']) ? (int)$body['from_item_id'] : null,
                isset($body['to_item_id']) ? (int)$body['to_item_id'] : null,
                $body['start_x'] ?? null,
                $body['start_y'] ?? null,
                $body['end_x'] ?? null,
                $body['end_y'] ?? null,
                (string)($body['label'] ?? ''),
                ($body['style_json'] ?? '') !== '' ? (string)$body['style_json'] : null,
                (int)($body['z_index'] ?? 0),
                $connector_id,
                $board_id,
            ]);
            $final_id = $connector_id;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO kanban_workspace_connectors
                (board_id, from_item_id, to_item_id, start_x, start_y, end_x, end_y, label, style_json, z_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $board_id,
                isset($body['from_item_id']) ? (int)$body['from_item_id'] : null,
                isset($body['to_item_id']) ? (int)$body['to_item_id'] : null,
                $body['start_x'] ?? null,
                $body['start_y'] ?? null,
                $body['end_x'] ?? null,
                $body['end_y'] ?? null,
                (string)($body['label'] ?? ''),
                ($body['style_json'] ?? '') !== '' ? (string)$body['style_json'] : null,
                (int)($body['z_index'] ?? 0),
            ]);
            $final_id = (int)$pdo->lastInsertId();
        }
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_connectors WHERE id = ? LIMIT 1');
        $stmt->execute([$final_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Failed to load connector', 500);
        }
        return cg_desktop_normalize_workspace_connector($row);
    }
}

if (!function_exists('cg_desktop_workspace_delete_connector')) {
    function cg_desktop_workspace_delete_connector(PDO $pdo, int $user_id, int $connector_id): void
    {
        if ($connector_id <= 0) {
            throw new InvalidArgumentException('Missing connector_id');
        }
        $stmt = $pdo->prepare('SELECT board_id FROM kanban_workspace_connectors WHERE id = ? LIMIT 1');
        $stmt->execute([$connector_id]);
        $board_id = (int)($stmt->fetchColumn() ?: 0);
        if ($board_id <= 0) {
            throw new RuntimeException('Connector not found', 404);
        }
        if (!user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $pdo->prepare('DELETE FROM kanban_workspace_connectors WHERE id = ?')->execute([$connector_id]);
    }
}

if (!function_exists('cg_desktop_workspace_upload_image')) {
    function cg_desktop_workspace_upload_image(PDO $pdo, int $user_id, int $board_id, string $image_base64, string $mime_type): string
    {
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime = strtolower(trim($mime_type));
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Unsupported image type');
        }
        $raw = base64_decode($image_base64, true);
        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('Invalid image data');
        }
        if (strlen($raw) > 12 * 1024 * 1024) {
            throw new InvalidArgumentException('Image too large');
        }
        global $CG_LEGACY_PUBLIC;
        $ext = $allowed[$mime];
        $legacyBase = isset($CG_LEGACY_PUBLIC) && is_string($CG_LEGACY_PUBLIC) && $CG_LEGACY_PUBLIC !== ''
            ? rtrim($CG_LEGACY_PUBLIC, '/\\')
            : '';
        $kanbanRoot = dirname(__DIR__);
        $candidates = [$kanbanRoot];
        if ($legacyBase !== '') {
            $candidates[] = $legacyBase;
        }
        $filename = 'ws_' . uniqid('', true) . '.' . $ext;
        foreach ($candidates as $base) {
            $upload_dir = $base . '/uploads/workspace_images';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }
            if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                continue;
            }
            $path = $upload_dir . '/' . $filename;
            if (@file_put_contents($path, $raw) !== false) {
                return 'uploads/workspace_images/' . $filename;
            }
        }
        throw new RuntimeException('Failed to save image', 500);
    }
}

if (!function_exists('cg_desktop_workspace_presence_actor_id')) {
    function cg_desktop_workspace_presence_actor_id(int $user_id, string $device_id): string
    {
        $hash = substr(hash('sha256', trim($device_id)), 0, 24);
        return 'u:' . max(0, $user_id) . ':' . $hash;
    }
}

if (!function_exists('cg_desktop_workspace_presence_display_name')) {
    function cg_desktop_workspace_presence_display_name(PDO $pdo, int $user_id): string
    {
        if ($user_id <= 0) {
            return 'Collaborator';
        }
        try {
            $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $name = trim((string)($stmt->fetchColumn() ?: ''));
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable $e) {
        }
        return 'Collaborator';
    }
}

if (!function_exists('cg_desktop_workspace_presence_ensure_table')) {
    function cg_desktop_workspace_presence_ensure_table(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS kanban_workspace_presence (
                    board_id INT NOT NULL,
                    actor_id VARCHAR(96) NOT NULL,
                    user_id INT NOT NULL DEFAULT 0,
                    display_name VARCHAR(120) NOT NULL DEFAULT '',
                    sx DECIMAL(6,5) NOT NULL DEFAULT 0.50000,
                    sy DECIMAL(6,5) NOT NULL DEFAULT 0.50000,
                    wx DOUBLE NULL DEFAULT NULL,
                    wy DOUBLE NULL DEFAULT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (board_id, actor_id),
                    KEY idx_workspace_presence_updated (board_id, updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            error_log('cg_desktop_workspace_presence_ensure_table: ' . $e->getMessage());
        }
        $done = true;
    }
}

if (!function_exists('cg_desktop_workspace_presence_list_active')) {
    function cg_desktop_workspace_presence_list_active(PDO $pdo, int $board_id, int $activeWithinSeconds = 12): array
    {
        cg_desktop_workspace_presence_ensure_table($pdo);
        $activeWithinSeconds = max(3, min(120, $activeWithinSeconds));
        try {
            $stmt = $pdo->prepare("
                SELECT actor_id, user_id, display_name, sx, sy, wx, wy, updated_at
                FROM kanban_workspace_presence
                WHERE board_id = ?
                  AND updated_at >= (NOW() - INTERVAL {$activeWithinSeconds} SECOND)
                ORDER BY updated_at DESC
                LIMIT 64
            ");
            $stmt->execute([$board_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('cg_desktop_workspace_presence_list_active: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('cg_desktop_workspace_presence_payload')) {
    function cg_desktop_workspace_presence_payload(PDO $pdo, int $board_id, int $user_id, string $device_id): array
    {
        return [
            'workspace_presence_self_actor' => cg_desktop_workspace_presence_actor_id($user_id, $device_id),
            'workspace_presence' => cg_desktop_workspace_presence_list_active($pdo, $board_id),
        ];
    }
}

if (!function_exists('cg_desktop_workspace_presence_ping')) {
    function cg_desktop_workspace_presence_ping(PDO $pdo, int $user_id, int $board_id, string $device_id, array $body): array
    {
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }
        cg_desktop_workspace_presence_ensure_table($pdo);
        $sx = max(0.0, min(1.0, (float)($body['sx'] ?? 0.5)));
        $sy = max(0.0, min(1.0, (float)($body['sy'] ?? 0.5)));
        $wx = isset($body['wx']) ? (float)$body['wx'] : null;
        $wy = isset($body['wy']) ? (float)$body['wy'] : null;
        if ($wx !== null && !is_finite($wx)) {
            $wx = null;
        }
        if ($wy !== null && !is_finite($wy)) {
            $wy = null;
        }
        $actorId = cg_desktop_workspace_presence_actor_id($user_id, $device_id);
        $displayName = cg_desktop_workspace_presence_display_name($pdo, $user_id);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO kanban_workspace_presence (board_id, actor_id, user_id, display_name, sx, sy, wx, wy, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    display_name = VALUES(display_name),
                    sx = VALUES(sx),
                    sy = VALUES(sy),
                    wx = VALUES(wx),
                    wy = VALUES(wy),
                    updated_at = NOW()
            ");
            $stmt->execute([$board_id, $actorId, $user_id, $displayName, $sx, $sy, $wx, $wy]);
            $pdo->prepare('DELETE FROM kanban_workspace_presence WHERE board_id = ? AND updated_at < (NOW() - INTERVAL 2 MINUTE)')
                ->execute([$board_id]);
        } catch (Throwable $e) {
            error_log('cg_desktop_workspace_presence_ping: ' . $e->getMessage());
        }
        return array_merge(
            ['board_id' => $board_id],
            cg_desktop_workspace_presence_payload($pdo, $board_id, $user_id, $device_id),
        );
    }
}
