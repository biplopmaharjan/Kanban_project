<?php
require_once __DIR__ . '/_portal_config.php';

header('Location: ' . CG_PORTAL_SELF_ORIGIN . '/login', true, 302);
exit;
