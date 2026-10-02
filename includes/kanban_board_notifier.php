<?php
/**
 * Kanban Board Notifier
 * Sends immediate board invite emails and batched daily digest emails for board activity.
 */

class KanbanBoardNotifier {
    private $fromEmail = 'noreply@cinegrid.net';
    private $fromName = 'CineGrid Kanban';

    /**
     * Send one email to multiple recipients (BCC).
     */
    private function sendToMany(array $emails, string $subject, string $body): bool {
        $emails = array_filter(array_map('trim', $emails));
        $emails = array_filter($emails, function ($e) { return filter_var($e, FILTER_VALIDATE_EMAIL); });
        if (empty($emails)) return true;

        $messageId = '<' . bin2hex(random_bytes(8)) . '.' . time() . '@cinegrid.net>';
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "Message-ID: {$messageId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        $to = array_shift($emails);
        if (!empty($emails)) {
            $headers .= "Bcc: " . implode(", ", $emails) . "\r\n";
        }

        $result = @mail($to, $subject, $body, $headers);
        if (!$result) {
            error_log("KanbanBoardNotifier: Failed to send to {$to}");
        }
        return $result;
    }

    private function wrapHtml(string $content, string $boardName): string {
        $headerTitle = htmlspecialchars($boardName);
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;}.container{max-width:600px;margin:0 auto;padding:20px;}.header{background:#e03131;color:white;padding:12px 20px;}.content{padding:20px;background:#f8fafc;border:1px solid #e2e8f0;}.footer{text-align:center;padding:12px;color:#64748b;font-size:12px;}strong{color:#0f172a;}</style></head><body><div class='container'><div class='header'><strong>{$headerTitle}</strong></div><div class='content'>{$content}</div><div class='footer'>This is an automated notification from CineGrid.</div></div></body></html>";
    }

    /**
     * Notify when a card is moved to another column.
     */
    public function notifyCardMoved(string $boardName, array $emails, string $cardTitle, string $fromColumn, string $toColumn): bool {
        if (empty($emails)) return true;
        $subject = "[{$boardName}] Card moved: " . (strlen($cardTitle) > 50 ? substr($cardTitle, 0, 47) . '...' : $cardTitle) . " [" . date('d M H:i') . "]";
        $content = "<p><strong>Card moved</strong></p><p>Card <strong>" . htmlspecialchars($cardTitle) . "</strong> was moved from <strong>" . htmlspecialchars($fromColumn) . "</strong> to <strong>" . htmlspecialchars($toColumn) . "</strong> on board <strong>" . htmlspecialchars($boardName) . "</strong>.</p>";
        return $this->sendToMany($emails, $subject, $this->wrapHtml($content, $boardName));
    }

    /**
     * Notify when card details are updated.
     * $status = column name (e.g. To Do, Doing, Done), $progress = 0–100.
     * $username = optional display name for "Recent Changes by \"Username\"".
     */
    public function notifyCardUpdated(string $boardName, array $emails, string $cardTitle, array $changesSummary, string $status = '', int $progress = 0, string $username = ''): bool {
        if (empty($emails)) return true;
        $subject = "[{$boardName}] Card updated: " . (strlen($cardTitle) > 50 ? substr($cardTitle, 0, 47) . '...' : $cardTitle) . " [" . date('d M H:i') . "]";
        $task = htmlspecialchars($cardTitle);
        $statusVal = $status !== '' ? htmlspecialchars($status) : '—';
        $progress = max(0, min(100, (int)$progress));
        $barColor = $progress >= 100 ? '#10b981' : ($progress >= 50 ? '#f59e0b' : '#e03131');
        $progressBar = '<div style="height:12px;background:#e2e8f0;border-radius:6px;overflow:hidden;"><div style="width:' . $progress . '%;height:100%;background:' . $barColor . ';border-radius:6px;"></div></div>';
        $progressCell = '<table cellpadding="0" cellspacing="0" style="width:100%;margin:6px 0 12px 0;"><tr><td style="width:100%;vertical-align:middle;">' . $progressBar . '</td><td style="white-space:nowrap;padding-left:8px;vertical-align:middle;font-weight:700;">' . $progress . '%</td></tr></table>';
        $list = implode('', array_map(function ($line) { return '<li>' . htmlspecialchars($line) . '</li>'; }, $changesSummary));
        $recentLabel = $username !== '' ? 'Recent Changes by "' . htmlspecialchars($username) . '"' : 'Recent Changes';
        $content = '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">'
            . '<tr><td style="padding:6px 0;color:#64748b;font-size:13px;">Task</td><td style="padding:6px 0;font-weight:700;">' . $task . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b;font-size:13px;">Status</td><td style="padding:6px 0;">' . $statusVal . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b;font-size:13px;">Progress</td><td style="padding:6px 0;">' . $progressCell . '</td></tr>'
            . '</table>'
            . '<p style="margin-top:16px;margin-bottom:6px;font-weight:700;color:#0f172a;">Card updated</p>'
            . '<p style="color:#64748b;font-size:13px;">' . $recentLabel . '</p><ul style="margin:0 0 12px 0;">' . $list . '</ul>';
        return $this->sendToMany($emails, $subject, $this->wrapHtml($content, $boardName));
    }

    /**
     * Notify when someone is invited to collaborate on a board (pending invite – no account yet).
     */
    public function notifyBoardInvite(string $boardName, string $inviteeEmail, string $loginOrSignupUrl): bool {
        $subject = "[CineGrid] You're invited to collaborate on board: " . $boardName;
        $content = "<p><strong>You've been invited to collaborate</strong></p><p>You have been invited to collaborate on the Kanban board <strong>" . htmlspecialchars($boardName) . "</strong>.</p><p><a href=\"" . htmlspecialchars($loginOrSignupUrl) . "\" style=\"display:inline-block;background:#e03131;color:white;padding:10px 20px;text-decoration:none;border-radius:8px;font-weight:700;\">Sign up or log in to get access</a></p><p style=\"color:#64748b;font-size:13px;\">If the button doesn't work, copy this link into your browser:<br>" . htmlspecialchars($loginOrSignupUrl) . "</p>";
        return $this->sendToMany([$inviteeEmail], $subject, $this->wrapHtml($content, 'CineGrid'));
    }

    /**
     * Notify when an existing user is added as a collaborator (they have an account; send board link).
     */
    public function notifyBoardAdded(string $boardName, string $collaboratorEmail, string $boardUrl): bool {
        $subject = "[CineGrid] You've been added to board: " . $boardName;
        $content = "<p><strong>You've been added as a collaborator</strong></p><p>You have been added to the Kanban board <strong>" . htmlspecialchars($boardName) . "</strong>.</p><p><a href=\"" . htmlspecialchars($boardUrl) . "\" style=\"display:inline-block;background:#e03131;color:white;padding:10px 20px;text-decoration:none;border-radius:8px;font-weight:700;\">Open board</a></p><p style=\"color:#64748b;font-size:13px;\">If the button doesn't work, copy this link into your browser:<br>" . htmlspecialchars($boardUrl) . "</p>";
        return $this->sendToMany([$collaboratorEmail], $subject, $this->wrapHtml($content, 'CineGrid'));
    }

    /**
     * Notify when a new comment is added (client view).
     */
    public function notifyNewComment(string $boardName, array $emails, string $authorName, string $commentSnippet, ?string $taskName = null): bool {
        if (empty($emails)) return true;
        $subject = "[{$boardName}] New comment on timeline [" . date('d M H:i') . "]";
        $taskLine = $taskName ? "<p><strong>Task:</strong> " . htmlspecialchars($taskName) . "</p>" : "";
        $snippet = strlen($commentSnippet) > 300 ? htmlspecialchars(substr($commentSnippet, 0, 297)) . '...' : htmlspecialchars($commentSnippet);
        $content = "<p><strong>New comment</strong></p><p><strong>" . htmlspecialchars($authorName) . "</strong> left a comment on board <strong>" . htmlspecialchars($boardName) . "</strong>.</p>{$taskLine}<p style='background:#fff;padding:10px;border-left:4px solid #e03131;'>" . nl2br($snippet) . "</p>";
        return $this->sendToMany($emails, $subject, $this->wrapHtml($content, $boardName));
    }

    /**
     * Board chat @mention: notify a single collaborator (one email per recipient).
     */
    public function notifyBoardChatTagged(string $boardName, string $recipientEmail, string $authorName, string $messageSnippet, string $boardUrl): bool {
        $recipientEmail = trim($recipientEmail);
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        $safeBoard = str_replace(["\r", "\n"], ' ', htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'));
        $safeAuthor = str_replace(["\r", "\n"], ' ', htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8'));
        $subject = '[' . $safeBoard . '] ' . $safeAuthor . ' mentioned you in board chat [' . date('d M H:i') . ']';
        $snippet = strlen($messageSnippet) > 500 ? htmlspecialchars(substr($messageSnippet, 0, 497)) . '...' : htmlspecialchars($messageSnippet);
        $content = '<p><strong>You were mentioned in board chat</strong></p>'
            . '<p><strong>' . htmlspecialchars($authorName) . '</strong> tagged you on <strong>' . htmlspecialchars($boardName) . '</strong>.</p>'
            . "<p style='background:#fff;padding:10px;border-left:4px solid #e03131;'>" . nl2br($snippet) . '</p>'
            . '<p><a href="' . htmlspecialchars($boardUrl) . '" style="display:inline-block;background:#e03131;color:white;padding:10px 20px;text-decoration:none;border-radius:8px;font-weight:700;">Open board</a></p>'
            . '<p style="color:#64748b;font-size:13px;">If the button does not work, copy this link:<br>' . htmlspecialchars($boardUrl) . '</p>';
        return $this->sendToMany([$recipientEmail], $subject, $this->wrapHtml($content, $boardName));
    }

    private function formatDigestEventHtml(array $event): string {
        $type = (string)($event['event_type'] ?? '');
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $timeLabel = '';
        if (!empty($event['created_at'])) {
            $ts = strtotime((string)$event['created_at']);
            if ($ts !== false) {
                $timeLabel = date('d M Y, H:i', $ts);
            }
        }

        $title = 'Board activity';
        $body = '';

        if ($type === 'card_moved') {
            $title = 'Card moved';
            $body = 'Card <strong>' . htmlspecialchars((string)($payload['card_title'] ?? 'Untitled')) . '</strong> moved from <strong>' . htmlspecialchars((string)($payload['from_column'] ?? 'Unknown')) . '</strong> to <strong>' . htmlspecialchars((string)($payload['to_column'] ?? 'Unknown')) . '</strong>.';
        } elseif ($type === 'card_updated') {
            $title = 'Card updated';
            $cardTitle = htmlspecialchars((string)($payload['card_title'] ?? 'Untitled'));
            $changes = $payload['changes_summary'] ?? [];
            if (!is_array($changes)) {
                $changes = [(string)$changes];
            }
            $changeList = '';
            if (!empty($changes)) {
                $items = array_map(function ($line) {
                    return '<li>' . htmlspecialchars((string)$line) . '</li>';
                }, $changes);
                $changeList = '<ul style="margin:8px 0 0 18px;padding:0;">' . implode('', $items) . '</ul>';
            }
            $metaParts = [];
            if (!empty($payload['status'])) {
                $metaParts[] = 'Status: ' . htmlspecialchars((string)$payload['status']);
            }
            if (isset($payload['progress']) && $payload['progress'] !== '') {
                $metaParts[] = 'Progress: ' . max(0, min(100, (int)$payload['progress'])) . '%';
            }
            if (!empty($payload['username'])) {
                $metaParts[] = 'By: ' . htmlspecialchars((string)$payload['username']);
            }
            $metaHtml = !empty($metaParts)
                ? '<div style="margin-top:8px;color:#64748b;font-size:13px;">' . implode(' | ', $metaParts) . '</div>'
                : '';
            $body = 'Card <strong>' . $cardTitle . '</strong> was updated.' . $metaHtml . $changeList;
        } elseif ($type === 'comment_added') {
            $title = 'New comment';
            $comment = (string)($payload['comment'] ?? '');
            if (mb_strlen($comment) > 220) {
                $comment = mb_substr($comment, 0, 217) . '...';
            }
            $taskLine = !empty($payload['task_name'])
                ? '<div style="margin:6px 0 0 0;color:#64748b;font-size:13px;">Task: ' . htmlspecialchars((string)$payload['task_name']) . '</div>'
                : (!empty($payload['card_title'])
                    ? '<div style="margin:6px 0 0 0;color:#64748b;font-size:13px;">Task: ' . htmlspecialchars((string)$payload['card_title']) . '</div>'
                    : '');
            $body = '<strong>' . htmlspecialchars((string)($payload['author_name'] ?? 'Someone')) . '</strong> added a comment.' . $taskLine;
            if ($comment !== '') {
                $body .= "<div style='margin-top:8px;background:#fff;padding:10px;border-left:4px solid #e03131;'>" . nl2br(htmlspecialchars($comment)) . '</div>';
            }
        } else {
            $body = 'New board activity was recorded.';
        }

        $timeHtml = $timeLabel !== '' ? '<div style="margin-top:8px;color:#94a3b8;font-size:12px;">' . htmlspecialchars($timeLabel) . '</div>' : '';

        return "<div style='padding:12px 0;border-bottom:1px solid #e2e8f0;'><div style='font-weight:700;color:#0f172a;'>" . htmlspecialchars($title) . "</div><div style='margin-top:6px;color:#334155;'>" . $body . "</div>{$timeHtml}</div>";
    }

    public function notifyBoardDigest(string $boardName, string $email, array $events): bool {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($events)) return true;

        $subject = "[{$boardName}] Daily activity summary [" . date('d M H:i') . "]";
        $items = implode('', array_map(function ($event) {
            return $this->formatDigestEventHtml($event);
        }, $events));
        $count = count($events);
        $content = "<p><strong>Daily activity summary</strong></p><p>Here is your board digest for the last 24 hours.</p><p style='color:#64748b;font-size:13px;'>Included updates: <strong>{$count}</strong></p><div>{$items}</div>";

        return $this->sendToMany([$email], $subject, $this->wrapHtml($content, $boardName));
    }
}

function kanban_digest_mysql_value(PDO $pdo, string $expr): string {
    $stmt = $pdo->query('SELECT ' . $expr);
    return (string)($stmt ? $stmt->fetchColumn() : '');
}

function queue_kanban_digest_event(PDO $pdo, int $board_id, string $event_type, array $payload = []): void {
    if ($board_id <= 0 || trim($event_type) === '') {
        return;
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO kanban_board_digest_events (board_id, event_type, payload)
            VALUES (?, ?, ?)
        ");
        $json = null;
        if ($payload !== []) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $json = ($encoded === false || $encoded === '') ? null : $encoded;
        }
        $stmt->execute([$board_id, $event_type, $json]);
    } catch (Throwable $e) {
        error_log('queue_kanban_digest_event: ' . $e->getMessage());
    }
}

function process_pending_kanban_digests(PDO $pdo): void {
    try {
        $nowHour = (int)kanban_digest_mysql_value($pdo, 'HOUR(NOW())');
        if ($nowHour < 20) {
            return;
        }

        $lockStmt = $pdo->query("SELECT GET_LOCK('kanban_daily_digest_8pm', 1)");
        if ((int)($lockStmt ? $lockStmt->fetchColumn() : 0) !== 1) {
            return;
        }

        $digestCutoff = kanban_digest_mysql_value($pdo, 'CONCAT(CURDATE(), " 20:00:00")');
        if ($digestCutoff === '') {
            return;
        }

        $stmt = $pdo->query("
            SELECT
                kbe.board_id,
                LOWER(TRIM(kbe.email)) AS email,
                MAX(kbe.last_digest_sent_at) AS last_digest_sent_at,
                MAX(b.name) AS board_name
            FROM kanban_board_emails kbe
            JOIN kanban_boards b ON b.id = kbe.board_id
            GROUP BY kbe.board_id, LOWER(TRIM(kbe.email))
            HAVING MAX(COALESCE(kbe.last_digest_sent_at, '1970-01-01 00:00:00')) < " . $pdo->quote($digestCutoff) . "
        ");
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($targets)) {
            return;
        }

        $stmtEvents = $pdo->prepare("
            SELECT id, event_type, payload, created_at
            FROM kanban_board_digest_events
            WHERE board_id = ?
              AND created_at > COALESCE(?, '1970-01-01 00:00:00')
              AND created_at <= ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmtUpdate = $pdo->prepare("UPDATE kanban_board_emails SET last_digest_sent_at = ? WHERE board_id = ? AND LOWER(TRIM(email)) = LOWER(TRIM(?))");
        $notifier = new KanbanBoardNotifier();

        foreach ($targets as $target) {
            $boardId = (int)($target['board_id'] ?? 0);
            $email = trim((string)($target['email'] ?? ''));
            if ($boardId <= 0 || $email === '') {
                continue;
            }

            $stmtEvents->execute([$boardId, $target['last_digest_sent_at'] ?? null, $digestCutoff]);
            $rows = $stmtEvents->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (empty($rows)) {
                continue;
            }

            $events = array_map(function ($row) {
                $payload = [];
                if (!empty($row['payload'])) {
                    $payload = json_decode((string)$row['payload'], true) ?: [];
                }
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'event_type' => (string)($row['event_type'] ?? ''),
                    'payload' => $payload,
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }, $rows);

            if ($notifier->notifyBoardDigest((string)($target['board_name'] ?? 'Board'), $email, $events)) {
                $stmtUpdate->execute([$digestCutoff, $boardId, $email]);
            }
        }
    } catch (Throwable $e) {
        error_log('process_pending_kanban_digests: ' . $e->getMessage());
    } finally {
        try {
            $pdo->query("SELECT RELEASE_LOCK('kanban_daily_digest_8pm')");
        } catch (Throwable $e) {
        }
    }
}
