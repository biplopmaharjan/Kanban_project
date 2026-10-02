<?php
/**
 * Kanban subdomain document root: main app lives in this directory (sibling to public_html).
 */
if (!is_file(__DIR__ . '/kanban.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Kanban app not found.');
}
if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL')) {
    define('CG_KANBAN_SUBDOMAIN_PORTAL', true);
}

$board_id = isset($_GET['board_id']) ? (int) $_GET['board_id'] : 0;
$card_id = isset($_GET['card_id']) ? (int) $_GET['card_id'] : 0;
if ($board_id <= 0 && $card_id <= 0) {
    require __DIR__ . '/boards.php';
    exit;
}
require __DIR__ . '/kanban.php';
