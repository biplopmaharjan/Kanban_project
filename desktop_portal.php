<?php
/**
 * Desktop portal SSO — alternate entry at site root (not under /api/).
 * GET/POST + Bearer token; params: view, board_id
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/includes/cg_desktop_api_bootstrap.php';
require_once CG_KANBAN_ROOT . '/includes/freelance_projects.php';
require_once CG_KANBAN_ROOT . '/includes/kanban_board_access.php';
require_once CG_KANBAN_ROOT . '/includes/cg_desktop_auth.php';

function cg_desktop_portal_entry_fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function cg_desktop_portal_entry_ok(array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    cg_ensure_freelance_tables();
    $pdo = getDB();
    cg_desktop_ensure_tables($pdo);
} catch (Throwable $e) {
    error_log('desktop_portal.php init: ' . $e->getMessage());
    cg_desktop_portal_entry_fail('Init failed', 500);
}

$view = strtolower(trim((string)($_GET['view'] ?? '')));
$board_id = (int)($_GET['board_id'] ?? 0);
$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    $body = json_decode($raw, true);
    if (is_array($body)) {
        if ($view === '') {
            $view = strtolower(trim((string)($body['view'] ?? '')));
        }
        if ($board_id <= 0) {
            $board_id = (int)($body['board_id'] ?? 0);
        }
    }
}

$auth = cg_desktop_authenticate_request($pdo);
if (!$auth) {
    cg_desktop_portal_entry_fail('Unauthorized', 401);
}
$user_id = (int)$auth['user_id'];
if (!cg_desktop_user_has_freelance_access($pdo, $user_id)) {
    cg_desktop_portal_entry_fail('Kanban access revoked', 403);
}

if ($view === '') {
    cg_desktop_portal_entry_fail('view required', 400);
}
if (!in_array($view, ['wallet', 'switch_account'], true) && $board_id <= 0) {
    cg_desktop_portal_entry_fail('board_id required', 400);
}
if ($board_id > 0 && !user_can_access_board($pdo, $board_id, $user_id)) {
    cg_desktop_portal_entry_fail('Board not found', 404);
}

require_once CG_KANBAN_ROOT . '/includes/cg_desktop_portal.php';
try {
    $url = cg_desktop_portal_issue_sso_url($pdo, $user_id, $view, $board_id);
} catch (InvalidArgumentException $e) {
    cg_desktop_portal_entry_fail($e->getMessage(), 400);
} catch (RuntimeException $e) {
    $code = $e->getCode() >= 400 && $e->getCode() < 600 ? (int)$e->getCode() : 500;
    cg_desktop_portal_entry_fail($e->getMessage(), $code);
} catch (Throwable $e) {
    error_log('desktop_portal.php: ' . $e->getMessage());
    cg_desktop_portal_entry_fail('Portal URL failed', 500);
}

cg_desktop_portal_entry_ok(['url' => $url]);
