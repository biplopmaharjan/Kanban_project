<?php
/**
 * Shared bootstrap for api/desktop — resolves db.php the same way as auth.php / freelance_kanban.php.
 */

if (!defined('CG_KANBAN_ROOT')) {
    define('CG_KANBAN_ROOT', dirname(__DIR__));
}

if (!defined('CG_MAIN_INCLUDES')) {
    $candidates = [
        dirname(CG_KANBAN_ROOT) . '/public_html/includes',
        CG_KANBAN_ROOT . '/../public_html/includes',
        dirname(CG_KANBAN_ROOT, 2) . '/public_html/includes',
    ];
    $resolved = null;
    foreach ($candidates as $dir) {
        $real = realpath($dir);
        $check = $real !== false ? $real : $dir;
        if (is_file($check . '/db.php')) {
            $resolved = $check;
            break;
        }
    }
    if ($resolved === null) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Server configuration error: database bootstrap not found.',
        ]);
        exit;
    }
    define('CG_MAIN_INCLUDES', $resolved);
}

if (!defined('CG_MAIN_SITE_INCLUDES')) {
    define('CG_MAIN_SITE_INCLUDES', CG_MAIN_INCLUDES);
}

require_once CG_MAIN_INCLUDES . '/db.php';
