<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/cg_kanban_switch_account.php';

try {
    cg_kanban_portal_switch_account_and_redirect('/');
} catch (Throwable $e) {
    error_log('kanban switch_account fatal: ' . $e->getMessage());
    header('Location: /', true, 302);
    exit;
}
