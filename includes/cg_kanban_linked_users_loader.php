<?php
/**
 * Load linked_users helpers from public_html only (never kanban/includes/linked_users.php duplicate).
 */

function cg_kanban_main_site_linked_users_path(): ?string
{
    $candidates = [];
    if (defined('CG_MAIN_SITE_INCLUDES')) {
        $candidates[] = CG_MAIN_SITE_INCLUDES . '/linked_users.php';
    }
    $candidates[] = dirname(__DIR__) . '/../public_html/includes/linked_users.php';
    $candidates[] = dirname(__DIR__, 2) . '/public_html/includes/linked_users.php';

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function cg_kanban_ensure_linked_users_loaded(): bool
{
    if (function_exists('getPrimaryUserId') && function_exists('getLinkedUserIds') && function_exists('switchToLinkedUser')) {
        return true;
    }

    $path = cg_kanban_main_site_linked_users_path();
    if ($path === null) {
        return false;
    }

    require_once $path;
    return function_exists('getPrimaryUserId') && function_exists('getLinkedUserIds');
}
