<?php
/**
 * Minimal shell for kanban.cinegrid.net (no includes/header.php — avoids index.php hero + marketplace chrome).
 * Layout aligned with transfer.cinegrid.net/header.php.
 */
$cg_kph_title = isset($page_title) ? (string)$page_title : 'Kanban';
$cg_kph_user = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : 'User';
$cg_kph_user_id = (int) ($_SESSION['user_id'] ?? 0);
$cg_kph_avatar_url = '';
if ($cg_kph_user_id > 0) {
    $cg_kph_avatar_url = '/api/profile_avatar.php?user_id=' . $cg_kph_user_id . '&size=64';
}
require_once __DIR__ . '/cg_kanban_switch_account.php';
$cg_kph_has_linked_accounts = cg_kanban_portal_has_linked_accounts();
/** Set $cg_kph_board_menu_actions = false before include to hide Board settings, PM link, and Activities (e.g. timeline/calendar/workspace). Board chat stays in the menu when logged in. */
$cg_kph_show_board_menu_actions = !isset($cg_kph_board_menu_actions) || $cg_kph_board_menu_actions;
/** Set $cg_kph_hide_user_menu = true before include to hide User dropdown + CinePoints pill (workspace client/guest share). */
$cg_kph_hide_user_menu = !empty($cg_kph_hide_user_menu);
/** Optional: set $cg_kph_include_board_members_css = true before include to load board-member facepile CSS in head (project timeline on kanban subdomain). Kanban does not set this. */
/** Optional: set $cg_kph_include_site_wallet_styles = true before include on mywallet.php (loads cinegrid.net style.css to match apex wallet). */
/** Optional: set $cg_kph_body_extra_class = 'cg-wallet-route' (or other) before include for page-specific body classes. */
/** Same three-column mark as .cg-kanban-portal-logo-icon (not main-site favicon). */
$cg_kanban_portal_favicon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect x="48" y="128" width="112" height="256" rx="24" fill="#ff3c3c"/><rect x="200" y="80" width="112" height="352" rx="24" fill="#ff3c3c"/><rect x="352" y="160" width="112" height="224" rx="24" fill="#ff3c3c"/></svg>';
$cg_kanban_portal_favicon_href = 'data:image/svg+xml,' . rawurlencode($cg_kanban_portal_favicon_svg);
?>
<!DOCTYPE html>
<html lang="en" class="cg-kanban-portal-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <script>
    (function () {
      window.cgPortalKanbanBoardHref = function (boardId, cardId) {
        var bid = boardId != null ? String(boardId).trim() : '';
        var cid = cardId != null ? String(cardId).trim() : '';
        var q = new URLSearchParams();
        if (bid) q.set('board_id', bid);
        if (cid) q.set('card_id', cid);
        var s = q.toString();
        // Use /kanban?… (not /?…) — DirectoryIndex + index.php→/ rewrite loops as HTTP 500.
        return s ? ('/kanban?' + s) : '/boards';
      };
    })();
    </script>
    <title><?php echo htmlspecialchars($cg_kph_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars($cg_kanban_portal_favicon_href, ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Montserrat:wght@400;600;700&amp;display=swap" rel="stylesheet">
    <?php if (!empty($cg_kph_include_board_members_css)): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_board_members_ui.css">
    <?php endif; ?>
    <?php if (!empty($cg_kph_include_site_wallet_styles)): ?>
    <?php require __DIR__ . '/cg_wallet_site_styles.php'; ?>
    <?php endif; ?>
    <style>
        :root {
            --cg-kanban-portal-header-h: 60px;
        }
        html.cg-kanban-portal-html {
            height: 100%;
            overscroll-behavior: none;
        }
        html.cg-kanban-portal-html body.cg-kanban-portal {
            margin: 0;
            min-height: 100%;
            height: 100%;
            max-height: 100%;
            overflow: hidden;
            overscroll-behavior: none;
            overscroll-behavior-y: none;
            display: flex;
            flex-direction: column;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        /* Same as includes/header.php: Montserrat for headings (board title matches cinegrid.net/kanban) */
        body.cg-kanban-portal h1,
        body.cg-kanban-portal h2,
        body.cg-kanban-portal h3,
        body.cg-kanban-portal h4,
        body.cg-kanban-portal h5,
        body.cg-kanban-portal h6 {
            font-family: "Montserrat", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .cg-kanban-portal-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10001;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            min-height: var(--cg-kanban-portal-header-h);
            box-sizing: border-box;
            display: flex;
            align-items: center;
        }
        .cg-kanban-portal-header .container-fluid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            /* !important: kanban.php full-page rules must not flatten this bar */
            padding-left: max(1.25rem, env(safe-area-inset-left, 0px)) !important;
            padding-right: max(1.25rem, env(safe-area-inset-right, 0px)) !important;
        }
        @media (min-width: 576px) {
            .cg-kanban-portal-header .container-fluid {
                padding-left: max(1.5rem, env(safe-area-inset-left, 0px)) !important;
                padding-right: max(1.5rem, env(safe-area-inset-right, 0px)) !important;
            }
        }
        @media (min-width: 992px) {
            .cg-kanban-portal-header .container-fluid {
                padding-left: max(2rem, env(safe-area-inset-left, 0px)) !important;
                padding-right: max(2rem, env(safe-area-inset-right, 0px)) !important;
            }
        }
        .cg-kanban-portal-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1f2937;
            font-weight: 700;
            font-size: 1.35rem;
        }
        /* FA “objects-column” is Pro-only in webfont CDN; this SVG matches that metaphor (original paths). */
        .cg-kanban-portal-logo-icon {
            width: 1.65rem;
            height: 1.65rem;
            flex-shrink: 0;
            margin-right: 8px;
            color: #ff3c3c;
        }
        .cg-kanban-portal-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .cg-kanban-portal-nav a.cg-kanban-portal-link {
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .cg-kanban-portal-nav a.cg-kanban-portal-link:hover {
            color: #ff3c3c;
        }
        .cg-kanban-portal-dropdown { position: relative; }
        .cg-kanban-portal-dropdown-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: none;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 8px;
            color: #1f2937;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .cg-kanban-portal-dropdown-btn:hover {
            background: rgba(0,0,0,0.04);
        }
        .cg-kanban-portal-user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            background: #e5e7eb;
            flex-shrink: 0;
            display: block;
        }
        .cg-kanban-portal-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            padding: 8px 0;
            display: none;
            z-index: 10002;
        }
        @keyframes cg-portal-dropdown-in {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .cg-kanban-portal-dropdown-menu.show {
            display: block;
            animation: cg-portal-dropdown-in 0.22s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        @media (prefers-reduced-motion: reduce) {
            .cg-kanban-portal-dropdown-menu.show {
                animation: none;
            }
        }
        .cg-kanban-portal-dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .cg-kanban-portal-dropdown-item:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #4b5563;
        }
        .cg-kanban-portal-dropdown-item:not(.logout) i {
            color: #6b7280;
        }
        .cg-kanban-portal-dropdown-item:not(.logout):hover i {
            color: #4b5563;
        }
        .cg-kanban-portal-dropdown-item.logout {
            color: #ef4444;
        }
        .cg-kanban-portal-dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        .cg-kanban-portal-dropdown-item i {
            margin-right: 8px;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        .cg-kanban-portal-theme-label {
            color: inherit;
            font-weight: 500;
        }
        /* Match Homepage <a> row: UA button styles shrink text/icons unless reset explicitly (do not use font: inherit — parent menu has no size set). */
        button.cg-kanban-portal-dropdown-item {
            width: 100%;
            border: none;
            background: none;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 400;
            line-height: normal;
            color: inherit;
            -webkit-appearance: none;
            appearance: none;
        }
        .cg-kanban-portal-dropdown-divider {
            margin: 6px 12px;
            border: 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        /* padding-top clears fixed bar; margin-top is killed by kanban fullpage CSS — do not use margin here */
        main.cg-kanban-portal-main {
            margin-top: 0 !important;
            padding-top: var(--cg-kanban-portal-header-h);
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
        }
        /*
         * Bottom view bar — inlined so it always paints on kanban.cinegrid.net (Bootstrap loads in head;
         * cg_view_menu.css loads later from cinegrid.net and can be blocked or stale).
         */
        body.cg-kanban-portal .cg-page--bottom-nav {
            padding-bottom: calc(84px + env(safe-area-inset-bottom, 0px));
        }
        body.cg-kanban-portal .cg-bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: calc(8px + max(18px, env(safe-area-inset-bottom, 0px)));
            margin-left: auto;
            margin-right: auto;
            width: fit-content;
            max-width: min(820px, calc(100vw - 24px));
            box-sizing: border-box;
            /* Above site support .chat-widget (9999); below board-chat backdrop (10025) */
            z-index: 10010;
            display: flex;
            align-items: stretch;
            justify-content: center;
            gap: 6px;
            padding: 8px 10px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow:
                0 4px 6px rgba(15, 23, 42, 0.06),
                0 18px 44px rgba(15, 23, 42, 0.16);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            pointer-events: auto;
            isolation: isolate;
        }
        body.cg-kanban-portal .cg-workspace-page .cg-bottom-nav {
            bottom: calc(96px + env(safe-area-inset-bottom, 0px));
            z-index: 10015;
        }
        body.cg-kanban-portal .cg-bottom-nav__btn {
            flex: 1 1 0;
            min-width: 0;
            min-height: 44px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 6px 8px 5px;
            margin: 0;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #334155;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", Arial, sans-serif;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            line-height: 1.15;
            cursor: pointer;
            -webkit-appearance: none;
            appearance: none;
            -webkit-tap-highlight-color: transparent;
            transition: background 0.15s ease, color 0.15s ease;
        }
        body.cg-kanban-portal .cg-bottom-nav__btn i {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1px;
        }
        body.cg-kanban-portal .cg-bottom-nav__btn:hover,
        body.cg-kanban-portal .cg-bottom-nav__btn:focus-visible {
            background: rgba(15, 23, 42, 0.07);
            color: #0f172a;
            outline: none;
        }
        body.cg-kanban-portal .cg-bottom-nav__btn.is-active {
            background: rgba(224, 49, 49, 0.14);
            color: #c92a2a;
        }
        body.cg-kanban-portal .cg-bottom-nav__btn.is-active i {
            opacity: 1;
        }
        body.cg-kanban-portal .cg-bottom-nav__btn:disabled,
        body.cg-kanban-portal .cg-bottom-nav__btn[aria-disabled="true"] {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }
        body.cg-kanban-portal.kanban-theme-dark .cg-bottom-nav {
            background: rgba(30, 41, 59, 0.96);
            border-color: rgba(148, 163, 184, 0.22);
            box-shadow:
                0 4px 6px rgba(2, 6, 23, 0.35),
                0 18px 48px rgba(2, 6, 23, 0.55);
        }
        body.cg-kanban-portal.kanban-theme-dark .cg-bottom-nav__btn {
            color: #cbd5e1;
        }
        body.cg-kanban-portal.kanban-theme-dark .cg-bottom-nav__btn:hover,
        body.cg-kanban-portal.kanban-theme-dark .cg-bottom-nav__btn:focus-visible {
            background: rgba(148, 163, 184, 0.14);
            color: #f8fafc;
        }
        body.cg-kanban-portal.kanban-theme-dark .cg-bottom-nav__btn.is-active {
            background: rgba(224, 49, 49, 0.22);
            color: #ffa8a8;
        }
        @media (max-width: 767.98px) {
            body:has(.modal.show) .cg-bottom-nav {
                visibility: hidden;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.18s ease, visibility 0.18s ease;
            }
            body:has(.modal.show) .cg-page--bottom-nav {
                padding-bottom: env(safe-area-inset-bottom, 0px);
            }
            body.cg-kanban-portal .modal .modal-dialog.modal-dialog-centered {
                align-items: flex-start;
                min-height: auto;
            }
            body.cg-kanban-portal .modal {
                padding-top: calc(env(safe-area-inset-top, 0px) + var(--cg-kanban-portal-header-h, 60px) + 12px);
            }
        }
        body.cg-kanban-portal.kanban-theme-dark {
            background: #0f172a;
        }
        body.kanban-theme-dark .cg-kanban-portal-header {
            background: rgba(15, 23, 42, 0.94);
            border-bottom-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
        }
        body.kanban-theme-dark .cg-kanban-portal-logo { color: #e2e8f0; }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-btn {
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-btn:hover { background: rgba(255, 255, 255, 0.08); }
        body.kanban-theme-dark .cg-kanban-portal-user-avatar {
            background: #374151;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-menu {
            background: #1e293b;
            border-color: rgba(255, 255, 255, 0.1);
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-item:not(.logout) {
            color: #94a3b8;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-item:not(.logout):hover {
            background: rgba(255, 255, 255, 0.06);
            color: #cbd5e1;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-item.logout {
            color: #fca5a5;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #fecaca;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-divider {
            border-top-color: rgba(255, 255, 255, 0.12);
            opacity: 1;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-btn .fa-chevron-down {
            color: #94a3b8 !important;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-item:not(.logout) i {
            color: #94a3b8;
        }
        body.kanban-theme-dark .cg-kanban-portal-dropdown-item:not(.logout):hover i {
            color: #cbd5e1;
        }
        .cg-portal-alert-overlay {
            position: fixed;
            inset: 0;
            z-index: 11050;
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .cg-portal-alert-overlay.is-open {
            display: flex;
            animation: cgPortalAlertOverlayIn 0.18s ease;
        }
        @keyframes cgPortalAlertOverlayIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .cg-portal-alert-dialog {
            width: min(440px, calc(100vw - 24px));
            max-width: 100%;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: #ffffff;
            box-shadow: 0 24px 64px rgba(2, 6, 23, 0.22), 0 8px 20px rgba(2, 6, 23, 0.08);
            overflow: hidden;
            animation: cgPortalAlertDialogIn 0.22s cubic-bezier(0.22, 1, 0.36, 1);
        }
        @keyframes cgPortalAlertDialogIn {
            from { opacity: 0; transform: translateY(10px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .cg-portal-alert-icon {
            width: 44px;
            height: 44px;
            margin: 16px 16px 0;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }
        .cg-portal-alert-dialog--danger .cg-portal-alert-icon {
            background: #fef2f2;
            color: #dc2626;
        }
        .cg-portal-alert-dialog--success .cg-portal-alert-icon {
            background: #ecfdf5;
            color: #059669;
        }
        .cg-portal-alert-head {
            padding: 12px 16px 6px;
            font-family: Montserrat, Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .cg-portal-alert-body {
            padding: 0 16px 16px;
            color: #475569;
            font-size: 0.92rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .cg-portal-alert-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 0 16px 16px;
        }
        .cg-portal-alert-btn {
            border: 0;
            border-radius: 11px;
            min-height: 40px;
            padding: 0 16px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            background: #e03131;
            color: #fff;
            transition: background 0.15s ease, transform 0.15s ease;
        }
        .cg-portal-alert-btn:hover {
            background: #c92a2a;
        }
        .cg-portal-alert-btn:active {
            transform: scale(0.98);
        }
        .cg-portal-alert-btn--ghost {
            background: #fff;
            color: #475569;
            border: 1px solid rgba(15, 23, 42, 0.14);
        }
        .cg-portal-alert-btn--ghost:hover {
            background: #f8fafc;
        }
        .cg-portal-alert-dialog--default .cg-portal-alert-btn:not(.cg-portal-alert-btn--ghost) {
            background: #6366f1;
        }
        .cg-portal-alert-dialog--default .cg-portal-alert-btn:not(.cg-portal-alert-btn--ghost):hover {
            background: #4f46e5;
        }
        .cg-portal-alert-dialog--success .cg-portal-alert-btn:not(.cg-portal-alert-btn--ghost) {
            background: #059669;
        }
        .cg-portal-alert-dialog--success .cg-portal-alert-btn:not(.cg-portal-alert-btn--ghost):hover {
            background: #047857;
        }
        body.kanban-theme-dark .cg-portal-alert-btn--ghost {
            color: #cbd5e1;
            border-color: rgba(148, 163, 184, 0.28);
        }
        body.kanban-theme-dark .cg-portal-alert-btn--ghost:hover {
            background: rgba(148, 163, 184, 0.08);
        }
        body.kanban-theme-dark .cg-portal-alert-dialog {
            background: #1a1d27;
            border-color: rgba(148, 163, 184, 0.2);
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.5);
        }
        body.kanban-theme-dark .cg-portal-alert-head {
            color: #e2e8f0;
        }
        body.kanban-theme-dark .cg-portal-alert-body {
            color: #94a3b8;
        }
        body.kanban-theme-dark .cg-portal-alert-btn--ghost {
            background: #1a1d27;
        }
        .cg-portal-alert-typeconfirm {
            padding: 0 16px 14px;
        }
        .cg-portal-alert-typeconfirm__label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            color: #64748b;
        }
        .cg-portal-alert-typeconfirm__label strong {
            color: #dc2626;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .cg-portal-alert-typeconfirm__input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid rgba(15, 23, 42, 0.14);
            border-radius: 11px;
            min-height: 40px;
            padding: 0 12px;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #0f172a;
            background: #fff;
        }
        .cg-portal-alert-typeconfirm__input:focus {
            outline: none;
            border-color: rgba(220, 38, 38, 0.45);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
        }
        .cg-portal-alert-btn.is-disabled,
        .cg-portal-alert-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }
        body.kanban-theme-dark .cg-portal-alert-typeconfirm__label {
            color: #94a3b8;
        }
        body.kanban-theme-dark .cg-portal-alert-typeconfirm__input {
            background: #0f172a;
            border-color: rgba(148, 163, 184, 0.28);
            color: #e2e8f0;
        }
        .cinepoints-nav-pill {
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            color: #333 !important;
            transition: color 0.3s ease, background 0.3s ease, border-color 0.3s ease;
        }
        .cinepoints-nav-pill .cinepoints-nav-pill__icon {
            color: #ff3c3c !important;
        }
        .cinepoints-nav-pill .cinepoints-nav-pill__value {
            color: inherit !important;
        }
        body.kanban-theme-dark .cinepoints-nav-pill {
            color: #e2e8f0 !important;
            border-color: rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
        }
        body.kanban-theme-dark .cinepoints-nav-pill .cinepoints-nav-pill__value {
            color: #e2e8f0 !important;
        }
    </style>
    <style>
        /* Kanban.php emits these before its large inline <style>; define in head so they never paint in-flow */
        .cg-activity-backdrop:not(.is-open) { display: none; }
        .cg-activity-panel:not(.is-open) {
            position: fixed;
            transform: translateX(100%);
            pointer-events: none;
        }
        /* Board chat: inline column beside board (kanban.php); overrides legacy slide-over if present */
        .cg-kb-board-chat-row {
            margin-top: 4px;
            min-height: min(56vh, 640px);
        }
        .cg-kb-board-chat-row__board {
            min-width: 0;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .cg-kb-board-chat-row__board::-webkit-scrollbar {
            height: 0;
            width: 0;
            background: transparent;
        }
        .cg-kb-board-chat-row__board .cg-kb {
            width: max-content;
            min-width: 100%;
            max-width: none;
        }
        #cgBoardChatPanel.cg-board-chat--inline:not(.is-open) {
            display: none !important;
        }
        #cgBoardChatPanel.cg-board-chat--inline.is-open {
            position: fixed;
            left: 16px;
            top: 88px;
            right: auto;
            bottom: auto;
            transform: none;
            width: 320px;
            max-width: min(360px, calc(100vw - 24px));
            height: auto;
            max-height: min(72vh, calc(100vh - 120px));
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: rgba(255, 255, 255, 0.96);
            border-radius: 16px;
            box-shadow: 0 28px 60px rgba(2, 6, 23, 0.22);
            overflow: hidden;
            pointer-events: auto;
            z-index: 10052;
            touch-action: manipulation;
        }
        #cgBoardChatPanel.cg-board-chat--inline.is-open.cg-board-chat--dragging {
            box-shadow: 0 32px 68px rgba(2, 6, 23, 0.32);
            opacity: 0.98;
        }
        @media (max-width: 576px) {
            #cgBoardChatPanel.cg-board-chat--inline.is-open {
                width: calc(100vw - 20px);
                max-width: none;
                max-height: min(70vh, calc(100vh - 100px));
            }
        }
        /* Scroll area + composer: required if cg_kanban_board_chat.css fails to load from CDN */
        #cgBoardChatMessages {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }
        .cg-board-chat__composer-wrap {
            flex-shrink: 0;
        }
        .cg-board-chat__header,
        .cg-board-chat__online {
            flex-shrink: 0;
        }
        .cg-board-chat__composer-wrap .cg-board-chat__typing {
            flex-shrink: 0;
        }
        #cgBoardChatFab.is-hidden {
            display: none !important;
        }
        body.kanban-theme-dark #cgBoardChatPanel.cg-board-chat--inline.is-open {
            border-color: rgba(148, 163, 184, 0.22);
            background: rgba(30, 41, 59, 0.96);
            box-shadow: 0 28px 60px rgba(0, 0, 0, 0.45);
        }
        .cg-card-context-menu:not(.is-open),
        .cg-card-color-menu:not(.is-open),
        .cg-card-column-flyout:not(.is-open) { display: none; }
        .cg-card-color-picker-popup:not(.is-open) { display: none; }
        .task-detail-bubble:not(.is-visible) { display: none; }
        /* Incremental HTML paint: hide main until the full document is parsed (includes page inline styles) */
        body.cg-kanban-portal:not(.cg-kb-portal-main-ready) main.cg-kanban-portal-main {
            visibility: hidden;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('cg-kb-portal-main-ready');
    });
    </script>
    <noscript><style>body.cg-kanban-portal main.cg-kanban-portal-main { visibility: visible !important; }</style></noscript>
    <?php if (isset($_SESSION['user_id'])): ?>
    <?php
    /* clipboard-sync.js calls https://cinegrid.net/api/clipboard.php. Only same-origin (apex) avoids
       CORS failures; Kanban/staging/other hosts would spam 415/OPTIONS in the console and waste requests. */
    $__cg_host_raw = (string)($_SERVER['HTTP_HOST'] ?? '');
    $__cg_host_lc = strtolower(trim((string)(explode(':', $__cg_host_raw, 2)[0] ?? '')));
    $__cg_clipboard_sync_allowed = ($__cg_host_lc === 'cinegrid.net' || $__cg_host_lc === 'www.cinegrid.net');
    if ($__cg_clipboard_sync_allowed): ?>
    <script src="/api/public_js.php?f=clipboard-sync.js" defer></script>
    <?php endif; ?>
    <?php endif; ?>
</head>
<body class="cg-kanban-fullpage cg-kanban-portal<?php
    if (!empty($cg_kph_body_extra_class)) {
        echo ' ' . htmlspecialchars((string) $cg_kph_body_extra_class, ENT_QUOTES, 'UTF-8');
    }
?>" style="--cg-kanban-portal-header-h: 60px;">
<script>
/* Apply stored theme before paint to avoid a light flash on /boards and other portal pages. */
(function () {
  try {
    if ((localStorage.getItem('kanban_theme_mode') || '') === 'dark') {
      document.body.classList.add('kanban-theme-dark');
    }
  } catch (e) {}
})();
</script>
<script>
(function () {
  var nativeAlert = window.alert ? window.alert.bind(window) : function() {};
  var nativeConfirm = window.confirm ? window.confirm.bind(window) : function() { return false; };
  var queue = [];
  var isShowing = false;
  var refs = null;
  var confirmQueue = [];
  var confirmShowing = false;
  var activeConfirmText = '';

  function normalizeConfirmToken(value) {
    return String(value == null ? '' : value).trim().toUpperCase();
  }

  function portalConfirmTypedValueMatches(token) {
    if (!token) return true;
    if (!refs || !refs.typeInput) return false;
    return normalizeConfirmToken(refs.typeInput.value) === normalizeConfirmToken(token);
  }

  function ensurePortalAlertDom() {
    if (refs) return refs;
    if (!document.body) return null;
    var overlay = document.createElement('div');
    overlay.className = 'cg-portal-alert-overlay';
    overlay.id = 'cgPortalAlertOverlay';
    overlay.innerHTML = ''
      + '<div class="cg-portal-alert-dialog cg-portal-alert-dialog--default" role="alertdialog" aria-modal="true" aria-labelledby="cgPortalAlertTitle" aria-describedby="cgPortalAlertBody">'
      + '  <div class="cg-portal-alert-icon" id="cgPortalAlertIcon" aria-hidden="true"><i class="fas fa-circle-info"></i></div>'
      + '  <div class="cg-portal-alert-head" id="cgPortalAlertTitle">Notice</div>'
      + '  <div class="cg-portal-alert-body" id="cgPortalAlertBody"></div>'
      + '  <div class="cg-portal-alert-typeconfirm" id="cgPortalAlertTypeConfirm" hidden>'
      + '    <label class="cg-portal-alert-typeconfirm__label" for="cgPortalAlertTypeInput">Type <strong>DELETE</strong> to confirm</label>'
      + '    <input type="text" class="cg-portal-alert-typeconfirm__input" id="cgPortalAlertTypeInput" autocomplete="off" spellcheck="false" aria-label="Type DELETE to confirm">'
      + '  </div>'
      + '  <div class="cg-portal-alert-actions">'
      + '    <button type="button" class="cg-portal-alert-btn cg-portal-alert-btn--ghost" id="cgPortalAlertCancel" hidden>Cancel</button>'
      + '    <button type="button" class="cg-portal-alert-btn" id="cgPortalAlertOk">OK</button>'
      + '  </div>'
      + '</div>';
    document.body.appendChild(overlay);
    refs = {
      overlay: overlay,
      dialog: overlay.querySelector('.cg-portal-alert-dialog'),
      icon: overlay.querySelector('#cgPortalAlertIcon'),
      title: overlay.querySelector('#cgPortalAlertTitle'),
      body: overlay.querySelector('#cgPortalAlertBody'),
      typeConfirm: overlay.querySelector('#cgPortalAlertTypeConfirm'),
      typeLabel: overlay.querySelector('.cg-portal-alert-typeconfirm__label'),
      typeInput: overlay.querySelector('#cgPortalAlertTypeInput'),
      ok: overlay.querySelector('#cgPortalAlertOk'),
      cancel: overlay.querySelector('#cgPortalAlertCancel')
    };
    if (refs.ok) {
      refs.ok.addEventListener('click', function () {
        if (confirmShowing && confirmQueue.length) {
          var item = confirmQueue[0];
          if (!portalConfirmTypeMatches(item)) return;
          confirmQueue.shift();
          confirmShowing = false;
          overlay.classList.remove('is-open');
          clearPortalConfirmTypeState();
          if (refs.cancel) refs.cancel.hidden = true;
          if (item && typeof item.resolve === 'function') item.resolve(true);
          if (confirmQueue.length) setTimeout(showNextConfirm, 0);
          return;
        }
        overlay.classList.remove('is-open');
        isShowing = false;
        if (queue.length) {
          setTimeout(showNextAlert, 0);
        } else if (confirmQueue.length) {
          setTimeout(showNextConfirm, 0);
        }
      });
    }
    if (refs.cancel) {
      refs.cancel.addEventListener('click', function () {
        if (!confirmShowing || !confirmQueue.length) return;
        var item = confirmQueue.shift();
        confirmShowing = false;
        overlay.classList.remove('is-open');
        clearPortalConfirmTypeState();
        refs.cancel.hidden = true;
        if (item && typeof item.resolve === 'function') item.resolve(false);
        if (confirmQueue.length) setTimeout(showNextConfirm, 0);
      });
    }
    overlay.addEventListener('click', function (e) {
      if (e.target !== overlay) return;
      if (confirmShowing && refs.cancel) refs.cancel.click();
    });
    return refs;
  }

  function applyPortalAlertVariant(variant) {
    if (!refs || !refs.dialog) return;
    var v = variant === 'danger' || variant === 'success' ? variant : 'default';
    refs.dialog.classList.remove('cg-portal-alert-dialog--default', 'cg-portal-alert-dialog--danger', 'cg-portal-alert-dialog--success');
    refs.dialog.classList.add('cg-portal-alert-dialog--' + v);
    if (refs.icon) {
      var iconClass = v === 'danger' ? 'fa-triangle-exclamation' : (v === 'success' ? 'fa-circle-check' : 'fa-circle-info');
      refs.icon.innerHTML = '<i class="fas ' + iconClass + '"></i>';
    }
  }

  function clearPortalConfirmTypeState() {
    if (!refs) return;
    activeConfirmText = '';
    if (refs.typeConfirm) refs.typeConfirm.hidden = true;
    if (refs.typeInput) {
      refs.typeInput.value = '';
      if (refs.typeInputHandler) {
        refs.typeInput.removeEventListener('input', refs.typeInputHandler);
        refs.typeInput.removeEventListener('keydown', refs.typeInputKeyHandler);
        refs.typeInput.removeEventListener('keyup', refs.typeInputHandler);
        refs.typeInput.removeEventListener('change', refs.typeInputHandler);
        refs.typeInputHandler = null;
        refs.typeInputKeyHandler = null;
      }
    }
    if (refs.ok) {
      refs.ok.disabled = false;
      refs.ok.classList.remove('is-disabled');
    }
  }

  function portalConfirmTypeMatches(item) {
    var token = item && item.confirmText ? String(item.confirmText) : activeConfirmText;
    return portalConfirmTypedValueMatches(token);
  }

  function applyPortalConfirmTypeState(item) {
    if (!refs) return;
    clearPortalConfirmTypeState();
    if (!item || !item.confirmText) return;
    var token = String(item.confirmText);
    activeConfirmText = token;
    if (refs.typeConfirm) refs.typeConfirm.hidden = false;
    if (refs.typeLabel) {
      refs.typeLabel.innerHTML = 'Type <strong>' + token + '</strong> to confirm';
    }
    if (refs.typeInput) {
      refs.typeInput.value = '';
      refs.typeInput.placeholder = token;
      refs.typeInputHandler = function () {
        var matched = portalConfirmTypedValueMatches(activeConfirmText);
        if (refs.ok) {
          if (matched) {
            refs.ok.disabled = false;
            refs.ok.removeAttribute('disabled');
            refs.ok.classList.remove('is-disabled');
          } else {
            refs.ok.disabled = true;
            refs.ok.classList.add('is-disabled');
          }
        }
      };
      refs.typeInputKeyHandler = function (e) {
        if (e.key === 'Enter' && portalConfirmTypeMatches(item) && refs.ok) {
          e.preventDefault();
          refs.ok.click();
        }
      };
      refs.typeInput.addEventListener('input', refs.typeInputHandler);
      refs.typeInput.addEventListener('keydown', refs.typeInputKeyHandler);
      refs.typeInput.addEventListener('keyup', refs.typeInputHandler);
      refs.typeInput.addEventListener('change', refs.typeInputHandler);
      refs.typeInputHandler();
    }
  }

  function showNextConfirm() {
    if (confirmShowing || !confirmQueue.length || isShowing) return;
    var r = ensurePortalAlertDom();
    if (!r || !r.overlay || !r.title || !r.body) {
      var fallback = confirmQueue.shift();
      var ok = nativeConfirm(fallback && fallback.message ? fallback.message : 'Continue?');
      if (fallback && typeof fallback.resolve === 'function') fallback.resolve(!!ok);
      if (confirmQueue.length) setTimeout(showNextConfirm, 0);
      return;
    }
    var item = confirmQueue[0];
    confirmShowing = true;
    applyPortalAlertVariant(item.variant || 'danger');
    r.title.textContent = item.title || 'Confirm';
    r.body.textContent = item.message || '';
    if (r.ok) r.ok.textContent = item.okText || 'OK';
    if (r.cancel) {
      r.cancel.textContent = item.cancelText || 'Cancel';
      r.cancel.hidden = false;
    }
    applyPortalConfirmTypeState(item);
    r.overlay.classList.add('is-open');
    if (item.confirmText && r.typeInput) r.typeInput.focus({ preventScroll: true });
    else if (r.cancel) r.cancel.focus({ preventScroll: true });
    else if (r.ok) r.ok.focus({ preventScroll: true });
  }

  window.cgPortalConfirm = function(message, opts) {
    opts = opts && typeof opts === 'object' ? opts : {};
    return new Promise(function(resolve) {
      confirmQueue.push({
        title: opts.title != null ? String(opts.title) : 'Confirm',
        message: message == null ? '' : String(message),
        okText: opts.okText != null ? String(opts.okText) : 'OK',
        cancelText: opts.cancelText != null ? String(opts.cancelText) : 'Cancel',
        variant: opts.variant != null ? String(opts.variant) : 'danger',
        confirmText: opts.confirmText != null ? String(opts.confirmText) : '',
        resolve: resolve
      });
      showNextConfirm();
    });
  };

  function showNextAlert() {
    if (isShowing || confirmShowing || !queue.length) return;
    var r = ensurePortalAlertDom();
    if (!r || !r.overlay || !r.title || !r.body) {
      var fallback = queue.shift();
      nativeAlert(fallback && fallback.message ? fallback.message : '');
      isShowing = false;
      if (queue.length) setTimeout(showNextAlert, 0);
      return;
    }
    var item = queue.shift();
    isShowing = true;
    applyPortalAlertVariant(item.variant || 'default');
    r.title.textContent = item.title || 'Notice';
    r.body.textContent = item.message || '';
    if (r.ok) r.ok.textContent = item.okText || 'OK';
    if (r.cancel) r.cancel.hidden = true;
    r.overlay.classList.add('is-open');
    if (r.ok) r.ok.focus({ preventScroll: true });
  }

  window.cgPortalAlert = function(message, opts) {
    opts = opts && typeof opts === 'object' ? opts : {};
    queue.push({
      title: opts.title != null ? String(opts.title) : 'Notice',
      message: message == null ? '' : String(message),
      okText: opts.okText != null ? String(opts.okText) : 'OK',
      variant: opts.variant != null ? String(opts.variant) : (opts.success ? 'success' : 'default')
    });
    showNextAlert();
  };

  function cgPortalFriendlyAlertMessage(message) {
    var msg = message == null ? '' : String(message);
    if (/networkerror|failed to fetch|load failed|network request failed/i.test(msg)) {
      return 'Could not reach the server. Check your connection and try again.';
    }
    return msg;
  }

  window.alert = function(message) {
    window.cgPortalAlert(cgPortalFriendlyAlertMessage(message));
  };

  document.addEventListener('contextmenu', function (e) {
    var el = e.target;
    if (el && el.nodeType === 3 && el.parentElement) el = el.parentElement;
    if (el && el.closest && el.closest('.modal')) return;
    if (el && el.closest && (el.closest('.cg-boards-card') || el.closest('.cg-boards-context-menu') || el.closest('.cg-boards-rename-overlay'))) return;
    if (el && el.tagName) {
      var tag = el.tagName.toUpperCase();
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable) return;
    }
    e.preventDefault();
  }, true);
})();
</script>
<header class="cg-kanban-portal-header">
    <div class="container-fluid">
        <a href="/boards" class="cg-kanban-portal-logo" aria-label="Your boards">
            <svg class="cg-kanban-portal-logo-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <rect x="48" y="128" width="112" height="256" rx="24" fill="currentColor"/>
                <rect x="200" y="80" width="112" height="352" rx="24" fill="currentColor"/>
                <rect x="352" y="160" width="112" height="224" rx="24" fill="currentColor"/>
            </svg>
            <span>Kanban</span>
        </a>
        <nav class="cg-kanban-portal-nav" aria-label="Portal">
            <?php if (!$cg_kph_hide_user_menu): ?>
            <?php require __DIR__ . '/cg_cinepoints_nav_pill.php'; ?>
            <div class="cg-kanban-portal-dropdown">
                <button type="button" class="cg-kanban-portal-dropdown-btn" id="cgKanbanPortalUserBtn" aria-expanded="false" aria-haspopup="true">
                    <?php if ($cg_kph_avatar_url !== ''): ?>
                    <img class="cg-kanban-portal-user-avatar" src="<?php echo htmlspecialchars($cg_kph_avatar_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="28" height="28" referrerpolicy="no-referrer" loading="lazy" onerror="this.onerror=null;this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&amp;s=64';">
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($cg_kph_user, ENT_QUOTES, 'UTF-8'); ?></span>
                    <i class="fas fa-chevron-down" style="font-size:0.7rem;color:#6b7280;"></i>
                </button>
                <div class="cg-kanban-portal-dropdown-menu" id="cgKanbanPortalUserMenu" role="menu">
                    <a href="https://cinegrid.net/" class="cg-kanban-portal-dropdown-item" target="_blank" rel="noopener" role="menuitem">
                        <i class="fas fa-home" aria-hidden="true"></i>Homepage
                    </a>
                    <a href="/mywallet.php" class="cg-kanban-portal-dropdown-item" role="menuitem">
                        <i class="fas fa-coins" aria-hidden="true"></i>My Wallet
                    </a>
                    <hr class="cg-kanban-portal-dropdown-divider" aria-hidden="true">
                    <?php if ($cg_kph_show_board_menu_actions): ?>
                    <button type="button" class="cg-kanban-portal-dropdown-item cg-kanban-portal-dropdown-action" id="editBoardBtn" role="menuitem" title="Board settings" aria-label="Board settings"><i class="fas fa-gear" aria-hidden="true"></i>Board settings</button>
                    <a href="/project_manager.php" class="cg-kanban-portal-dropdown-item" role="menuitem" title="Project Manager"><i class="fas fa-tasks" aria-hidden="true"></i>Project Manager</a>
                    <hr class="cg-kanban-portal-dropdown-divider" aria-hidden="true">
                    <button type="button" class="cg-kanban-portal-dropdown-item cg-kanban-portal-dropdown-action" id="activitiesPanelBtn" role="menuitem" title="Activities" aria-label="Open activities panel"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Activities</button>
                    <?php endif; ?>
                    <?php if ((int)($_SESSION['user_id'] ?? 0) > 0): ?>
                    <?php if ($cg_kph_show_board_menu_actions): ?>
                    <hr class="cg-kanban-portal-dropdown-divider" aria-hidden="true">
                    <?php endif; ?>
                    <button type="button" class="cg-kanban-portal-dropdown-item cg-kanban-portal-dropdown-action" id="cgKanbanPortalChatBtn" role="menuitem" title="Board chat" aria-label="Open board chat"><i class="fas fa-comments" aria-hidden="true"></i>Board chat</button>
                    <hr class="cg-kanban-portal-dropdown-divider" aria-hidden="true">
                    <?php endif; ?>
                    <button type="button" class="cg-kanban-portal-dropdown-item cg-kanban-portal-dropdown-action" id="themeToggleBtn" data-kanban-theme-toggle data-kanban-theme-toggle-label role="menuitem" title="Switch to dark mode" aria-label="Switch to dark mode"><i class="fas fa-moon" aria-hidden="true"></i><span class="cg-kanban-portal-theme-label">Dark mode</span></button>
                    <hr class="cg-kanban-portal-dropdown-divider" aria-hidden="true">
                    <?php if ($cg_kph_has_linked_accounts): ?>
                    <a href="/switch_account" class="cg-kanban-portal-dropdown-item switch-account" role="menuitem" title="Switch account" aria-label="Switch account"><i class="fas fa-exchange-alt" aria-hidden="true"></i>Switch account</a>
                    <?php endif; ?>
                    <a href="/settings" class="cg-kanban-portal-dropdown-item" role="menuitem" title="Settings" aria-label="Settings"><i class="fas fa-cog" aria-hidden="true"></i>Settings</a>
                    <hr class="cg-kanban-portal-dropdown-divider" aria-hidden="true">
                    <a href="/logout" class="cg-kanban-portal-dropdown-item logout" role="menuitem">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </nav>
    </div>
</header>
<script>
(function () {
    var btn = document.getElementById('cgKanbanPortalUserBtn');
    var menu = document.getElementById('cgKanbanPortalUserMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        menu.classList.toggle('show');
        btn.setAttribute('aria-expanded', menu.classList.contains('show') ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.cg-kanban-portal-dropdown')) {
            menu.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
    menu.querySelectorAll('.cg-kanban-portal-dropdown-action').forEach(function (actionBtn) {
        actionBtn.addEventListener('click', function () {
            menu.classList.remove('show');
            btn.setAttribute('aria-expanded', 'false');
        });
    });
})();
</script>
<script>
(function () {
  var KEY = 'kanban_theme_mode';
  window.KANBAN_THEME_STORAGE_KEY = window.KANBAN_THEME_STORAGE_KEY || KEY;

  function getStoredKanbanTheme() {
    try {
      return localStorage.getItem(window.KANBAN_THEME_STORAGE_KEY) || 'light';
    } catch (err) {
      return 'light';
    }
  }

  function cgKanbanApplyThemeChrome(mode) {
    var isDark = mode === 'dark';
    document.body.classList.toggle('kanban-theme-dark', isDark);
    document.querySelectorAll('[data-kanban-theme-toggle]').forEach(function (toggleBtn) {
      var labeled = toggleBtn.hasAttribute('data-kanban-theme-toggle-label');
      if (labeled) {
        var label = isDark ? 'Light mode' : 'Dark mode';
        var iconClass = isDark ? 'fa-sun' : 'fa-moon';
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

  window.cgKanbanApplyThemeChrome = cgKanbanApplyThemeChrome;
  if (typeof window.getStoredKanbanTheme !== 'function') {
    window.getStoredKanbanTheme = getStoredKanbanTheme;
  }
  if (typeof window.applyKanbanTheme !== 'function') {
    window.applyKanbanTheme = function (mode) {
      cgKanbanApplyThemeChrome(mode);
    };
  }
  if (typeof window.toggleKanbanTheme !== 'function') {
    window.toggleKanbanTheme = function () {
      var nextMode = document.body.classList.contains('kanban-theme-dark') ? 'light' : 'dark';
      if (typeof window.applyKanbanTheme === 'function') {
        window.applyKanbanTheme(nextMode);
      } else {
        cgKanbanApplyThemeChrome(nextMode);
      }
      try {
        localStorage.setItem(window.KANBAN_THEME_STORAGE_KEY, nextMode);
      } catch (err) {}
    };
  }

  cgKanbanApplyThemeChrome(getStoredKanbanTheme());

  if (!window.__cgKanbanThemeToggleWired) {
    window.__cgKanbanThemeToggleWired = true;
    document.addEventListener('click', function (e) {
      var t = e.target && e.target.closest ? e.target.closest('[data-kanban-theme-toggle]') : null;
      if (!t) return;
      e.preventDefault();
      if (typeof window.toggleKanbanTheme === 'function') {
        window.toggleKanbanTheme();
      }
    }, true);
  }
})();
</script>
<main class="cg-kanban-portal-main">
