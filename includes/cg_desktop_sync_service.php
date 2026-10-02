<?php
/**
 * Desktop sync pull/push helpers (boards, columns, cards).
 */

if (!function_exists('cg_desktop_pull_boards')) {
  /**
   * @param int[] $board_ids Empty = no board payloads (list only via separate call).
   * @return array{boards:array,columns:array,cards:array,cursors:array}
   */
  function cg_desktop_pull_boards(PDO $pdo, int $user_id, array $board_ids, array $cursors = []): array
  {
    $board_ids = array_values(array_unique(array_filter(array_map('intval', $board_ids), static fn($id) => $id > 0)));
    $columns = [];
    $cards = [];
    $outCursors = [];

    foreach ($board_ids as $board_id) {
      if (!user_can_access_board($pdo, $board_id, $user_id)) {
        continue;
      }

      $since = isset($cursors[(string)$board_id]) ? trim((string)$cursors[(string)$board_id]) : '';
      $boardUpdated = cg_desktop_board_updated_at($pdo, $board_id);

      $stmt = $pdo->prepare('SELECT id, name, position, COALESCE(is_hidden, 0) AS is_hidden, updated_at FROM kanban_columns WHERE board_id = ? ORDER BY position ASC, id ASC');
      $stmt->execute([$board_id]);
      $boardColumns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

      $colIds = array_map(static fn($c) => (int)$c['id'], $boardColumns);
      $boardCards = [];
      if ($colIds) {
        $in = implode(',', array_fill(0, count($colIds), '?'));
        $sql = "SELECT id, column_id, title, description, start_date, start_time, due_date, due_time, progress, priority,
                       is_pinned, COALESCE(is_hidden, 0) AS is_hidden, card_color, card_type, link_url, links, attachments, position, updated_at
                FROM kanban_cards
                WHERE column_id IN ($in) AND COALESCE(is_trashed, 0) = 0";
        $params = $colIds;
        if ($since !== '') {
          $sql .= ' AND updated_at > ?';
          $params[] = $since;
        }
        $sql .= ' ORDER BY is_pinned DESC, position ASC, id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $boardCards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }

      if ($since === '' || cg_desktop_any_newer_than($boardColumns, $since) || $boardCards !== []) {
        foreach ($boardColumns as $col) {
          $columns[] = array_merge($col, ['board_id' => $board_id]);
        }
        foreach ($boardCards as $card) {
          $cards[] = $card;
        }
      }

      $outCursors[(string)$board_id] = $boardUpdated;
    }

    return [
      'columns' => $columns,
      'cards' => $cards,
      'cursors' => $outCursors,
    ];
  }
}

if (!function_exists('cg_desktop_board_updated_at')) {
  function cg_desktop_board_updated_at(PDO $pdo, int $board_id): string
  {
    $stmt = $pdo->prepare('SELECT updated_at FROM kanban_boards WHERE id = ? LIMIT 1');
    $stmt->execute([$board_id]);
    $v = $stmt->fetchColumn();
    return $v ? (string)$v : gmdate('Y-m-d H:i:s');
  }
}

if (!function_exists('cg_desktop_any_newer_than')) {
  function cg_desktop_any_newer_than(array $rows, string $since): bool
  {
    foreach ($rows as $row) {
      $u = (string)($row['updated_at'] ?? '');
      if ($u !== '' && $u > $since) {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('cg_desktop_apply_push_mutations')) {
  /**
   * @param array<int,array<string,mixed>> $mutations
   * @return array{id_map:array<string,int>,applied:int,errors:array}
   */
  function cg_desktop_apply_push_mutations(PDO $pdo, int $user_id, array $mutations): array
  {
    $id_map = [];
    $applied = 0;
    $errors = [];

    foreach ($mutations as $i => $mutation) {
      $op = trim((string)($mutation['op'] ?? ''));
      $payload = is_array($mutation['payload'] ?? null) ? $mutation['payload'] : [];
      $local_id = trim((string)($mutation['local_id'] ?? $payload['local_id'] ?? ''));

      try {
        switch ($op) {
          case 'update_card_title':
            $card_id = (int)($payload['card_id'] ?? 0);
            $title = trim((string)($payload['title'] ?? ''));
            if ($card_id <= 0 || $title === '') {
              throw new InvalidArgumentException('Invalid update_card_title');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            $pdo->prepare('UPDATE kanban_cards SET title = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$title, $card_id]);
            $applied++;
            break;

          case 'create_card':
            $column_id = (int)($payload['column_id'] ?? 0);
            $title = trim((string)($payload['title'] ?? ''));
            if ($column_id <= 0 || $title === '') {
              throw new InvalidArgumentException('Invalid create_card');
            }
            if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
              throw new RuntimeException('Column not found', 404);
            }
            $pos = cg_desktop_next_card_position($pdo, $column_id);
            $pdo->prepare('INSERT INTO kanban_cards (column_id, title, position) VALUES (?, ?, ?)')
              ->execute([$column_id, $title, $pos]);
            $newId = (int)$pdo->lastInsertId();
            if ($local_id !== '') {
              $id_map[$local_id] = $newId;
            }
            $applied++;
            break;

          case 'move_cards':
            $moves = is_array($payload['moves'] ?? null) ? $payload['moves'] : [];
            foreach ($moves as $move) {
              $card_id = (int)($move['card_id'] ?? 0);
              $column_id = (int)($move['column_id'] ?? ($move['to_column_id'] ?? 0));
              $position = (int)($move['position'] ?? 0);
              if ($card_id <= 0 || $column_id <= 0) {
                continue;
              }
              if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
                continue;
              }
              if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
                continue;
              }
              $pdo->prepare('UPDATE kanban_cards SET column_id = ?, position = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$column_id, $position, $card_id]);
            }
            $applied++;
            break;

          case 'move_columns':
            $moves = is_array($payload['moves'] ?? null) ? $payload['moves'] : [];
            $board_id = 0;
            foreach ($moves as $move) {
              $column_id = (int)($move['column_id'] ?? 0);
              $position = (int)($move['position'] ?? 0);
              if ($column_id <= 0) {
                continue;
              }
              if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
                continue;
              }
              $stmt = $pdo->prepare('SELECT board_id FROM kanban_columns WHERE id = ? LIMIT 1');
              $stmt->execute([$column_id]);
              $current_board_id = (int)$stmt->fetchColumn();
              if ($current_board_id <= 0) {
                continue;
              }
              if ($board_id === 0) {
                $board_id = $current_board_id;
              } elseif ($board_id !== $current_board_id) {
                throw new RuntimeException('Columns must belong to the same board');
              }
              $pdo->prepare('UPDATE kanban_columns SET position = ? WHERE id = ?')
                ->execute([$position, $column_id]);
            }
            $applied++;
            break;

          case 'update_card':
            $card_id = (int)($payload['card_id'] ?? 0);
            $title = trim((string)($payload['title'] ?? ''));
            if ($card_id <= 0 || $title === '') {
              throw new InvalidArgumentException('Invalid update_card');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            $description = trim((string)($payload['description'] ?? ''));
            if (!function_exists('kanban_sanitize_card_description_html')) {
              require_once __DIR__ . '/kanban_card_description.php';
            }
            $description = kanban_sanitize_card_description_html($description);
            $progress = (int)($payload['progress'] ?? 0);
            $priority = trim((string)($payload['priority'] ?? ''));
            $start_date = trim((string)($payload['start_date'] ?? ''));
            $start_time = cg_desktop_normalize_card_time($payload['start_time'] ?? null);
            $due_date = trim((string)($payload['due_date'] ?? ''));
            $due_time = cg_desktop_normalize_card_time($payload['due_time'] ?? null);
            if ($start_date === '') {
              $start_time = null;
            }
            if ($due_date === '') {
              $due_time = null;
            }
            $links_arr = [];
            if (isset($payload['links']) && is_array($payload['links'])) {
              foreach ($payload['links'] as $url) {
                if (is_string($url) && trim($url) !== '') {
                  $links_arr[] = trim($url);
                }
              }
            }
            $links_json = $links_arr === [] ? null : json_encode($links_arr, JSON_UNESCAPED_UNICODE);
            $attachments_arr = [];
            if (isset($payload['attachments']) && is_array($payload['attachments'])) {
              foreach ($payload['attachments'] as $item) {
                if (is_array($item) && !empty($item['path'])) {
                  $attachments_arr[] = $item;
                } elseif (is_string($item) && trim($item) !== '') {
                  $attachments_arr[] = ['path' => trim($item), 'name' => basename($item)];
                }
              }
            }
            $new_attachments = [];
            if (isset($payload['new_attachments']) && is_array($payload['new_attachments'])) {
              global $CG_KANBAN_ROOT;
              $upload_dir = rtrim((string)($CG_KANBAN_ROOT ?? dirname(__DIR__)), '/') . '/uploads/kanban_attachments';
              if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
              }
              $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
              foreach ($payload['new_attachments'] as $item) {
                if (!is_array($item)) {
                  continue;
                }
                $mime = trim((string)($item['mime'] ?? 'image/jpeg'));
                if (!in_array($mime, $allowed, true)) {
                  continue;
                }
                $b64 = (string)($item['data_base64'] ?? '');
                if ($b64 === '') {
                  continue;
                }
                $bin = base64_decode($b64, true);
                if ($bin === false || $bin === '') {
                  continue;
                }
                $name = trim((string)($item['name'] ?? 'image.jpg'));
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'jpg';
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                  $ext = 'jpg';
                }
                $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name, '.' . $ext));
                $filename = 'card_' . $card_id . '_' . uniqid('', true) . '_' . substr((string)$safe_name, 0, 32) . '.' . $ext;
                $path = $upload_dir . '/' . $filename;
                if (@file_put_contents($path, $bin) !== false) {
                  $new_attachments[] = [
                    'path' => 'uploads/kanban_attachments/' . $filename,
                    'name' => $name,
                  ];
                }
              }
            }
            if ($new_attachments !== []) {
              $attachments_arr = array_merge($attachments_arr, $new_attachments);
            }
            $attachments_json = $attachments_arr === [] ? null : json_encode($attachments_arr, JSON_UNESCAPED_UNICODE);
            $pdo->prepare('UPDATE kanban_cards SET title = ?, description = ?, progress = ?, priority = ?, start_date = ?, start_time = ?, due_date = ?, due_time = ?, links = ?, attachments = ?, updated_at = NOW() WHERE id = ?')
              ->execute([
                $title,
                $description !== '' ? $description : null,
                max(0, min(100, $progress)),
                $priority !== '' ? $priority : null,
                $start_date !== '' ? $start_date : null,
                $start_time,
                $due_date !== '' ? $due_date : null,
                $due_time,
                $links_json,
                $attachments_json,
                $card_id,
              ]);
            $applied++;
            break;

          case 'update_link_card':
            $card_id = (int)($payload['card_id'] ?? 0);
            $title = trim((string)($payload['title'] ?? ''));
            if ($card_id <= 0 || $title === '') {
              throw new InvalidArgumentException('Invalid update_link_card');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            cg_desktop_ensure_card_type_schema($pdo);
            $link_url = cg_desktop_normalize_card_link_url($payload['link_url'] ?? null);
            if ($link_url === null) {
              throw new InvalidArgumentException('A valid link URL is required');
            }
            $pdo->prepare("UPDATE kanban_cards SET title = ?, link_url = ?, card_type = 'link', updated_at = NOW() WHERE id = ?")
              ->execute([$title, $link_url, $card_id]);
            $applied++;
            break;

          case 'delete_card':
            $card_id = (int)($payload['card_id'] ?? 0);
            if ($card_id <= 0) {
              throw new InvalidArgumentException('Invalid delete_card');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            if (function_exists('ensure_kanban_card_trash_schema')) {
              ensure_kanban_card_trash_schema($pdo);
            }
            cg_desktop_ensure_card_trash_schema($pdo);
            $pdo->prepare('
              UPDATE kanban_cards
              SET is_trashed = 1,
                  trashed_at = NOW(),
                  trashed_from_column_id = COALESCE(trashed_from_column_id, column_id),
                  trashed_position = COALESCE(trashed_position, position)
              WHERE id = ?
            ')->execute([$card_id]);
            $applied++;
            break;

          case 'set_card_pin':
            $card_id = (int)($payload['card_id'] ?? 0);
            $is_pinned = (int)($payload['is_pinned'] ?? 0) ? 1 : 0;
            if ($card_id <= 0) {
              throw new InvalidArgumentException('Invalid set_card_pin');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            $pdo->prepare('UPDATE kanban_cards SET is_pinned = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$is_pinned, $card_id]);
            $applied++;
            break;

          case 'set_card_hidden':
            $card_id = (int)($payload['card_id'] ?? 0);
            $is_hidden = !empty($payload['is_hidden']) ? 1 : 0;
            if ($card_id <= 0) {
              throw new InvalidArgumentException('Invalid set_card_hidden');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            $pdo->prepare('UPDATE kanban_cards SET is_hidden = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$is_hidden, $card_id]);
            if (!$is_hidden && function_exists('ensure_kanban_card_hidden_viewers_schema')) {
              try {
                ensure_kanban_card_hidden_viewers_schema($pdo);
                $pdo->prepare('DELETE FROM kanban_card_hidden_viewers WHERE card_id = ?')->execute([$card_id]);
              } catch (Throwable $e) {
                error_log('desktop set_card_hidden viewers: ' . $e->getMessage());
              }
            }
            $applied++;
            break;

          case 'set_card_color':
            $card_id = (int)($payload['card_id'] ?? 0);
            if ($card_id <= 0) {
              throw new InvalidArgumentException('Invalid set_card_color');
            }
            if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
              throw new RuntimeException('Card not found', 404);
            }
            if (function_exists('ensure_kanban_card_color_schema')) {
              ensure_kanban_card_color_schema($pdo);
            }
            $raw_color = trim((string)($payload['card_color'] ?? ''));
            if (function_exists('kanban_normalize_card_color')) {
              $card_color = kanban_normalize_card_color($raw_color);
            } else {
              $card_color = $raw_color;
            }
            $allowed = ['rose', 'peach', 'mint', 'sky', 'lavender', 'butter', ''];
            if (!in_array($card_color, $allowed, true) && !preg_match('/^#[0-9a-f]{6}$/', $card_color)) {
              throw new InvalidArgumentException('Invalid card color');
            }
            $value = $card_color === '' ? null : $card_color;
            $pdo->prepare('UPDATE kanban_cards SET card_color = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$value, $card_id]);
            $applied++;
            break;

          case 'update_board':
            $board_id = (int)($payload['board_id'] ?? 0);
            $name = trim((string)($payload['name'] ?? ''));
            if ($board_id <= 0 || $name === '') {
              throw new InvalidArgumentException('Invalid update_board');
            }
            if (!cg_desktop_user_owns_board($pdo, $board_id, $user_id)) {
              throw new RuntimeException('Board not found', 404);
            }
            $pdo->prepare('UPDATE kanban_boards SET name = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$name, $board_id]);
            $applied++;
            break;

          case 'create_column':
            $board_id = (int)($payload['board_id'] ?? 0);
            $name = trim((string)($payload['name'] ?? 'New Column'));
            if ($board_id <= 0 || $name === '') {
              throw new InvalidArgumentException('Invalid create_column');
            }
            if (!user_can_access_board($pdo, $board_id, $user_id)) {
              throw new RuntimeException('Board not found', 404);
            }
            $pos = cg_desktop_next_column_position($pdo, $board_id);
            $pdo->prepare('INSERT INTO kanban_columns (board_id, name, position) VALUES (?, ?, ?)')
              ->execute([$board_id, $name, $pos]);
            $newId = (int)$pdo->lastInsertId();
            if ($local_id !== '') {
              $id_map[$local_id] = $newId;
            }
            $applied++;
            break;

          case 'update_column':
            $column_id = (int)($payload['column_id'] ?? 0);
            $name = trim((string)($payload['name'] ?? ''));
            if ($column_id <= 0 || $name === '') {
              throw new InvalidArgumentException('Invalid update_column');
            }
            if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
              throw new RuntimeException('Column not found', 404);
            }
            $pdo->prepare('UPDATE kanban_columns SET name = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$name, $column_id]);
            $applied++;
            break;

          case 'set_column_hidden':
            $column_id = (int)($payload['column_id'] ?? 0);
            $is_hidden = !empty($payload['is_hidden']) ? 1 : 0;
            if ($column_id <= 0) {
              throw new InvalidArgumentException('Invalid set_column_hidden');
            }
            if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
              throw new RuntimeException('Column not found', 404);
            }
            $pdo->prepare('UPDATE kanban_columns SET is_hidden = ?, updated_at = NOW() WHERE id = ?')
              ->execute([$is_hidden, $column_id]);
            $applied++;
            break;

          case 'delete_column':
            $column_id = (int)($payload['column_id'] ?? 0);
            if ($column_id <= 0) {
              throw new InvalidArgumentException('Invalid delete_column');
            }
            if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
              throw new RuntimeException('Column not found', 404);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM kanban_cards WHERE column_id = ? AND COALESCE(is_trashed, 0) = 0');
            $stmt->execute([$column_id]);
            if ((int)$stmt->fetchColumn() > 0) {
              throw new RuntimeException('HAS_CARDS');
            }
            try {
              if (function_exists('ensure_kanban_column_hidden_viewers_schema')) {
                ensure_kanban_column_hidden_viewers_schema($pdo);
                $pdo->prepare('DELETE FROM kanban_column_hidden_viewers WHERE column_id = ?')->execute([$column_id]);
              }
            } catch (Throwable $e) {
              error_log('desktop delete_column hidden viewers: ' . $e->getMessage());
            }
            $pdo->prepare('DELETE FROM kanban_columns WHERE id = ?')->execute([$column_id]);
            $applied++;
            break;

          case 'delete_column_force':
            $column_id = (int)($payload['column_id'] ?? 0);
            if ($column_id <= 0) {
              throw new InvalidArgumentException('Invalid delete_column_force');
            }
            if (!cg_desktop_user_owns_column($pdo, $column_id, $user_id)) {
              throw new RuntimeException('Column not found', 404);
            }
            $stmt = $pdo->prepare('SELECT id FROM kanban_cards WHERE column_id = ?');
            $stmt->execute([$column_id]);
            $cardIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($cardIds) {
              $ph = implode(',', array_fill(0, count($cardIds), '?'));
              $pdo->prepare("DELETE FROM kanban_cards WHERE id IN ($ph)")->execute($cardIds);
            }
            try {
              if (function_exists('ensure_kanban_column_hidden_viewers_schema')) {
                ensure_kanban_column_hidden_viewers_schema($pdo);
                $pdo->prepare('DELETE FROM kanban_column_hidden_viewers WHERE column_id = ?')->execute([$column_id]);
              }
            } catch (Throwable $e) {
              error_log('desktop delete_column_force hidden viewers: ' . $e->getMessage());
            }
            $pdo->prepare('DELETE FROM kanban_columns WHERE id = ?')->execute([$column_id]);
            $applied++;
            break;

          case 'restore_card':
            $card_id = (int)($payload['card_id'] ?? 0);
            if ($card_id <= 0) {
              throw new InvalidArgumentException('Invalid restore_card');
            }
            cg_desktop_restore_card($pdo, $user_id, $card_id);
            $applied++;
            break;

          case 'delete_card_forever':
            $card_id = (int)($payload['card_id'] ?? 0);
            if ($card_id <= 0) {
              throw new InvalidArgumentException('Invalid delete_card_forever');
            }
            cg_desktop_delete_card_forever($pdo, $user_id, $card_id);
            $applied++;
            break;

          case 'empty_card_trash_forever':
            $board_id = (int)($payload['board_id'] ?? 0);
            if ($board_id <= 0) {
              throw new InvalidArgumentException('Invalid empty_card_trash_forever');
            }
            cg_desktop_empty_card_trash_forever($pdo, $user_id, $board_id);
            $applied++;
            break;

          default:
            $errors[] = ['index' => $i, 'op' => $op, 'message' => 'Unknown op'];
        }
      } catch (Throwable $e) {
        $errors[] = ['index' => $i, 'op' => $op, 'message' => $e->getMessage()];
      }
    }

    return ['id_map' => $id_map, 'applied' => $applied, 'errors' => $errors];
  }
}

if (!function_exists('cg_desktop_ensure_card_trash_schema')) {
  function cg_desktop_ensure_card_trash_schema(PDO $pdo): void
  {
    if (function_exists('ensure_kanban_card_trash_schema')) {
      ensure_kanban_card_trash_schema($pdo);
      return;
    }
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    try {
      $cols = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'is_trashed'")->fetchAll();
      if (!$cols) {
        $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN is_trashed TINYINT(1) NOT NULL DEFAULT 0");
      }
    } catch (Throwable $e) {
      error_log('cg_desktop_ensure_card_trash_schema: ' . $e->getMessage());
    }
  }
}

if (!function_exists('cg_desktop_list_trashed_cards')) {
  /**
   * @return array<int,array<string,mixed>>
   */
  function cg_desktop_list_trashed_cards(PDO $pdo, int $user_id, int $board_id): array
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    cg_desktop_ensure_card_trash_schema($pdo);
    $stmt = $pdo->prepare("
      SELECT
        k.id, k.column_id, k.title, k.description,
        k.start_date, k.due_date, k.progress, k.priority,
        k.trashed_at, k.trashed_from_column_id, k.trashed_position, k.updated_at
      FROM kanban_cards k
      JOIN kanban_columns c ON c.id = k.column_id
      WHERE c.board_id = ? AND COALESCE(k.is_trashed, 0) = 1
      ORDER BY k.trashed_at DESC, k.id DESC
    ");
    $stmt->execute([$board_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}

if (!function_exists('cg_desktop_restore_card')) {
  function cg_desktop_restore_card(PDO $pdo, int $user_id, int $card_id): void
  {
    if ($card_id <= 0 || !cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
      throw new RuntimeException('Card not found', 404);
    }
    cg_desktop_ensure_card_trash_schema($pdo);
    $stmt = $pdo->prepare("
      SELECT k.column_id, k.trashed_from_column_id, k.trashed_position
      FROM kanban_cards k
      WHERE k.id = ?
      LIMIT 1
    ");
    $stmt->execute([$card_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      throw new RuntimeException('Card not found', 404);
    }
    $targetColumnId = (int)($row['trashed_from_column_id'] ?? 0);
    if ($targetColumnId <= 0) {
      $targetColumnId = (int)($row['column_id'] ?? 0);
    }
    if ($targetColumnId <= 0 || !cg_desktop_user_owns_column($pdo, $targetColumnId, $user_id)) {
      throw new RuntimeException('Column not found', 404);
    }
    $targetPosition = $row['trashed_position'] ?? null;
    if ($targetPosition === null || $targetPosition === '' || !is_numeric($targetPosition)) {
      $stmtPos = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM kanban_cards WHERE column_id = ? AND COALESCE(is_trashed, 0) = 0');
      $stmtPos->execute([$targetColumnId]);
      $targetPosition = (int)$stmtPos->fetchColumn();
    } else {
      $targetPosition = (int)$targetPosition;
    }
    $pdo->prepare("
      UPDATE kanban_cards
      SET column_id = ?, position = ?, is_trashed = 0,
          trashed_at = NULL, trashed_from_column_id = NULL, trashed_position = NULL,
          updated_at = NOW()
      WHERE id = ?
    ")->execute([$targetColumnId, $targetPosition, $card_id]);
  }
}

if (!function_exists('cg_desktop_delete_card_forever')) {
  function cg_desktop_delete_card_forever(PDO $pdo, int $user_id, int $card_id): void
  {
    if ($card_id <= 0 || !cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
      throw new RuntimeException('Card not found', 404);
    }
    cg_desktop_ensure_card_trash_schema($pdo);
    $stmt = $pdo->prepare('SELECT COALESCE(is_trashed, 0) FROM kanban_cards WHERE id = ? LIMIT 1');
    $stmt->execute([$card_id]);
    if ((int)$stmt->fetchColumn() !== 1) {
      throw new RuntimeException('Card is not in Trash', 400);
    }
    if (function_exists('permanently_delete_card_and_relations')) {
      permanently_delete_card_and_relations($pdo, $card_id);
      return;
    }
    $pdo->prepare('DELETE FROM kanban_cards WHERE id = ?')->execute([$card_id]);
  }
}

if (!function_exists('cg_desktop_empty_card_trash_forever')) {
  function cg_desktop_empty_card_trash_forever(PDO $pdo, int $user_id, int $board_id): void
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    cg_desktop_ensure_card_trash_schema($pdo);
    $stmt = $pdo->prepare("
      SELECT k.id
      FROM kanban_cards k
      JOIN kanban_columns c ON c.id = k.column_id
      WHERE c.board_id = ? AND COALESCE(k.is_trashed, 0) = 1
    ");
    $stmt->execute([$board_id]);
    $trashedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if (!count($trashedIds)) {
      return;
    }
    if (function_exists('ensure_kanban_card_assignments_schema')) {
      ensure_kanban_card_assignments_schema($pdo);
    }
    if (function_exists('ensure_kanban_card_comments_schema')) {
      ensure_kanban_card_comments_schema($pdo);
    }
    $pdo->beginTransaction();
    try {
      foreach ($trashedIds as $cid) {
        if (function_exists('permanently_delete_card_and_relations')) {
          permanently_delete_card_and_relations($pdo, (int)$cid);
        } else {
          $pdo->prepare('DELETE FROM kanban_cards WHERE id = ?')->execute([(int)$cid]);
        }
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }
}

if (!function_exists('cg_desktop_user_owns_column')) {
  function cg_desktop_user_owns_column(PDO $pdo, int $column_id, int $user_id): bool
  {
    $stmt = $pdo->prepare('SELECT board_id FROM kanban_columns WHERE id = ? LIMIT 1');
    $stmt->execute([$column_id]);
    $board_id = (int)$stmt->fetchColumn();
    return $board_id > 0 && user_can_access_board($pdo, $board_id, $user_id);
  }
}

if (!function_exists('cg_desktop_user_owns_card')) {
  function cg_desktop_user_owns_card(PDO $pdo, int $card_id, int $user_id): bool
  {
    $stmt = $pdo->prepare('
      SELECT c.board_id FROM kanban_cards k
      JOIN kanban_columns c ON c.id = k.column_id
      WHERE k.id = ? LIMIT 1
    ');
    $stmt->execute([$card_id]);
    $board_id = (int)$stmt->fetchColumn();
    return $board_id > 0 && user_can_access_board($pdo, $board_id, $user_id);
  }
}

if (!function_exists('cg_desktop_next_card_position')) {
  function cg_desktop_next_card_position(PDO $pdo, int $column_id): int
  {
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM kanban_cards WHERE column_id = ?');
    $stmt->execute([$column_id]);
    return (int)$stmt->fetchColumn();
  }
}

if (!function_exists('cg_desktop_next_column_position')) {
  function cg_desktop_next_column_position(PDO $pdo, int $board_id): int
  {
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM kanban_columns WHERE board_id = ?');
    $stmt->execute([$board_id]);
    return (int)$stmt->fetchColumn();
  }
}

if (!function_exists('cg_desktop_user_owns_board')) {
  function cg_desktop_user_owns_board(PDO $pdo, int $board_id, int $user_id): bool
  {
    if (function_exists('board_belongs_to_user')) {
      return board_belongs_to_user($pdo, $board_id, $user_id);
    }
    return user_can_access_board($pdo, $board_id, $user_id);
  }
}

if (!function_exists('cg_desktop_list_activities')) {
  /**
   * @return array<int,array<string,mixed>>
   */
  function cg_desktop_list_activities(PDO $pdo, int $user_id, int $board_id, int $limit = 50): array
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    $limit = min(150, max(10, $limit));
    $stmt = $pdo->prepare("
      SELECT a.id, a.user_id, a.user_name, a.action_type, a.payload, a.created_at, u.profile_pic AS user_profile_pic
      FROM kanban_board_activities a
      LEFT JOIN users u ON u.id = a.user_id
      WHERE a.board_id = ?
      ORDER BY a.created_at DESC
      LIMIT ?
    ");
    $stmt->execute([$board_id, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
      if (isset($r['payload']) && $r['payload'] !== null && is_string($r['payload'])) {
        $r['payload'] = json_decode($r['payload'], true);
      }
    }
    unset($r);

    $deduped = [];
    $seen = [];
    $window_sec = 300;
    foreach ($rows as $r) {
      $ts = isset($r['created_at']) ? strtotime((string)$r['created_at']) : 0;
      $p = is_array($r['payload'] ?? null) ? $r['payload'] : [];
      $uid = (string)($r['user_id'] ?? '');
      $card = trim((string)($p['card_title'] ?? ''));
      $cardIdKey = isset($p['card_id']) ? (string)(int)$p['card_id'] : '';
      $progress = isset($p['progress']) ? (int)$p['progress'] : -1;
      $from = trim((string)($p['from_column'] ?? ''));
      $to = trim((string)($p['to_column'] ?? ''));
      $email = trim((string)($p['email'] ?? ''));
      $comment = isset($p['comment']) ? substr(trim((string)$p['comment']), 0, 80) : '';
      $key = ($r['action_type'] ?? '') . "\0" . $uid . "\0" . $cardIdKey . "\0" . $card . "\0" . $progress . "\0" . $from . "\0" . $to . "\0" . $email . "\0" . $comment;
      if (array_key_exists($key, $seen)) {
        if (abs($seen[$key] - $ts) <= $window_sec) {
          continue;
        }
      }
      $seen[$key] = $ts;
      $deduped[] = $r;
    }
    return $deduped;
  }
}

if (!function_exists('cg_desktop_ensure_card_comments_schema')) {
  function cg_desktop_ensure_card_comments_schema(PDO $pdo): void
  {
    if (function_exists('ensure_kanban_card_comments_schema')) {
      ensure_kanban_card_comments_schema($pdo);
      return;
    }
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    try {
      $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_card_comments (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          card_id BIGINT UNSIGNED NOT NULL,
          user_id BIGINT UNSIGNED NULL DEFAULT NULL,
          author_name VARCHAR(160) NOT NULL DEFAULT '',
          body TEXT NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_kcc_card_created (card_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ");
    } catch (Throwable $e) {
      error_log('cg_desktop_ensure_card_comments_schema: ' . $e->getMessage());
    }
  }
}

if (!function_exists('cg_desktop_list_card_comments')) {
  /**
   * @return array<int,array<string,mixed>>
   */
  function cg_desktop_list_card_comments(PDO $pdo, int $user_id, int $card_id): array
  {
    if ($card_id <= 0 || !cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
      return [];
    }
    cg_desktop_ensure_card_comments_schema($pdo);
    $stmt = $pdo->prepare("
      SELECT c.id, c.card_id, c.user_id, c.author_name, c.body, c.created_at, u.profile_pic
      FROM kanban_card_comments c
      LEFT JOIN users u ON u.id = c.user_id
      WHERE c.card_id = ?
      ORDER BY c.created_at ASC, c.id ASC
      LIMIT 500
    ");
    $stmt->execute([$card_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}

if (!function_exists('cg_desktop_add_card_comment')) {
  /**
   * @return array<string,mixed>
   */
  function cg_desktop_add_card_comment(PDO $pdo, int $user_id, int $card_id, string $body): array
  {
    if ($card_id <= 0) {
      throw new InvalidArgumentException('Missing card_id');
    }
    if (!cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
      throw new RuntimeException('Card not found', 404);
    }
    $body = strip_tags(trim($body));
    if ($body === '') {
      throw new InvalidArgumentException('Comment cannot be empty');
    }
    if (mb_strlen($body) > 8000) {
      throw new InvalidArgumentException('Comment is too long (max 8000 characters)');
    }

    cg_desktop_ensure_card_comments_schema($pdo);

    $stmtRt = $pdo->prepare('SELECT created_at FROM kanban_card_comments WHERE card_id = ? ORDER BY id DESC LIMIT 1');
    $stmtRt->execute([$card_id]);
    $lastAt = $stmtRt->fetchColumn();
    if ($lastAt && (time() - strtotime((string)$lastAt)) < 2) {
      throw new RuntimeException('Please wait a moment before posting another comment.', 429);
    }

    $author_name = '';
    if ($user_id > 0) {
      $stmtU = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
      $stmtU->execute([$user_id]);
      $author_name = trim((string)($stmtU->fetchColumn())) ?: 'Someone';
    }
    if ($author_name === '') {
      $author_name = 'Someone';
    }

    $stmt = $pdo->prepare('INSERT INTO kanban_card_comments (card_id, user_id, author_name, body) VALUES (?, ?, ?, ?)');
    $stmt->execute([$card_id, $user_id > 0 ? $user_id : null, $author_name, $body]);
    $newId = (int)$pdo->lastInsertId();

    $profile_pic = null;
    if ($user_id > 0) {
      $stmtP = $pdo->prepare('SELECT profile_pic FROM users WHERE id = ? LIMIT 1');
      $stmtP->execute([$user_id]);
      $profile_pic = $stmtP->fetchColumn() ?: null;
    }

    return [
      'id' => $newId,
      'card_id' => $card_id,
      'user_id' => $user_id > 0 ? $user_id : null,
      'author_name' => $author_name,
      'body' => $body,
      'created_at' => gmdate('Y-m-d H:i:s'),
      'profile_pic' => $profile_pic,
    ];
  }
}

if (!function_exists('cg_desktop_list_card_activities')) {
  /**
   * @return array<int,array<string,mixed>>
   */
  function cg_desktop_list_card_activities(PDO $pdo, int $user_id, int $board_id, int $card_id, int $limit = 80): array
  {
    if ($board_id <= 0 || $card_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    $limit = min(150, max(10, $limit));
    $stmt = $pdo->prepare("
      SELECT a.id, a.user_id, a.user_name, a.action_type, a.payload, a.created_at, u.profile_pic AS user_profile_pic
      FROM kanban_board_activities a
      LEFT JOIN users u ON u.id = a.user_id
      WHERE a.board_id = ?
        AND (
          JSON_VALID(a.payload) = 1
          AND (
            CAST(JSON_UNQUOTE(JSON_EXTRACT(a.payload, '$.card_id')) AS UNSIGNED) = ?
            OR JSON_EXTRACT(a.payload, '$.card_id') = ?
          )
        )
      ORDER BY a.created_at DESC
      LIMIT ?
    ");
    $stmt->execute([$board_id, $card_id, $card_id, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
      if (isset($r['payload']) && $r['payload'] !== null && is_string($r['payload'])) {
        $r['payload'] = json_decode($r['payload'], true);
      }
    }
    unset($r);
    return $rows;
  }
}

if (!function_exists('cg_desktop_list_board_members')) {
  /**
   * @return array<int,array<string,mixed>>
   */
  function cg_desktop_list_board_members(PDO $pdo, int $user_id, int $board_id): array
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    return get_board_members_for_board($pdo, $board_id);
  }
}

if (!function_exists('cg_desktop_list_board_card_assignees')) {
  /**
   * @return array<string,array<int,array<string,mixed>>>
   */
  function cg_desktop_list_board_card_assignees(PDO $pdo, int $user_id, int $board_id): array
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    $members = get_board_members_for_board($pdo, $board_id);
    $lookup = [];
    foreach ($members as $m) {
      $lookup[(int)($m['id'] ?? 0)] = $m;
    }
    $stmt = $pdo->prepare("
      SELECT a.card_id, a.user_id, u.name, u.profile_pic
      FROM kanban_card_assignments a
      INNER JOIN kanban_cards k ON k.id = a.card_id
      INNER JOIN kanban_columns c ON c.id = k.column_id
      INNER JOIN users u ON u.id = a.user_id
      WHERE c.board_id = ? AND COALESCE(k.is_trashed, 0) = 0
      ORDER BY a.assigned_at ASC, a.id ASC
    ");
    $stmt->execute([$board_id]);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $card_id = (int)($row['card_id'] ?? 0);
      $uid = (int)($row['user_id'] ?? 0);
      if ($card_id <= 0 || $uid <= 0) {
        continue;
      }
      $member = $lookup[$uid] ?? [
        'id' => $uid,
        'name' => (string)($row['name'] ?? ''),
        'profile_pic' => (string)($row['profile_pic'] ?? ''),
        'role' => 'editor',
      ];
      $key = (string)$card_id;
      if (!isset($out[$key])) {
        $out[$key] = [];
      }
      $out[$key][] = $member;
    }
    return $out;
  }
}

if (!function_exists('cg_desktop_ensure_card_hidden_viewers_schema')) {
  function cg_desktop_ensure_card_hidden_viewers_schema(PDO $pdo): void
  {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS kanban_card_hidden_viewers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        card_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        added_by INT UNSIGNED NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_card_hidden_user (card_id, user_id),
        KEY idx_hidden_card_id (card_id),
        KEY idx_hidden_user_id (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
  }
}

if (!function_exists('cg_desktop_list_board_hidden_viewers_by_card')) {
  /**
   * @return array<string,array<int,array<string,mixed>>>
   */
  function cg_desktop_list_board_hidden_viewers_by_card(PDO $pdo, int $user_id, int $board_id): array
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    cg_desktop_ensure_card_hidden_viewers_schema($pdo);
    $members = get_board_members_for_board($pdo, $board_id);
    $lookup = [];
    foreach ($members as $m) {
      $lookup[(int)($m['id'] ?? 0)] = $m;
    }
    $stmt = $pdo->prepare("
      SELECT k.id
      FROM kanban_cards k
      INNER JOIN kanban_columns c ON c.id = k.column_id
      WHERE c.board_id = ? AND COALESCE(k.is_trashed, 0) = 0 AND COALESCE(k.is_hidden, 0) = 1
    ");
    $stmt->execute([$board_id]);
    $card_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $card_ids = array_values(array_filter($card_ids, static fn($id) => $id > 0));
    if ($card_ids === []) {
      return [];
    }
    $in = implode(',', array_fill(0, count($card_ids), '?'));
    $stmt = $pdo->prepare("
      SELECT v.card_id, v.user_id, u.name, u.profile_pic
      FROM kanban_card_hidden_viewers v
      JOIN users u ON u.id = v.user_id
      WHERE v.card_id IN ($in)
      ORDER BY v.added_at ASC, v.id ASC
    ");
    $stmt->execute($card_ids);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $card_id = (int)($row['card_id'] ?? 0);
      $uid = (int)($row['user_id'] ?? 0);
      if ($card_id <= 0 || $uid <= 0) {
        continue;
      }
      $member = $lookup[$uid] ?? [
        'id' => $uid,
        'name' => (string)($row['name'] ?? ''),
        'profile_pic' => (string)($row['profile_pic'] ?? ''),
        'role' => 'editor',
      ];
      $key = (string)$card_id;
      if (!isset($out[$key])) {
        $out[$key] = [];
      }
      $out[$key][] = $member;
    }
    return $out;
  }
}

if (!function_exists('cg_desktop_set_card_hidden_viewers')) {
  /**
   * @param list<int> $user_ids
   * @return list<int>
   */
  function cg_desktop_set_card_hidden_viewers(PDO $pdo, int $user_id, int $card_id, array $user_ids): array
  {
    if ($card_id <= 0 || !cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
      throw new RuntimeException('Card not found', 404);
    }
    cg_desktop_ensure_card_hidden_viewers_schema($pdo);
    $stmt = $pdo->prepare('SELECT COALESCE(is_hidden, 0) FROM kanban_cards WHERE id = ? LIMIT 1');
    $stmt->execute([$card_id]);
    if (!(int)$stmt->fetchColumn()) {
      throw new RuntimeException('Card is not hidden', 400);
    }
    $stmtB = $pdo->prepare('SELECT c.board_id FROM kanban_cards k JOIN kanban_columns c ON c.id = k.column_id WHERE k.id = ? LIMIT 1');
    $stmtB->execute([$card_id]);
    $board_id = (int)$stmtB->fetchColumn();
    if ($board_id <= 0) {
      throw new RuntimeException('Card not found', 404);
    }
    $members = get_board_members_for_board($pdo, $board_id);
    $allowed_ids = [];
    foreach ($members as $member) {
      $mid = (int)($member['id'] ?? 0);
      if ($mid > 0 && ($member['role'] ?? '') !== 'owner') {
        $allowed_ids[$mid] = true;
      }
    }
    $valid_ids = [];
    foreach ($user_ids as $member_id) {
      $member_id = (int)$member_id;
      if ($member_id > 0 && isset($allowed_ids[$member_id])) {
        $valid_ids[] = $member_id;
      }
    }
    $valid_ids = array_values(array_unique($valid_ids));
    $pdo->beginTransaction();
    try {
      $pdo->prepare('DELETE FROM kanban_card_hidden_viewers WHERE card_id = ?')->execute([$card_id]);
      if ($valid_ids !== []) {
        $stmtIns = $pdo->prepare('INSERT INTO kanban_card_hidden_viewers (card_id, user_id, added_by) VALUES (?, ?, ?)');
        foreach ($valid_ids as $member_id) {
          $stmtIns->execute([$card_id, $member_id, $user_id]);
        }
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
    return $valid_ids;
  }
}

if (!function_exists('cg_desktop_ensure_column_hidden_viewers_schema')) {
  function cg_desktop_ensure_column_hidden_viewers_schema(PDO $pdo): void
  {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS kanban_column_hidden_viewers (
        column_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        added_by INT UNSIGNED NOT NULL DEFAULT 0,
        added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (column_id, user_id),
        KEY idx_user (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists('cg_desktop_list_board_hidden_viewers_by_column')) {
  /**
   * @return array<string, list<array<string, mixed>>>
   */
  function cg_desktop_list_board_hidden_viewers_by_column(PDO $pdo, int $user_id, int $board_id): array
  {
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    cg_desktop_ensure_column_hidden_viewers_schema($pdo);
    $stmt = $pdo->prepare('SELECT id FROM kanban_columns WHERE board_id = ?');
    $stmt->execute([$board_id]);
    $column_ids = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    if ($column_ids === []) {
      return [];
    }
    $members = get_board_members_for_board($pdo, $board_id);
    $lookup = [];
    foreach ($members as $member) {
      $lookup[(int)($member['id'] ?? 0)] = $member;
    }
    $in = implode(',', array_fill(0, count($column_ids), '?'));
    $stmt = $pdo->prepare("
      SELECT v.column_id, v.user_id, u.name, u.profile_pic
      FROM kanban_column_hidden_viewers v
      JOIN users u ON u.id = v.user_id
      WHERE v.column_id IN ($in)
      ORDER BY v.added_at ASC, v.id ASC
    ");
    $stmt->execute($column_ids);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $column_id = (int)($row['column_id'] ?? 0);
      $uid = (int)($row['user_id'] ?? 0);
      if ($column_id <= 0 || $uid <= 0) {
        continue;
      }
      $member = $lookup[$uid] ?? [
        'id' => $uid,
        'name' => (string)($row['name'] ?? ''),
        'profile_pic' => (string)($row['profile_pic'] ?? ''),
        'role' => 'editor',
      ];
      $key = (string)$column_id;
      if (!isset($out[$key])) {
        $out[$key] = [];
      }
      $out[$key][] = $member;
    }
    return $out;
  }
}

if (!function_exists('cg_desktop_set_column_hidden_viewers')) {
  /**
   * @param list<int> $user_ids
   * @return list<int>
   */
  function cg_desktop_set_column_hidden_viewers(PDO $pdo, int $user_id, int $column_id, array $user_ids): array
  {
    if ($column_id <= 0) {
      throw new RuntimeException('Column not found', 404);
    }
    $stmt = $pdo->prepare('SELECT board_id, COALESCE(is_hidden, 0) AS is_hidden FROM kanban_columns WHERE id = ? LIMIT 1');
    $stmt->execute([$column_id]);
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
      throw new RuntimeException('Column not found', 404);
    }
    $board_id = (int)($col['board_id'] ?? 0);
    if ($board_id <= 0 || !cg_desktop_user_owns_board($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Column not found', 404);
    }
    if (!(int)($col['is_hidden'] ?? 0)) {
      throw new RuntimeException('Column is not hidden', 400);
    }
    cg_desktop_ensure_column_hidden_viewers_schema($pdo);
    $members = get_board_members_for_board($pdo, $board_id);
    $allowed_ids = [];
    foreach ($members as $member) {
      $mid = (int)($member['id'] ?? 0);
      if ($mid > 0 && ($member['role'] ?? '') !== 'owner') {
        $allowed_ids[$mid] = true;
      }
    }
    $valid_ids = [];
    foreach ($user_ids as $member_id) {
      $member_id = (int)$member_id;
      if ($member_id > 0 && isset($allowed_ids[$member_id])) {
        $valid_ids[] = $member_id;
      }
    }
    $valid_ids = array_values(array_unique($valid_ids));
    $pdo->beginTransaction();
    try {
      $pdo->prepare('DELETE FROM kanban_column_hidden_viewers WHERE column_id = ?')->execute([$column_id]);
      if ($valid_ids !== []) {
        $stmtIns = $pdo->prepare('INSERT INTO kanban_column_hidden_viewers (column_id, user_id, added_by) VALUES (?, ?, ?)');
        foreach ($valid_ids as $member_id) {
          $stmtIns->execute([$column_id, $member_id, $user_id]);
        }
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
    return $valid_ids;
  }
}

if (!function_exists('cg_desktop_assign_card_members')) {
  /**
   * @param list<int> $user_ids
   * @return list<int>
   */
  function cg_desktop_assign_card_members(PDO $pdo, int $user_id, int $card_id, array $user_ids): array
  {
    if ($card_id <= 0 || !cg_desktop_user_owns_card($pdo, $card_id, $user_id)) {
      throw new RuntimeException('Card not found', 404);
    }
    $board_id = 0;
    $stmtB = $pdo->prepare('SELECT c.board_id FROM kanban_cards k JOIN kanban_columns c ON c.id = k.column_id WHERE k.id = ? LIMIT 1');
    $stmtB->execute([$card_id]);
    $board_id = (int)$stmtB->fetchColumn();
    if ($board_id <= 0) {
      throw new RuntimeException('Card not found', 404);
    }
    $allowed = [];
    foreach (get_board_members_for_board($pdo, $board_id) as $member) {
      $allowed[(int)($member['id'] ?? 0)] = true;
    }
    $valid_ids = [];
    foreach ($user_ids as $member_id) {
      $member_id = (int)$member_id;
      if ($member_id > 0 && isset($allowed[$member_id])) {
        $valid_ids[] = $member_id;
      }
    }
    $valid_ids = array_values(array_unique($valid_ids));

    $pdo->beginTransaction();
    try {
      $pdo->prepare('DELETE FROM kanban_card_assignments WHERE card_id = ?')->execute([$card_id]);
      if ($valid_ids !== []) {
        $ins = $pdo->prepare('INSERT INTO kanban_card_assignments (card_id, user_id, assigned_by) VALUES (?, ?, ?)');
        foreach ($valid_ids as $member_id) {
          $ins->execute([$card_id, $member_id, $user_id]);
        }
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }

    if (function_exists('log_kanban_activity')) {
      try {
        $stmtT = $pdo->prepare('SELECT title FROM kanban_cards WHERE id = ? LIMIT 1');
        $stmtT->execute([$card_id]);
        $ctitle = trim((string)$stmtT->fetchColumn());
        $stmtU = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $stmtU->execute([$user_id]);
        $uname = trim((string)$stmtU->fetchColumn()) ?: 'Someone';
        log_kanban_activity($pdo, $board_id, $user_id, $uname, 'assignments_updated', [
          'card_id' => $card_id,
          'card_title' => $ctitle,
        ]);
      } catch (Throwable $e) {
        error_log('cg_desktop_assign_card_members activity: ' . $e->getMessage());
      }
    }

    return $valid_ids;
  }
}

if (!function_exists('cg_desktop_normalize_card_time')) {
  /** Normalize HTML time input to MySQL TIME (H:i:s) or null. */
  function cg_desktop_normalize_card_time($raw): ?string
  {
    $t = trim((string)$raw);
    if ($t === '') {
      return null;
    }
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
      return null;
    }
    $h = (int)$m[1];
    $mi = (int)$m[2];
    $s = isset($m[3]) ? (int)$m[3] : 0;
    if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59 || $s < 0 || $s > 59) {
      return null;
    }
    return sprintf('%02d:%02d:%02d', $h, $mi, $s);
  }
}

if (!function_exists('cg_desktop_ensure_card_type_schema')) {
  function cg_desktop_ensure_card_type_schema(PDO $pdo): void
  {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'card_type'");
    if ($stmt && $stmt->fetch() === false) {
      $pdo->exec("ALTER TABLE kanban_cards ADD COLUMN card_type VARCHAR(16) NOT NULL DEFAULT 'default' AFTER card_color");
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM kanban_cards LIKE 'link_url'");
    if ($stmt && $stmt->fetch() === false) {
      $pdo->exec('ALTER TABLE kanban_cards ADD COLUMN link_url TEXT NULL DEFAULT NULL AFTER card_type');
    }
  }
}

if (!function_exists('cg_desktop_normalize_card_link_url')) {
  function cg_desktop_normalize_card_link_url($raw): ?string
  {
    $url = trim((string)$raw);
    if ($url === '') {
      return null;
    }
    if (!preg_match('#\Ahttps?://#i', $url)) {
      $url = 'https://' . $url;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return null;
    }
    return $url;
  }
}

if (!function_exists('cg_desktop_delete_board')) {
  /**
   * Delete a Kanban board and related rows (owner-only; called from desktop API).
   */
  function cg_desktop_delete_board(PDO $pdo, int $board_id): void
  {
    if ($board_id <= 0) {
      throw new InvalidArgumentException('Invalid board_id');
    }

    $stmt = $pdo->prepare('SELECT id FROM kanban_columns WHERE board_id = ?');
    $stmt->execute([$board_id]);
    $columnIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $cardIds = [];
    if ($columnIds !== []) {
      $placeholders = implode(',', array_fill(0, count($columnIds), '?'));
      $stmt = $pdo->prepare("SELECT id FROM kanban_cards WHERE column_id IN ($placeholders)");
      $stmt->execute($columnIds);
      $cardIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    if ($cardIds !== [] && function_exists('ensure_kanban_card_assignments_schema')) {
      ensure_kanban_card_assignments_schema($pdo);
    }

    $pdo->beginTransaction();
    try {
      if ($cardIds !== []) {
        $ph = implode(',', array_fill(0, count($cardIds), '?'));
        try {
          $pdo->prepare("DELETE FROM kanban_card_assignments WHERE card_id IN ($ph)")->execute($cardIds);
        } catch (Throwable $e) {
          error_log('cg_desktop_delete_board assignments: ' . $e->getMessage());
        }
      }

      $chartStmt = $pdo->prepare('SELECT id FROM gantt_charts WHERE source_board_id = ?');
      $chartStmt->execute([$board_id]);
      $chartIds = array_map('intval', $chartStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
      if ($chartIds !== []) {
        $chPh = implode(',', array_fill(0, count($chartIds), '?'));
        try {
          $pdo->prepare("DELETE FROM timeline_views WHERE chart_id IN ($chPh)")->execute($chartIds);
        } catch (Throwable $e) {
          error_log('cg_desktop_delete_board timeline_views chart: ' . $e->getMessage());
        }
        $pdo->prepare("DELETE FROM gantt_charts WHERE source_board_id = ?")->execute([$board_id]);
      }

      foreach (
        [
          'kanban_board_digest_events',
          'kanban_board_activities',
          'kanban_board_chat_messages',
          'kanban_workspace_items',
          'kanban_board_emails',
          'kanban_board_collaborators',
          'kanban_board_invites',
          'kanban_google_event_links',
          'kanban_google_sync_cursors',
          'kanban_google_calendars',
          'kanban_google_account_members',
          'kanban_google_accounts',
        ] as $table
      ) {
        try {
          $pdo->prepare("DELETE FROM $table WHERE board_id = ?")->execute([$board_id]);
        } catch (Throwable $e) {
          error_log("cg_desktop_delete_board $table: " . $e->getMessage());
        }
      }

      if ($columnIds !== []) {
        $ph = implode(',', array_fill(0, count($columnIds), '?'));
        $pdo->prepare("DELETE FROM kanban_cards WHERE column_id IN ($ph)")->execute($columnIds);
      }
      $pdo->prepare('DELETE FROM kanban_columns WHERE board_id = ?')->execute([$board_id]);
      $pdo->prepare('DELETE FROM kanban_boards WHERE id = ?')->execute([$board_id]);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }
}

if (!function_exists('cg_desktop_board_access_helpers')) {
  function cg_desktop_board_access_helpers(): void
  {
    if (!function_exists('user_can_access_board')) {
      require_once __DIR__ . '/kanban_board_access.php';
    }
  }
}

if (!function_exists('cg_desktop_user_display_name')) {
  function cg_desktop_user_display_name(PDO $pdo, int $user_id): string
  {
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $name = $stmt->fetchColumn();
    return is_string($name) && $name !== '' ? $name : 'Someone';
  }
}

if (!function_exists('cg_desktop_list_board_emails')) {
  /**
   * @return list<string>
   */
  function cg_desktop_list_board_emails(PDO $pdo, int $user_id, int $board_id): array
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      return [];
    }
    $stmt = $pdo->prepare('SELECT email FROM kanban_board_emails WHERE board_id = ? ORDER BY created_at ASC');
    $stmt->execute([$board_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }
}

if (!function_exists('cg_desktop_add_board_email')) {
  function cg_desktop_add_board_email(PDO $pdo, int $user_id, int $board_id, string $email): string
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || $email === '') {
      throw new InvalidArgumentException('Missing board_id or email');
    }
    if (!user_can_access_board($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('Invalid email address');
    }
    try {
      $stmt = $pdo->prepare('INSERT INTO kanban_board_emails (board_id, email) VALUES (?, ?)');
      $stmt->execute([$board_id, $email]);
    } catch (PDOException $e) {
      if ((int)$e->getCode() === 23000) {
        throw new RuntimeException('This email is already added for this board', 400);
      }
      throw $e;
    }
    if (!function_exists('log_kanban_activity')) {
      $act = __DIR__ . '/kanban_activities.php';
      if (is_readable($act)) {
        require_once $act;
      }
    }
    if (function_exists('log_kanban_activity')) {
      log_kanban_activity(
        $pdo,
        $board_id,
        $user_id,
        cg_desktop_user_display_name($pdo, $user_id),
        'notification_added',
        ['email' => $email],
      );
    }
    return $email;
  }
}

if (!function_exists('cg_desktop_remove_board_email')) {
  function cg_desktop_remove_board_email(PDO $pdo, int $user_id, int $board_id, string $email): void
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || trim($email) === '') {
      throw new InvalidArgumentException('Missing board_id or email');
    }
    if (!user_can_access_board($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    $stmt = $pdo->prepare('DELETE FROM kanban_board_emails WHERE board_id = ? AND email = ?');
    $stmt->execute([$board_id, strtolower(trim($email))]);
  }
}

if (!function_exists('cg_desktop_list_collaborators')) {
  /**
   * @return array{collaborators: list<array<string,mixed>>, pending: list<array<string,mixed>>}
   */
  function cg_desktop_list_collaborators(PDO $pdo, int $user_id, int $board_id): array
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || !board_belongs_to_user($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    $stmt = $pdo->prepare('
      SELECT bc.user_id, u.name, u.email, bc.role, bc.invited_at
      FROM kanban_board_collaborators bc
      JOIN users u ON u.id = bc.user_id
      WHERE bc.board_id = ?
      ORDER BY bc.invited_at ASC
    ');
    $stmt->execute([$board_id]);
    $collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT email, created_at FROM kanban_board_invites WHERE board_id = ? ORDER BY created_at ASC');
    $stmt->execute([$board_id]);
    $pending = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
      $row['pending'] = true;
      $pending[] = $row;
    }

    return ['collaborators' => $collaborators, 'pending' => $pending];
  }
}

if (!function_exists('cg_desktop_add_collaborator')) {
  /**
   * @return array<string,mixed>
   */
  function cg_desktop_add_collaborator(PDO $pdo, int $user_id, int $board_id, string $email): array
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || trim($email) === '') {
      throw new InvalidArgumentException('Missing board_id or email');
    }
    if (!board_belongs_to_user($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('Invalid email address');
    }

    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $invitee = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT p.user_id FROM kanban_boards b JOIN freelance_projects p ON p.id = b.project_id WHERE b.id = ? LIMIT 1');
    $stmt->execute([$board_id]);
    $owner_id = (int)$stmt->fetchColumn();
    if ($invitee && (int)$invitee['id'] === $owner_id) {
      throw new RuntimeException('Cannot add the board owner as a collaborator', 400);
    }

    if (!function_exists('log_kanban_activity')) {
      $act = __DIR__ . '/kanban_activities.php';
      if (is_readable($act)) {
        require_once $act;
      }
    }
    $uname = cg_desktop_user_display_name($pdo, $user_id);

    if ($invitee) {
      try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO kanban_board_collaborators (board_id, user_id, role, invited_by) VALUES (?, ?, 'editor', ?)");
        $stmt->execute([$board_id, $invitee['id'], $user_id]);
        if ($stmt->rowCount() === 0) {
          throw new RuntimeException('This user already has access to this board', 400);
        }
        if (function_exists('log_kanban_activity')) {
          log_kanban_activity($pdo, $board_id, $user_id, $uname, 'collaborator_added', [
            'collaborator_name' => $invitee['name'] ?? $email,
            'email' => $email,
          ]);
        }
        $stmt = $pdo->prepare('SELECT name FROM kanban_boards WHERE id = ? LIMIT 1');
        $stmt->execute([$board_id]);
        $board_name = $stmt->fetchColumn() ?: 'Board';
        $notifierPath = __DIR__ . '/kanban_board_notifier.php';
        if (is_readable($notifierPath)) {
          require_once $notifierPath;
          if (class_exists('KanbanBoardNotifier')) {
            $login_url = (defined('CG_KANBAN_APP_ORIGIN') ? CG_KANBAN_APP_ORIGIN : '') . '/login.php';
            (new KanbanBoardNotifier())->notifyBoardAdded($board_name, $email, $login_url);
          }
        }
        return ['added' => true, 'user_id' => (int)$invitee['id'], 'email' => $email];
      } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
          throw new RuntimeException('This user already has access to this board', 400);
        }
        throw $e;
      }
    }

    try {
      $stmt = $pdo->prepare('INSERT IGNORE INTO kanban_board_invites (board_id, email) VALUES (?, ?)');
      $stmt->execute([$board_id, $email]);
      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('This email already has a pending invite for this board', 400);
      }
    } catch (PDOException $e) {
      if ((int)$e->getCode() === 23000) {
        throw new RuntimeException('This email already has a pending invite for this board', 400);
      }
      throw $e;
    }

    if (function_exists('log_kanban_activity')) {
      log_kanban_activity($pdo, $board_id, $user_id, $uname, 'collaborator_added', [
        'collaborator_name' => $email,
        'email' => $email,
        'pending' => true,
      ]);
    }

    $stmt = $pdo->prepare('SELECT name FROM kanban_boards WHERE id = ? LIMIT 1');
    $stmt->execute([$board_id]);
    $board_name = $stmt->fetchColumn() ?: 'Board';
    $notifierPath = __DIR__ . '/kanban_board_notifier.php';
    if (is_readable($notifierPath)) {
      require_once $notifierPath;
      if (class_exists('KanbanBoardNotifier')) {
        $login_url = (defined('CG_KANBAN_APP_ORIGIN') ? CG_KANBAN_APP_ORIGIN : '') . '/login.php';
        (new KanbanBoardNotifier())->notifyBoardInvite($board_name, $email, $login_url);
      }
    }

    return ['added' => true, 'pending' => true, 'email' => $email];
  }
}

if (!function_exists('cg_desktop_remove_collaborator')) {
  function cg_desktop_remove_collaborator(
    PDO $pdo,
    int $user_id,
    int $board_id,
    int $remove_user_id = 0,
    string $remove_email = '',
  ): void {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || !board_belongs_to_user($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    if ($remove_user_id > 0) {
      $linkedIds = kanban_linked_user_ids($remove_user_id);
      $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));
      $stmt = $pdo->prepare("DELETE FROM kanban_board_collaborators WHERE board_id = ? AND user_id IN ($placeholders)");
      $stmt->execute(array_merge([$board_id], $linkedIds));
      return;
    }
    if ($remove_email !== '') {
      $stmt = $pdo->prepare('DELETE FROM kanban_board_invites WHERE board_id = ? AND email = ?');
      $stmt->execute([$board_id, strtolower(trim($remove_email))]);
      return;
    }
    throw new InvalidArgumentException('Provide user_id or email to remove');
  }
}

if (!function_exists('cg_desktop_leave_board')) {
  function cg_desktop_leave_board(PDO $pdo, int $user_id, int $board_id): void
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0) {
      throw new InvalidArgumentException('Missing board_id');
    }
    if (board_belongs_to_user($pdo, $board_id, $user_id)) {
      throw new RuntimeException(
        'Board owners cannot leave this board. Delete the board instead if you no longer need it.',
        400,
      );
    }
    if (!user_can_access_board($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    $linkedIds = kanban_linked_user_ids($user_id);
    $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM kanban_board_collaborators WHERE board_id = ? AND user_id IN ($placeholders)");
    $stmt->execute(array_merge([$board_id], $linkedIds));
  }
}

if (!function_exists('cg_desktop_queue_digest')) {
  /**
   * Queue a board digest event (e.g. automation notify_team).
   */
  function cg_desktop_queue_digest(PDO $pdo, int $user_id, int $board_id, string $event_type, array $payload = []): void
  {
    cg_desktop_board_access_helpers();
    if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
      throw new RuntimeException('Board not found', 404);
    }
    $event_type = trim($event_type);
    if ($event_type === '') {
      throw new InvalidArgumentException('event_type required');
    }
    if (!function_exists('queue_kanban_digest_event')) {
      require_once __DIR__ . '/kanban_board_notifier.php';
    }
    queue_kanban_digest_event($pdo, $board_id, $event_type, $payload);
  }
}
