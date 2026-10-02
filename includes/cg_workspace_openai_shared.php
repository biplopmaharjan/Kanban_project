<?php
/**
 * Shared OpenAI workspace helpers (reference vision + image generation).
 * Extracted from api/freelance_kanban.php for desktop + web reuse.
 */

if (!function_exists('workspace_env_string')) {
    function workspace_env_string(string $key): string
    {
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
}

if (!function_exists('workspace_openai_is_gpt_image_model')) {
    function workspace_openai_is_gpt_image_model(string $model): bool
    {
        static $gpt = ['gpt-image-1' => true, 'gpt-image-1-mini' => true, 'gpt-image-1.5' => true];
        return isset($gpt[$model]);
    }
}

if (!function_exists('workspace_openai_resolve_vision_model')) {
    function workspace_openai_resolve_vision_model(): string
    {
        if (defined('OPENAI_VISION_MODEL')) {
            $c = trim((string)constant('OPENAI_VISION_MODEL'));
            if ($c !== '') {
                return $c;
            }
        }
        $env = trim(workspace_env_string('OPENAI_VISION_MODEL'));
        if ($env !== '') {
            return $env;
        }
        return 'gpt-4.1-mini';
    }
}

if (!function_exists('workspace_openai_resolve_image_model')) {
    function workspace_openai_resolve_image_model(?string $requested): string
    {
        $allowed = [
            'dall-e-2' => true,
            'dall-e-3' => true,
            'gpt-image-1' => true,
            'gpt-image-1-mini' => true,
            'gpt-image-1.5' => true,
        ];
        $default = 'gpt-image-1';
        $req = trim((string)($requested ?? ''));
        if ($req !== '' && isset($allowed[$req])) {
            return $req;
        }
        return $default;
    }
}

if (!function_exists('workspace_openai_storyboard_panel_size')) {
    /** Landscape storyboard shot box — matches panel UI (16:9). */
    function workspace_openai_storyboard_panel_size(): string
    {
        return '1536x1024';
    }
}

if (!function_exists('workspace_openai_is_storyboard_panel_request')) {
    /** True only when Storyboard Creator explicitly requests a panel (not Kanban workspace). */
    function workspace_openai_is_storyboard_panel_request(array $body): bool
    {
        return !empty($body['storyboard_panel']);
    }
}

if (!function_exists('workspace_storyboard_panel_aspect_prompt_lock')) {
    function workspace_storyboard_panel_aspect_prompt_lock(): string
    {
        return 'MANDATORY FRAME LOCK: wide horizontal landscape storyboard panel, strict 16:9 widescreen aspect ratio, cinematic horizontal composition, never portrait, never vertical, never square, never 3:4, never 4:5, never 5:4. ';
    }
}

if (!function_exists('workspace_openai_resolve_image_size_for_request')) {
    function workspace_openai_resolve_image_size_for_request(string $model, ?string $requested, array $body = []): string
    {
        if (workspace_openai_is_storyboard_panel_request($body)) {
            return workspace_openai_resolve_image_size($model, workspace_openai_storyboard_panel_size());
        }
        return workspace_openai_resolve_image_size($model, $requested);
    }
}

if (!function_exists('workspace_openai_resolve_image_size')) {
    function workspace_openai_resolve_image_size(string $model, ?string $requested): string
    {
        if ($model === 'dall-e-2') {
            $allowed = ['256x256' => true, '512x512' => true, '1024x1024' => true];
        } elseif (workspace_openai_is_gpt_image_model($model)) {
            $allowed = ['1024x1024' => true, '1024x1536' => true, '1536x1024' => true, 'auto' => true];
        } else {
            $allowed = ['1024x1024' => true, '1024x1792' => true, '1792x1024' => true];
        }
        $req = trim((string)($requested ?? ''));
        if ($req !== '' && isset($allowed[$req])) {
            return $req;
        }
        return '1024x1024';
    }
}

if (!function_exists('workspace_openai_resolve_image_detail')) {
    function workspace_openai_resolve_image_detail(?string $requested): string
    {
        $allowed = ['low' => true, 'high' => true, 'original' => true, 'auto' => true];
        $req = strtolower(trim((string)($requested ?? '')));
        if ($req !== '' && isset($allowed[$req])) {
            return $req;
        }
        return 'auto';
    }
}

if (!function_exists('workspace_sanitize_reference_image_url')) {
    function workspace_sanitize_reference_image_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strncasecmp($url, 'https://', 8) !== 0) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || strtolower((string)$parts['scheme']) !== 'https') {
            return '';
        }
        $host = isset($parts['host']) ? strtolower((string)$parts['host']) : '';
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return '';
        }
        return $url;
    }
}

if (!function_exists('workspace_prompt_explicitly_requests_image_text')) {
    function workspace_prompt_explicitly_requests_image_text(string $prompt): bool
    {
        $t = strtolower(trim($prompt));
        if ($t === '') {
            return false;
        }
        return (bool)preg_match(
            '/\b(?:with\s+(?:the\s+)?(?:name|text|words|letters|caption|label)|dialogue\s+bubble|speech\s+bubble|lettering|typography)\b/',
            $t
        );
    }
}

if (!function_exists('workspace_no_text_in_image_prompt_append')) {
    function workspace_no_text_in_image_prompt_append(string $userPrompt): string
    {
        if (workspace_prompt_explicitly_requests_image_text($userPrompt)) {
            return '';
        }
        return 'CRITICAL: the image must contain zero text — no letters, words, numbers, captions, labels, name plates, or character names drawn on the art';
    }
}

if (!function_exists('workspace_reference_strength_prompt_append')) {
    function workspace_reference_strength_prompt_append(int $strength): string
    {
        $n = max(0, min(100, $strength));
        if ($n <= 10) {
            $tier = 'ignore the reference image; use only the written prompt';
        } elseif ($n <= 30) {
            $tier = 'use the reference image loosely for broad subject/theme only';
        } elseif ($n <= 70) {
            $tier = 'use the reference image as a moderate guide';
        } else {
            $tier = 'strongly follow the reference image; preserve subject placement, camera angle, pose, and layout';
        }
        return $tier . ', based on the provided reference image';
    }
}

if (!function_exists('workspace_reference_strength_vision_instruction')) {
    function workspace_reference_strength_vision_instruction(int $strength): string
    {
        $n = max(0, min(100, $strength));
        if ($n <= 10) {
            return 'Reference strength is ' . $n . '/100. Ignore the reference image completely.';
        }
        if ($n <= 30) {
            return 'Reference strength is ' . $n . '/100. Use the image only for loose subject/theme hints.';
        }
        if ($n <= 70) {
            return 'Reference strength is ' . $n . '/100. Use the reference as a moderate guide.';
        }
        return 'Reference strength is ' . $n . '/100. Use the reference strongly: preserve composition, pose, and layout.';
    }
}

if (!function_exists('workspace_normalize_storyboard_sketch_mode')) {
    function workspace_normalize_storyboard_sketch_mode($raw): string
    {
        $m = strtolower(trim((string)$raw));
        return ($m === 'detailed' || $m === 'light') ? $m : '';
    }
}

if (!function_exists('workspace_storyboard_sketch_mode_vision_instruction')) {
    function workspace_storyboard_sketch_mode_vision_instruction(string $mode): string
    {
        if ($mode === 'light') {
            return ' STORYBOARD SKETCH MODE: LIGHT. Loose gesture pencil sketch with pale grey lines on white paper.';
        }
        if ($mode === 'detailed') {
            return ' STORYBOARD SKETCH MODE: DETAILED. Darker monochrome pencil storyboard with cross-hatching.';
        }
        return '';
    }
}

if (!function_exists('workspace_storyboard_sketch_mode_prompt_lock')) {
    function workspace_storyboard_sketch_mode_prompt_lock(string $mode): string
    {
        if ($mode === 'light') {
            return 'MANDATORY STYLE LOCK: light loose gesture storyboard pencil sketch on pure white paper. ';
        }
        if ($mode === 'detailed') {
            return 'MANDATORY STYLE LOCK: darker detailed monochrome pencil storyboard on white paper. ';
        }
        return '';
    }
}

if (!function_exists('workspace_storyboard_sketch_mode_negative_append')) {
    function workspace_storyboard_sketch_mode_negative_append(string $mode): string
    {
        if ($mode === 'light') {
            return ' Avoid heavy shadows, deep black fills, dense cross-hatching.';
        }
        return '';
    }
}

if (!function_exists('workspace_openai_responses_extract_output_text')) {
    function workspace_openai_responses_extract_output_text(array $apiJson): string
    {
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
}

if (!function_exists('workspace_openai_expand_prompt_with_vision')) {
    function workspace_openai_expand_prompt_with_vision(
        string $apiKey,
        string $userPrompt,
        string $imageUrl,
        string $detail,
        int $referenceStrength = 50,
        string $storyboardSketchMode = ''
    ): array {
        $model = workspace_openai_resolve_vision_model();
        $sketchMode = workspace_normalize_storyboard_sketch_mode($storyboardSketchMode);
        $sketchVision = workspace_storyboard_sketch_mode_vision_instruction($sketchMode);
        $instructions = 'You write a single detailed English prompt for DALL·E image generation. '
            . workspace_reference_strength_vision_instruction($referenceStrength)
            . $sketchVision
            . ' Output only the final prompt text — no title, no quotes, no markdown.';
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
                    ['type' => 'input_text', 'text' => workspace_reference_strength_vision_instruction($referenceStrength) . $sketchVision . "\n\nUser prompt:\n" . $userPrompt],
                    $imageBlock,
                ],
            ]],
            'max_output_tokens' => 1200,
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
        if ($rawBody === false || !is_array($apiJson = json_decode((string)$rawBody, true))) {
            return ['ok' => false, 'prompt' => $userPrompt, 'error' => 'json'];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'prompt' => $userPrompt, 'error' => 'http'];
        }
        $text = workspace_openai_responses_extract_output_text($apiJson);
        if ($text === '') {
            return ['ok' => false, 'prompt' => $userPrompt, 'error' => 'empty'];
        }
        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, 4000);
        } else {
            $text = substr($text, 0, 4000);
        }
        return ['ok' => true, 'prompt' => $text];
    }
}

if (!function_exists('cg_desktop_reference_sketch_data_url')) {
    function cg_desktop_reference_sketch_data_url(array $body): string
    {
        $b64 = trim((string)($body['reference_sketch_base64'] ?? ''));
        if ($b64 === '') {
            return '';
        }
        $mime = strtolower(trim((string)($body['reference_sketch_mime'] ?? 'image/png')));
        if ($mime !== 'image/png' && $mime !== 'image/jpeg') {
            $mime = 'image/png';
        }
        $raw = base64_decode($b64, true);
        if ($raw === false || $raw === '' || strlen($raw) > 4 * 1024 * 1024) {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }
}
