<?php
/**
 * CinePoints wallet nav pill (matches cinegrid.net includes/header.php).
 * Requires session; getCurrentUserCinePoints() when available.
 */
if (!isset($cg_cinepoints_nav_balance)) {
    $cg_cinepoints_nav_balance = 0;
    if ((int) ($_SESSION['user_id'] ?? 0) > 0 && function_exists('getCurrentUserCinePoints')) {
        $cg_cinepoints_nav_balance = (int) getCurrentUserCinePoints();
    }
}
if (!isset($cg_cinepoints_nav_href)) {
    $cg_cinepoints_nav_href = 'mywallet.php';
}
?>
<?php if ((int) ($_SESSION['user_id'] ?? 0) > 0): ?>
<a href="<?php echo htmlspecialchars((string) $cg_cinepoints_nav_href, ENT_QUOTES, 'UTF-8'); ?>" id="cinepointsNavPill" class="cinepoints-nav-pill d-flex align-items-center text-decoration-none rounded-pill px-2 py-1 flex-shrink-0" title="CinePoints wallet">
    <i class="fas fa-coins me-1 cinepoints-nav-pill__icon" style="font-size:0.9em;" aria-hidden="true"></i><span class="cinepoints-nav-pill__value" id="cinepointsNavBalanceMain"><?php echo number_format($cg_cinepoints_nav_balance); ?></span>
</a>
<?php endif; ?>
