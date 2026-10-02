<?php
/**
 * Kanban shim — always use public_html/includes/linked_users.php (avoid duplicate function declarations).
 */
if (function_exists('getPrimaryUserId') && function_exists('getLinkedUserIds') && function_exists('linkedUsersTableExists')) {
    return;
}

$paths = [
    dirname(__DIR__) . '/../public_html/includes/linked_users.php',
    dirname(__DIR__, 2) . '/public_html/includes/linked_users.php',
];
if (defined('CG_MAIN_SITE_INCLUDES')) {
    array_unshift($paths, CG_MAIN_SITE_INCLUDES . '/linked_users.php');
}

foreach ($paths as $path) {
    if (is_readable($path)) {
        require_once $path;
        return;
    }
}
