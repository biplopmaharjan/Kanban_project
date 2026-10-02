<?php
/**
 * Early include from cinegrid.net/api/freelance_kanban.php (before public google_calendar_sync.php).
 * Only loads Kanban Google helpers if a client-invite OAuth row exists (avoids function redeclare).
 */
$__here = __DIR__;
$__cgA = trim((string)($_GET['action'] ?? ''));
if ($__cgA !== 'google_connect_callback') {
    return;
}
if (empty($_GET['state']) || (!isset($_GET['code']) && !isset($_GET['error']))) {
    return;
}

$__cgState = preg_replace('/[^a-f0-9]/', '', strtolower(trim((string)$_GET['state'])));
if ($__cgState === '' || strlen($__cgState) < 16) {
    return;
}

$__dbCandidates = [
    $__here . '/../../public_html/includes/db.php',
    dirname($__here, 2) . '/public_html/includes/db.php',
];
$__dbFile = null;
foreach ($__dbCandidates as $__p) {
    if (is_file($__p)) {
        $__dbFile = $__p;
        break;
    }
}
if ($__dbFile === null) {
    return;
}
require_once $__dbFile;
$pdo = getDB();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kanban_google_client_oauth_states (
            state VARCHAR(64) NOT NULL,
            board_id INT UNSIGNED NOT NULL,
            invite_token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (state),
            KEY idx_gco_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    error_log('cg_client_google_oauth_public_entry ensure table: ' . $e->getMessage());
    return;
}

$__chk = $pdo->prepare('
    SELECT 1 FROM kanban_google_client_oauth_states
    WHERE state = ? AND created_at > DATE_SUB(NOW(), INTERVAL 20 MINUTE)
    LIMIT 1
');
$__chk->execute([$__cgState]);
$__hasRow = (bool) $__chk->fetchColumn();
if (!$__hasRow) {
    return;
}

require_once $__here . '/google_calendar_sync.php';
require_once $__here . '/cg_client_google_calendar_common.php';
$__handled = cg_client_google_maybe_finish_oauth_callback($pdo);
if ($__handled) {
    exit;
}
