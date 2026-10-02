<?php
/**
 * Echo once per response: UI scale to 85% (15% smaller).
 * Wrap content in <div class="cg-page-scale-80">…</div> or add class to .fmain-content / .cg-workspace-page.
 * (Class name is historical; scale is 0.85.)
 */
if (defined('CG_PAGE_SCALE_80_RULES_ECHOED')) {
    return;
}
define('CG_PAGE_SCALE_80_RULES_ECHOED', true);
?>
<style id="cg-page-scale-80-rules">
/* 85% scale (~15% smaller). Prefer zoom (layout follows visual in Chromium). */
.cg-page-scale-80 {
    transform: scale(0.85);
    transform-origin: top left;
    width: calc(100% / 0.85);
    min-height: calc(100vh / 0.85);
    box-sizing: border-box;
}
@supports (zoom: 0.85) {
    .cg-page-scale-80 {
        zoom: 0.85;
        transform: none;
        width: auto;
        min-height: 0;
    }
}
/*
 * kanban.cinegrid.net portal: <main> is a flex column; the scale wrapper was shrink-wrapping
 * to content height while .fmain-content holds the dot-grid background — so only the lower
 * band showed dots. Stretch wrapper + fmain to fill <main> below the fixed header.
 */
body.cg-kanban-portal.cg-kanban-fullpage main.cg-kanban-portal-main > .cg-page-scale-80 {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    align-self: stretch;
    width: 100%;
    box-sizing: border-box;
}
body.cg-kanban-portal.cg-kanban-fullpage main.cg-kanban-portal-main > .cg-page-scale-80 > .fmain-content {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    box-sizing: border-box;
}
/* Project Manager: scale class is on .fmain-content itself */
body.cg-kanban-portal.cg-kanban-fullpage main.cg-kanban-portal-main > .fmain-content.cg-page-scale-80 {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    box-sizing: border-box;
}
/*
 * Bootstrap modals are usually outside .cg-page-scale-80 (siblings on body), so they read as
 * oversized next to 0.85-scaled UI. Match dialog size to the page scale.
 * Board chat (#cgBoardChatPanel / FAB) is not a .modal — unchanged. Opt out: .cg-modal-no-page-scale on .modal-dialog.
 */
@supports (zoom: 0.85) {
    body:has(.cg-page-scale-80) .modal .modal-dialog:not(.cg-modal-no-page-scale) {
        zoom: 0.85;
    }
}
@supports not (zoom: 0.85) {
    body:has(.cg-page-scale-80) .modal.fade .modal-dialog:not(.cg-modal-no-page-scale) {
        transform: translate(0, -50px) scale(0.85);
        transform-origin: center center;
    }
    body:has(.cg-page-scale-80) .modal.show .modal-dialog:not(.cg-modal-no-page-scale) {
        transform: scale(0.85);
        transform-origin: center center;
    }
}
/*
 * Kanban card overlays: zoom lives on inner .cg-card-context-menu__surface so #cardContextMenu can be
 * positioned with clientX/clientY without browser zoom/left mismatch. Board chat unchanged.
 */
@supports (zoom: 0.85) {
    body:has(.cg-page-scale-80) .cg-card-context-menu__surface,
    body:has(.cg-page-scale-80) .cg-card-color-menu,
    body:has(.cg-page-scale-80) .cg-card-column-flyout,
    body:has(.cg-page-scale-80) .cg-card-color-picker-popup {
        zoom: 0.85;
    }
}
@supports not (zoom: 0.85) {
    body:has(.cg-page-scale-80) .cg-card-context-menu__surface,
    body:has(.cg-page-scale-80) .cg-card-color-menu,
    body:has(.cg-page-scale-80) .cg-card-column-flyout,
    body:has(.cg-page-scale-80) .cg-card-color-picker-popup {
        transform: scale(0.85);
        transform-origin: top left;
    }
}
</style>
<script>
(function () {
  if (window.__cgBootstrapModalBackdropCleanup) return;
  window.__cgBootstrapModalBackdropCleanup = true;
  function cgSweepOrphanModalBackdrops() {
    if (document.querySelectorAll('.modal.show').length) return;
    document.querySelectorAll('.modal-backdrop').forEach(function (el) {
      el.remove();
    });
    document.body.classList.remove('modal-open');
    var s = document.body.style;
    if (s.overflow === 'hidden') s.overflow = '';
    if (s.paddingRight) s.paddingRight = '';
    if (s.paddingLeft) s.paddingLeft = '';
  }
  document.addEventListener('hidden.bs.modal', function () {
    requestAnimationFrame(function () {
      cgSweepOrphanModalBackdrops();
      setTimeout(cgSweepOrphanModalBackdrops, 48);
    });
  });
})();
</script>
