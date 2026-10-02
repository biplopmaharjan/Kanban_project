<?php
/**
 * Desktop portal SSO redirect — establishes a web session, then opens calendar / workspace / timeline.
 */
require_once __DIR__ . '/auth.php';

$cg_public_root = dirname(__DIR__) . '/public_html';
require_once $cg_public_root . '/includes/db.php';
require_once __DIR__ . '/includes/freelance_projects.php';
require_once __DIR__ . '/includes/cg_desktop_portal.php';

try {
    $pdo = getDB();
    cg_ensure_freelance_tables();

    $token = trim((string)($_GET['t'] ?? ''));
    $view = strtolower(trim((string)($_GET['view'] ?? '')));
    $board_id = (int)($_GET['board_id'] ?? 0);

    $user = cg_desktop_portal_consume_sso($pdo, $token);
    if (!$user) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'This sign-in link has expired. Return to the desktop app and try again.';
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    $origin = cg_desktop_portal_site_origin();
    $target = $origin . '/offline/kanban.php';
    if ($view === 'calendar' && $board_id > 0) {
        $month = trim((string)($_GET['month'] ?? gmdate('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = gmdate('Y-m');
        }
        $target = $origin . '/offline/calendar.php?board_id=' . rawurlencode((string)$board_id)
            . '&month=' . rawurlencode($month);
    } elseif ($view === 'workspace' && $board_id > 0) {
        $target = $origin . '/offline/workspace.php?board_id=' . rawurlencode((string)$board_id);
    } elseif ($view === 'timeline') {
        $chart_id = (int)($_GET['chart_id'] ?? 0);
        if ($chart_id > 0) {
            $target = $origin . '/offline/project_timeline.php?chart_id=' . rawurlencode((string)$chart_id);
        }
    } elseif ($view === 'wallet') {
        $target = $origin . '/mywallet';
    } elseif ($view === 'project_manager') {
        $target = $origin . '/project_manager';
        if ($board_id > 0) {
            $target .= '?board_id=' . rawurlencode((string)$board_id);
        }
    } elseif ($view === 'switch_account') {
        $target = $origin . '/switch_account';
    }

    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    error_log('cg_desktop_portal_sso: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to complete sign-in. Return to the desktop app and try again.';
    exit;
}
