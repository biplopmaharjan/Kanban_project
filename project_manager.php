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

cg_require_freelancer();
cg_ensure_freelance_tables();

$page_title = 'Project Manager';
if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) {
    $cg_kph_board_menu_actions = false;
    require_once __DIR__ . '/includes/cg_kanban_portal_header.php';
} else {
    require_once $cg_public_root . '/includes/header.php';
}
?>
<?php require_once __DIR__ . '/includes/cg_page_scale_80_apply.php'; ?>

<div class="fmain-content cg-project-manager-page cg-page-scale-80" style="overflow-x: hidden; overflow-y: auto;">
    <div class="container-fluid cg-pm-container" style="padding: 12px 32px; max-width: 100%; width: 100%;">
        <div id="projectManagerRoot">
            <div class="text-center py-5">
                <div class="spinner-border text-danger" role="status" aria-label="Loading"></div>
                <div class="text-muted mt-2">Loading project data…</div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Board Modal -->
<div class="modal fade" id="editBoardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Board</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="editBoardForm">
        <input type="hidden" id="edit_board_id" name="board_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Board Name</label>
            <input class="form-control" id="edit_board_name" name="name" required />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Notification emails</label>
            <p class="text-muted small mb-2">Send these addresses one collective board activity email every 24 hours for card updates, moves, and client timeline comments.</p>
            <div id="editBoardEmailsList" class="mb-2" style="min-height: 24px;"></div>
            <div class="d-flex gap-2">
                <input type="email" class="form-control form-control-sm" id="editBoardNewEmailInput" placeholder="email@example.com" />
                <button type="button" class="btn btn-outline-danger btn-sm" id="editBoardAddEmailBtn"><i class="fas fa-plus me-1"></i>Add</button>
            </div>
            <div class="invalid-feedback" id="editBoardEmailError" style="display: none;"></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Timeline Modal -->
<div class="modal fade" id="editTimelineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Timeline</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="editTimelineForm">
        <input type="hidden" id="edit_timeline_id" name="chart_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Timeline Name</label>
            <input class="form-control" id="edit_timeline_name" name="name" required />
        </div>
        <input type="hidden" id="edit_timeline_board" name="source_board_id" />
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="deleteConfirmMessage">Are you sure you want to delete this item?</p>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Warning:</strong> This action cannot be undone.
        </div>
        <div class="d-none" id="deleteConfirmTypeWrap">
          <label class="form-label small text-muted mb-1" for="deleteConfirmTypeInput">Type <strong class="text-danger">DELETE</strong> to confirm</label>
          <input type="text" class="form-control" id="deleteConfirmTypeInput" autocomplete="off" spellcheck="false" aria-label="Type DELETE to confirm" placeholder="DELETE">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
            <i class="fas fa-trash me-2"></i>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Move Card Modal -->
<div class="modal fade" id="moveCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Move Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="moveCardForm">
        <input type="hidden" id="move_card_id" name="card_id" />
        <p class="mb-2 text-muted small" id="moveCardTitleLabel">Card: —</p>
        <div class="mb-3">
            <label class="form-label fw-semibold">Target Board</label>
            <select class="form-select" id="move_target_board" name="target_board_id">
                <option value="">Select a board...</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Target Column</label>
            <select class="form-select" id="move_target_column" name="target_column_id">
                <option value="">Select a column...</option>
            </select>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-exchange-alt me-2"></i>Move</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.cg-project-manager-page {
    min-height: 100vh;
    background-color: #f8fafc;
    --cg-dot-color: rgba(15, 23, 42, 0.08);
    --cg-grid-size: 18px;
    background-image: radial-gradient(circle, var(--cg-dot-color) 1.2px, transparent 1.6px);
    background-size: var(--cg-grid-size) var(--cg-grid-size);
}
body.cg-kanban-portal.cg-kanban-fullpage .fmain-content.cg-project-manager-page {
    flex: 1 1 auto;
    min-height: 0 !important;
    height: auto !important;
    max-height: none !important;
    box-sizing: border-box;
}
body.cg-kanban-portal .cg-pm-container.container-fluid {
    padding-top: 16px !important;
    padding-bottom: 12px !important;
    padding-left: max(1.25rem, env(safe-area-inset-left, 0px)) !important;
    padding-right: max(1.25rem, env(safe-area-inset-right, 0px)) !important;
}
@media (min-width: 576px) {
    body.cg-kanban-portal .cg-pm-container.container-fluid {
        padding-left: max(1.5rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(1.5rem, env(safe-area-inset-right, 0px)) !important;
    }
}
@media (min-width: 992px) {
    body.cg-kanban-portal .cg-pm-container.container-fluid {
        padding-left: max(2rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(2rem, env(safe-area-inset-right, 0px)) !important;
    }
}
body.cg-kanban-fullpage #mainNav {
    transform: translateY(-100%);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.3s ease, opacity 0.3s ease !important;
}
body.cg-kanban-fullpage main {
    margin-top: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}
body.cg-kanban-fullpage .fmain-content.cg-project-manager-page {
    margin-left: 0 !important;
    margin-top: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    min-height: 100vh;
    box-sizing: border-box;
}
@media (min-width: 769px) {
    body.cg-kanban-fullpage .fmain-content.cg-project-manager-page {
        min-height: 100vh;
    }
}
.kanban-theme-dark .fmain-content.cg-project-manager-page {
    background-color: #0f172a;
    --cg-dot-color: rgba(255, 255, 255, 0.06);
}
.kanban-theme-dark .pm-hero h2,
.kanban-theme-dark .pm-section-title,
.kanban-theme-dark .pm-title,
.kanban-theme-dark .pm-metric .pm-metric-value {
    color: #e2e8f0;
}
.kanban-theme-dark .pm-hero .pm-hero-sub,
.kanban-theme-dark .pm-metric .pm-metric-title,
.kanban-theme-dark .pm-meta {
    color: #94a3b8;
}
.kanban-theme-dark .pm-hero,
.kanban-theme-dark .pm-section,
.kanban-theme-dark .pm-metric,
.kanban-theme-dark .pm-card {
    background: rgba(30, 41, 59, 0.85);
    border-color: rgba(148, 163, 184, 0.12);
}

.pm-hero {
    background: radial-gradient(circle at 15% 20%, rgba(224,49,49,0.12), transparent 25%), 
                radial-gradient(circle at 85% 10%, rgba(59,130,246,0.12), transparent 22%),
                linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border: 1px solid rgba(15,23,42,0.06);
    border-radius: 18px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 10px 30px rgba(15,23,42,0.06);
}
.pm-hero h2 {
    margin: 0;
    font-weight: 800;
    color: #0f172a;
}
.pm-hero .pm-hero-sub {
    color: #475569;
    margin-top: 4px;
}
.pm-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin: 18px 0;
}
.pm-metric {
    background: #fff;
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 14px;
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.04);
}
.pm-metric-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(224,49,49,0.08);
    color: #e03131;
    font-size: 18px;
}
.pm-metric .pm-metric-title {
    font-size: 0.9rem;
    color: #475569;
    margin-bottom: 2px;
    font-weight: 600;
}
.pm-metric .pm-metric-value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
}
.pm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 16px;
}
.pm-section {
    background: #fff;
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(15,23,42,0.05);
}
.pm-section-title {
    font-weight: 800;
    font-size: 1.2rem;
    color: #0f172a;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.pm-card {
    border: 1px solid rgba(15,23,42,0.07);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 6px 18px rgba(15,23,42,0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.pm-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(15,23,42,0.10);
}
.pm-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}
.pm-title {
    font-size: 1.05rem;
    font-weight: 400;
    color: #0f172a;
    margin: 0 0 6px 0;
}
.pm-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    font-size: 0.9rem;
    color: #475569;
}
.pm-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 400;
    background: rgba(224,49,49,0.08);
    color: #c53030;
}
.pm-badge.neutral { background: rgba(15,23,42,0.06); color: #0f172a; }
.pm-badge.blue { background: rgba(59,130,246,0.1); color: #1d4ed8; }
.pm-badge.green { background: rgba(16,185,129,0.12); color: #0f9f6e; }
.pm-badge.purple { background: rgba(139,92,246,0.12); color: #6d28d9; }
.pm-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.pm-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 10px;
    background: rgba(15,23,42,0.05);
    color: #334155;
    font-size: 0.85rem;
    font-weight: 400;
}
.pm-card-list { border-top: 1px solid rgba(15,23,42,0.06); }
.pm-card-list-collapsed { display: none; }
.pm-card-row { background: rgba(15,23,42,0.03); }
.pm-card-row:hover { background: rgba(15,23,42,0.06); }
.pm-cards-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    font-size: 0.875rem;
    color: #e03131;
    background: rgba(224,49,49,0.08);
    border: 1px solid rgba(224,49,49,0.2);
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
}
.pm-cards-toggle-btn:hover { background: rgba(224,49,49,0.14); color: #c92a2a; }
.pm-cards-toggle-btn .fa-chevron-down, .pm-cards-toggle-btn .fa-chevron-up { font-size: 0.7rem; transition: transform 0.2s; }
.pm-btn {
    padding: 7px 12px;
    font-size: 0.9rem;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
}
.pm-btn-view { background: #e8f0ff; color: #1d4ed8; border-color: rgba(29,78,216,0.15); }
.pm-btn-view:hover { background: #dbeafe; }
.pm-btn-edit { background: #fff7ed; color: #c05621; border-color: rgba(192,86,33,0.2); }
.pm-btn-edit:hover { background: #ffedd5; }
.pm-btn-delete { background: #fef2f2; color: #b91c1c; border-color: rgba(185,28,28,0.15); }
.pm-btn-delete:hover { background: #fee2e2; }
.pm-btn-move { background: #f5f3ff; color: #6d28d9; border-color: rgba(109,40,217,0.18); }
.pm-btn-move:hover { background: #ede9fe; }
.pm-divider {
    height: 1px;
    background: rgba(15,23,42,0.06);
    margin: 12px 0;
}
.pm-empty {
    text-align: center;
    padding: 42px 18px;
    color: #475569;
    border: 1px dashed rgba(15,23,42,0.14);
    border-radius: 14px;
    background: rgba(248,250,252,0.8);
}
.pm-empty i {
    font-size: 44px;
    margin-bottom: 12px;
    opacity: 0.6;
}
</style>

<script>
let PM = {
    boards: [],
    timelines: [],
    projects: [],
    cards: [],
    columns: []
};

function qs(sel, root=document){ return root.querySelector(sel); }
function qsa(sel, root=document){ return root.querySelectorAll(sel); }

function escapeHtml(str) {
    return (str ?? '').toString()
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
}

async function loadProjectData() {
    try {
        // Load boards
        const boardsRes = await fetch('api/freelance_kanban.php?action=list_all', { credentials:'same-origin' });
        const boardsData = await boardsRes.json();
        PM.boards = boardsData.success ? (boardsData.boards || []) : [];

        // Load timelines
        const timelinesRes = await fetch('/api/freelance_timeline.php?action=list_all', { credentials:'same-origin' });
        const timelinesData = await timelinesRes.json();
        PM.timelines = timelinesData.success ? (timelinesData.timelines || []) : [];

        // Load cards and columns (for move card)
        const cardsRes = await fetch('api/freelance_kanban.php?action=list_cards', { credentials:'same-origin' });
        const cardsData = await cardsRes.json();
        PM.cards = cardsData.success ? (cardsData.cards || []) : [];
        const colsRes = await fetch('api/freelance_kanban.php?action=list_columns', { credentials:'same-origin' });
        const colsData = await colsRes.json();
        PM.columns = colsData.success ? (colsData.columns || []) : [];

        renderProjectManager();
        if (window.refreshSidebars) {
            window.refreshSidebars('both');
        }
    } catch (err) {
        console.error('Load error:', err);
        qs('#projectManagerRoot').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to load project data.</div>`;
    }
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function computeTotals() {
    const totalBoards = PM.boards.length;
    const totalTimelines = PM.timelines.length;
    const totalColumns = PM.boards.reduce((sum, b) => sum + (parseInt(b.column_count || 0, 10)), 0);
    const totalCards = PM.boards.reduce((sum, b) => sum + (parseInt(b.card_count || 0, 10)), 0);
    return { totalBoards, totalTimelines, totalColumns, totalCards };
}

function renderProjectManager() {
    const root = qs('#projectManagerRoot');
    const { totalBoards, totalTimelines, totalColumns, totalCards } = computeTotals();

    let html = '';

    // Hero
    html += `<div class="pm-hero">
        <div>
            <h2>Project Manager</h2>
            <div class="pm-hero-sub">Boards, timelines, cards, and columns in one place.</div>
        </div>
    </div>`;

    // Metrics
    html += '<div class="pm-metrics">';
    html += `<div class="pm-metric"><div class="pm-metric-icon"><i class="fas fa-table-columns"></i></div><div><div class="pm-metric-title">Boards</div><div class="pm-metric-value">${totalBoards}</div></div></div>`;
    html += `<div class="pm-metric"><div class="pm-metric-icon" style="background: rgba(16,185,129,0.12); color:#0f9f6e;"><i class="fas fa-diagram-project"></i></div><div><div class="pm-metric-title">Timelines</div><div class="pm-metric-value">${totalTimelines}</div></div></div>`;
    html += `<div class="pm-metric"><div class="pm-metric-icon" style="background: rgba(59,130,246,0.12); color:#1d4ed8;"><i class="fas fa-layer-group"></i></div><div><div class="pm-metric-title">Columns</div><div class="pm-metric-value">${totalColumns}</div></div></div>`;
    html += `<div class="pm-metric"><div class="pm-metric-icon" style="background: rgba(245,158,11,0.15); color:#c05621;"><i class="fas fa-clipboard-list"></i></div><div><div class="pm-metric-title">Cards</div><div class="pm-metric-value">${totalCards}</div></div></div>`;
    html += '</div>';

    // Grid of sections
    html += '<div class="pm-grid">';

    // Boards Section
    html += '<div class="pm-section">';
    html += '<div class="pm-section-title"><i class="fas fa-table-columns"></i> Kanban Boards</div>';
    if (PM.boards.length === 0) {
        html += '<div class="pm-empty"><i class="fas fa-inbox"></i><div>No boards found</div></div>';
    } else {
        PM.boards.forEach(board => {
            const cardCount = board.card_count || 0;
            const columnCount = board.column_count || 0;
            const updated = formatDateLabel(board.updated_at) || '—';
            const isOwner = String(board.is_owner) === '1' || board.is_owner === 1 || board.is_owner === true;
            html += `<div class="pm-card" data-board-id="${board.id}">`;
            html += '<div class="pm-card-head">';
            html += `<div><div class="pm-title">${escapeHtml(board.name)}</div>`;
            html += `<div class="pm-meta"><span class="pm-badge blue"><i class="fas fa-clock"></i>Updated ${escapeHtml(updated)}</span></div></div>`;
            html += '<div class="pm-actions">';
            html += `<button class="pm-btn pm-btn-view" onclick="viewBoard(${board.id})"><i class="fas fa-eye"></i>View</button>`;
            if (isOwner) {
                html += `<button class="pm-btn pm-btn-edit" onclick="editBoard(${board.id})"><i class="fas fa-pen"></i>Edit</button>`;
                html += `<button class="pm-btn pm-btn-delete" onclick="deleteBoard(${board.id}, '${escapeHtml(board.name)}')"><i class="fas fa-trash"></i>Delete</button>`;
            }
            html += '</div></div>';
            html += '<div class="pm-divider"></div>';
            html += '<div class="pm-meta" style="gap:12px;">';
            html += `<span class="pm-chip"><i class="fas fa-columns"></i>${columnCount} columns</span>`;
            html += `<span class="pm-chip"><i class="fas fa-clipboard-list"></i>${cardCount} cards</span>`;
            html += '</div>';
            const boardCards = (PM.cards || []).filter(c => parseInt(c.board_id, 10) === parseInt(board.id, 10));
            if (boardCards.length > 0) {
                const cardLabel = boardCards.length === 1 ? '1 card' : boardCards.length + ' cards';
                html += '<div class="pm-cards-expandable mt-2" data-board-id="' + board.id + '">';
                html += '<button type="button" class="pm-cards-toggle-btn" onclick="toggleBoardCards(this)" aria-expanded="false">';
                html += '<i class="fas fa-chevron-down"></i> View all ' + cardLabel;
                html += '</button>';
                html += '<div class="pm-card-list pm-card-list-collapsed mt-2">';
                boardCards.forEach(card => {
                    html += `<div class="pm-card-row d-flex align-items-center justify-content-between gap-2 py-1 px-2 rounded small">
                        <span class="text-truncate">${escapeHtml(card.title || 'Untitled')}</span>
                        <span class="text-muted">${escapeHtml(card.column_name || '')}</span>
                        <button type="button" class="pm-btn pm-btn-move btn btn-sm" onclick="moveCard(${card.id})"><i class="fas fa-exchange-alt"></i> Move</button>
                    </div>`;
                });
                html += '</div></div>';
            }
            html += '</div>';
        });
    }
    html += '</div>';

    // Timelines Section
    html += '<div class="pm-section">';
    html += '<div class="pm-section-title"><i class="fas fa-diagram-project"></i> Project Timelines</div>';
    if (PM.timelines.length === 0) {
        html += '<div class="pm-empty"><i class="fas fa-calendar-times"></i><div>No timelines found</div></div>';
    } else {
        PM.timelines.forEach(timeline => {
            const sourceBoard = PM.boards.find(b => b.id === timeline.source_board_id);
            const updated = formatDateLabel(timeline.updated_at) || '—';
            html += `<div class="pm-card" data-timeline-id="${timeline.id}">`;
            html += '<div class="pm-card-head">';
            html += `<div><div class="pm-title">${escapeHtml(timeline.name)}</div>`;
            html += `<div class="pm-meta"><span class="pm-badge purple"><i class="fas fa-table-columns"></i>${escapeHtml(sourceBoard?.name || 'No board')}</span><span class="pm-badge blue"><i class="fas fa-clock"></i>Updated ${escapeHtml(updated)}</span></div></div>`;
            html += '<div class="pm-actions">';
            html += `<button class="pm-btn pm-btn-view" onclick="viewTimeline(${timeline.id})"><i class="fas fa-eye"></i>View</button>`;
            html += `<button class="pm-btn pm-btn-edit" onclick="editTimeline(${timeline.id})"><i class="fas fa-pen"></i>Edit</button>`;
            html += `<button class="pm-btn pm-btn-delete" onclick="deleteTimeline(${timeline.id}, '${escapeHtml(timeline.name)}')"><i class="fas fa-trash"></i>Delete</button>`;
            html += '</div></div>';
            html += '</div>';
        });
    }
    html += '</div>'; // end timelines section

    html += '</div>'; // end grid

    root.innerHTML = html;
}

function toggleBoardCards(btn) {
    const wrapper = btn.closest('.pm-cards-expandable');
    if (!wrapper) return;
    const list = wrapper.querySelector('.pm-card-list');
    const isExpanded = !list.classList.contains('pm-card-list-collapsed');
    if (isExpanded) {
        list.classList.add('pm-card-list-collapsed');
        const n = (list.querySelectorAll('.pm-card-row').length);
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> View all ' + (n === 1 ? '1 card' : n + ' cards');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        list.classList.remove('pm-card-list-collapsed');
        btn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide cards';
        btn.setAttribute('aria-expanded', 'true');
    }
}

function viewBoard(boardId) {
    window.location.href = typeof window.cgPortalKanbanBoardHref === 'function'
        ? window.cgPortalKanbanBoardHref(boardId)
        : `/kanban?board_id=${boardId}`;
}

function viewTimeline(chartId) {
    window.location.href = `project_timeline.php?chart_id=${chartId}`;
}

function loadBoardEmailsPM(boardId) {
    const listEl = qs('#editBoardEmailsList');
    if (!listEl || !boardId) return;
    listEl.innerHTML = '<span class="text-muted small">Loading…</span>';
    fetch('api/freelance_kanban.php?action=list_board_emails&board_id=' + encodeURIComponent(boardId), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { listEl.innerHTML = '<span class="text-muted small">Could not load emails</span>'; return; }
            const emails = data.emails || [];
            if (emails.length === 0) {
                listEl.innerHTML = '<span class="text-muted small">No daily digest emails yet.</span>';
                return;
            }
            listEl.innerHTML = emails.map(email => {
                const safe = document.createElement('div');
                safe.textContent = email;
                const enc = safe.innerHTML;
                return '<span class="d-inline-flex align-items-center gap-1 me-2 mb-1 px-2 py-1 rounded bg-light" style="font-size:13px;">' +
                    '<span>' + enc + '</span>' +
                    '<button type="button" class="btn btn-link btn-sm p-0 text-danger" style="font-size:12px;" data-email="' + enc + '" aria-label="Remove">×</button></span>';
            }).join('');
            listEl.querySelectorAll('button[data-email]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const email = btn.getAttribute('data-email');
                    const bid = qs('#edit_board_id').value;
                    const fd = new FormData();
                    fd.append('action', 'remove_board_email');
                    fd.append('board_id', bid);
                    fd.append('email', email);
                    fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(d => { if (d.success) loadBoardEmailsPM(bid); });
                });
            });
        })
        .catch(() => { listEl.innerHTML = '<span class="text-muted small">Failed to load emails</span>'; });
}

function editBoard(boardId) {
    const board = PM.boards.find(b => b.id === boardId);
    if (!board) return;
    
    qs('#edit_board_id').value = board.id;
    qs('#edit_board_name').value = board.name;
    loadBoardEmailsPM(board.id);
    qs('#editBoardNewEmailInput').value = '';
    const errEl = qs('#editBoardEmailError');
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    qs('#editBoardAddEmailBtn').onclick = () => {
        const email = qs('#editBoardNewEmailInput').value.trim().toLowerCase();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (errEl) { errEl.textContent = 'Please enter a valid email'; errEl.style.display = 'block'; }
            return;
        }
        if (errEl) errEl.style.display = 'none';
        const fd = new FormData();
        fd.append('action', 'add_board_email');
        fd.append('board_id', qs('#edit_board_id').value);
        fd.append('email', email);
        fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                if (d.success) { qs('#editBoardNewEmailInput').value = ''; loadBoardEmailsPM(qs('#edit_board_id').value); }
                else if (errEl) { errEl.textContent = d.message || 'Failed to add'; errEl.style.display = 'block'; }
            });
    };
    new bootstrap.Modal(qs('#editBoardModal')).show();
}

function editTimeline(chartId) {
    const timeline = PM.timelines.find(t => t.id === chartId);
    if (!timeline) return;
    
    qs('#edit_timeline_id').value = timeline.id;
    qs('#edit_timeline_name').value = timeline.name;
    qs('#edit_timeline_board').value = timeline.source_board_id || '';
    
    new bootstrap.Modal(qs('#editTimelineModal')).show();
}

function wireDeleteConfirmTypeInput() {
    const input = qs('#deleteConfirmTypeInput');
    const btn = qs('#confirmDeleteBtn');
    if (!input || !btn || input.dataset.cgWired) return;
    input.dataset.cgWired = '1';
    const sync = () => {
        if (input.dataset.required !== '1') return;
        btn.disabled = String(input.value || '').trim().toUpperCase() !== 'DELETE';
    };
    input.addEventListener('input', sync);
    input.addEventListener('keyup', sync);
    input.addEventListener('change', sync);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !btn.disabled) {
            e.preventDefault();
            btn.click();
        }
    });
}

function setDeleteConfirmTypeRequired(required) {
    const wrap = qs('#deleteConfirmTypeWrap');
    const input = qs('#deleteConfirmTypeInput');
    const btn = qs('#confirmDeleteBtn');
    wireDeleteConfirmTypeInput();
    if (wrap) wrap.classList.toggle('d-none', !required);
    if (input) {
        input.dataset.required = required ? '1' : '0';
        input.value = '';
    }
    if (btn) btn.disabled = !!required;
    if (required && input) setTimeout(() => input.focus({ preventScroll: true }), 120);
}

function deleteBoard(boardId, boardName) {
    qs('#deleteConfirmMessage').textContent = `Are you sure you want to delete the board "${boardName}"? This will also delete all columns and cards in this board.`;
    setDeleteConfirmTypeRequired(true);
    qs('#confirmDeleteBtn').onclick = async () => {
        const input = qs('#deleteConfirmTypeInput');
        if (!input || String(input.value || '').trim().toUpperCase() !== 'DELETE') return;
        try {
            const fd = new FormData();
            fd.append('action', 'delete_board');
            fd.append('board_id', boardId);
            const res = await fetch('api/freelance_kanban.php', { method:'POST', body: fd, credentials:'same-origin' });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(qs('#deleteConfirmModal')).hide();
                await loadProjectData();
                if (window.refreshSidebars) {
                    window.refreshSidebars('boards');
                }
            } else {
                alert(data.message || 'Failed to delete board');
            }
        } catch (err) {
            console.error('Delete error:', err);
            alert('Failed to delete board');
        }
    };
    new bootstrap.Modal(qs('#deleteConfirmModal')).show();
}

function deleteTimeline(chartId, timelineName) {
    qs('#deleteConfirmMessage').textContent = `Are you sure you want to delete the timeline "${timelineName}"?`;
    setDeleteConfirmTypeRequired(false);
    qs('#confirmDeleteBtn').onclick = async () => {
        try {
            const fd = new FormData();
            fd.append('action', 'delete_chart');
            fd.append('chart_id', chartId);
            const res = await fetch('/api/freelance_timeline.php', { method:'POST', body: fd, credentials:'same-origin' });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(qs('#deleteConfirmModal')).hide();
                await loadProjectData();
                if (window.refreshSidebars) {
                    window.refreshSidebars('timelines');
                }
            } else {
                alert(data.message || 'Failed to delete timeline');
            }
        } catch (err) {
            console.error('Delete error:', err);
            alert('Failed to delete timeline');
        }
    };
    new bootstrap.Modal(qs('#deleteConfirmModal')).show();
}

function moveCard(cardId) {
    const card = (PM.cards || []).find(c => parseInt(c.id, 10) === parseInt(cardId, 10));
    if (!card) return;
    qs('#move_card_id').value = card.id;
    qs('#moveCardTitleLabel').textContent = 'Card: ' + (card.title || 'Untitled');
    const boardSelect = qs('#move_target_board');
    boardSelect.innerHTML = '<option value="">Select a board...</option>';
    PM.boards.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = b.name;
        boardSelect.appendChild(opt);
    });
    const colSelect = qs('#move_target_column');
    colSelect.innerHTML = '<option value="">Select a column...</option>';
    boardSelect.value = '';
    boardSelect.dispatchEvent(new Event('change', { bubbles: true }));
    new bootstrap.Modal(qs('#moveCardModal')).show();
}

// Form handlers
qs('#move_target_board').addEventListener('change', function() {
    const boardId = this.value;
    const colSelect = qs('#move_target_column');
    colSelect.innerHTML = '<option value="">Select a column...</option>';
    if (!boardId) return;
    const cols = (PM.columns || []).filter(c => parseInt(c.board_id, 10) === parseInt(boardId, 10));
    cols.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        colSelect.appendChild(opt);
    });
});

qs('#moveCardForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cardId = qs('#move_card_id').value;
    const targetColumnId = qs('#move_target_column').value;
    if (!cardId || !targetColumnId) {
        alert('Please select target board and column.');
        return;
    }
    try {
        const res = await fetch('api/freelance_kanban.php?action=move_cards', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ moves: [{ card_id: parseInt(cardId, 10), to_column_id: parseInt(targetColumnId, 10), position: 0 }] }),
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(qs('#moveCardModal')).hide();
            await loadProjectData();
            if (window.refreshSidebars) window.refreshSidebars('boards');
        } else {
            alert(data.message || 'Failed to move card');
        }
    } catch (err) {
        console.error('Move card error:', err);
        alert('Failed to move card.');
    }
});

qs('#editBoardForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const boardId = qs('#edit_board_id').value;
    const name = qs('#edit_board_name').value.trim();
    if (!name) return;
    
    try {
        const fd = new FormData();
        fd.append('action', 'update_board');
        fd.append('board_id', boardId);
        fd.append('name', name);
        const res = await fetch('api/freelance_kanban.php', { method:'POST', body: fd, credentials:'same-origin' });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(qs('#editBoardModal')).hide();
            await loadProjectData();
            if (window.refreshSidebars) {
                window.refreshSidebars('boards');
            }
        } else {
            alert(data.message || 'Failed to update board');
        }
    } catch (err) {
        console.error('Update error:', err);
        alert('Failed to update board');
    }
});

qs('#editTimelineForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const chartId = qs('#edit_timeline_id').value;
    const name = qs('#edit_timeline_name').value.trim();
    const sourceBoardId = qs('#edit_timeline_board').value;
    if (!name) return;
    
    try {
        const fd = new FormData();
        fd.append('action', 'update_chart');
        fd.append('chart_id', chartId);
        fd.append('name', name);
        fd.append('source_board_id', sourceBoardId);
        const res = await fetch('/api/freelance_timeline.php', { method:'POST', body: fd, credentials:'same-origin' });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(qs('#editTimelineModal')).hide();
            await loadProjectData();
            if (window.refreshSidebars) {
                window.refreshSidebars('timelines');
            }
        } else {
            alert(data.message || 'Failed to update timeline');
        }
    } catch (err) {
        console.error('Update error:', err);
        alert('Failed to update timeline');
    }
});

<?php if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL): ?>
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
if (!window.__cgKanbanThemeToggleWired) {
    document.querySelectorAll('[data-kanban-theme-toggle]').forEach((b) => b.addEventListener('click', toggleKanbanTheme));
}
applyKanbanTheme(getStoredKanbanTheme());
<?php endif; ?>

loadProjectData();
</script>

<?php if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL): ?>
</main>
</body>
</html>
<?php else: ?>
<?php require_once $cg_public_root . '/includes/footer.php'; ?>
<?php endif; ?>
