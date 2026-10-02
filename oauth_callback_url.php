<?php
/**
 * Open this URL in the browser and paste the plain text into Google Cloud Console:
 * APIs & Services → Credentials → your Web client → Authorized redirect URIs.
 */
require_once __DIR__ . '/_portal_config.php';
header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
echo CG_PORTAL_GOOGLE_REDIRECT_URI;
