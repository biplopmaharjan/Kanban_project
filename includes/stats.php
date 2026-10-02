<?php
/**
 * Kanban board statistics modal (View → Stats). Included from kanban.php only.
 * Solid panel background (light + .kanban-theme-dark).
 */
?>
<style>
/* Bootstrap 5 uses --bs-modal-bg / --bs-modal-color on .modal-content — set on #kanbanStatsModal so they inherit */
#kanbanStatsModal.cg-kanban-stats-modal {
    --bs-modal-zindex: 10060;
    --bs-modal-bg: #fafbfc;
    --bs-modal-color: #0f172a;
    --bs-modal-border-color: rgba(15, 23, 42, 0.1);
    --bs-modal-header-border-color: rgba(15, 23, 42, 0.08);
    --bs-body-bg: #fafbfc;
    --bs-body-color: #0f172a;
    --bs-secondary-color: #64748b;
    --bs-border-color: rgba(15, 23, 42, 0.12);
    /* Bar height scale; tighter corner radius on tracks/fills */
    --cg-stats-bar-radius: 4px;
    --cg-stats-bar-transition-duration: 1.85s;
    --cg-stats-bar-transition-ease: ease-out;
    --cg-stats-bar-mini: 14px;
    --cg-stats-bar-cat: 14px;
    --cg-stats-bar-overall: 16px;
    --cg-stats-bar-team: 16px;
    --cg-stats-icon-mini: 48px;
    --cg-stats-icon-cat: 44px;
    --cg-stats-icon-title: 32px;
    --cg-stats-text-section: 0.6875rem;
    --cg-stats-card-pad: 1.25rem;
    --cg-stats-card-gap: 1.25rem;
    color-scheme: light;
}
body.kanban-theme-dark #kanbanStatsModal.cg-kanban-stats-modal,
body.kanban-theme-dark #kanbanStatsModal.cg-kanban-stats-modal[data-bs-theme="dark"],
#kanbanStatsModal.cg-kanban-stats-modal[data-bs-theme="dark"] {
    --bs-modal-bg: #0f172a;
    --bs-modal-color: #e2e8f0;
    --bs-modal-border-color: rgba(148, 163, 184, 0.22);
    --bs-modal-header-border-color: rgba(148, 163, 184, 0.18);
    --bs-body-bg: #0f172a;
    --bs-body-color: #e2e8f0;
    --bs-secondary-color: #94a3b8;
    --bs-border-color: rgba(148, 163, 184, 0.2);
    color-scheme: dark;
}
/*
 * Bootstrap 5 sets --bs-modal-bg / --bs-body-bg on .modal-content from :root, which shadows
 * variables inherited from #kanbanStatsModal — override on this element so dark mode matches Kanban.
 */
.cg-kanban-stats-modal .modal-content.cg-stats-panel {
    --bs-modal-bg: #fafbfc;
    --bs-modal-color: #0f172a;
    --bs-body-bg: #fafbfc;
    --bs-body-color: #0f172a;
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background-color: #fafbfc !important;
    color: #0f172a;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.12);
}
body.kanban-theme-dark .cg-kanban-stats-modal .modal-content.cg-stats-panel,
#kanbanStatsModal[data-bs-theme="dark"] .modal-content.cg-stats-panel {
    --bs-modal-bg: #0f172a;
    --bs-modal-color: #e2e8f0;
    --bs-body-bg: #0f172a;
    --bs-body-color: #e2e8f0;
    border-color: rgba(148, 163, 184, 0.22);
    background-color: #0f172a !important;
    color: #e2e8f0;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.cg-kanban-stats-modal .modal-header {
    border-bottom: 1px solid var(--bs-modal-header-border-color, rgba(15, 23, 42, 0.08));
    padding: 1rem 1.5rem;
    background: transparent;
}
.cg-kanban-stats-modal .modal-title {
    font-family: "Montserrat", sans-serif;
    font-weight: 700;
    font-size: 1.25rem;
    color: #0f172a;
    letter-spacing: -0.02em;
}
body.kanban-theme-dark .cg-kanban-stats-modal .modal-title {
    color: #f8fafc;
}
.cg-kanban-stats-modal .modal-title .cg-stats-title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: var(--cg-stats-icon-title);
    height: var(--cg-stats-icon-title);
    border-radius: 50%;
    background: linear-gradient(135deg, #ff3c3c 0%, #dc2626 100%);
    color: #fff;
    font-size: 0.8rem;
    margin-right: 0.6rem;
    vertical-align: middle;
}
.cg-kanban-stats-modal .cg-stats-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}
.cg-kanban-stats-modal .cg-stats-delete-btn {
    white-space: nowrap;
    font-size: 0.8125rem;
    font-weight: 600;
    padding: 0.35rem 0.75rem;
}
.cg-kanban-stats-modal .btn-close {
    opacity: 0.55;
}
.cg-kanban-stats-modal .btn-close:hover {
    opacity: 0.9;
}
body.kanban-theme-dark .cg-kanban-stats-modal .btn-close {
    filter: invert(1);
    opacity: 0.75;
}
body.kanban-theme-dark .cg-kanban-stats-modal .btn-close:hover {
    opacity: 1;
}
.cg-kanban-stats-modal .modal-body {
    padding: 0.5rem var(--cg-stats-card-pad) var(--cg-stats-card-pad);
    max-height: min(78vh, 900px);
    overflow-y: auto;
    background: transparent;
    color: var(--bs-modal-color, #0f172a);
}
.cg-kanban-stats-modal .cg-stats-top-stats {
    margin-bottom: var(--cg-stats-card-gap) !important;
}
.cg-kanban-stats-modal .cg-stats-subtitle {
    color: #64748b !important;
    font-size: 0.8125rem;
    margin-bottom: 0.75rem !important;
    font-weight: 500;
    line-height: 1.45;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stats-subtitle {
    color: #94a3b8 !important;
}
/* Summary cards: icon + figure on top, label under, bar bottom (dashboard reference) */
.cg-kanban-stats-modal .cg-stat-mini {
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: linear-gradient(180deg, #fff, #fafafa);
    border-radius: 16px;
    padding: var(--cg-stats-card-pad);
    height: 100%;
    box-shadow: 0 10px 26px rgba(2, 6, 23, 0.06);
    display: flex;
    flex-direction: column;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-mini {
    border-color: rgba(148, 163, 184, 0.2);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.96));
    box-shadow: 0 12px 28px rgba(2, 6, 23, 0.34);
}
.cg-kanban-stats-modal .cg-stat-mini__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.cg-kanban-stats-modal .cg-stat-mini__label {
    font-size: var(--cg-stats-text-section);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 12px;
    line-height: 1.35;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-mini__label {
    color: #94a3b8;
}
.cg-kanban-stats-modal .cg-stat-mini__value {
    font-size: 2rem;
    font-weight: 800;
    font-family: "Montserrat", sans-serif;
    line-height: 1;
    color: #0f172a;
    letter-spacing: -0.03em;
    text-align: right;
    flex: 1;
    min-width: 0;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-mini__value {
    color: #f8fafc;
}
.cg-kanban-stats-modal .cg-stat-mini__icon {
    width: var(--cg-stats-icon-mini);
    height: var(--cg-stats-icon-mini);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.cg-kanban-stats-modal .cg-stat-mini__bar {
    height: var(--cg-stats-bar-mini);
    border-radius: var(--cg-stats-bar-radius);
    background: rgba(15, 23, 42, 0.06);
    margin-top: 0;
    overflow: hidden;
    flex-shrink: 0;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-mini__bar {
    background: rgba(255, 255, 255, 0.1);
}
.cg-kanban-stats-modal .cg-stat-mini__bar > span {
    display: block;
    height: 100%;
    border-radius: var(--cg-stats-bar-radius);
}
.cg-kanban-stats-modal .cg-stat-progress-fill,
.cg-kanban-stats-modal .cg-stat-team-row__fill {
    transition: width var(--cg-stats-bar-transition-duration) var(--cg-stats-bar-transition-ease);
}
/* Diagonal hatch over fill; base color from inline background-color (same tiers as .cg-card__progress-bar) */
.cg-kanban-stats-modal .cg-stat-progress-fill--textured {
    background-image: repeating-linear-gradient(
        -45deg,
        rgba(255, 255, 255, 0.2) 0px,
        rgba(255, 255, 255, 0.2) 1px,
        transparent 1px,
        transparent 7px
    );
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-progress-fill--textured {
    background-image: repeating-linear-gradient(
        -45deg,
        rgba(255, 255, 255, 0.12) 0px,
        rgba(255, 255, 255, 0.12) 1px,
        transparent 1px,
        transparent 7px
    );
}
@media (prefers-reduced-motion: reduce) {
    .cg-kanban-stats-modal .cg-stat-progress-fill,
    .cg-kanban-stats-modal .cg-stat-team-row__fill {
        transition: none;
    }
}
.cg-kanban-stats-modal .cg-stat-mini--total .cg-stat-mini__icon { background: rgba(34, 197, 94, 0.18); color: #16a34a; }
.cg-kanban-stats-modal .cg-stat-mini--done .cg-stat-mini__icon { background: rgba(74, 222, 128, 0.25); color: #15803d; }
.cg-kanban-stats-modal .cg-stat-mini--late .cg-stat-mini__icon { background: rgba(249, 115, 22, 0.2); color: #ea580c; }
.cg-kanban-stats-modal .cg-stat-mini--today .cg-stat-mini__icon { background: rgba(59, 130, 246, 0.18); color: #2563eb; }
.cg-kanban-stats-modal .cg-stat-section-title {
    font-size: var(--cg-stats-text-section);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
    font-weight: 800;
    margin-bottom: 14px;
    margin-top: 0;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-section-title {
    color: #94a3b8;
}
.cg-kanban-stats-modal .cg-stat-team-wrap {
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: linear-gradient(180deg, #fff, #fafafa);
    border-radius: 16px;
    padding: 16px 16px 12px;
    margin-bottom: 16px;
    box-shadow: 0 10px 26px rgba(2, 6, 23, 0.06);
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-team-wrap {
    border-color: rgba(148, 163, 184, 0.2);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.96));
    box-shadow: 0 12px 28px rgba(2, 6, 23, 0.34);
}
.cg-kanban-stats-modal .cg-stat-team-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.cg-kanban-stats-modal .cg-stat-team-row:last-child {
    margin-bottom: 0;
}
.cg-kanban-stats-modal .cg-stat-team-row__avatar-wrap {
    width: 40px;
    height: 40px;
    min-width: 40px;
    flex-shrink: 0;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(15, 23, 42, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-team-row__avatar-wrap {
    background: rgba(255, 255, 255, 0.12);
}
.cg-kanban-stats-modal .cg-stat-team-row__avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.cg-kanban-stats-modal .cg-stat-team-row__initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    color: #475569;
    letter-spacing: -0.02em;
    line-height: 1;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-team-row__initials {
    color: #e2e8f0;
}
.cg-kanban-stats-modal .cg-stat-team-row__track {
    flex: 1;
    min-width: 0;
    height: var(--cg-stats-bar-team);
    border-radius: var(--cg-stats-bar-radius);
    background: rgba(15, 23, 42, 0.06);
    overflow: hidden;
    position: relative;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-team-row__track {
    background: rgba(255, 255, 255, 0.08);
}
.cg-kanban-stats-modal .cg-stat-team-row__fill {
    height: 100%;
    border-radius: var(--cg-stats-bar-radius);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 12px;
    font-size: 0.8125rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    min-width: 2.25rem;
    box-sizing: border-box;
}
.cg-kanban-stats-modal .cg-stat-overall {
    text-align: center;
    padding: 20px 16px;
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: linear-gradient(180deg, #fff, #fafafa);
    margin-top: 0;
    margin-bottom: 4px;
    box-shadow: 0 10px 26px rgba(2, 6, 23, 0.06);
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-overall {
    border-color: rgba(148, 163, 184, 0.2);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.96));
    box-shadow: 0 12px 28px rgba(2, 6, 23, 0.34);
}
.cg-kanban-stats-modal .cg-stat-overall__label {
    font-size: var(--cg-stats-text-section);
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #64748b;
    font-weight: 800;
    margin-bottom: 8px;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-overall__label {
    color: #94a3b8;
}
.cg-kanban-stats-modal .cg-stat-overall__value {
    font-size: 2.75rem;
    font-weight: 800;
    font-family: "Montserrat", sans-serif;
    color: #14b8a8;
    line-height: 1.05;
    letter-spacing: -0.04em;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-overall__value {
    color: #2dd4bf;
}
.cg-kanban-stats-modal .cg-stat-alert {
    border-radius: 12px;
    padding: 13px 15px;
    margin-bottom: 10px;
    font-size: 0.875rem;
    border: 1px solid transparent;
}
.cg-kanban-stats-modal .cg-stat-alert--danger {
    background: rgba(254, 226, 226, 0.92);
    border-color: rgba(248, 113, 113, 0.45);
    color: #7f1d1d;
}
.cg-kanban-stats-modal .cg-stat-alert--danger .cg-stat-alert__title {
    color: #991b1b;
}
.cg-kanban-stats-modal .cg-stat-alert--danger .cg-stat-alert__sub {
    color: #9f1239;
    opacity: 0.95;
}
.cg-kanban-stats-modal .cg-stat-alert--warn {
    background: rgba(255, 247, 237, 0.95);
    border-color: rgba(251, 146, 60, 0.4);
    color: #7c2d12;
}
.cg-kanban-stats-modal .cg-stat-alert--warn .cg-stat-alert__sub {
    color: #9a3412;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-alert--danger {
    background: rgba(127, 29, 29, 0.4);
    border-color: rgba(248, 113, 113, 0.35);
    color: #fecaca;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-alert--danger .cg-stat-alert__title {
    color: #fecaca;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-alert--danger .cg-stat-alert__sub {
    color: #fca5a5;
    opacity: 1;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-alert--warn {
    background: rgba(154, 52, 18, 0.35);
    border-color: rgba(251, 146, 60, 0.35);
    color: #fed7aa;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-alert--warn .cg-stat-alert__sub {
    color: #fdba74;
}
.cg-kanban-stats-modal .cg-stat-alert__title {
    font-weight: 700;
    margin-bottom: 6px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    line-height: 1.35;
}
.cg-kanban-stats-modal .cg-stat-alert__title i {
    margin-top: 2px;
    opacity: 0.9;
}
.cg-kanban-stats-modal .cg-stat-alert__sub {
    font-size: 0.8rem;
    line-height: 1.4;
    padding-left: 1.6rem;
}
.cg-kanban-stats-modal .cg-stat-empty {
    color: #64748b;
    font-size: 0.88rem;
    text-align: center;
    padding: 16px 8px;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-empty {
    color: #94a3b8;
}
/* Layout: main (by column) / sidebar */
.cg-kanban-stats-modal .cg-stats-layout-grid {
    align-items: flex-start;
    --bs-gutter-x: 1.5rem;
    --bs-gutter-y: 1.5rem;
}
.cg-kanban-stats-modal .cg-stats-main-col {
    min-width: 0;
}
@media (min-width: 992px) {
    .cg-kanban-stats-modal .cg-stats-main-col {
        padding-right: 1rem;
        border-right: 1px solid rgba(15, 23, 42, 0.07);
    }
    body.kanban-theme-dark .cg-kanban-stats-modal .cg-stats-main-col {
        border-right-color: rgba(148, 163, 184, 0.16);
    }
    .cg-kanban-stats-modal .cg-stats-side-col {
        padding-left: 0.75rem;
    }
}
.cg-kanban-stats-modal .cg-stat-category-card {
    border-radius: 16px;
    padding: var(--cg-stats-card-pad);
    min-height: 100%;
    border: 1px solid rgba(15, 23, 42, 0.1);
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 8px 20px rgba(2, 6, 23, 0.06);
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-category-card {
    border-color: rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.92);
    box-shadow: 0 8px 20px rgba(2, 6, 23, 0.28);
}
.cg-kanban-stats-modal .cg-stat-category-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}
.cg-kanban-stats-modal .cg-stat-category-card__titlewrap {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.cg-kanban-stats-modal .cg-stat-category-card__titlewrap .cg-stat-category-card__icon {
    flex-shrink: 0;
    width: var(--cg-stats-icon-cat);
    height: var(--cg-stats-icon-cat);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.0625rem;
}
.cg-kanban-stats-modal .cg-stat-category-card__name {
    font-weight: 700;
    font-size: 0.9375rem;
    line-height: 1.3;
    word-break: break-word;
    color: #0f172a;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-category-card__name {
    color: #f8fafc;
}
.cg-kanban-stats-modal .cg-stat-category-card__menu {
    border: none;
    background: transparent;
    padding: 4px;
    line-height: 1;
    opacity: 0.45;
    flex-shrink: 0;
}
.cg-kanban-stats-modal .cg-stat-category-card__barline {
    display: flex;
    align-items: center;
    gap: 10px;
}
.cg-kanban-stats-modal .cg-stat-category-card__pct {
    font-weight: 800;
    font-size: 0.875rem;
    flex-shrink: 0;
    min-width: 2.75rem;
    text-align: right;
    color: #64748b;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-category-card__pct {
    color: #94a3b8;
}
.cg-kanban-stats-modal .cg-stat-category-card__track {
    flex: 1;
    min-width: 0;
    height: var(--cg-stats-bar-cat);
    border-radius: var(--cg-stats-bar-radius);
    overflow: hidden;
    background: rgba(15, 23, 42, 0.07);
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-category-card__track {
    background: rgba(255, 255, 255, 0.1);
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-category-card__titlewrap .cg-stat-category-card__icon {
    background: rgba(255, 255, 255, 0.1) !important;
}
.cg-kanban-stats-modal .cg-stat-category-card__track > span {
    display: block;
    height: 100%;
    border-radius: var(--cg-stats-bar-radius);
}
.cg-kanban-stats-modal .cg-stat-overall__track {
    height: var(--cg-stats-bar-overall);
    border-radius: var(--cg-stats-bar-radius);
    overflow: hidden;
    margin-top: 14px;
    background: rgba(15, 23, 42, 0.07);
}
.cg-kanban-stats-modal .cg-stat-overall__track > span {
    display: block;
    height: 100%;
    border-radius: inherit;
}
body.kanban-theme-dark .cg-kanban-stats-modal .cg-stat-overall__track {
    background: rgba(255, 255, 255, 0.1);
}
.cg-kanban-stats-modal .cg-stat-side-stack {
    display: flex;
    flex-direction: column;
    gap: 0;
}
/* Dark mode: dimmer backdrop so the panel matches the board (Bootstrap backdrop sits above the page) */
body.kanban-theme-dark.modal-open .modal-backdrop.show {
    --bs-backdrop-bg: #020617;
    opacity: 0.72;
}
</style>
<div class="modal fade cg-kanban-stats-modal" id="kanbanStatsModal" tabindex="-1" aria-hidden="true" aria-labelledby="kanbanStatsModalTitle" data-bs-theme="light">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content cg-stats-panel">
            <div class="modal-header border-0 flex-column align-items-stretch pb-0 px-4 pt-3">
                <div class="d-flex align-items-center justify-content-between w-100">
                    <h5 class="modal-title mb-0" id="kanbanStatsModalTitle">
                        <span class="cg-stats-title-icon" aria-hidden="true"><i class="fas fa-chart-pie"></i></span>Board stats
                    </h5>
                    <div class="cg-stats-header-actions">
                        <button type="button" class="btn btn-outline-danger btn-sm cg-stats-delete-btn" id="kanbanStatsDeleteBoardBtn" style="display: none;"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i>Delete board</button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <p class="cg-stats-subtitle small mb-0 mt-2" id="cgStatsBoardSubtitle">—</p>
            </div>
            <div class="modal-body pt-2 px-4">
                <div class="row g-3 g-lg-4 mb-0 cg-stats-top-stats">
                    <div class="col-6 col-lg-3">
                        <div class="cg-stat-mini cg-stat-mini--total">
                            <div class="cg-stat-mini__top">
                                <span class="cg-stat-mini__icon" aria-hidden="true"><i class="fas fa-briefcase"></i></span>
                                <span class="cg-stat-mini__value" id="cgStatsTotal">0</span>
                            </div>
                            <div class="cg-stat-mini__label">Total tasks</div>
                            <div class="cg-stat-mini__bar"><span id="cgStatsTotalBar" class="cg-stat-progress-fill" data-cg-width="0" style="width:0%"></span></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="cg-stat-mini cg-stat-mini--done">
                            <div class="cg-stat-mini__top">
                                <span class="cg-stat-mini__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                                <span class="cg-stat-mini__value" id="cgStatsCompletedNum">0</span>
                            </div>
                            <div class="cg-stat-mini__label">Completed</div>
                            <div class="cg-stat-mini__bar"><span id="cgStatsCompletedBar" class="cg-stat-progress-fill" data-cg-width="0" style="width:0%"></span></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="cg-stat-mini cg-stat-mini--late">
                            <div class="cg-stat-mini__top">
                                <span class="cg-stat-mini__icon" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>
                                <span class="cg-stat-mini__value" id="cgStatsOverdue">0</span>
                            </div>
                            <div class="cg-stat-mini__label">Overdue</div>
                            <div class="cg-stat-mini__bar"><span id="cgStatsOverdueBar" class="cg-stat-progress-fill" data-cg-width="0" style="width:0%"></span></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="cg-stat-mini cg-stat-mini--today">
                            <div class="cg-stat-mini__top">
                                <span class="cg-stat-mini__icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>
                                <span class="cg-stat-mini__value" id="cgStatsDueToday">0</span>
                            </div>
                            <div class="cg-stat-mini__label">Due today</div>
                            <div class="cg-stat-mini__bar"><span id="cgStatsDueTodayBar" class="cg-stat-progress-fill" data-cg-width="0" style="width:0%"></span></div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 cg-stats-layout-grid">
                    <div class="col-12 col-lg-9 cg-stats-main-col">
                        <div class="cg-stat-section-title">By column</div>
                        <div class="row g-3 g-lg-4 mb-0" id="cgStatsCategoryCards"></div>
                        <div class="cg-stat-section-title mt-4">Alerts</div>
                        <div id="cgStatsAlertsWrap">
                            <div class="cg-stat-alert cg-stat-alert--danger d-none" id="cgStatsAlertHigh">
                                <div class="cg-stat-alert__title"><i class="fas fa-clock" aria-hidden="true"></i><span id="cgStatsAlertHighTitle">0 high priority tasks overdue</span></div>
                                <div class="cg-stat-alert__sub" id="cgStatsAlertHighSub"></div>
                            </div>
                            <div class="cg-stat-alert cg-stat-alert--warn d-none" id="cgStatsAlertStuck">
                                <div class="cg-stat-alert__title"><i class="fas fa-hourglass-half" aria-hidden="true"></i><span id="cgStatsAlertStuckTitle">0 tasks stuck in progress</span></div>
                                <div class="cg-stat-alert__sub" id="cgStatsAlertStuckSub"></div>
                            </div>
                            <p class="cg-stat-empty small mb-0 d-none" id="cgStatsAlertsEmpty">No alerts</p>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3 cg-stats-side-col cg-stat-side-stack">
                        <div class="cg-stat-section-title">Team activity</div>
                        <div class="cg-stat-team-wrap">
                            <div id="cgStatsTeamActivity"></div>
                        </div>
                        <div class="cg-stat-overall">
                            <div class="cg-stat-overall__label">Overall progress</div>
                            <div class="cg-stat-overall__value" id="cgStatsOverallPct">0%</div>
                            <div class="cg-stat-overall__track" aria-hidden="true"><span id="cgStatsOverallFill" class="cg-stat-progress-fill" data-cg-width="0" style="width:0%"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
