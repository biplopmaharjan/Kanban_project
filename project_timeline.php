<?php
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
require_once __DIR__ . '/includes/cg_kanban_board_chat_embed.php';
require_once __DIR__ . '/includes/cg_kanban_bottom_nav.php';

// Docroot bridges — /api/*.php returns empty HTTP 500 on this host.
$cg_freelance_kanban_api_url = 'kanban_api.php';
$cg_freelance_timeline_api_url = 'kanban_timeline_api.php';
$cg_freelance_share_api_url = 'api/freelance_share.php';
$cg_kanban_static_origin = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) ? 'https://cinegrid.net' : '';
/** Sent with share POST so cinegrid.net/api/freelance_share.php builds kanban client links (Origin is often missing cross-origin). */
$cg_client_share_portal_base = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) ? 'https://kanban.cinegrid.net' : '';

cg_require_freelancer();
cg_ensure_freelance_tables();

$page_title = 'Project Timeline';
if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) {
    $cg_kph_board_menu_actions = false;
    $cg_kph_include_board_members_css = true;
    require_once __DIR__ . '/includes/cg_kanban_portal_header.php';
} else {
    require_once $cg_public_root . '/includes/header.php';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_view_menu.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_portal_loading.css">
<?php if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL') || !CG_KANBAN_SUBDOMAIN_PORTAL): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_board_members_ui.css">
<?php endif; ?>
<script src="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_board_tab_title.js"></script>
<script src="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_portal_loading.js"></script>
<?php cg_kanban_board_chat_embed_echo_css_and_user(); ?>
<?php require_once __DIR__ . '/includes/cg_page_scale_80_apply.php'; ?>

<div class="cg-page-scale-80">
<div class="fmain-content cg-timeline-page cg-page--bottom-nav" style="overflow-x: auto; overflow-y: <?php echo (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) ? 'auto' : 'visible'; ?>;">
    <div class="cg-portal-loading-overlay" id="timelineLoadingOverlay" hidden aria-hidden="true">
        <div class="cg-portal-loading-card cg-portal-loading-card--message-only" role="status" aria-live="polite">
            <div class="cg-portal-loading-spinner" aria-hidden="true"></div>
            <div class="cg-portal-loading-subtitle">Loading your board...</div>
        </div>
    </div>
    <div class="container-fluid cg-timeline-container" style="padding: 12px 32px; max-width: 100%; width: 100%;">
        <div class="cg-timeline-header mb-3">
            <div>
                <h2 class="mb-1" id="timelineBoardTitle" style="font-weight:800;">Project Timeline</h2>
                <div class="text-muted" id="timelineBoardSubtitle">Start Date: N/A | End Date: N/A</div>
            </div>
            <div class="cg-timeline-header-actions">
                <div class="cg-board-members-wrap d-flex align-items-center me-2" id="boardMembersWrap">
                    <div class="cg-board-avatars d-flex align-items-center" id="boardMembersAvatars" aria-label="Board collaborators"></div>
                </div>
                <select class="form-select" id="timelineBoardSelect" aria-label="Select board" title="Select board"></select>
                <div class="cg-timeline-trailing-actions">
                    <button class="btn btn-danger" id="shareBtn" type="button" title="Share (client link)" aria-label="Share (client link)"><i class="fas fa-share-alt" aria-hidden="true"></i></button>
                    <?php cg_kanban_echo_header_procurement_button(); ?>
                    <?php if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL') || !CG_KANBAN_SUBDOMAIN_PORTAL): ?>
                    <button class="btn btn-outline-dark" id="themeToggleBtn" type="button" data-kanban-theme-toggle title="Switch to dark mode" aria-label="Switch to dark mode"><i class="fas fa-moon" aria-hidden="true"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="cg-tl">
            <div class="cg-tl__top">
                <div class="cg-tl__legend" id="dynamicLegend">
                    <span class="text-muted">Loading columns...</span>
                </div>
            </div>

            <div id="timelineWrap" class="cg-tl__wrap">
                <div class="cg-tl__freeze" id="timelineFreeze"></div>
                <div class="cg-tl__scroll">
                    <div id="timelineSvgHost"></div>
                </div>
            </div>

            <div class="cg-tl__comments card mt-3" style="border:1px solid rgba(15,23,42,0.08); border-radius:12px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0" style="font-weight:700;">Client Comments</h6>
                        <small class="text-muted">Shared view feedback (read/delete)</small>
                    </div>
                    <div id="ownerCommentList" class="d-flex flex-column gap-2">
                        <div class="text-muted small">Loading comments…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<nav class="cg-bottom-nav" aria-label="Open board in another view">
    <?php cg_kanban_echo_bottom_nav_boards_button(); ?>
    <button type="button" class="cg-bottom-nav__btn" id="cgBottomNavKanban">
        <i class="fas fa-table-columns" aria-hidden="true"></i>
        <span>Kanban</span>
    </button>
    <button type="button" class="cg-bottom-nav__btn is-active" id="cgBottomNavTimeline" aria-current="page">
        <i class="fas fa-diagram-project" aria-hidden="true"></i>
        <span>Timeline</span>
    </button>
    <button type="button" class="cg-bottom-nav__btn" id="cgBottomNavCalendar">
        <i class="fas fa-calendar-days" aria-hidden="true"></i>
        <span>Calendar</span>
    </button>
    <button type="button" class="cg-bottom-nav__btn" id="cgBottomNavWorkspace">
        <i class="fas fa-vector-square" aria-hidden="true"></i>
        <span>Workspace</span>
    </button>
<?php cg_kanban_board_chat_embed_echo_bottom_nav_chat_button(); ?>
</nav>
</div>
<?php cg_kanban_board_chat_embed_echo_panel_and_fab(); ?>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-link me-2"></i>Client link</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted mb-2">Share this link with your client (read-only):</div>
        <input class="form-control" id="shareLink" readonly />
        <div class="d-grid mt-3">
            <button class="btn btn-danger" id="copyShareBtn"><i class="fas fa-copy me-2"></i>Copy link</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* Full-page timeline: no freelance sidebar; site header hidden (body.cg-kanban-fullpage in header) */
body.cg-kanban-fullpage #mainNav {
    transform: translateY(-100%);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.3s ease, opacity 0.3s ease !important;
}
/* Do not zero .container-fluid globally — it flattens the portal nav and removes timeline horizontal inset (inline padding loses to !important). */
body.cg-kanban-fullpage main {
    margin-top: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}
body.cg-kanban-fullpage .fmain-content.cg-timeline-page {
    margin-left: 0 !important;
    margin-top: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    min-height: 100vh;
    box-sizing: border-box;
}
@media (min-width: 769px) {
    body.cg-kanban-fullpage .fmain-content.cg-timeline-page {
        min-height: 100vh;
    }
}
body.cg-kanban-portal.cg-kanban-fullpage .fmain-content.cg-timeline-page {
    flex: 1 1 auto;
    min-height: 0 !important;
    height: auto !important;
    max-height: none !important;
    box-sizing: border-box;
}
/* Match portal top bar horizontal inset (same rhythm as Kanban board). */
body.cg-kanban-portal .cg-timeline-container.container-fluid {
    padding-top: 16px !important;
    padding-bottom: 12px !important;
    padding-left: max(1.25rem, env(safe-area-inset-left, 0px)) !important;
    padding-right: max(1.25rem, env(safe-area-inset-right, 0px)) !important;
}
@media (min-width: 576px) {
    body.cg-kanban-portal .cg-timeline-container.container-fluid {
        padding-left: max(1.5rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(1.5rem, env(safe-area-inset-right, 0px)) !important;
    }
}
@media (min-width: 992px) {
    body.cg-kanban-portal .cg-timeline-container.container-fluid {
        padding-left: max(2rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(2rem, env(safe-area-inset-right, 0px)) !important;
    }
}
body.cg-kanban-portal #timelineBoardTitle,
body.cg-kanban-portal #timelineBoardSubtitle {
    margin-left: 0;
}

.cg-tl {
    background: rgba(255,255,255,0.7);
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 18px;
    padding: 20px;
    width: 100%;
}
.cg-tl__top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
}
.cg-tl__legend { 
    display: flex; 
    gap: 16px; 
    align-items: center; 
    flex-wrap: wrap; 
    padding: 12px 16px;
    background: rgba(248,250,252,0.8);
    border-radius: 12px;
}
.cg-dot { 
    width: 12px; 
    height: 12px; 
    border-radius: 999px; 
    display: inline-block; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.cg-dot--todo { background: #6366f1; }
.cg-dot--doing { background: #f59e0b; }
.cg-dot--done { background: #10b981; }
.cg-leg { 
    font-weight: 700; 
    font-size: 13px; 
    color: #0f172a; 
    margin-right: 4px;
}

.cg-tl__wrap {
    position: relative;
    display: flex;
    align-items: stretch;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(15,23,42,0.08);
    width: 100%;
    min-height: 400px;
    overflow: hidden;
}
.cg-tl__freeze {
    position: sticky;
    left: 0;
    top: 0;
    z-index: 3;
    background: linear-gradient(90deg, #ffffff, rgba(255,255,255,0.96));
    border-right: 1px solid rgba(15,23,42,0.08);
    flex: 0 0 auto;
}
.cg-tl__scroll {
    flex: 1 1 auto;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 20px;
}
.cg-tl__freeze-inner {
    position: relative;
}
.cg-tl__freeze-head {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 16px;
    padding: 10px 16px 6px 16px;
    border-bottom: 1px solid rgba(15,23,42,0.08);
}
.cg-tl__freeze-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 16px;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(15,23,42,0.04);
    background: #ffffff;
}
.cg-tl__freeze-title {
    font-family: "Inter", sans-serif;
    font-weight: 700;
    font-size: 14px;
    color: #0f172a;
}
.cg-tl__freeze-date {
    font-weight: 600;
    font-size: 11px;
    color: #475569;
}
.cg-tl__freeze-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 4px;
    background: rgba(148,163,184,0.12);
    color: #475569;
}
.cg-tl__freeze-progress {
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
}
.cg-svg {
    width: 100%;
    min-width: 1400px;
    max-width: none;
    display: block;
}
.cg-row-label { 
    font: 700 14px/1.3 "Inter", sans-serif; 
    fill: #0f172a; 
}
.cg-row-sub { 
    font: 500 12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; 
    fill: #64748b; 
}
.cg-row-date { 
    font: 600 11px/1.2 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; 
    fill: #475569; 
}
.cg-row-progress { 
    font: 700 11px/1.2 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; 
    fill: #0f172a; 
}
/* Kanban-style collaborator stack (foreignObject in SVG) */
#timelineWrap .cg-tl-assignees {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
#timelineWrap .cg-tl-assignee-avatar,
#timelineWrap .cg-tl-assignee-more {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    border: 2px solid rgba(255,255,255,0.95);
    margin-left: -7px;
    box-shadow: 0 2px 8px rgba(2,6,23,0.12);
    object-fit: cover;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 9px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
#timelineWrap .cg-tl-assignee-avatar:first-child,
#timelineWrap .cg-tl-assignee-more:first-child {
    margin-left: 0;
}
#timelineWrap .cg-tl-assignee-avatar.is-initials {
    background: #dbeafe;
    color: #1d4ed8;
}
#timelineWrap .cg-tl-assignee-more {
    background: #0f172a;
    color: #fff;
}
.cg-grid { 
    stroke: rgba(15,23,42,0.08); 
    stroke-width: 1; 
}
.cg-grid-today { 
    stroke: #e03131; 
    stroke-width: 2; 
    stroke-dasharray: 4,4;
    opacity: 0.6;
}
.cg-bar { 
    rx: 6; 
    ry: 6; 
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    cursor: pointer;
    transition: filter 0.15s ease;
}
.cg-bar:hover {
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)) brightness(0.85);
}
.cg-bar--todo { fill: #6366f1; }
.cg-bar--doing { fill: #f59e0b; }
.cg-bar--done { fill: #10b981; }
.cg-progress-bg {
    fill: rgba(15,23,42,0.05);
    rx: 4;
    ry: 4;
}
.cg-progress-fill {
    fill: #0f172a;
    rx: 4;
    ry: 4;
}
.cg-connector { 
    stroke: rgba(15,23,42,0.20); 
    stroke-width: 2; 
    fill: none; 
    stroke-dasharray: 3,3;
}
.cg-head-text { 
    font: 800 12px/1 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; 
    fill: #334155; 
}
.cg-today-line {
    stroke: #e03131;
    stroke-width: 2;
    stroke-dasharray: 4,4;
    opacity: 0.7;
}

/* SVG chart surface (#timelineWrap) — light defaults */
.cg-svg-bg { fill: #ffffff; }
.cg-svg-header-bg { fill: #f8fafc; }
.cg-progress-path { stroke: #0f172a; }
.cg-timeline-node { stroke: #ffffff; }
.cg-progress-node { stroke: #ffffff; }

.cg-timeline-header {
    width: 100%;
    max-width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 1rem;
}
.cg-timeline-header > div:first-child {
    min-width: 0;
    overflow: hidden;
}
#timelineBoardTitle,
#timelineBoardSubtitle {
    margin-left: 20px;
}
.cg-timeline-header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
    align-items: center;
    margin-left: 0;
}
/* Keep Share + View + theme on one row (avoids Share wrapping above View) */
.cg-timeline-trailing-actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}
.cg-timeline-trailing-actions #shareBtn,
.cg-timeline-trailing-actions #kanbanArtDeptBtn,
.cg-timeline-trailing-actions .cg-view-menu,
.cg-timeline-trailing-actions #themeToggleBtn {
    flex-shrink: 0;
}
/* Match View toggle (cg_view_menu: min-height 38px, vertical padding 0.45rem) */
.cg-timeline-trailing-actions #shareBtn,
.cg-timeline-trailing-actions #kanbanArtDeptBtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    height: 38px;
    width: 38px;
    padding: 0;
    line-height: 1;
    border-radius: 0.5rem;
    box-sizing: border-box;
}
.cg-timeline-trailing-actions #shareBtn i,
.cg-timeline-trailing-actions #kanbanArtDeptBtn i {
    font-size: 0.9375rem;
}
#timelineBoardSelect {
    min-width: 220px;
    max-width: 320px;
}
.cg-timeline-header-actions .cg-view-menu {
    flex-shrink: 0;
}
/* Board members facepile — match Kanban (inline backup + beats global img{height:auto} on portal) */
.cg-timeline-page #boardMembersAvatars.cg-board-avatars {
    display: inline-flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    flex-shrink: 0;
    min-width: 0;
}
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap {
    position: relative;
    margin-left: -8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px !important;
    height: 32px !important;
    max-width: 32px !important;
    max-height: 32px !important;
    overflow: hidden;
    border-radius: 50% !important;
    box-sizing: border-box;
}
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap.is-active-presence {
    --presence-color: #93c5fd;
}
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap:first-child {
    margin-left: 0;
}
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap img.cg-board-avatar {
    width: 32px !important;
    height: 32px !important;
    max-width: 32px !important;
    max-height: 32px !important;
    min-width: 0 !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 2px solid #fff !important;
    background: #e2e8f0;
    flex-shrink: 0;
    box-sizing: border-box;
    display: block;
    box-shadow: inset 0 0 0 3px var(--presence-color, transparent);
}
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap .cg-board-avatar-initials {
    width: 32px !important;
    height: 32px !important;
    max-width: 32px !important;
    max-height: 32px !important;
    border-radius: 50% !important;
    border: 2px solid #fff;
    background: #e03131;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-sizing: border-box;
    box-shadow: inset 0 0 0 3px var(--presence-color, transparent);
}
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap.is-active-presence img.cg-board-avatar,
.cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap.is-active-presence .cg-board-avatar-initials {
    border-width: 3px !important;
    border-color: var(--presence-color, #93c5fd) !important;
}
.kanban-theme-dark .cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap img.cg-board-avatar,
.kanban-theme-dark .cg-timeline-page #boardMembersAvatars .cg-board-avatar-wrap .cg-board-avatar-initials {
    border-color: rgba(30, 41, 59, 0.95) !important;
}

body.cg-kanban-portal.kanban-theme-dark {
    background: #0f172a;
}

.kanban-theme-dark .fmain-content.cg-timeline-page {
    background-color: #0f172a;
}
.kanban-theme-dark #timelineBoardTitle {
    color: #f8fafc !important;
}
.kanban-theme-dark #timelineBoardSubtitle,
.kanban-theme-dark .cg-timeline-page .text-muted {
    color: #94a3b8 !important;
}
.kanban-theme-dark .cg-tl {
    background: rgba(30, 41, 59, 0.72);
    border-color: rgba(148, 163, 184, 0.16);
}
.kanban-theme-dark .cg-tl__legend {
    background: rgba(15, 23, 42, 0.65);
}
.kanban-theme-dark .cg-leg {
    color: #e2e8f0;
}
.kanban-theme-dark .cg-tl__wrap {
    background: rgba(15, 23, 42, 0.55);
    border-color: rgba(148, 163, 184, 0.14);
}
.kanban-theme-dark .cg-tl__freeze {
    background: linear-gradient(90deg, rgba(30, 41, 59, 0.98), rgba(30, 41, 59, 0.92));
    border-right-color: rgba(148, 163, 184, 0.12);
}
.kanban-theme-dark .cg-tl__freeze-row {
    background: rgba(15, 23, 42, 0.75);
}
.kanban-theme-dark .cg-tl__freeze-title {
    color: #f8fafc;
}
.kanban-theme-dark .cg-tl__freeze-date {
    color: #94a3b8;
}
.kanban-theme-dark #themeToggleBtn {
    color: #e2e8f0;
    border-color: rgba(148, 163, 184, 0.28);
    background: rgba(30, 41, 59, 0.72);
}
.kanban-theme-dark #themeToggleBtn:hover,
.kanban-theme-dark #themeToggleBtn:focus-visible {
    color: #f8fafc;
    border-color: rgba(248, 113, 113, 0.34);
    background: rgba(51, 65, 85, 0.9);
}
.kanban-theme-dark #themeToggleBtn i {
    color: #fff !important;
}
.kanban-theme-dark #timelineBoardSelect {
    color: #e2e8f0;
    border-color: rgba(148, 163, 184, 0.28);
    background: rgba(15, 23, 42, 0.55);
}

/* #timelineWrap / SVG — dark mode (inline SVG fills are themed via classes + !important) */
.kanban-theme-dark #timelineWrap.cg-tl__wrap {
    background: #1e293b !important;
    border-color: rgba(148, 163, 184, 0.18) !important;
}
.kanban-theme-dark #timelineWrap .cg-svg-bg {
    fill: #1e293b !important;
}
.kanban-theme-dark #timelineWrap .cg-svg-header-bg {
    fill: #0f172a !important;
}
.kanban-theme-dark #timelineWrap .cg-head-text {
    fill: #cbd5e1 !important;
}
.kanban-theme-dark #timelineWrap .cg-today-label {
    fill: #f87171 !important;
}
.kanban-theme-dark #timelineWrap .cg-row-label {
    fill: #f8fafc !important;
}
.kanban-theme-dark #timelineWrap .cg-tl-assignee-avatar,
.kanban-theme-dark #timelineWrap .cg-tl-assignee-more {
    border-color: rgba(30,41,59,0.98);
}
.kanban-theme-dark #timelineWrap .cg-tl-assignee-avatar.is-initials {
    background: rgba(51,65,85,0.95);
    color: #e2e8f0;
}
.kanban-theme-dark #timelineWrap .cg-tl-assignee-more {
    background: #f1f5f9;
    color: #0f172a;
}
.kanban-theme-dark #timelineWrap .cg-row-date:not(.cg-bar-date-label) {
    fill: #94a3b8 !important;
}
.kanban-theme-dark #timelineWrap .cg-row-progress:not(.cg-row-progress--spark) {
    fill: #e2e8f0 !important;
}
.kanban-theme-dark #timelineWrap .cg-grid {
    stroke: rgba(148, 163, 184, 0.14) !important;
}
.kanban-theme-dark #timelineWrap .cg-grid-today {
    stroke: #f87171 !important;
    opacity: 0.85;
}
.kanban-theme-dark #timelineWrap .cg-progress-bg {
    fill: rgba(30, 41, 59, 0.95) !important;
}
.kanban-theme-dark #timelineWrap .cg-progress-fill {
    fill: #fbbf24 !important;
}
.kanban-theme-dark #timelineWrap .cg-progress-path {
    stroke: rgba(248, 250, 252, 0.35) !important;
}
.kanban-theme-dark #timelineWrap .cg-connector {
    stroke: rgba(148, 163, 184, 0.4) !important;
}
.kanban-theme-dark #timelineWrap .cg-timeline-node {
    stroke: #0f172a !important;
}
.kanban-theme-dark #timelineWrap .cg-progress-node {
    stroke: #0f172a !important;
}
.kanban-theme-dark .cg-tl__scroll {
    background: transparent;
}
</style>

<script>
const CG_FK_API = <?php echo json_encode($cg_freelance_kanban_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_FT_API = <?php echo json_encode($cg_freelance_timeline_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_FS_API = <?php echo json_encode($cg_freelance_share_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_CLIENT_SHARE_PORTAL_BASE = <?php echo json_encode($cg_client_share_portal_base, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_STATIC_ORIGIN = <?php echo json_encode($cg_kanban_static_origin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function cgKanbanResolvePublicUrl(path) {
  if (path == null || path === '') return path;
  const p = String(path).trim();
  if (!p) return p;
  if (/^https?:\/\//i.test(p)) return p;
  const rel = p.startsWith('/') ? p : '/' + p;
  return CG_KANBAN_STATIC_ORIGIN ? CG_KANBAN_STATIC_ORIGIN + rel : rel;
}

let TL = {
  project_id: null,
  chart_id: null,
  charts: [],
  boards: [],
  chart: null,
  tasks: [],
  columns: [],
  board_members: [],
  workspace_presence: [],
  activeMemberIds: [],
  activeMemberColors: {},
  presencePollTimerId: null,
};

function qs(sel, root=document){ return root.querySelector(sel); }

function escapeHtml(str) {
  return (str ?? '').toString()
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

const KANBAN_THEME_STORAGE_KEY = 'kanban_theme_mode';

function applyKanbanTheme(mode) {
  const isDark = mode === 'dark';
  document.body.classList.toggle('kanban-theme-dark', isDark);
  document.querySelectorAll('[data-kanban-theme-toggle]').forEach((toggleBtn) => {
    const labeled = toggleBtn.hasAttribute('data-kanban-theme-toggle-label');
    if (labeled) {
      const label = isDark ? 'Light mode' : 'Dark mode';
      const iconClass = isDark ? 'fa-sun' : 'fa-moon';
      toggleBtn.innerHTML = '<i class="fas ' + iconClass + '" aria-hidden="true"></i><span class="cg-kanban-portal-theme-label">' + label + '</span>';
    } else {
      toggleBtn.innerHTML = isDark
        ? '<i class="fas fa-sun" aria-hidden="true"></i>'
        : '<i class="fas fa-moon" aria-hidden="true"></i>';
    }
    toggleBtn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    toggleBtn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  });
}
function getStoredKanbanTheme() {
  try {
    return localStorage.getItem(KANBAN_THEME_STORAGE_KEY) || 'light';
  } catch (err) {
    return 'light';
  }
}
function toggleKanbanTheme() {
  const nextMode = document.body.classList.contains('kanban-theme-dark') ? 'light' : 'dark';
  applyKanbanTheme(nextMode);
  try {
    localStorage.setItem(KANBAN_THEME_STORAGE_KEY, nextMode);
  } catch (err) {}
}

async function fetchKanbanJson(url) {
  const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    data = null;
  }
  if (!res.ok) {
    const message = (data && data.message) ? data.message : (raw.substring(0, 180) || `HTTP ${res.status}`);
    throw new Error(message);
  }
  if (!data.success) {
    throw new Error(data.message || 'Request failed');
  }
  return data;
}

function parseBoardDate(dateStr) {
  const normalized = String(dateStr || '').trim();
  if (!normalized) return null;
  const d = new Date(normalized);
  if (isNaN(d.getTime())) return null;
  d.setHours(0, 0, 0, 0);
  return d;
}
function formatBoardSummaryDate(date) {
  if (!(date instanceof Date) || isNaN(date.getTime())) return 'N/A';
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}
function buildBoardDateSummary(cardsByColumn) {
  let minDate = null;
  let maxDate = null;
  Object.values(cardsByColumn || {}).forEach((cards) => {
    (cards || []).forEach((card) => {
      [card.start_date, card.due_date].forEach((dateStr) => {
        const date = parseBoardDate(dateStr);
        if (!date) return;
        if (!minDate || date < minDate) minDate = date;
        if (!maxDate || date > maxDate) maxDate = date;
      });
    });
  });
  return `Start Date: ${formatBoardSummaryDate(minDate)} | End Date: ${formatBoardSummaryDate(maxDate)}`;
}

function renderTimelineBoardSelect() {
  const sel = qs('#timelineBoardSelect');
  if (!sel) return;
  const boards = TL.boards || [];
  const currentId = TL.chart && TL.chart.source_board_id ? String(TL.chart.source_board_id) : '';
  if (!boards.length) {
    sel.innerHTML = '<option value="">No boards</option>';
    sel.disabled = true;
    return;
  }
  sel.disabled = false;
  sel.innerHTML = boards.map((b) => `
    <option value="${b.id}" ${String(b.id) === currentId ? 'selected' : ''}>${escapeHtml(b.name || 'Board')}</option>
  `).join('');
}

async function updateTimelineHeader() {
  const titleEl = qs('#timelineBoardTitle');
  const subEl = qs('#timelineBoardSubtitle');
  const sourceBoardId = TL.chart && TL.chart.source_board_id ? String(TL.chart.source_board_id) : '';
  if (!sourceBoardId) {
    if (titleEl) titleEl.textContent = 'Project Timeline';
    if (subEl) subEl.textContent = 'Start Date: N/A | End Date: N/A';
    if (typeof cgSetBoardTabTitle === 'function') cgSetBoardTabTitle('', 'Timeline');
    return;
  }
  const board = (TL.boards || []).find((b) => String(b.id) === sourceBoardId);
  if (titleEl) titleEl.textContent = board ? `${board.name} Timeline` : 'Project Timeline';
  if (typeof cgSetBoardTabTitle === 'function') {
    cgSetBoardTabTitle(board && board.name ? String(board.name).trim() : '', 'Timeline');
  }
  if (!subEl) return;
  try {
    const data = await fetchKanbanJson(`${CG_FK_API}?action=bootstrap&board_id=${encodeURIComponent(sourceBoardId)}&_t=${Date.now()}`);
    subEl.textContent = buildBoardDateSummary(data.cardsByColumn || {});
  } catch (e) {
    subEl.textContent = 'Start Date: N/A | End Date: N/A';
  }
}

async function switchTimelineBoard(boardId) {
  if (!boardId) return;
  const current = TL.chart && TL.chart.source_board_id ? String(TL.chart.source_board_id) : '';
  if (String(boardId) === current) return;
  try {
    const res = await fetch(`${CG_FT_API}?action=get_or_create_chart&board_id=${encodeURIComponent(boardId)}`, { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to switch board');
      return;
    }
    const nextUrl = `project_timeline.php?chart_id=${encodeURIComponent(data.chart_id)}`;
    if (typeof window.cgPortalVisit === 'function') window.cgPortalVisit(nextUrl);
    else window.location.href = nextUrl;
  } catch (e) {
    console.error(e);
    alert('Failed to switch board.');
  }
}

function navigateTimelineViewKanban() {
  const id = TL.chart && TL.chart.source_board_id ? String(TL.chart.source_board_id) : '';
  const href = typeof window.cgPortalKanbanBoardHref === 'function'
    ? window.cgPortalKanbanBoardHref(id)
    : (id ? `/kanban?board_id=${encodeURIComponent(id)}` : '/boards');
  if (typeof window.cgPortalVisit === 'function') window.cgPortalVisit(href);
  else window.location.href = href;
}
function navigateTimelineViewCalendar() {
  const id = TL.chart && TL.chart.source_board_id ? String(TL.chart.source_board_id) : '';
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const href = id
    ? `calendar.php?board_id=${encodeURIComponent(id)}&month=${encodeURIComponent(month)}`
    : `calendar.php?month=${encodeURIComponent(month)}`;
  if (typeof window.cgPortalVisit === 'function') window.cgPortalVisit(href);
  else window.location.href = href;
}
function navigateTimelineViewWorkspace() {
  const id = TL.chart && TL.chart.source_board_id ? String(TL.chart.source_board_id) : '';
  if (!id) {
    alert('No board linked to this timeline.');
    return;
  }
  const href = `workspace.php?board_id=${encodeURIComponent(id)}`;
  if (typeof window.cgPortalVisit === 'function') window.cgPortalVisit(href);
  else window.location.href = href;
}

function capitalizeWords(str) {
  if (!str) return '';
  return str.toString()
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ');
}

function daysBetween(a, b) {
  const ms = 24*60*60*1000;
  return Math.round((b - a) / ms);
}
function parseDate(s) {
  if (!s) return null;
  const d = new Date(s + 'T00:00:00');
  return isNaN(d.getTime()) ? null : d;
}
function fmt(d) {
  return d.toLocaleDateString(undefined, { month:'short', day:'numeric' });
}

function formatRelativeDate(date) {
  if (!date) return '';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(date);
  target.setHours(0, 0, 0, 0);
  
  const diffDays = Math.round((target - today) / (1000 * 60 * 60 * 24));
  
  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Tomorrow';
  if (diffDays === -1) return 'Yesterday';
  if (diffDays > 0) return `${diffDays} days from now`;
  if (diffDays < 0) return `${Math.abs(diffDays)} days ago`;
  return '';
}

function getTodayX(min, max, totalDays, left, chartW) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  if (today < min || today > max) return null;
  const daysFromMin = Math.round((today - min) / (1000 * 60 * 60 * 24));
  return left + (daysFromMin / totalDays) * chartW;
}

// Generate a consistent color for a column based on its ID
function getColumnColor(columnId) {
  const colors = [
    '#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6',
    '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#14b8a6',
    '#3b82f6', '#a855f7', '#eab308', '#22c55e', '#f43f5e'
  ];
  return colors[parseInt(columnId, 10) % colors.length];
}

// Get column info by ID
function getColumnInfo(columnId) {
  const col = TL.columns.find(c => parseInt(c.id, 10) === parseInt(columnId, 10));
  return col || { id: columnId, name: 'Unknown' };
}

function renderTimelineBoardMembersAvatars() {
  const container = qs('#boardMembersAvatars');
  if (!container) return;
  const members = TL.board_members || [];
  const activeIdSet = new Set((TL.activeMemberIds || []).map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0));
  const activeMembers = members.filter((m) => activeIdSet.has(Number(m?.id || 0)));
  const represented = new Set(activeMembers.map((m) => Number(m?.id || 0)).filter((id) => id > 0));
  const me = Number(window?.CG_BOARD_CHAT_USER?.id || 0);
  if (me > 0 && activeIdSet.has(me) && !represented.has(me)) {
    activeMembers.unshift({
      id: me,
      name: String(window?.CG_BOARD_CHAT_USER?.name || 'You'),
      profile_pic: '',
      role: 'collaborator',
    });
  }
  const maxVisible = 4;
  const show = activeMembers.slice(0, maxVisible);
  container.innerHTML = show.map((m) => {
    const src = timelineBoardMemberPicUrl(m.profile_pic);
    const name = (m.name || '').trim() || 'User';
    const title = escapeHtml(name) + (m.role === 'owner' ? ' (Owner)' : '');
    const uid = Number(m?.id || 0);
    const color = (uid > 0 && TL.activeMemberColors && TL.activeMemberColors[String(uid)]) ? TL.activeMemberColors[String(uid)] : '';
    const ringStyle = color ? ` style="--presence-color:${escapeHtml(color)}"` : '';
    const inner = src
      ? `<img class="cg-board-avatar" src="${escapeHtml(src)}" alt="${title}" title="${title}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="cg-board-avatar-initials" style="display:none;" title="${title}">${escapeHtml(timelineAssigneeInitials(name))}</span>`
      : `<span class="cg-board-avatar-initials" title="${title}">${escapeHtml(timelineAssigneeInitials(name))}</span>`;
    return `<span class="cg-board-avatar-wrap is-active-presence"${ringStyle}>${inner}</span>`;
  }).join('');
}
function timelinePresenceColor(seed) {
  const raw = String(seed || '');
  let hash = 0;
  for (let i = 0; i < raw.length; i++) hash = ((hash << 5) - hash + raw.charCodeAt(i)) | 0;
  const hue = Math.abs(hash) % 360;
  const sat = 72 + (Math.abs(hash >> 3) % 12);
  const light = 58 + (Math.abs(hash >> 7) % 10);
  return `hsl(${hue}deg ${sat}% ${light}%)`;
}
function syncTimelineChatPresenceGlobals() {
  window.CG_ACTIVE_MEMBER_IDS = Array.isArray(TL.activeMemberIds) ? TL.activeMemberIds.slice() : [];
  window.CG_ACTIVE_MEMBER_COLORS = { ...(TL.activeMemberColors || {}) };
}
function ensureTimelineCurrentUserPresenceFallback(nextIds, nextColors) {
  const me = Number(window?.CG_BOARD_CHAT_USER?.id || 0);
  if (!(me > 0)) return;
  const isMember = (TL.board_members || []).some((m) => Number(m?.id || 0) === me);
  if (!isMember) return;
  if (!nextIds.includes(me)) nextIds.push(me);
  if (!nextColors[String(me)]) nextColors[String(me)] = timelinePresenceColor(`u:${me}`);
}
function seedTimelineCurrentUserPresenceImmediately() {
  const nextIds = [];
  const nextColors = {};
  ensureTimelineCurrentUserPresenceFallback(nextIds, nextColors);
  if (!nextIds.length) return;
  TL.activeMemberIds = nextIds;
  TL.activeMemberColors = nextColors;
  syncTimelineChatPresenceGlobals();
  renderTimelineBoardMembersAvatars();
}
async function syncTimelinePresenceOnce() {
  const bid = Number((TL && TL.chart ? TL.chart.source_board_id : 0) || 0);
  if (!bid) {
    TL.activeMemberIds = [];
    TL.activeMemberColors = {};
    syncTimelineChatPresenceGlobals();
    renderTimelineBoardMembersAvatars();
    return;
  }
  try {
    const data = await fetchKanbanJsonPost(`${CG_FK_API}?action=workspace_presence_ping`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ board_id: bid, sx: 0.5, sy: 0.5 }),
    });
    const rows = Array.isArray(data?.workspace_presence) ? data.workspace_presence : [];
    TL.workspace_presence = rows.slice();
    const nextColors = {};
    const nextIds = [];
    rows.forEach((row) => {
      const uid = Number(row?.user_id || 0);
      const actor = String(row?.actor_id || '').trim();
      if (uid <= 0 || !actor) return;
      if (!nextColors[String(uid)]) {
        nextColors[String(uid)] = timelinePresenceColor(actor);
        nextIds.push(uid);
      }
    });
    ensureTimelineCurrentUserPresenceFallback(nextIds, nextColors);
    TL.activeMemberIds = nextIds;
    TL.activeMemberColors = nextColors;
    syncTimelineChatPresenceGlobals();
    renderTimelineBoardMembersAvatars();
  } catch (e) {
    const nextIds = [];
    const nextColors = {};
    ensureTimelineCurrentUserPresenceFallback(nextIds, nextColors);
    TL.activeMemberIds = nextIds;
    TL.activeMemberColors = nextColors;
    syncTimelineChatPresenceGlobals();
    renderTimelineBoardMembersAvatars();
  }
}
async function fetchKanbanJsonPost(url, opts) {
  const res = await fetch(url, { credentials: 'include', cache: 'no-store', ...(opts || {}) });
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    data = null;
  }
  if (!res.ok) {
    throw new Error((data && data.message) ? data.message : (`HTTP ${res.status}`));
  }
  if (!data || !data.success) {
    throw new Error((data && data.message) ? data.message : 'Request failed');
  }
  return data;
}
function stopTimelinePresencePolling() {
  if (TL.presencePollTimerId != null) {
    clearInterval(TL.presencePollTimerId);
    TL.presencePollTimerId = null;
  }
}
function startTimelinePresencePolling() {
  stopTimelinePresencePolling();
  seedTimelineCurrentUserPresenceImmediately();
  void syncTimelinePresenceOnce();
  TL.presencePollTimerId = setInterval(() => {
    if (document.visibilityState === 'hidden') return;
    void syncTimelinePresenceOnce();
  }, 2200);
}
function timelineBoardMemberPicUrl(profilePic) {
  if (!profilePic || !String(profilePic).trim()) return null;
  const p = String(profilePic).trim();
  if (/^https?:\/\//i.test(p)) return p;
  if (p.startsWith('/')) return cgKanbanResolvePublicUrl(p);
  if (p.startsWith('uploads/')) return cgKanbanResolvePublicUrl('/' + p);
  return cgKanbanResolvePublicUrl('/uploads/profile_pics/' + p);
}
function boardMemberPicUrl(profilePic) {
  return timelineBoardMemberPicUrl(profilePic) || '';
}
function timelineAssigneeInitials(name) {
  if (!name || !String(name).trim()) return '?';
  return String(name).trim().split(/\s+/).map(w => w.charAt(0)).slice(0, 2).join('').toUpperCase();
}
/** HTML inside SVG foreignObject — same stacking idea as Kanban card assignees */
function buildTimelineAssigneesForeignObject(assignees, x, y) {
  const items = Array.isArray(assignees) ? assignees.filter(Boolean) : [];
  if (!items.length) return '';
  const visible = items.slice(0, 3);
  let inner = visible.map((member) => {
    const src = timelineBoardMemberPicUrl(member.profile_pic);
    const name = (member.name || '').trim() || 'User';
    if (src) {
      return `<img class="cg-tl-assignee-avatar" src="${escapeHtml(src)}" alt="" title="${escapeHtml(name)}" loading="lazy" />`;
    }
    return `<span class="cg-tl-assignee-avatar is-initials" title="${escapeHtml(name)}">${escapeHtml(timelineAssigneeInitials(name))}</span>`;
  }).join('');
  if (items.length > 3) {
    inner += `<span class="cg-tl-assignee-more" title="${items.length - 3} more">+${items.length - 3}</span>`;
  }
  const w = 86;
  const h = 24;
  return `<foreignObject x="${x}" y="${y}" width="${w}" height="${h}"><div xmlns="http://www.w3.org/1999/xhtml" class="cg-tl-assignees">${inner}</div></foreignObject>`;
}

function buildSvg(tasks) {
  const wrap = qs('#timelineWrap');
  if (!tasks.length) {
    wrap.innerHTML = `<div class="text-center py-5">
      <i class="fas fa-calendar-plus text-muted" style="font-size:48px;"></i>
      <div class="mt-3" style="font-weight:900;">No dated tasks yet</div>
      <div class="text-muted">Open Kanban and set Start/Due dates on cards.</div>
      <a class="btn btn-danger mt-3" href="${typeof window.cgPortalKanbanBoardHref === 'function' ? window.cgPortalKanbanBoardHref('') : '/'}"><i class="fas fa-table-columns me-2"></i>Go to Kanban</a>
    </div>`;
    return;
  }

  // Normalize tasks dates
  const norm = tasks.map(t => {
    const s = parseDate(t.start_date) || parseDate(t.due_date);
    const e = parseDate(t.due_date) || parseDate(t.start_date);
    const progress = parseInt(t.progress || 0, 10);
    const columnId = t.column_id || t.columnId || null;
    const colInfo = getColumnInfo(columnId);
    return { 
      ...t, 
      _s: s, 
      _e: e, 
      _columnId: columnId,
      _columnName: colInfo.name,
      _columnColor: getColumnColor(columnId),
      _progress: Math.max(0, Math.min(100, progress))
    };
  }).filter(t => {
    // Only filter out tasks that have no valid dates at all
    if (!t._s && !t._e) {
      return false;
    }
    // If only one date exists, use it for both start and end
    if (!t._s) t._s = t._e;
    if (!t._e) t._e = t._s;
    return true;
  });

  if (!norm.length) return buildSvg([]);

  // Range
  let min = norm[0]._s, max = norm[0]._e;
  norm.forEach(t => {
    if (t._s < min) min = t._s;
    if (t._e > max) max = t._e;
  });
  // Pad by 1 day
  min = new Date(min.getTime() - 24*60*60*1000);
  max = new Date(max.getTime() + 24*60*60*1000);

  const totalDays = Math.max(1, daysBetween(min, max));
  // Auto-fit column widths from content (approx 8.5px/char title, 6.5px/char date)
  let maxTaskW = 0;
  let maxStatusW = 0;
  norm.forEach(t => {
    const asgN = Array.isArray(t.assignees) ? t.assignees.length : 0;
    const titleCap = asgN > 0 ? 34 : 45;
    const titleLen = Math.min(titleCap, (t.title || '').length);
    const startRel = formatRelativeDate(t.start_date);
    const dueRel = formatRelativeDate(t.due_date);
    const dateStr = startRel && dueRel ? `${startRel} → ${dueRel}` : (startRel || dueRel);
    const dateLen = (dateStr || '').length;
    maxTaskW = Math.max(maxTaskW, titleLen * 8.5 + (asgN > 0 ? 88 : 0), dateLen * 6.5);
    const statusLabel = capitalizeWords(t._columnName || 'Unknown');
    const badgeW = Math.max(60, statusLabel.length * 7 + 20);
    maxStatusW = Math.max(maxStatusW, badgeW);
  });
  const taskColWidth = Math.max(220, 32 + maxTaskW);
  const statusColWidth = Math.max(180, 16 + maxStatusW + 140 + 8 + 28 + 16);
  const statusColStart = taskColWidth;
  const left = taskColWidth + statusColWidth;
  const rowH = 72;        // taller rows for progress display
  const headH = 48;
  const chartW = Math.max(1200, totalDays * 32); // px per day (wider)
  const width = left + chartW + 40;
  const height = headH + norm.length * rowH + 40;

  const xFor = (d) => left + (daysBetween(min, d) / totalDays) * chartW;

  // Month/day header ticks (daily)
  const headTicks = [];
  for (let i=0;i<=totalDays;i++) {
    const d = new Date(min.getTime() + i*24*60*60*1000);
    if (d.getDate() === 1 || i === 0) headTicks.push({ i, d, major:true });
    else if (d.getDay() === 1) headTicks.push({ i, d, major:false }); // Monday
  }

  // Progress line graph data points (for tasks with progress)
  const progressPoints = [];
  norm.forEach((t, idx) => {
    if (t._progress !== undefined && t._progress !== null) {
      const barX = xFor(t._s);
      const barW = Math.max(12, Math.abs(xFor(t._e) - xFor(t._s)));
      const centerX = barX + (barW / 2);
      const y = headH + idx * rowH + 36; // Center of the bar
      progressPoints.push({ x: centerX, y, progress: t._progress, task: t });
    }
  });

  const svgParts = [];
  svgParts.push(`<svg class="cg-svg" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg">`);

  // Background (colors from CSS so dark mode can theme #timelineWrap)
  svgParts.push(`<rect class="cg-svg-bg" x="0" y="0" width="${width}" height="${height}" rx="16" ry="16"></rect>`);

  // Vertical grid lines
  for (let i=0;i<=totalDays;i++) {
    const d = new Date(min.getTime() + i*24*60*60*1000);
    const isWeek = d.getDay() === 1;
    const x = left + (i/totalDays)*chartW;
    svgParts.push(`<line class="cg-grid" x1="${x}" y1="${headH}" x2="${x}" y2="${height-12}" opacity="${isWeek ? 1 : 0.45}"></line>`);
  }

  // Today line position
  const todayX = getTodayX(min, max, totalDays, left, chartW);
  
  // Header area
  svgParts.push(`<rect class="cg-svg-header-bg" x="0" y="0" width="${width}" height="${headH}"></rect>`);
  svgParts.push(`<line class="cg-grid" x1="0" y1="${headH}" x2="${width}" y2="${headH}"></line>`);
  svgParts.push(`<text class="cg-head-text" x="16" y="28">Task</text>`);
  svgParts.push(`<text class="cg-head-text" x="${statusColStart + 16}" y="28">Status &amp; Progress</text>`);
  svgParts.push(`<text class="cg-head-text" x="16" y="42">Timeline</text>`);

  // Today line in header
  if (todayX !== null && todayX >= left && todayX <= left + chartW) {
    svgParts.push(`<line class="cg-today-line" x1="${todayX}" y1="0" x2="${todayX}" y2="${headH}"></line>`);
    svgParts.push(`<text class="cg-head-text cg-today-label" x="${todayX + 6}" y="16" fill="#e03131" font-weight="900">Today</text>`);
  }

  headTicks.forEach(t => {
    const x = left + (t.i/totalDays)*chartW;
    const dateStr = t.major ? t.d.toLocaleString(undefined,{month:'short', year:'numeric'}) : fmt(t.d);
    const relative = formatRelativeDate(t.d);
    svgParts.push(`<text class="cg-head-text" x="${x+6}" y="28" opacity="${t.major ? 1 : 0.7}">${dateStr}</text>`);
    if (relative && !t.major) {
      svgParts.push(`<text class="cg-head-text" x="${x+6}" y="42" opacity="0.6" font-size="10">${relative}</text>`);
    }
  });

  // Rows with enhanced information
  norm.forEach((t, idx) => {
    const y = headH + idx*rowH;
    const rowCenterY = y + rowH / 2;
    
    // Grid line
    svgParts.push(`<line class="cg-grid" x1="0" y1="${y+rowH}" x2="${width}" y2="${y+rowH}"></line>`);
    
    // Today line in row
    if (todayX !== null && todayX >= left && todayX <= left + chartW) {
      svgParts.push(`<line class="cg-grid-today" x1="${todayX}" y1="${y}" x2="${todayX}" y2="${y+rowH}"></line>`);
    }
    
    // Task title (left side) + assignees (Kanban-style stack)
    const asgCount = Array.isArray(t.assignees) ? t.assignees.length : 0;
    const titleSlice = asgCount > 0 ? 34 : 45;
    svgParts.push(`<text class="cg-row-label" x="16" y="${y+20}">${escapeHtml(capitalizeWords(t.title)).slice(0, titleSlice)}</text>`);
    const assigneesFo = buildTimelineAssigneesForeignObject(t.assignees || [], taskColWidth - 90, y + 8);
    if (assigneesFo) svgParts.push(assigneesFo);
    
    // Status badge and progress (middle area) - use column color
    const statusColor = t._columnColor || '#64748b';
    const statusLabel = capitalizeWords(t._columnName || 'Unknown');
    
    // Status badge (width based on label length)
    const badgeWidth = Math.max(60, statusLabel.length * 7 + 20);
    const progressX = statusColStart + 16;
    svgParts.push(`<rect x="${progressX}" y="${y+12}" width="${badgeWidth}" height="20" rx="10" fill="${statusColor}" opacity="0.15"></rect>`);
    svgParts.push(`<circle cx="${progressX + 10}" cy="${y+22}" r="5" fill="${statusColor}"></circle>`);
    svgParts.push(`<text class="cg-row-sub" x="${progressX + 20}" y="${y+25}" fill="${statusColor}" font-weight="700">${escapeHtml(statusLabel)}</text>`);
    
    // Progress percentage and bar
    const progressY = y + 38;
    const progressW = 140;
    const progressH = 6;
    svgParts.push(`<rect class="cg-progress-bg" x="${progressX}" y="${progressY}" width="${progressW}" height="${progressH}"></rect>`);
    svgParts.push(`<rect class="cg-progress-fill" x="${progressX}" y="${progressY}" width="${(t._progress / 100) * progressW}" height="${progressH}"></rect>`);
    svgParts.push(`<text class="cg-row-progress" x="${progressX + progressW + 8}" y="${progressY + 5}">${t._progress}%</text>`);
    
    // Date information
    const startRel = formatRelativeDate(t.start_date);
    const dueRel = formatRelativeDate(t.due_date);
    if (startRel || dueRel) {
      const dateInfo = startRel && dueRel ? `${startRel} → ${dueRel}` : (startRel || dueRel);
      svgParts.push(`<text class="cg-row-date" x="16" y="${y+46}">${escapeHtml(dateInfo)}</text>`);
    }
    
    // Timeline bar
    const xs = xFor(t._s);
    const xe = xFor(t._e);
    const barX = Math.min(xs, xe);
    const barW = Math.max(12, Math.abs(xe - xs));
    const barY = y + 20;
    const barH = 32;
    const barColor = t._columnColor || '#64748b';
    
    // Bar with progress indicator - use column color
    svgParts.push(`<rect class="cg-bar" x="${barX}" y="${barY}" width="${barW}" height="${barH}" fill="${barColor}" opacity="0.9"></rect>`);
    
    // Progress overlay on bar
    if (t._progress > 0 && t._progress < 100) {
      const progressBarW = (t._progress / 100) * barW;
      svgParts.push(`<rect x="${barX}" y="${barY}" width="${progressBarW}" height="${barH}" fill="rgba(255,255,255,0.3)" rx="6"></rect>`);
    }
    
    // Start and end date markers
    svgParts.push(`<circle class="cg-timeline-node" cx="${xs}" cy="${rowCenterY}" r="4" fill="${statusColor}" stroke-width="2"></circle>`);
    svgParts.push(`<circle class="cg-timeline-node" cx="${xe}" cy="${rowCenterY}" r="4" fill="${statusColor}" stroke-width="2"></circle>`);
    
    // Date labels on timeline
    if (barW > 80) {
      const startLabel = fmt(t._s);
      const endLabel = fmt(t._e);
      svgParts.push(`<text class="cg-row-date cg-bar-date-label" x="${xs + 6}" y="${barY - 4}" fill="${statusColor}" font-weight="700">${startLabel}</text>`);
      svgParts.push(`<text class="cg-row-date cg-bar-date-label" x="${xe - 40}" y="${barY + barH + 14}" fill="${statusColor}" font-weight="700">${endLabel}</text>`);
    }
  });

  /* Progress path between rows was removed: it overlapped the sequential cg-connector paths (both dashed), which read as “double” lines. Keep per-bar progress nodes only. */
  if (progressPoints.length) {
    progressPoints.forEach(point => {
      const color = point.progress === 100 ? '#10b981' : point.progress > 50 ? '#f59e0b' : '#6366f1';
      svgParts.push(`<circle class="cg-progress-node" cx="${point.x}" cy="${point.y}" r="6" fill="${color}" stroke-width="2.5"></circle>`);
      svgParts.push(`<text class="cg-row-progress cg-row-progress--spark" x="${point.x + 10}" y="${point.y + 5}" fill="${color}" font-size="11" font-weight="700">${point.progress}%</text>`);
    });
  }
  
  // Connectors: simple sequential dependency lines (task i ends -> task i+1 starts)
  const connectors = [];
  for (let i=0;i<norm.length-1;i++) {
    connectors.push([norm[i], norm[i+1]]);
  }
  
  // Connectors (draw after bars)
  connectors.forEach(([a,b], idx) => {
    const aIdx = norm.findIndex(t => parseInt(t.id,10)===parseInt(a.id,10));
    const bIdx = norm.findIndex(t => parseInt(t.id,10)===parseInt(b.id,10));
    if (aIdx < 0 || bIdx < 0) return;
    const ay = headH + aIdx*rowH + 36;
    const by = headH + bIdx*rowH + 36;
    const ax = xFor(parseDate(a.due_date) || parseDate(a.start_date) || parseDate(a.start_date));
    const bx = xFor(parseDate(b.start_date) || parseDate(b.due_date) || parseDate(b.due_date));
    const midX = Math.max(ax + 16, (ax + bx)/2);
    svgParts.push(`<path class="cg-connector" d="M ${ax} ${ay} C ${midX} ${ay}, ${midX} ${by}, ${bx} ${by}" opacity="0.4"></path>`);
  });

  svgParts.push(`</svg>`);
  wrap.innerHTML = svgParts.join('');
}

async function bootstrap() {
  if (typeof window.cgPortalLoadingBegin === 'function') {
    window.cgPortalLoadingBegin('timelineLoadingOverlay');
  }
  try {
    // Check if chart_id is in URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlChartId = urlParams.get('chart_id');
    
    let url = `${CG_FT_API}?action=bootstrap`;
    if (urlChartId) {
      url += `&chart_id=${urlChartId}`;
    }
    const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed');

    TL.project_id = data.project_id;
    TL.chart_id = data.chart_id;
    TL.charts = data.charts || [];
    TL.boards = data.boards || [];
    TL.chart = data.chart || null;
    TL.tasks = data.tasks || [];
    TL.columns = data.columns || [];
    TL.board_members = Array.isArray(data.board_members) ? data.board_members : [];

    // Update URL if chart_id changed
    if (urlChartId && parseInt(urlChartId, 10) !== parseInt(data.chart_id, 10)) {
      const newUrl = new URL(window.location);
      newUrl.searchParams.set('chart_id', data.chart_id);
      window.history.replaceState({}, '', newUrl);
    }

    // Update legend with dynamic columns
    updateLegend();

    buildSvg(TL.tasks);
    await loadOwnerComments();
    await updateTimelineHeader();
    renderTimelineBoardSelect();
    renderTimelineBoardMembersAvatars();
    startTimelinePresencePolling();
    if (typeof window.cgSyncBoardChatFromPortal === 'function') {
      const bid = TL.chart && TL.chart.source_board_id ? TL.chart.source_board_id : 0;
      syncTimelineChatPresenceGlobals();
      window.cgSyncBoardChatFromPortal(TL.board_members, bid);
    }
  } catch (err) {
    console.error('Bootstrap error:', err);
    throw err;
  } finally {
    if (typeof window.cgPortalLoadingEnd === 'function') {
      window.cgPortalLoadingEnd('timelineLoadingOverlay');
    }
  }
}

function updateLegend() {
  const legendEl = qs('.cg-tl__legend');
  if (!legendEl) return;
  
  if (TL.columns.length === 0) {
    legendEl.innerHTML = '<span class="text-muted">No columns found</span>';
    return;
  }
  
  legendEl.innerHTML = TL.columns.map(col => {
    const color = getColumnColor(col.id);
    return `<span class="cg-dot" style="background: ${color};"></span><span class="cg-leg">${escapeHtml(capitalizeWords(col.name))}</span>`;
  }).join('');
}


function showLoading() {
  const wrap = qs('#timelineWrap');
  if (wrap) {
    wrap.innerHTML = `<div class="cg-tl__loading text-center py-5">
      <div class="spinner-border text-danger" role="status" aria-label="Loading"></div>
      <div class="text-muted mt-2">Refreshing timeline…</div>
    </div>`;
  }
}

function hideLoading() {
  // Loading is hidden when buildSvg is called
}

async function loadOwnerComments() {
  const list = qs('#ownerCommentList');
  if (!list || !TL.project_id) return;
  list.innerHTML = '<div class="text-muted small">Loading comments…</div>';
  try {
    const res = await fetch(`${CG_FT_API}?action=list_comments&project_id=${TL.project_id}&chart_id=${TL.chart_id}`, { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load comments');
    const comments = data.comments || [];
    if (!comments.length) {
      list.innerHTML = '<div class="text-muted small">No comments yet.</div>';
      return;
    }
    list.innerHTML = comments.map(c => `
      <div class="p-2 rounded" style="background: rgba(248,250,252,0.8); border:1px solid rgba(15,23,42,0.06);">
        <div class="d-flex justify-content-between small">
          <span>${escapeHtml(c.author_name || 'Anon')}</span>
          <span class="text-muted">${escapeHtml(c.created_at || '')}</span>
        </div>
        <div class="small mt-1">${escapeHtml(c.body || '')}</div>
        <div class="mt-1 text-end">
          <button class="btn btn-sm btn-outline-danger" onclick="deleteOwnerComment(${c.id})"><i class="fas fa-trash me-1"></i>Delete</button>
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.error('Owner comments load error:', err);
    list.innerHTML = '<div class="text-muted small">Failed to load comments.</div>';
  }
}

async function deleteOwnerComment(commentId) {
  if (!confirm('Delete this comment?')) return;
  try {
    const fd = new FormData();
    fd.append('action', 'delete_comment');
    fd.append('comment_id', commentId);
    const res = await fetch(CG_FT_API, { method: 'POST', body: fd, credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to delete comment');
      return;
    }
    await loadOwnerComments();
  } catch (err) {
    console.error('Delete comment error:', err);
    alert('Failed to delete comment.');
  }
}

async function shareClient() {
  if (!TL.chart_id || TL.chart_id <= 0) {
    alert('Please select a timeline first. Each timeline requires its own unique share link.');
    return;
  }
  try {
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('project_id', TL.project_id || '');
    fd.append('chart_id', TL.chart_id);
    if (CG_CLIENT_SHARE_PORTAL_BASE) {
      fd.append('portal_base', CG_CLIENT_SHARE_PORTAL_BASE);
    }
    const res = await fetch(CG_FS_API, { method: 'POST', body: fd, credentials: 'include', cache: 'no-store' });
    const raw = await res.text();
    let data = null;
    try {
      data = JSON.parse(raw);
    } catch (parseErr) {
      console.error('Share response is not JSON:', parseErr, raw);
      const preview = String(raw || '').replace(/\s+/g, ' ').trim().slice(0, 180);
      alert('Share link failed: invalid JSON from server.\n' + (preview || '(empty response)'));
      return;
    }
    if (!res.ok || !data || !data.success) {
      alert((data && data.message) ? data.message : 'Failed to create share link.');
      return;
    }
    const shareUrl = String(data.url || '').replace(/&amp;/g, '&');
    qs('#shareLink').value = shareUrl;
    new bootstrap.Modal(qs('#shareModal')).show();
  } catch (err) {
    console.error('shareClient error:', err);
    alert('Failed to create share link.');
  }
}

qs('#shareBtn').addEventListener('click', shareClient);
qs('#copyShareBtn').addEventListener('click', async () => {
  const input = qs('#shareLink');
  input.select();
  input.setSelectionRange(0, 99999);
  await navigator.clipboard.writeText(input.value);
});

applyKanbanTheme(getStoredKanbanTheme());
if (!window.__cgKanbanThemeToggleWired) {
  document.querySelectorAll('[data-kanban-theme-toggle]').forEach((t) => {
    t.addEventListener('click', toggleKanbanTheme);
  });
}
const tlBnKanban = qs('#cgBottomNavKanban');
const tlBnCalendar = qs('#cgBottomNavCalendar');
const tlBnWorkspace = qs('#cgBottomNavWorkspace');
if (tlBnKanban) tlBnKanban.addEventListener('click', navigateTimelineViewKanban);
if (tlBnCalendar) tlBnCalendar.addEventListener('click', navigateTimelineViewCalendar);
if (tlBnWorkspace) tlBnWorkspace.addEventListener('click', navigateTimelineViewWorkspace);
qs('#timelineBoardSelect').addEventListener('change', (e) => {
  switchTimelineBoard(e.target.value);
});

bootstrap().catch(err => {
  console.error('Timeline bootstrap failed:', err);
  const msg = err.message || 'Failed to load timeline';
  const titleEl = qs('#timelineBoardTitle');
  const subEl = qs('#timelineBoardSubtitle');
  if (titleEl) titleEl.textContent = 'Project Timeline';
  if (subEl) subEl.textContent = 'Start Date: N/A | End Date: N/A';
  if (typeof cgSetBoardTabTitle === 'function') cgSetBoardTabTitle('', 'Timeline');
  qs('#timelineWrap').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to load timeline.<br><small class="text-muted mt-2">${escapeHtml(msg)}</small></div>`;
});
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') stopTimelinePresencePolling();
  else startTimelinePresencePolling();
});
window.addEventListener('beforeunload', () => {
  stopTimelinePresencePolling();
});
</script>
<?php cg_kanban_board_chat_embed_echo_deferred_js(); ?>
<?php if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL): ?>
</main>
</body>
</html>
<?php else: ?>
<?php require_once $cg_public_root . '/includes/footer.php'; ?>
<?php endif; ?>
