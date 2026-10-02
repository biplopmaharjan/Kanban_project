<?php
/**
 * Load cinegrid.net wallet page CSS on kanban portal (matches public_html/mywallet.php).
 * Set $cg_kph_include_site_wallet_styles = true before cg_kanban_portal_header.php.
 */
if (defined('CG_WALLET_SITE_STYLES_ECHOED')) {
    return;
}
define('CG_WALLET_SITE_STYLES_ECHOED', true);

$cg_wallet_style_href = 'https://cinegrid.net/includes/style.css';
$cg_wallet_skeleton_href = 'https://cinegrid.net/includes/skeleton.css';
?>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700&amp;family=Montserrat:wght@400;600;700;800&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo htmlspecialchars($cg_wallet_style_href, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($cg_wallet_skeleton_href, ENT_QUOTES, 'UTF-8'); ?>">
<style id="cg-wallet-match-cinegrid-styles">
/*
 * Kanban portal shell + cinegrid.net style.css: keep board overflow model, wallet content matches apex mywallet.
 */
body.cg-kanban-portal.cg-wallet-route {
    overflow: hidden !important;
    height: 100% !important;
    max-height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
}
body.cg-kanban-portal.cg-wallet-route main.cg-kanban-portal-main {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    padding-top: var(--cg-kanban-portal-header-h, 60px);
}
body.cg-kanban-portal.cg-wallet-route main.cg-kanban-portal-main > .fmain-content.cg-wallet-page {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    max-width: 100%;
    margin: 0;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    box-sizing: border-box;
    background: #ffffff;
    color: #212529;
    font-family: 'Raleway', 'Segoe UI', Arial, sans-serif;
    padding-bottom: max(2.5rem, env(safe-area-inset-bottom, 0px));
}
body.cg-kanban-portal .cg-wallet-page .container {
    max-width: 640px;
}
body.cg-kanban-portal .cg-wallet-page h1,
body.cg-kanban-portal .cg-wallet-page h2,
body.cg-kanban-portal .cg-wallet-page h3,
body.cg-kanban-portal .cg-wallet-page h4,
body.cg-kanban-portal .cg-wallet-page h5,
body.cg-kanban-portal .cg-wallet-page h6 {
    font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
body.cg-kanban-portal .cg-wallet-page #walletBalanceCp {
    font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
/* Apex mywallet is always light — do not inherit kanban portal dark theme into wallet body */
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page,
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .card,
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .card-body {
    background-color: #ffffff;
    color: #212529;
}
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .card.border {
    border-color: rgba(0, 0, 0, 0.125) !important;
}
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .text-muted {
    color: #6c757d !important;
}
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .form-control,
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .input-group-text {
    background-color: #fff;
    border-color: #ced4da;
    color: #212529;
}
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
}
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: #fff;
}
body.kanban-theme-dark.cg-kanban-portal.cg-wallet-route .cg-wallet-page .transfer-recipient-card {
    background: #fff;
    border-color: #dee2e6 !important;
}
body.kanban-theme-dark.cg-kanban-portal .wallet-transfer-progress-overlay__dialog,
body.kanban-theme-dark.cg-kanban-portal .wallet-transfer-progress-overlay__dialog #walletTransferProgressText {
    background: #fff;
    color: #212529 !important;
}
body.kanban-theme-dark.cg-kanban-portal .wallet-transfer-progress-track {
    background: rgba(0, 0, 0, 0.08);
}
body.cg-kanban-portal .cg-wallet-page .btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}
body.cg-kanban-portal .cg-wallet-page .btn-outline-primary {
    color: #0d6efd;
    border-color: #0d6efd;
}
</style>
