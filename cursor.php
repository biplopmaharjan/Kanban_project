<?php
declare(strict_types=1);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$rawColor = trim((string)($_GET['color'] ?? ''));
if ($rawColor === '') {
    $rawColor = '#60a5fa';
}

// Allow common CSS color formats used by workspace presence colors (including hsl(...deg ...)).
if (!preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|[a-zA-Z]{3,20})$/', $rawColor)) {
    $rawColor = '#60a5fa';
}

$color = htmlspecialchars($rawColor, ENT_QUOTES, 'UTF-8');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="24" viewBox="0 0 18 24" fill="none">
  <path d="M0.0 0.0 L0.0 18.0 L4.9 13.6 L8.7 23.0 L11.5 21.8 L7.8 12.8 L16.0 12.8 Z"
        fill="{$color}" stroke="white" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round"/>
</svg>
SVG;

