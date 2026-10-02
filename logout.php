<?php
/**
 * Kanban subdomain logout — clear session and return to /login (not index.php → / loop).
 */
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['user_id'])) {
    try {
        require_once dirname(__DIR__) . '/public_html/includes/db.php';
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, user_name, user_email, user_role, action_type, action_name, action_details, page_url, ip_address, device_type, browser, os, platform, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['user_name'] ?? 'Unknown',
            $_SESSION['user_email'] ?? 'Unknown',
            $_SESSION['user_role'] ?? 'customer',
            'user_action',
            'User Logout',
            'User logged out (Kanban portal)',
            'logout.php',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'Unknown',
            'Unknown',
            'Unknown',
            'cinegrid',
        ]);
    } catch (Throwable $e) {
        // activity log is best-effort
    }
}

if (function_exists('logoutUser')) {
    logoutUser();
} else {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

header('Location: /login', true, 302);
exit;
