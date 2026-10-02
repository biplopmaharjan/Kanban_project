<?php
/**
 * Kanban subdomain: shared client timeline (read-only).
 *
 * Canonical markup/logic lives in public_html/client.php. The old rewrite
 * `/client` → ../public_html/client.php often produced 505 on this vhost
 * (wrong docroot / include resolution). This entrypoint chdir()s to the
 * main site tree so client.php’s relative includes resolve correctly.
 */

declare(strict_types=1);

$cgMainPublic = dirname(__DIR__) . '/public_html';
$cgClientScript = $cgMainPublic . '/client.php';

if (!is_file($cgClientScript)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Client timeline is not available (missing main site client script).';
    exit;
}

if (!@chdir($cgMainPublic)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Client timeline is not available (cannot access application directory).';
    exit;
}

require $cgClientScript;
