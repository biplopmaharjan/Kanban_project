<?php
/**
 * Serves *.css / *.js from this vhost only: kanban.cinegrid.net/includes/
 * (no public_html path — Kanban portal assets are self-contained under this docroot.)
 */
declare(strict_types=1);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$f = isset($_GET['f']) && is_string($_GET['f']) ? basename($_GET['f']) : '';
if ($f === '' || !preg_match('/\A[A-Za-z0-9_.\-]+\.(css|js)\z/', $f)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad request';
    exit;
}

$kanbanRoot = __DIR__;
$envDir = getenv('CG_KANBAN_INCLUDES_DIR');
$candidates = [];
if (is_string($envDir) && $envDir !== '') {
    $candidates[] = $envDir;
}
$candidates[] = $kanbanRoot . DIRECTORY_SEPARATOR . 'includes';

$real = null;
foreach ($candidates as $c) {
    if ($c === '') {
        continue;
    }
    $r = realpath($c);
    if ($r === false || !is_dir($r)) {
        continue;
    }
    $p = $r . DIRECTORY_SEPARATOR . $f;
    $rf = realpath($p);
    if ($rf !== false && is_file($rf)) {
        $incDirNorm = rtrim(str_replace('\\', '/', $r), '/');
        $realNorm = str_replace('\\', '/', $rf);
        if (stripos($realNorm, $incDirNorm . '/') === 0) {
            $real = $rf;
            break;
        }
    }
}

if ($real === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    error_log('serve_include_static: missing ' . $f . ' (kanbanRoot=' . $kanbanRoot . ')');
    echo 'Not found';
    exit;
}

$mime = (substr($f, -4) === '.css')
    ? 'text/css; charset=utf-8'
    : 'application/javascript; charset=utf-8';
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
readfile($real);
