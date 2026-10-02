<?php
/**
 * Desktop workspace AI image generation (storyboard cells, etc.)
 */

if (!function_exists('cg_desktop_store_ai_image_binary')) {
    function cg_desktop_store_ai_image_binary(string $binary): string
    {
        global $CG_LEGACY_PUBLIC;
        $legacyBase = isset($CG_LEGACY_PUBLIC) && is_string($CG_LEGACY_PUBLIC) && $CG_LEGACY_PUBLIC !== ''
            ? rtrim($CG_LEGACY_PUBLIC, '/\\')
            : '';
        $kanbanRoot = dirname(__DIR__);
        $candidates = [$kanbanRoot];
        if ($legacyBase !== '') {
            $candidates[] = $legacyBase;
        }
        $filename = 'ws_ai_' . uniqid('', true) . '.png';
        foreach ($candidates as $base) {
            $upload_dir = $base . '/uploads/workspace_images';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }
            if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                continue;
            }
            $path = $upload_dir . '/' . $filename;
            if (@file_put_contents($path, $binary) !== false) {
                return 'uploads/workspace_images/' . $filename;
            }
        }
        throw new RuntimeException('Workspace image directory is not writable.');
    }
}

if (!function_exists('cg_desktop_openai_api_key')) {
    function cg_desktop_openai_api_key(): string
    {
        if (defined('OPENAI_API_KEY') && is_string(OPENAI_API_KEY) && trim(OPENAI_API_KEY) !== '') {
            return OPENAI_API_KEY;
        }

        $candidates = [
            __DIR__ . '/openai_local.php',
            dirname(__DIR__, 2) . '/public_html/includes/openai_local.php',
            dirname(__DIR__) . '/includes/openai_local.php',
        ];
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
        if ($docRoot !== '') {
            $root = rtrim($docRoot, '/\\');
            $candidates[] = $root . '/includes/openai_local.php';
            $candidates[] = dirname($root) . '/public_html/includes/openai_local.php';
            $candidates[] = dirname($root) . '/kanban.cinegrid.net/includes/openai_local.php';
        }
        foreach ($candidates as $local) {
            if (!is_file($local)) {
                continue;
            }
            require_once $local;
            if (defined('OPENAI_API_KEY') && is_string(OPENAI_API_KEY) && trim(OPENAI_API_KEY) !== '') {
                return OPENAI_API_KEY;
            }
        }

        $env = getenv('OPENAI_API_KEY');
        return is_string($env) ? trim($env) : '';
    }
}

if (!function_exists('cg_desktop_workspace_generate_ai_image')) {
    function cg_desktop_workspace_generate_ai_image(PDO $pdo, int $user_id, array $body): array
    {
        require_once __DIR__ . '/cg_workspace_desktop.php';
        require_once __DIR__ . '/cg_workspace_openai_shared.php';

        if (!function_exists('user_can_access_board')) {
            $access = __DIR__ . '/kanban_board_access.php';
            if (is_readable($access)) {
                require_once $access;
            }
        }
        if (!function_exists('user_can_access_board')) {
            $polyfill = dirname(__DIR__, 2) . '/storyboard.cinegrid.net/includes/cg_storyboard_board_access_polyfill.php';
            if (is_readable($polyfill)) {
                require_once $polyfill;
            }
        }
        if (!function_exists('user_can_access_board')) {
            throw new RuntimeException('Board access helper unavailable.', 500);
        }

        $board_id = (int)($body['board_id'] ?? 0);
        if ($board_id <= 0 || !user_can_access_board($pdo, $board_id, $user_id)) {
            throw new RuntimeException('Board not found', 404);
        }

        $cpStmt = $pdo->prepare('SELECT cine_points FROM users WHERE id = ? LIMIT 1');
        $cpStmt->execute([$user_id]);
        if ((int)($cpStmt->fetchColumn() ?: 0) < 1) {
            throw new RuntimeException('You have exhausted your CinePoints for AI images.', 403);
        }

        $prompt = trim((string)($body['prompt'] ?? ''));
        if ($prompt === '') {
            throw new InvalidArgumentException('Missing prompt');
        }
        if (function_exists('mb_substr')) {
            $prompt = mb_substr($prompt, 0, 4000);
        } else {
            $prompt = substr($prompt, 0, 4000);
        }
        $userPromptBeforeVision = $prompt;

        $storyboardSketchMode = workspace_normalize_storyboard_sketch_mode($body['storyboard_sketch_mode'] ?? '');
        $storyboardPanel = workspace_openai_is_storyboard_panel_request($body);
        $sketchDataUrl = cg_desktop_reference_sketch_data_url($body);
        $referenceUrl = workspace_sanitize_reference_image_url((string)($body['reference_image_url'] ?? ''));
        $visionDetail = workspace_openai_resolve_image_detail($body['image_detail'] ?? null);
        $refStrength = max(0, min(100, (int)($body['reference_strength'] ?? 50)));

        $apiKey = cg_desktop_openai_api_key();
        if ($apiKey === '') {
            throw new RuntimeException('AI image generation is not configured on the server.', 500);
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('AI image generation is unavailable on this server.', 500);
        }

        $visionImage = $sketchDataUrl !== '' ? $sketchDataUrl : $referenceUrl;
        if ($visionImage !== '') {
            $expanded = workspace_openai_expand_prompt_with_vision(
                $apiKey,
                $prompt,
                $visionImage,
                $visionDetail,
                $refStrength,
                $storyboardSketchMode
            );
            if (!empty($expanded['ok'])) {
                $prompt = (string)$expanded['prompt'];
            }
            $prompt = trim($prompt . ' ' . workspace_reference_strength_prompt_append($refStrength) . ' ' . workspace_no_text_in_image_prompt_append($userPromptBeforeVision));
            if ($storyboardSketchMode !== '') {
                $prompt = trim(
                    workspace_storyboard_sketch_mode_prompt_lock($storyboardSketchMode)
                    . $prompt
                    . workspace_storyboard_sketch_mode_negative_append($storyboardSketchMode)
                );
            }
            if ($storyboardPanel) {
                $prompt = trim(workspace_storyboard_panel_aspect_prompt_lock() . $prompt);
            }
            if (function_exists('mb_substr')) {
                $prompt = mb_substr($prompt, 0, 4000);
            } else {
                $prompt = substr($prompt, 0, 4000);
            }
        } else {
            $prompt = trim($prompt . ' ' . workspace_no_text_in_image_prompt_append($userPromptBeforeVision));
            if ($storyboardSketchMode !== '') {
                $prompt = trim(
                    workspace_storyboard_sketch_mode_prompt_lock($storyboardSketchMode)
                    . $prompt
                    . workspace_storyboard_sketch_mode_negative_append($storyboardSketchMode)
                );
            }
            if ($storyboardPanel) {
                $prompt = trim(workspace_storyboard_panel_aspect_prompt_lock() . $prompt);
            }
            if (function_exists('mb_substr')) {
                $prompt = mb_substr($prompt, 0, 4000);
            } else {
                $prompt = substr($prompt, 0, 4000);
            }
        }

        $x = (float)($body['x'] ?? 0);
        $y = (float)($body['y'] ?? 0);
        $width = (float)($body['width'] ?? 340);
        $height = (float)($body['height'] ?? 260);
        $z_index = (int)($body['z_index'] ?? 0);

        if ($sketchDataUrl !== '' && $refStrength > 30) {
            $imageModel = 'gpt-image-1';
            $imageSize = workspace_openai_resolve_image_size_for_request($imageModel, $body['size'] ?? null, $body);
            $ch = curl_init('https://api.openai.com/v1/images/edits');
            $payload = [
                'model' => $imageModel,
                'prompt' => $prompt,
                'size' => $imageSize,
                'quality' => 'low',
                'n' => 1,
                'images' => [['image_url' => $sketchDataUrl]],
            ];
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 180,
            ]);
        } else {
            $imageModel = workspace_openai_resolve_image_model($body['model'] ?? null);
            $imageSize = workspace_openai_resolve_image_size_for_request($imageModel, $body['size'] ?? null, $body);
            $ch = curl_init('https://api.openai.com/v1/images/generations');
            $payload = [
                'model' => $imageModel,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $imageSize,
            ];
            if (workspace_openai_is_gpt_image_model($imageModel)) {
                $payload['quality'] = 'low';
            } else {
                $payload['response_format'] = 'b64_json';
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 180,
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($response) || $response === '') {
            throw new RuntimeException('Image generation failed.', 502);
        }
        $apiJson = json_decode($response, true);
        if (!is_array($apiJson)) {
            throw new RuntimeException('Image generation returned invalid JSON.', 502);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = (string)($apiJson['error']['message'] ?? $apiJson['message'] ?? 'Image generation failed.');
            throw new RuntimeException($message, $httpCode >= 400 ? $httpCode : 502);
        }

        $b64 = $apiJson['data'][0]['b64_json'] ?? null;
        if (!is_string($b64) || $b64 === '') {
            throw new RuntimeException('OpenAI did not return image data.', 502);
        }
        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Failed to decode generated image.', 502);
        }

        $deduct = $pdo->prepare('UPDATE users SET cine_points = cine_points - 1 WHERE id = ? AND cine_points > 0');
        $deduct->execute([$user_id]);
        if ($deduct->rowCount() < 1) {
            throw new RuntimeException('You have exhausted your CinePoints for AI images.', 403);
        }
        $balStmt = $pdo->prepare('SELECT cine_points FROM users WHERE id = ? LIMIT 1');
        $balStmt->execute([$user_id]);
        $remaining_cine_points = (int)($balStmt->fetchColumn() ?: 0);

        try {
            $imagePath = cg_desktop_store_ai_image_binary($binary);
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE users SET cine_points = cine_points + 1 WHERE id = ?')->execute([$user_id]);
            throw new RuntimeException($e->getMessage(), 500);
        }

        $meta_json = json_encode([
            'ai_prompt' => $prompt,
            'ai_provider' => 'openai',
            'ai_model' => $imageModel,
        ], JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare("
            INSERT INTO kanban_workspace_items
            (board_id, item_type, title, content, source_url, embed_url, image_path,
             x, y, width, height, rotation, z_index, style_json, meta_json)
            VALUES (?, 'image', '', '', '', '', ?, ?, ?, ?, ?, 0, ?, NULL, ?)
        ");
        $stmt->execute([
            $board_id,
            $imagePath,
            $x,
            $y,
            $width,
            $height,
            $z_index,
            $meta_json !== '' ? $meta_json : null,
        ]);
        $new_id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM kanban_workspace_items WHERE id = ? LIMIT 1');
        $stmt->execute([$new_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->prepare('UPDATE users SET cine_points = cine_points + 1 WHERE id = ?')->execute([$user_id]);
            throw new RuntimeException('Failed to load generated image item', 500);
        }

        return [
            'item' => cg_desktop_normalize_workspace_item($row),
            'cine_points' => $remaining_cine_points,
        ];
    }
}
