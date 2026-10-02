<?php
/**
 * Kanban desktop API entry (kanban subdomain root).
 * Upload to kanban docroot — avoids /api/ → public_html proxy in .htaccess.
 */
require __DIR__ . '/api/desktop/index.php';
