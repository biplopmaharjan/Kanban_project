<?php
/**
 * Loads Google OAuth web client from kanban.json (same folder).
 * Copy kanban.json.example → kanban.json and paste credentials from Google Cloud Console.
 */
function cg_kanban_google_credentials(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $path = __DIR__ . '/kanban.json';
    if (!is_readable($path)) {
        throw new RuntimeException('kanban.json is missing or not readable next to google_login.php');
    }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException('kanban.json is not valid JSON');
    }
    $web = $data['web'] ?? $data;
    if (!is_array($web)) {
        throw new RuntimeException('kanban.json: expected a "web" object');
    }
    $id = trim((string)($web['client_id'] ?? ''));
    $secret = trim((string)($web['client_secret'] ?? ''));
    if ($id === '' || $secret === '') {
        throw new RuntimeException('kanban.json: client_id and client_secret are required');
    }
    $cache = ['client_id' => $id, 'client_secret' => $secret];
    return $cache;
}
