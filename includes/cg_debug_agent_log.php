<?php
/**
 * Debug NDJSON for agent session (no secrets / no PII in $data).
 */
function cg_debug_agent_log(string $hypothesisId, string $location, string $message, array $data = []): void {
    $paths = [];
    if (defined('CG_DEBUG_LOG_PATH') && is_string(CG_DEBUG_LOG_PATH) && CG_DEBUG_LOG_PATH !== '') {
        $paths[] = CG_DEBUG_LOG_PATH;
    }
    $incDir = __DIR__;
    $paths[] = dirname($incDir, 2) . '/.cursor/debug-99c215.log';
    $paths[] = $incDir . '/../../.cursor/debug-99c215.log';
    $paths[] = '/Users/antlerproduction/Desktop/CINEGRID NET/.cursor/debug-99c215.log';
    $paths = array_values(array_unique(array_filter($paths)));
    $line = json_encode([
        'sessionId' => '99c215',
        'hypothesisId' => $hypothesisId,
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    $written = false;
    foreach ($paths as $p) {
        if ($p === '') {
            continue;
        }
        $dir = dirname($p);
        if (!is_dir($dir)) {
            continue;
        }
        if (@file_put_contents($p, $line . "\n", FILE_APPEND | LOCK_EX) !== false) {
            $written = true;
            break;
        }
    }
    if (!$written) {
        error_log('[cg-debug-99c215] ' . $line);
    }
}
