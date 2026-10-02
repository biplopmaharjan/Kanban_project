<?php
/**
 * Your boards — post-login home (matches kanban-desktop BoardsHome).
 */
require_once __DIR__ . '/auth.php';
$cg_public_root = dirname(__DIR__) . '/public_html';
if (!function_exists('cg_request_kanban_hostname')) {
    require_once $cg_public_root . '/includes/auth.php';
}
if (cg_request_kanban_hostname() !== null) {
    if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL')) {
        define('CG_KANBAN_SUBDOMAIN_PORTAL', true);
    }
}
require_once $cg_public_root . '/includes/db.php';
require_once __DIR__ . '/includes/freelance_projects.php';

cg_require_freelancer_or_linked();
cg_ensure_freelance_tables();

$cg_boards_user_name = trim((string)($_SESSION['user_name'] ?? ''));

function cg_boards_nepal_greeting(string $fullName = ''): string
{
    $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
    $hour = (int) $now->format('G');
    if ($hour >= 5 && $hour < 12) {
        $greeting = 'Good Morning';
    } elseif ($hour >= 12 && $hour < 17) {
        $greeting = 'Good Afternoon';
    } else {
        $greeting = 'Good Evening';
    }
    $fullName = trim($fullName);
    if ($fullName !== '') {
        $parts = preg_split('/\s+/u', $fullName, 2);
        $first = trim((string) ($parts[0] ?? ''));
        if ($first !== '') {
            return $greeting . ' ' . $first . ',';
        }
    }
    return $greeting . ',';
}

$page_title = cg_boards_nepal_greeting($cg_boards_user_name);
$cg_kph_board_menu_actions = false;
$cg_kph_body_extra_class = 'cg-boards-route';

$cg_boards_fk_api = 'kanban_api.php';
$cg_boards_profile_pic_api = 'api/profile_pic.php';
$cg_boards_home_js = __DIR__ . '/includes/cg_boards_home.js';
$cg_boards_home_css = __DIR__ . '/includes/cg_boards_home.css';
$cg_boards_asset_v = max((int) (@filemtime($cg_boards_home_js) ?: 0), (int) (@filemtime($cg_boards_home_css) ?: 0));
$cg_boards_includes_base = cg_portal_includes_base();
$cg_boards_asset_qs = strpos($cg_boards_includes_base, '?') !== false ? '&' : '?';

if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) {
    require_once __DIR__ . '/includes/cg_kanban_portal_header.php';
} else {
    require_once $cg_public_root . '/includes/header.php';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($cg_boards_includes_base, ENT_QUOTES, 'UTF-8'); ?>cg_boards_home.css<?php echo $cg_boards_asset_qs; ?>v=<?php echo (int) $cg_boards_asset_v; ?>">
<div class="cg-boards-page" id="cgBoardsHomeRoot" aria-live="polite"></div>
<script>
window.CG_BOARDS_FK_API = <?php echo json_encode($cg_boards_fk_api, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
window.CG_BOARDS_PROFILE_PIC_API = <?php echo json_encode($cg_boards_profile_pic_api, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
window.CG_BOARDS_USER_NAME = <?php echo json_encode($cg_boards_user_name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars($cg_boards_includes_base, ENT_QUOTES, 'UTF-8'); ?>cg_boards_home.js<?php echo $cg_boards_asset_qs; ?>v=<?php echo (int) $cg_boards_asset_v; ?>" defer></script>
</main>
</body>
</html>
