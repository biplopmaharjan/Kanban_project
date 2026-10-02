<?php
/**
 * AI character description from portrait/reference (vision).
 * Used by api/workspace_character_describe.php and api/freelance_kanban.php.
 */

if (!function_exists('cg_workspace_character_describe_request')) {

function cg_wscd_env_string(string $key): string {
    $value = getenv($key);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }
    if (isset($_ENV[$key]) && is_string($_ENV[$key]) && trim($_ENV[$key]) !== '') {
        return trim($_ENV[$key]);
    }
    if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && trim($_SERVER[$key]) !== '') {
        return trim($_SERVER[$key]);
    }
    return '';
}

function cg_wscd_openai_constant_key(): string {
    if (!defined('OPENAI_API_KEY')) {
        return '';
    }
    $v = constant('OPENAI_API_KEY');
    return is_string($v) && trim($v) !== '' ? trim($v) : '';
}

function cg_wscd_load_openai_local_config(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $candidates = [
        dirname(__DIR__) . '/includes/openai_local.php',
        dirname(__DIR__, 2) . '/public_html/includes/openai_local.php',
    ];
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string)$_SERVER['DOCUMENT_ROOT'] : '';
    if ($docRoot !== '') {
        $candidates[] = rtrim($docRoot, '/\\') . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'openai_local.php';
    }
    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            require_once $path;
            return;
        }
    }
}

function cg_wscd_openai_api_key(): string {
    $k = cg_wscd_openai_constant_key();
    if ($k !== '') {
        return $k;
    }
    cg_wscd_load_openai_local_config();
    $k = cg_wscd_openai_constant_key();
    if ($k !== '') {
        return $k;
    }
    return cg_wscd_env_string('OPENAI_API_KEY');
}

function cg_wscd_openai_resolve_vision_model(): string {
    if (defined('OPENAI_VISION_MODEL')) {
        $c = trim((string)constant('OPENAI_VISION_MODEL'));
        if ($c !== '') {
            return $c;
        }
    }
    $env = trim(cg_wscd_env_string('OPENAI_VISION_MODEL'));
    if ($env !== '') {
        return $env;
    }
    return 'gpt-4.1-mini';
}

function cg_wscd_openai_resolve_image_detail(?string $requested): string {
    $allowed = ['low' => true, 'high' => true, 'original' => true, 'auto' => true];
    $req = strtolower(trim((string)($requested ?? '')));
    if ($req !== '' && isset($allowed[$req])) {
        return $req;
    }
    if (defined('OPENAI_VISION_IMAGE_DETAIL')) {
        $c = strtolower(trim((string)constant('OPENAI_VISION_IMAGE_DETAIL')));
        if ($c !== '' && isset($allowed[$c])) {
            return $c;
        }
    }
    $env = strtolower(trim(cg_wscd_env_string('OPENAI_VISION_IMAGE_DETAIL')));
    if ($env !== '' && isset($allowed[$env])) {
        return $env;
    }
    return 'auto';
}

function cg_wscd_sanitize_reference_image_url(string $url): string {
    $url = trim($url);
    if ($url === '' || strncasecmp($url, 'https://', 8) !== 0) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || strtolower((string)$parts['scheme']) !== 'https') {
        return '';
    }
    $host = isset($parts['host']) ? strtolower((string)$parts['host']) : '';
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '0.0.0.0' || $host === '::1') {
        return '';
    }
    if (strlen($host) >= 6 && substr($host, -6) === '.local') {
        return '';
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ipFlags = FILTER_FLAG_NO_PRIV_RANGE;
        if (defined('FILTER_FLAG_NO_RES_RANGE')) {
            $ipFlags |= FILTER_FLAG_NO_RES_RANGE;
        }
        if (!filter_var($host, FILTER_VALIDATE_IP, $ipFlags)) {
            return '';
        }
    }
    return $url;
}

function cg_wscd_workspace_image_public_url(string $imagePath): string {
    if (function_exists('workspace_workspace_image_public_url')) {
        return workspace_workspace_image_public_url($imagePath);
    }
    $raw = trim(str_replace(["\0", '\\'], '', $imagePath));
    if ($raw === '') {
        return '';
    }
    if (preg_match('#\Ahttps?://#i', $raw)) {
        $parsed = parse_url($raw);
        if (is_array($parsed) && isset($parsed['path'])) {
            $relFromUrl = ltrim((string)$parsed['path'], '/');
            if (function_exists('workspace_resolve_workspace_image_rel_path')) {
                $resolved = workspace_resolve_workspace_image_rel_path($relFromUrl);
                if ($resolved !== '' && function_exists('workspace_workspace_image_api_url_for_rel')) {
                    $api = workspace_workspace_image_api_url_for_rel($resolved);
                    if ($api !== '') {
                        return $api;
                    }
                }
            }
        }
        return $raw;
    }
    $rel = ltrim($raw, '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return '';
    }
    if (function_exists('workspace_workspace_image_public_url')) {
        return workspace_workspace_image_public_url($rel);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $kanbanUrlBase = $host !== '' ? ($scheme . '://' . $host) : (defined('CG_KANBAN_APP_ORIGIN') ? rtrim((string)CG_KANBAN_APP_ORIGIN, '/') : 'https://kanban.cinegrid.net');
    if (strpos($rel, 'uploads/workspace_images/') === 0) {
        return rtrim($kanbanUrlBase, '/') . '/api/workspace_image?' . http_build_query(['path' => $rel], '', '&', PHP_QUERY_RFC3986);
    }
    return rtrim($kanbanUrlBase, '/') . '/' . $rel;
}

function cg_wscd_process_reference_sketch_upload(): string {
    if (empty($_FILES['reference_sketch']) || !is_array($_FILES['reference_sketch'])) {
        return '';
    }
    $err = (int)($_FILES['reference_sketch']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp = (string)($_FILES['reference_sketch']['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return '';
    }
    $size = (int)@filesize($tmp);
    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        return '';
    }
    $info = @getimagesize($tmp);
    if ($info === false || empty($info['mime'])) {
        return '';
    }
    $mime = strtolower((string)$info['mime']);
    if ($mime !== 'image/png' && $mime !== 'image/jpeg') {
        return '';
    }
    $raw = @file_get_contents($tmp);
    if ($raw === false || $raw === '') {
        return '';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function cg_wscd_local_image_path_to_data_url(string $imagePath): string {
    global $CG_LEGACY_PUBLIC, $CG_KANBAN_ROOT;
    $rel = ltrim(trim(str_replace(["\0", '\\'], '', $imagePath)), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return '';
    }
    $sep = DIRECTORY_SEPARATOR;
    $subPath = str_replace('/', $sep, $rel);
    $leg = isset($CG_LEGACY_PUBLIC) && is_string($CG_LEGACY_PUBLIC) ? rtrim($CG_LEGACY_PUBLIC, '/\\') : '';
    $kan = isset($CG_KANBAN_ROOT) && is_string($CG_KANBAN_ROOT) ? rtrim($CG_KANBAN_ROOT, '/\\') : '';
    $paths = [];
    if ($kan !== '') {
        $paths[] = $kan . $sep . $subPath;
    }
    if ($leg !== '') {
        $paths[] = $leg . $sep . $subPath;
    }
    $filePath = '';
    foreach ($paths as $p) {
        if (is_file($p)) {
            $filePath = $p;
            break;
        }
    }
    if ($filePath === '') {
        return '';
    }
    $size = (int)@filesize($filePath);
    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        return '';
    }
    $info = @getimagesize($filePath);
    if ($info === false || empty($info['mime'])) {
        return '';
    }
    $mime = strtolower((string)$info['mime']);
    if ($mime !== 'image/png' && $mime !== 'image/jpeg' && $mime !== 'image/webp' && $mime !== 'image/gif') {
        return '';
    }
    $raw = @file_get_contents($filePath);
    if ($raw === false || $raw === '') {
        return '';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function cg_wscd_resolve_vision_image_input(): string {
    $sketch = cg_wscd_process_reference_sketch_upload();
    if ($sketch !== '') {
        return $sketch;
    }
    $url = cg_wscd_sanitize_reference_image_url((string)($_POST['reference_image_url'] ?? ''));
    if ($url !== '') {
        return $url;
    }
    $path = trim((string)($_POST['image_path'] ?? ''));
    if ($path === '') {
        return '';
    }
    $local = cg_wscd_local_image_path_to_data_url($path);
    if ($local !== '') {
        return $local;
    }
    return cg_wscd_sanitize_reference_image_url(cg_wscd_workspace_image_public_url($path));
}

function cg_wscd_responses_extract_output_text(array $apiJson): string {
    $out = $apiJson['output'] ?? null;
    if (!is_array($out)) {
        return '';
    }
    $parts = [];
    foreach ($out as $item) {
        if (!is_array($item) || ($item['type'] ?? '') !== 'message') {
            continue;
        }
        $content = $item['content'] ?? null;
        if (!is_array($content)) {
            continue;
        }
        foreach ($content as $block) {
            if (!is_array($block) || ($block['type'] ?? '') !== 'output_text') {
                continue;
            }
            $t = $block['text'] ?? '';
            if (is_string($t) && $t !== '') {
                $parts[] = $t;
            }
        }
    }
    return trim(implode('', $parts));
}

function cg_wscd_character_description_field_allowlists(): array {
    return [
        'age_range' => ['child', 'teen', 'young adult', 'adult', 'middle-aged', 'senior'],
        'height' => ['very short', 'short', 'average', 'tall', 'very tall'],
        'body_type' => ['slim', 'lean athletic', 'average', 'stocky', 'muscular', 'curvy', 'plus-size'],
    ];
}

function cg_wscd_normalize_character_description_fields(array $raw): array {
    $allow = cg_wscd_character_description_field_allowlists();
    $textKeys = [
        'keywords', 'hair_style', 'hair_color', 'skin_tone', 'facial_features',
        'distinguishing_marks', 'typical_outfit', 'demeanor', 'appearance_notes',
        'suggested_name', 'suggested_role', 'generation_prompt',
    ];
    $out = [];
    foreach ($textKeys as $k) {
        $v = isset($raw[$k]) ? trim((string)$raw[$k]) : '';
        if ($v !== '') {
            $out[$k] = function_exists('mb_substr') ? mb_substr($v, 0, 2000) : substr($v, 0, 2000);
        }
    }
    foreach ($allow as $k => $opts) {
        $v = isset($raw[$k]) ? strtolower(trim((string)$raw[$k])) : '';
        if ($v === '') {
            continue;
        }
        foreach ($opts as $opt) {
            if ($v === strtolower($opt)) {
                $out[$k] = $opt;
                break;
            }
        }
    }
    return $out;
}

function cg_wscd_parse_json_object_from_model_text(string $text): ?array {
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
        $text = trim((string)$m[1]);
    }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($decoded) ? $decoded : null;
}

function cg_wscd_openai_describe_character_from_image(
    string $apiKey,
    string $imageUrl,
    string $detail,
    string $hintName = '',
    string $hintRole = ''
): array {
    $model = cg_wscd_openai_resolve_vision_model();
    $allow = cg_wscd_character_description_field_allowlists();
    $ageOpts = implode('|', $allow['age_range']);
    $heightOpts = implode('|', $allow['height']);
    $bodyOpts = implode('|', $allow['body_type']);
    $hints = '';
    if (trim($hintName) !== '') {
        $hints .= "\nKnown name (do not contradict): " . trim($hintName);
    }
    if (trim($hintRole) !== '') {
        $hints .= "\nKnown role (do not contradict): " . trim($hintRole);
    }
    $instructions = 'You analyze character reference images for film/storyboard production. Describe ONLY what is visible or strongly implied (clothing, hair, face, body, accessories). Do not invent story backstory. Output a single JSON object with these string keys (use empty string if unknown): keywords (1-3 sentence visual summary), age_range (' . $ageOpts . ' or empty), height (' . $heightOpts . ' or empty), body_type (' . $bodyOpts . ' or empty), hair_style, hair_color, skin_tone, facial_features, distinguishing_marks, typical_outfit, demeanor, appearance_notes, generation_prompt (one dense English image-generation prompt for a monochrome black/grey pencil storyboard character portrait — no color, graphite sketch on white paper). Optional: suggested_name, suggested_role only if clearly implied. No markdown, no code fences — raw JSON only.';
    $imageBlock = ['type' => 'input_image', 'image_url' => $imageUrl];
    $detailNorm = strtolower(trim($detail));
    if ($detailNorm !== '' && $detailNorm !== 'auto') {
        $imageBlock['detail'] = $detailNorm;
    }
    $payload = [
        'model' => $model,
        'instructions' => $instructions,
        'input' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'input_text', 'text' => 'Describe this character for a storyboard character bible.' . $hints],
                $imageBlock,
            ],
        ]],
        'max_output_tokens' => 1800,
        'store' => false,
    ];
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
    ]);
    $rawBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($rawBody === false) {
        return ['ok' => false, 'character' => [], 'error' => 'curl'];
    }
    $apiJson = json_decode((string)$rawBody, true);
    if (!is_array($apiJson)) {
        return ['ok' => false, 'character' => [], 'error' => 'json'];
    }
    if (!empty($apiJson['error'])) {
        $err = $apiJson['error'];
        $msg = is_array($err) ? (string)($err['message'] ?? '') : (string)$err;
        error_log('cg_wscd_openai_describe_character_from_image: ' . $msg);
        return ['ok' => false, 'character' => [], 'error' => 'api'];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'character' => [], 'error' => 'http'];
    }
    $status = (string)($apiJson['status'] ?? '');
    if ($status !== '' && $status !== 'completed') {
        return ['ok' => false, 'character' => [], 'error' => 'incomplete'];
    }
    $text = cg_wscd_responses_extract_output_text($apiJson);
    $parsed = cg_wscd_parse_json_object_from_model_text($text);
    if (!is_array($parsed)) {
        return ['ok' => false, 'character' => [], 'error' => 'parse'];
    }
    return ['ok' => true, 'character' => cg_wscd_normalize_character_description_fields($parsed)];
}

function cg_wscd_assert_board_access(PDO $pdo, int $board_id, int $user_id, ?array $workspace_share): void {
    if ($workspace_share !== null && (int)($workspace_share['board_id'] ?? 0) === $board_id) {
        return;
    }
    if (!function_exists('user_can_access_board') || !user_can_access_board($pdo, $board_id, $user_id)) {
        json_fail('Board not found', 404);
    }
}

/**
 * @param array<string, mixed>|null $workspace_share
 */
function cg_workspace_character_describe_request(PDO $pdo, int $user_id, ?array $workspace_share): void {
    if ($workspace_share !== null) {
        json_fail('Sign in to use AI character description.', 403);
    }
    $board_id = (int)($_POST['board_id'] ?? 0);
    if ($board_id <= 0) {
        json_fail('Missing board_id');
    }
    cg_wscd_assert_board_access($pdo, $board_id, $user_id, $workspace_share);

    $visionImage = cg_wscd_resolve_vision_image_input();
    if ($visionImage === '') {
        json_fail('Provide a portrait image (upload a file or use an existing portrait).', 400);
    }

    $apiKey = cg_wscd_openai_api_key();
    if ($apiKey === '') {
        json_fail(
            'AI is not configured. Add includes/openai_local.php (see openai_local.php.example), '
            . 'or set the OPENAI_API_KEY environment variable, or define OPENAI_API_KEY in includes/db.php.',
            503
        );
    }
    if (!function_exists('curl_init')) {
        json_fail('AI is unavailable on this server (curl missing).', 503);
    }

    $visionDetail = cg_wscd_openai_resolve_image_detail($_POST['image_detail'] ?? null);
    $hintName = trim((string)($_POST['character_name'] ?? ''));
    $hintRole = trim((string)($_POST['character_role'] ?? ''));
    $result = cg_wscd_openai_describe_character_from_image($apiKey, $visionImage, $visionDetail, $hintName, $hintRole);
    if (empty($result['ok'])) {
        $err = (string)($result['error'] ?? '');
        if ($err === 'parse') {
            json_fail('Could not read character description from AI. Try again or use a clearer photo.', 502);
        }
        json_fail('AI character description failed. Check your API key and try again.', 502);
    }
    json_ok(['character' => $result['character']]);
}

/**
 * Desktop JSON API — describe character from portrait (base64 or workspace image path).
 *
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function cg_desktop_workspace_describe_character(PDO $pdo, int $user_id, array $body): array {
    $board_id = (int)($body['board_id'] ?? 0);
    if ($board_id <= 0) {
        throw new InvalidArgumentException('Missing board_id');
    }
    if (!function_exists('user_can_access_board') || !user_can_access_board($pdo, $board_id, $user_id)) {
        throw new RuntimeException('Board not found', 404);
    }

    $visionImage = '';
    $image_base64 = trim((string)($body['image_base64'] ?? ''));
    if ($image_base64 !== '') {
        $mime = (string)($body['mime_type'] ?? 'image/jpeg');
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            $mime = 'image/jpeg';
        }
        $visionImage = 'data:' . $mime . ';base64,' . $image_base64;
    } else {
        $path = trim((string)($body['image_path'] ?? ''));
        if ($path !== '') {
            $local = cg_wscd_local_image_path_to_data_url($path);
            if ($local !== '') {
                $visionImage = $local;
            } else {
                $visionImage = cg_wscd_sanitize_reference_image_url(cg_wscd_workspace_image_public_url($path));
            }
        }
    }
    if ($visionImage === '') {
        throw new InvalidArgumentException('Provide a portrait image (upload a file or use an existing portrait).');
    }

    $apiKey = cg_wscd_openai_api_key();
    if ($apiKey === '') {
        throw new RuntimeException(
            'AI is not configured. Add includes/openai_local.php or set OPENAI_API_KEY.',
            503
        );
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('AI is unavailable on this server (curl missing).', 503);
    }

    $visionDetail = cg_wscd_openai_resolve_image_detail($body['image_detail'] ?? null);
    $hintName = trim((string)($body['character_name'] ?? ''));
    $hintRole = trim((string)($body['character_role'] ?? ''));
    $result = cg_wscd_openai_describe_character_from_image($apiKey, $visionImage, $visionDetail, $hintName, $hintRole);
    if (empty($result['ok'])) {
        $err = (string)($result['error'] ?? '');
        if ($err === 'parse') {
            throw new RuntimeException('Could not read character description from AI. Try a clearer photo.', 502);
        }
        throw new RuntimeException('AI character description failed. Check your API key and try again.', 502);
    }
    return ['character' => $result['character']];
}

} // end function_exists guard
