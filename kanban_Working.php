<?php
require_once __DIR__ . '/auth.php';
$cg_public_root = dirname(__DIR__) . '/public_html';
if (!function_exists('cg_request_kanban_hostname')) {
    require_once $cg_public_root . '/includes/auth.php';
}
require_once $cg_public_root . '/includes/db.php';
require_once __DIR__ . '/includes/freelance_projects.php';

cg_require_freelancer_or_linked();
cg_ensure_freelance_tables();

$user_id = (int)$_SESSION['user_id'];
$pdo = getDB();

$page_title = 'Kanban Boards';
require_once $cg_public_root . '/includes/header.php';
?>

<!-- Sidebar collapse / expand button (desktop: slide hide; mobile: overlay) -->
<button type="button" class="sidebar-toggle sidebar-collapse-btn" id="sidebarCollapseBtn" onclick="toggleKanbanSidebar()" aria-label="Collapse sidebar">
    <i class="fas fa-chevron-left" id="sidebarCollapseIcon" aria-hidden="true"></i>
</button>

<?php include $cg_public_root . '/Fsidebar.php'; ?>

<div class="fmain-content cg-kanban-page" style="overflow-x: auto; overflow-y: visible;">
    <div class="container-fluid cg-kanban-container" style="padding: 12px 32px; max-width: 100%; width: 100%;">
        <div class="cg-kanban-header mb-3">
            <div>
                <h2 class="mb-1" id="kanbanBoardTitle" style="font-weight:800;">Loading...</h2>
                <div class="text-muted" id="kanbanBoardSubtitle">Start Date: N/A | End Date: N/A</div>
            </div>
            <div class="cg-kanban-header-actions">
                <div class="cg-board-members-wrap position-relative d-flex align-items-center me-2" id="boardMembersWrap">
                    <div class="cg-board-avatars d-flex align-items-center cursor-pointer" id="boardMembersAvatars" aria-label="Board members" title="Show board members" role="button" tabindex="0"></div>
                    <div class="cg-board-members-dropdown shadow-sm border rounded" id="boardMembersDropdown" aria-hidden="true">
                        <div class="cg-board-members-dropdown-header small fw-semibold px-3 py-2 border-bottom bg-light rounded-top"><i class="fas fa-users me-2"></i>Board members</div>
                        <div class="cg-board-members-dropdown-body p-2" id="boardMembersDropdownBody"></div>
                    </div>
                </div>
                <button class="btn btn-outline-dark" id="newBoardBtn"><i class="fas fa-plus me-2"></i>New board</button>
                <button type="button" class="btn btn-danger" id="viewTimelineBtn"><i class="fas fa-diagram-project me-2"></i>View timeline</button>
                <button class="btn btn-outline-dark" id="editBoardBtn" type="button" title="Edit current board"><i class="fas fa-pen me-2"></i>Edit board</button>
                <button class="btn btn-outline-dark" id="activitiesPanelBtn" type="button" title="Activities" aria-label="Open activities panel"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                <button class="btn btn-outline-dark" id="themeToggleBtn" type="button" title="Toggle dark mode" aria-label="Toggle dark mode"><i class="fas fa-moon" aria-hidden="true"></i></button>
            </div>
        </div>

        <div id="kanbanRoot" class="cg-kb">
            <div class="cg-kb__loading">
                <div class="spinner-border text-danger" role="status" aria-label="Loading"></div>
                <div class="text-muted mt-2">Loading board…</div>
            </div>
        </div>
    </div>
</div>

<!-- Card detail bubble (shown on hover over card, like client.php task bar) -->
<div id="cardDetailBubble" class="task-detail-bubble" aria-hidden="true" role="tooltip"></div>

<!-- Card Modal -->
<div class="modal fade" id="cardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i>Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="cardForm">
        <input type="hidden" id="card_id" name="card_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Title</label>
            <input class="form-control" id="card_title" name="title" required />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="card_description" name="description" rows="5" placeholder="Add details, checklist, links…"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Priority</label>
            <select class="form-select" id="card_priority" name="priority">
                <option value="">No Priority</option>
                <option value="P0">P0 - Critical</option>
                <option value="P1">P1 - High</option>
                <option value="P2">P2 - Medium</option>
                <option value="P3">P3 - Low</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">
                Progress: <span id="progressValue">0</span>%
            </label>
            <input type="range" class="form-range" id="card_progress" name="progress" min="0" max="100" value="0" step="5" oninput="updateCardProgressSlider(this)" />
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Start date</label>
                <input type="date" class="form-control" id="card_start_date" name="start_date" />
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Due date</label>
                <input type="date" class="form-control" id="card_due_date" name="due_date" />
            </div>
        </div>
        <div class="mb-3 mt-3">
            <div id="cardAttachmentsContainer" class="mb-2"></div>
            <div id="cardAttachmentDropWrap" class="mb-2" style="display: none;">
                <div id="cardAttachmentDropZone" class="card-attachment-dropzone rounded border border-2 border-dashed d-flex flex-column align-items-center justify-content-center py-3 px-3 mb-2" style="min-height:80px;cursor:pointer;">
                    <input type="file" id="cardAttachmentFileInput" name="attachment[]" accept="image/*" multiple class="d-none" />
                    <span class="card-attachment-dropzone-text small text-center"><i class="fas fa-cloud-upload-alt me-1"></i>Drag and Drop</span>
                    <span class="card-attachment-dropzone-text small mt-1"><strong>Click to Browse</strong></span>
                    <span class="text-muted small mt-1">Images only (JPEG, PNG, GIF, WebP)</span>
                </div>
                <div id="cardNewAttachmentsList" class="mb-2"></div>
                <div id="cardAttachmentUploadProgress" class="card-attachment-upload-progress" style="display: none;">
                    <div class="upload-progress-bar"><div class="upload-progress-fill" id="cardAttachmentUploadProgressFill" style="width: 0%;"></div></div>
                    <div class="upload-progress-label" id="cardAttachmentUploadProgressLabel">Uploading… 0%</div>
                </div>
            </div>
            <input type="hidden" name="links" id="card_links_data" value="[]" />
            <input type="hidden" name="existing_attachments" id="card_existing_attachments" value="[]" />
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <button type="button" class="btn btn-outline-danger" id="deleteCardBtn" style="display: none;" onclick="deleteCard()">
                <i class="fas fa-trash me-2"></i>Delete Card
            </button>
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm p-2" id="cardFooterAddAttachmentBtn" title="Add attachment" aria-label="Add attachment"><i class="fas fa-image"></i></button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i>Save</button>
            </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Board Modal -->
<div class="modal fade" id="boardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-table-columns me-2"></i>New Board</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="boardForm">
        <div class="mb-3">
            <label class="form-label fw-semibold">Board name</label>
            <input class="form-control" id="board_name" name="name" placeholder="Enter board name" required autofocus />
            <div class="form-text">Create a new Kanban board for organizing your tasks.</div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-plus me-2"></i>Create Board</button>
        </div>
      </form>
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
        <input type="hidden" id="edit_board_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Board name</label>
            <input class="form-control" id="edit_board_name" placeholder="Board name" required />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Notification emails</label>
            <p class="text-muted small mb-2">Send these addresses one collective board activity email every 24 hours for card updates, moves, and client timeline comments.</p>
            <div id="editBoardEmailsList" class="mb-2" style="min-height: 24px;"></div>
            <div class="d-flex gap-2 align-items-center edit-board-add-row">
                <div class="position-relative flex-grow-1">
                    <input type="text" autocomplete="off" class="form-control form-control-sm edit-board-add-input" id="editBoardNewEmailInput" placeholder="Type name or email to find CineGrid users…" />
                    <div id="editBoardEmailSuggest" class="cg-user-suggest dropdown-menu show" style="display: none; position: absolute; z-index: 1050; max-height: 220px; overflow-y: auto;"></div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm edit-board-add-btn" id="editBoardAddEmailBtn"><i class="fas fa-plus me-1"></i>Add</button>
            </div>
            <div class="invalid-feedback" id="editBoardEmailError" style="display: none;"></div>
        </div>
        <div class="mb-3" id="editBoardCollaboratorsWrap" style="display: none;">
            <label class="form-label fw-semibold">Collaborators</label>
            <p class="text-muted small mb-2">Invite others to open and edit this board from their Kanban. They'll see it in their sidebar.</p>
            <div id="editBoardCollaboratorsList" class="mb-2" style="min-height: 24px;"></div>
            <div class="d-flex gap-2 align-items-center edit-board-add-row">
                <div class="position-relative flex-grow-1">
                    <input type="text" autocomplete="off" class="form-control form-control-sm edit-board-add-input" id="editBoardNewCollaboratorInput" placeholder="Type name or email to find CineGrid users…" />
                    <div id="editBoardCollaboratorSuggest" class="cg-user-suggest dropdown-menu show" style="display: none; position: absolute; z-index: 1050; max-height: 220px; overflow-y: auto;"></div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm edit-board-add-btn" id="editBoardAddCollaboratorBtn"><i class="fas fa-user-plus me-1"></i>Add</button>
            </div>
            <div class="invalid-feedback" id="editBoardCollaboratorError" style="display: none;"></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Activity side panel (owner only; slides in from right) -->
<div id="activityPanelBackdrop" class="cg-activity-backdrop" aria-hidden="true"></div>
<div id="activityPanel" class="cg-activity-panel" aria-hidden="true" role="dialog" aria-label="Board activity">
    <div class="cg-activity-panel-header">
        <h3 class="cg-activity-panel-title"><i class="fas fa-stream me-2"></i>Activities</h3>
        <button type="button" class="btn btn-link btn-sm p-1 text-dark cg-activity-close" id="activityPanelClose" aria-label="Close activity panel"><i class="fas fa-times"></i></button>
    </div>
    <div class="cg-activity-panel-body">
        <div id="activityPanelLoading" class="cg-activity-loading text-muted small" style="display: none;"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
        <div id="activityPanelEmpty" class="cg-activity-empty text-muted small" style="display: none;">No activity yet for this board.</div>
        <div id="activityPanelList" class="cg-activity-list"></div>
    </div>
</div>

<!-- Column Modal -->
<div class="modal fade" id="columnModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i><span id="columnModalTitle">New Column</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="columnForm">
        <input type="hidden" id="column_id" name="column_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Column name</label>
            <input class="form-control" id="column_name" name="name" placeholder="Enter column name" required autofocus />
            <div class="form-text" id="columnFormText">Create a new column for organizing your tasks.</div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-save me-2"></i><span id="columnSubmitText">Create Column</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Create Card Modal -->
<div class="modal fade" id="createCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>New Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="createCardForm">
        <input type="hidden" id="create_card_column_id" name="column_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Card title</label>
            <input class="form-control" id="create_card_title" name="title" placeholder="Enter card title" required autofocus />
            <div class="form-text">Give your task a clear, descriptive title.</div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-plus me-2"></i>Create Card</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Paste Into Card Modal -->
<div class="modal fade" id="pasteCardColumnModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-paste me-2"></i>Create Card From Paste</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="pasteCardColumnForm">
        <div class="mb-3">
            <label class="form-label fw-semibold">Choose column</label>
            <select class="form-select" id="paste_card_column_select" required></select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Preview</label>
            <div class="rounded border bg-light p-3 small">
                <div class="fw-semibold text-dark" id="pasteCardPreviewTitle">Untitled</div>
                <div class="text-muted mt-1" id="pasteCardPreviewMeta">Start Date: N/A | End Date: N/A</div>
                <div class="text-muted mt-2" id="pasteCardPreviewDescription">No description</div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="fas fa-plus me-2"></i>Create Card</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SortableJS for drag/drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>

<style>
.cg-kanban-container {
    box-sizing: border-box;
}
.cg-kanban-header {
    width: 100%;
    max-width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 1rem;
}
.cg-kanban-header > div:first-child {
    min-width: 0;
    overflow: hidden;
}
.cg-kanban-header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
    align-items: center;
    margin-left: 0;
}
.kanban-theme-dark .fmain-content.cg-kanban-page {
    --cg-dot-color: rgba(255, 255, 255, 0.16);
    background-color: #0f172a;
}
.kanban-theme-dark .cg-kanban-header h2,
.kanban-theme-dark #kanbanBoardTitle {
    color: #f8fafc !important;
}
.kanban-theme-dark #kanbanBoardSubtitle,
.kanban-theme-dark .text-muted {
    color: #94a3b8 !important;
}
.kanban-theme-dark .cg-col {
    border: 1px solid rgba(148,163,184,0.20);
    background: linear-gradient(180deg, rgba(30,41,59,0.95), rgba(15,23,42,0.96));
    box-shadow: 0 12px 28px rgba(2,6,23,0.34);
}
.kanban-theme-dark .cg-col__head {
    border-bottom-color: rgba(148,163,184,0.16);
}
.kanban-theme-dark .cg-col__title,
.kanban-theme-dark .cg-card__title,
.kanban-theme-dark .cg-card__progress-text {
    color: #f8fafc;
}
.kanban-theme-dark .cg-col__count {
    background: rgba(248,113,113,0.12);
    border-color: rgba(248,113,113,0.28);
    color: #fca5a5;
}
.kanban-theme-dark .cg-card {
    border-color: rgba(148,163,184,0.16);
    box-shadow: 0 8px 20px rgba(2,6,23,0.28);
}
.kanban-theme-dark .cg-card:not(.cg-card--low):not(.cg-card--mid):not(.cg-card--done) {
    background: rgba(30, 41, 59, 0.92);
}
.kanban-theme-dark .cg-card--low {
    background: rgba(99, 102, 241, 0.16);
}
.kanban-theme-dark .cg-card--mid {
    background: rgba(245, 158, 11, 0.18);
}
.kanban-theme-dark .cg-card--done {
    background: rgba(16, 185, 129, 0.18);
}
.kanban-theme-dark .cg-card__progress {
    background: rgba(255,255,255,0.10);
}
.kanban-theme-dark .cg-card__meta {
    color: #cbd5e1;
}
.kanban-theme-dark .cg-pill {
    background: rgba(99,102,241,0.18);
    border-color: rgba(129,140,248,0.22);
    color: #c7d2fe;
}
.kanban-theme-dark .cg-pill--date-past { color: #fecaca; }
.kanban-theme-dark .cg-pill--date-soon { color: #fdba74; }
.kanban-theme-dark .cg-pill--date-mid { color: #93c5fd; }
.kanban-theme-dark .cg-pill--date-far { color: #86efac; }
.kanban-theme-dark .cg-addBtn,
.kanban-theme-dark .cg-addColBtn {
    color: #cbd5e1;
    border-color: rgba(148,163,184,0.28);
    background: rgba(15,23,42,0.45);
}
.kanban-theme-dark .cg-addBtn:hover,
.kanban-theme-dark .cg-addColBtn:hover {
    background: rgba(224,49,49,0.10);
    border-color: rgba(248,113,113,0.34);
    color: #fca5a5;
}
.kanban-theme-dark .cg-col__actionBtn {
    color: #cbd5e1;
}
.kanban-theme-dark .cg-board-members-dropdown,
.kanban-theme-dark .cg-activity-panel {
    background: rgba(15,23,42,0.94);
    border-color: rgba(148,163,184,0.18);
    color: #f8fafc;
}
.kanban-theme-dark .cg-board-members-dropdown-header,
.kanban-theme-dark .cg-activity-panel-header {
    background: rgba(30,41,59,0.96) !important;
    border-bottom-color: rgba(148,163,184,0.16) !important;
}
.kanban-theme-dark .cg-activity-item {
    border-bottom-color: rgba(148,163,184,0.12);
}
.kanban-theme-dark .cg-activity-item .cg-activity-name {
    color: #f8fafc;
}
.kanban-theme-dark .cg-activity-item .cg-activity-time,
.kanban-theme-dark .cg-activity-item .cg-activity-card {
    color: #94a3b8;
}
.kanban-theme-dark #activitiesPanelBtn,
.kanban-theme-dark #themeToggleBtn,
.kanban-theme-dark #newBoardBtn,
.kanban-theme-dark #editBoardBtn {
    color: #e2e8f0;
    border-color: rgba(148,163,184,0.3);
    background: rgba(15,23,42,0.55);
}
.kanban-theme-dark #activitiesPanelBtn:hover,
.kanban-theme-dark #themeToggleBtn:hover,
.kanban-theme-dark #newBoardBtn:hover,
.kanban-theme-dark #editBoardBtn:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}
.cg-board-avatar-wrap {
    margin-left: -8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cg-board-avatars .cg-board-avatar-wrap:first-child { margin-left: 0; }
.cg-board-avatars .cg-board-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    background: #e2e8f0;
    flex-shrink: 0;
}
.cg-board-avatars .cg-board-avatar-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid #fff;
    background: #e03131;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cg-board-avatars.cursor-pointer { cursor: pointer; }
.cg-board-avatars.cursor-pointer:hover { opacity: 0.9; }
.cg-board-members-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 4px;
    min-width: 220px;
    max-width: 280px;
    max-height: 70vh;
    overflow-y: auto;
    background: #fff;
    z-index: 1050;
}
.cg-board-members-dropdown.is-open { display: block; }
.cg-board-members-modal-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}
.cg-board-members-modal-item:last-child { border-bottom: none; }
.cg-board-members-modal-item .cg-member-pic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}
.cg-board-members-modal-item .cg-member-initials {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e03131;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cg-board-members-modal-item .cg-member-name { font-weight: 600; }
.cg-board-members-modal-item .cg-member-role { font-size: 0.75rem; color: #64748b; }
/* Sidebar collapse button: bottom, outline, red stroke; red fill on hover */
.sidebar-collapse-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    bottom: 20px;
    left: 292px; /* 280px sidebar + 12px gap */
    z-index: 3200;
    width: 40px;
    height: 40px;
    background: transparent;
    color: #e03131;
    border: 2px solid #e03131;
    padding: 0;
    border-radius: 12px;
    transition: left 0.3s ease, background 0.2s ease, color 0.2s ease;
    cursor: pointer;
}
.sidebar-collapse-btn:hover {
    background: #e03131;
    color: white;
}
#mainNav {
    transition: transform 0.3s ease, opacity 0.3s ease !important;
    transform: translateY(0);
    opacity: 1;
}
body.sidebar-collapsed #mainNav {
    transform: translateY(-100%);
    opacity: 0;
    pointer-events: none;
}
/* When sidebar is collapsed, button moves to left edge */
body.sidebar-collapsed .sidebar-collapse-btn {
    left: 12px;
}

/* Activity side panel (right slide-in) */
.cg-activity-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.25);
    z-index: 1040;
    opacity: 0;
    transition: opacity 0.25s ease;
}
.cg-activity-backdrop.is-open {
    display: block;
    opacity: 1;
}
.cg-activity-panel {
    position: fixed;
    top: 80px;
    right: 0;
    width: 360px;
    max-width: 95vw;
    height: calc(100vh - 80px);
    background: #fff;
    box-shadow: -4px 0 20px rgba(0,0,0,0.12);
    z-index: 1060;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
}
/* Panel header: clear "Activities" title (panel starts below site top bar) */
.cg-activity-panel-header {
    background: #fff;
    box-shadow: 0 1px 0 rgba(0,0,0,0.08);
}
.cg-activity-panel.is-open {
    transform: translateX(0);
}
.cg-activity-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
}
.cg-activity-panel-title {
    margin: 0;
    color: #1e293b;
    font-size: 1.15rem;
    font-weight: 700;
}
.cg-activity-close {
    font-size: 1.25rem;
    text-decoration: none;
}
.cg-activity-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 1.25rem;
}
.cg-activity-loading, .cg-activity-empty {
    padding: 1rem 0;
}
.cg-activity-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.cg-activity-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem;
    line-height: 1.4;
}
.cg-activity-item:last-child { border-bottom: none; }
.cg-activity-item .cg-activity-name { font-weight: 600; color: #1e293b; }
.cg-activity-item .cg-activity-time { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; }
.cg-activity-item .cg-activity-card { font-style: italic; color: #475569; }
body.sidebar-collapsed .sidebar-collapse-btn .fa-chevron-left {
    transform: rotate(180deg); /* show as chevron-right */
}
/* Desktop: slide sidebar off and expand main content */
@media (min-width: 769px) {
    body.sidebar-collapsed .fsidebar.sidebar {
        transform: translateX(-100%);
    }
    body.sidebar-collapsed:not(.index) main {
        margin-top: 0px !important;
    }
    body.sidebar-collapsed .container,
    body.sidebar-collapsed .container-fluid,
    body.sidebar-collapsed main {
        padding-left: 0px !important;
        padding-right: 0px !important;
    }
    body.sidebar-collapsed .fmain-content {
        margin-left: 0 !important;
        margin-top: 0 !important;
        width: 100% !important;
        height: 100vh !important;
    }
    body.sidebar-collapsed .fmain-content.cg-kanban-page {
        padding: 50px !important;
        box-sizing: border-box;
    }
    body.sidebar-collapsed .fmain-content .container-fluid,
    body.sidebar-collapsed .cg-kanban-container {
        padding: 0 !important;
    }
    body.sidebar-collapsed .cg-kb {
        padding: 0;
    }
}
@media (max-width: 768px) {
    .sidebar-collapse-btn {
        left: 12px;
    }
    body.sidebar-collapsed .sidebar-collapse-btn .fa-chevron-left {
        transform: none;
    }
}

/* Dot grid background on kanban area */
.fmain-content.cg-kanban-page {
    --cg-grid-size: 28px;
    --cg-dot-color: rgba(15, 23, 42, 0.14);
    background-color: #fafbfc;
    background-image: radial-gradient(circle, var(--cg-dot-color) 1.6px, transparent 1.8px);
    background-size: var(--cg-grid-size) var(--cg-grid-size);
    background-position: calc(var(--cg-grid-size) / 2) calc(var(--cg-grid-size) / 2);
}

.cg-kb {
    background: transparent;
    border: none;
    border-radius: 18px;
    padding: 12px;
    overflow-x: visible;
    width: 100%;
    max-width: 100%;
}
.cg-kb:not(.dragging) .cg-kb__board {
    cursor: grab;
}
.cg-kb.dragging {
    cursor: grabbing;
    user-select: none;
}
.cg-kb.dragging .cg-kb__board {
    cursor: grabbing;
}
.cg-kb.dragging .cg-card,
.cg-kb.dragging .cg-addBtn,
.cg-kb.dragging .cg-addColBtn {
    pointer-events: none;
}
.cg-kb__loading {
    padding: 40px 12px;
    text-align: center;
}
.cg-kb__board {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(320px, 400px);
    gap: 12px;
    align-items: start;
    padding: 6px;
    padding-bottom: 20px; /* Space for scrollbar */
}
.cg-col {
    border: 1px solid rgba(15,23,42,0.10);
    background: linear-gradient(180deg, #fff, #fafafa);
    border-radius: 16px;
    box-shadow: 0 10px 26px rgba(2,6,23,0.06);
    overflow: hidden;
}
.cg-col__head {
    padding: 12px 12px 10px;
    border-bottom: 1px solid rgba(15,23,42,0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    cursor: grab;
}
.cg-col__title {
    font-weight: 900;
    color: #0f172a;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cg-col__count {
    font-size: 12px;
    font-weight: 800;
    padding: 4px 8px;
    border-radius: 999px;
    background: rgba(224,49,49,0.10);
    border: 1px solid rgba(224,49,49,0.18);
    color: #e03131;
}
.cg-col__body {
    padding: 10px;
    min-height: 60px;
}
.cg-card {
    border: 1px solid rgba(15,23,42,0.10);
    border-radius: 14px;
    background: rgba(255,255,255,0.95);
    padding: 10px 10px;
    cursor: grab;
    transition: transform .12s ease, box-shadow .12s ease;
    box-shadow: 0 6px 14px rgba(2,6,23,0.06);
}
.cg-card--low {
    background: rgba(99, 102, 241, 0.08);
}
.cg-card--mid {
    background: rgba(245, 158, 11, 0.09);
}
.cg-card--done {
    background: rgba(16, 185, 129, 0.09);
}
.cg-card:active { cursor: grabbing; }
.cg-card + .cg-card { margin-top: 10px; }
.cg-card:hover { transform: translateY(-1px); box-shadow: 0 12px 22px rgba(2,6,23,0.10); }
.cg-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.cg-card__title { 
    font-weight: 900; 
    font-size: 13px; 
    color: #0f172a; 
    line-height: 1.2; 
    flex: 1;
    min-width: 0;
}
.cg-card__progress {
    position: relative;
    height: 20px;
    background: rgba(15,23,42,0.05);
    border-radius: 10px;
    margin: 8px 0;
    overflow: hidden;
}
.cg-card__progress-bar {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease, background 0.2s ease;
}
.cg-card__progress-bar--low { background: #6366f1; }   /* 0–50% indigo */
.cg-card__progress-bar--mid { background: #f59e0b; }   /* 51–99% amber */
.cg-card__progress-bar--done { background: #10b981; }   /* 100% green */

.cg-user-suggest {
    min-width: 100%;
    padding: 0;
    margin-top: 2px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.08);
}
.cg-user-suggest-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cg-user-suggest-item:last-child { border-bottom: none; }
.cg-user-suggest-item:hover, .cg-user-suggest-item.selected { background: rgba(224,49,49,0.08); }

.edit-board-add-row .edit-board-add-input,
.edit-board-add-row .edit-board-add-btn {
    height: 38px;
    min-height: 38px;
    box-sizing: border-box;
}
.edit-board-add-row .edit-board-add-input {
    line-height: 1.25;
    padding: 0.375rem 0.75rem;
}
.edit-board-add-row .edit-board-add-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.375rem 0.75rem;
    border-radius: 0;
}
#editBoardAddEmailBtn {
    border: none;
}

.cg-card__progress-text {
    position: absolute;
    top: 50%;
    right: 8px;
    left: auto;
    transform: translateY(-50%);
    font-size: 10px;
    font-weight: 800;
    color: #0f172a;
    z-index: 1;
    text-shadow: 0 1px 2px rgba(255,255,255,0.8);
}

/* Card modal progress bar – solid segments: <10% red, 10–40% blue, 40–80% orange, 80–100% green. Bar fill and thumb use --progress-color. */
#cardModal #card_progress {
    --progress-pct: 0;
    --progress-color: #dc2626;
    height: 20px;
    margin: 8px 0;
    padding: 0;
    -webkit-appearance: none;
    appearance: none;
    background: linear-gradient(to right, var(--progress-color) 0%, var(--progress-color) calc(var(--progress-pct) * 1%), rgba(15,23,42,0.08) calc(var(--progress-pct) * 1%));
    border-radius: 10px;
    overflow: hidden;
}
#cardModal #card_progress::-webkit-slider-runnable-track {
    height: 20px;
    border-radius: 10px;
    -webkit-appearance: none;
    background: linear-gradient(to right, var(--progress-color) 0%, var(--progress-color) calc(var(--progress-pct) * 1%), rgba(15,23,42,0.08) calc(var(--progress-pct) * 1%));
}
#cardModal #card_progress::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--progress-color);
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    cursor: pointer;
    margin-top: -1px;
    border: 2px solid #fff;
}
#cardModal #card_progress::-moz-range-track {
    height: 20px;
    border-radius: 10px;
    background: linear-gradient(to right, var(--progress-color) 0%, var(--progress-color) calc(var(--progress-pct) * 1%), rgba(15,23,42,0.08) calc(var(--progress-pct) * 1%));
}
#cardModal #card_progress::-moz-range-thumb {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--progress-color);
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    cursor: pointer;
    border: 2px solid #fff;
}

/* Card modal attachment drop zone – faint red fill, red stroke, red label text */
#cardModal .card-attachment-dropzone {
    background: rgba(220, 38, 38, 0.06);
    border-color: rgba(220, 38, 38, 0.5) !important;
}
#cardModal .card-attachment-dropzone .card-attachment-dropzone-text { color: #dc2626; }
#cardModal .card-attachment-dropzone:hover { background: rgba(220, 38, 38, 0.1); border-color: #dc2626 !important; }

#cardModal .card-new-attachment-remove-btn { border-radius: 0; }

/* Card modal attachment upload progress */
#cardModal .card-attachment-upload-progress { margin-top: 8px; margin-bottom: 8px; }
#cardModal .card-attachment-upload-progress .upload-progress-bar { height: 8px; background: rgba(15,23,42,0.08); border-radius: 4px; overflow: hidden; }
#cardModal .card-attachment-upload-progress .upload-progress-fill { height: 100%; border-radius: 4px; background: #10b981; transition: width 0.15s ease; }
#cardModal .card-attachment-upload-progress .upload-progress-label { font-size: 12px; color: #64748b; margin-top: 4px; }

.cg-card__meta { margin-top: 6px; font-size: 12px; color: #64748b; display:flex; flex-wrap:wrap; gap:6px; align-items:center;}
.cg-pill { font-weight: 800; font-size: 11px; padding: 4px 8px; border-radius: 999px; background: rgba(99,102,241,0.10); border:1px solid rgba(99,102,241,0.18); color:#4338ca; }
.cg-pill--date { background: rgba(16,185,129,0.10); border-color: rgba(16,185,129,0.18); color:#047857; }
.cg-pill--date-past { background: rgba(220,38,38,0.12); border-color: rgba(220,38,38,0.25); color: #b91c1c; }
.cg-pill--date-soon { background: rgba(234,88,12,0.12); border-color: rgba(234,88,12,0.25); color: #c2410c; }
.cg-pill--date-mid { background: rgba(37,99,235,0.12); border-color: rgba(37,99,235,0.25); color: #1d4ed8; }
.cg-pill--date-far { background: rgba(16,185,129,0.10); border-color: rgba(16,185,129,0.18); color: #047857; }
.cg-priority-badge {
    font-weight: 900;
    font-size: 10px;
    padding: 3px 6px;
    border-radius: 6px;
    flex-shrink: 0;
    white-space: nowrap;
}
.cg-priority-p0 {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #dc2626;
}
.cg-priority-p1 {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    color: #d97706;
}
.cg-priority-p2 {
    background: #dbeafe;
    border: 1px solid #93c5fd;
    color: #2563eb;
}
.cg-priority-p3 {
    background: #e0e7ff;
    border: 1px solid #a5b4fc;
    color: #6366f1;
}
.cg-addBtn {
    border: 1px dashed rgba(15,23,42,0.24);
    background: transparent;
    width: 100%;
    border-radius: 14px;
    padding: 10px;
    color: #0f172a;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s ease;
}
.cg-addBtn:hover {
    border-color: rgba(224,49,49,0.4);
    background: rgba(224,49,49,0.05);
    color: #e03131;
}
.cg-addColBtn {
    border: 2px dashed rgba(15,23,42,0.24);
    background: rgba(255,255,255,0.5);
    width: 100%;
    min-width: 320px;
    max-width: 400px;
    border-radius: 16px;
    padding: 40px 20px;
    color: #64748b;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: fit-content;
    align-self: start;
}
.cg-addColBtn:hover {
    border-color: rgba(224,49,49,0.4);
    background: rgba(224,49,49,0.05);
    color: #e03131;
}
.cg-col__title {
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.2s ease;
}
.cg-col__title:hover {
    background: rgba(15,23,42,0.05);
}
.cg-col__title input {
    border: 2px solid rgba(224,49,49,0.3);
    border-radius: 6px;
    padding: 2px 6px;
    font-weight: 900;
    font-size: 14px;
    background: #fff;
    width: auto;
    min-width: 100px;
    max-width: 200px;
}
.cg-col__actions {
    display: flex;
    gap: 4px;
    opacity: 1;
    transition: opacity 0.2s ease;
}
.cg-col__actionBtn {
    background: transparent;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 12px;
    transition: all 0.2s ease;
}
.cg-col__actionBtn:hover {
    background: rgba(255,60,60,0.1);
    color: #ff3c3c;
}
.cg-col__actionBtn--delete:hover {
    background: rgba(255,60,60,0.1);
    color: #ff3c3c;
}
.sortable-ghost { opacity: 0.5; }
.sortable-chosen { transform: rotate(1deg); }

/* Card detail bubble (hover on .cg-card, same as client.php task bar) */
.task-detail-bubble {
    position: fixed;
    z-index: 1050;
    max-width: 360px;
    max-height: 70vh;
    overflow-y: auto;
    background: #fff;
    border: 1px solid rgba(15,23,42,0.12);
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    padding: 16px;
    font-size: 14px;
    display: none;
    pointer-events: auto;
}
.task-detail-bubble.is-visible { display: block; }
.task-detail-bubble .bubble-title { font-weight: 800; color: #0f172a; margin-bottom: 10px; font-size: 15px; }
.task-detail-bubble .bubble-meta { color: #64748b; font-size: 12px; margin-bottom: 8px; }
.task-detail-bubble .bubble-desc { color: #334155; margin-bottom: 12px; white-space: pre-wrap; line-height: 1.4; }
.task-detail-bubble .bubble-progress-wrap { margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
.task-detail-bubble .bubble-progress-wrap .bubble-progress-bar { flex: 1; min-width: 0; }
.task-detail-bubble .bubble-progress-wrap .bubble-progress-pct { flex-shrink: 0; }
.task-detail-bubble .bubble-progress-bar { height: 8px; background: rgba(15,23,42,0.08); border-radius: 4px; overflow: hidden; }
.task-detail-bubble .bubble-progress-fill { height: 100%; border-radius: 4px; transition: width 0.2s; }
.task-detail-bubble .bubble-progress-fill.bubble-progress-done { background: #10b981; }
.task-detail-bubble .bubble-progress-fill.bubble-progress-doing { background: #f59e0b; }
.task-detail-bubble .bubble-progress-fill.bubble-progress-todo { background: #6366f1; }
.task-detail-bubble .bubble-comments-title { font-weight: 700; margin-top: 12px; margin-bottom: 6px; color: #0f172a; }
.task-detail-bubble .bubble-comment { font-size: 12px; padding: 8px; background: rgba(248,250,252,0.9); border-radius: 8px; margin-bottom: 6px; border: 1px solid rgba(15,23,42,0.06); }
.task-detail-bubble .bubble-link { display: flex; align-items: center; gap: 6px; max-width: 100%; min-width: 0; margin-bottom: 6px; text-decoration: none; color: #0d6efd; }
.task-detail-bubble .bubble-link:hover { color: #0a58ca; text-decoration: underline; }
.task-detail-bubble .bubble-link .bubble-link-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
</style>

<script>
let KB = {
  project_id: null,
  board_id: null,
  chart_id: null,
  boards: [],
  columns: [],
  cardsByColumn: {},
  comments: [],
  board_members: [],
  is_board_owner: true,
  pollIntervalId: null,
  activityPanelBoardId: null,
};

const KANBAN_THEME_STORAGE_KEY = 'kanban_theme_mode';
let pendingPastedCardData = null;

function qs(sel, root=document){ return root.querySelector(sel); }
function qsa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

function applyKanbanTheme(mode) {
  const isDark = mode === 'dark';
  document.body.classList.toggle('kanban-theme-dark', isDark);
  const toggleBtn = qs('#themeToggleBtn');
  if (toggleBtn) {
    toggleBtn.innerHTML = isDark
      ? '<i class="fas fa-sun" aria-hidden="true"></i>'
      : '<i class="fas fa-moon" aria-hidden="true"></i>';
    toggleBtn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    toggleBtn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  }
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

function formatDateInputValue(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function getTodayDateInputValue() {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return formatDateInputValue(today);
}

function normalizeParsedDate(year, monthIndex, day) {
  const d = new Date(year, monthIndex, day);
  if (
    d.getFullYear() !== year ||
    d.getMonth() !== monthIndex ||
    d.getDate() !== day
  ) {
    return null;
  }
  d.setHours(0, 0, 0, 0);
  return d;
}

function extractDueDateFromText(text) {
  if (!text) return '';

  const monthMap = {
    jan: 0, january: 0,
    feb: 1, february: 1,
    mar: 2, march: 2,
    apr: 3, april: 3,
    may: 4,
    jun: 5, june: 5,
    jul: 6, july: 6,
    aug: 7, august: 7,
    sep: 8, sept: 8, september: 8,
    oct: 9, october: 9,
    nov: 10, november: 10,
    dec: 11, december: 11
  };
  const currentYear = new Date().getFullYear();
  const patterns = [
    {
      regex: /\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/,
      parser: (m) => {
        let year = parseInt(m[3], 10);
        if (year < 100) year += 2000;
        return normalizeParsedDate(year, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
      }
    },
    {
      regex: /\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t|tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)(?:[\s,]+(\d{4}))?\b/i,
      parser: (m) => {
        const monthKey = m[2].toLowerCase();
        const monthIndex = monthMap[monthKey];
        const year = m[3] ? parseInt(m[3], 10) : currentYear;
        return normalizeParsedDate(year, monthIndex, parseInt(m[1], 10));
      }
    },
    {
      regex: /\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t|tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:[\s,]+(\d{4}))?\b/i,
      parser: (m) => {
        const monthKey = m[1].toLowerCase();
        const monthIndex = monthMap[monthKey];
        const year = m[3] ? parseInt(m[3], 10) : currentYear;
        return normalizeParsedDate(year, monthIndex, parseInt(m[2], 10));
      }
    }
  ];

  for (const pattern of patterns) {
    const match = text.match(pattern.regex);
    if (!match) continue;
    const parsed = pattern.parser(match);
    if (parsed) return formatDateInputValue(parsed);
  }

  return '';
}

function parsePastedCardText(text) {
  const normalized = (text || '').replace(/\r\n/g, '\n').trim();
  if (!normalized) return null;

  const lines = normalized.split('\n');
  const titleLine = lines.find(line => line.trim() !== '') || '';
  const titleIndex = lines.indexOf(titleLine);
  const descriptionLines = lines.slice(titleIndex + 1);
  const description = descriptionLines.join('\n').trim();
  const startDate = getTodayDateInputValue();
  const dueDate = extractDueDateFromText(normalized);

  return {
    title: titleLine.trim().slice(0, 255),
    description,
    start_date: startDate,
    due_date: dueDate
  };
}

function isEditableContext(target) {
  if (!target || !(target instanceof Element)) return false;
  return !!target.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""], [role="textbox"]');
}

function isBlockedPasteContext(target) {
  if (!target || !(target instanceof Element)) return false;
  return !!target.closest('.modal, .cg-card');
}

function openPasteCardColumnModal(parsedCard) {
  if (!parsedCard || !parsedCard.title) return;
  if (!KB.columns || !KB.columns.length) {
    alert('Please create a column first.');
    return;
  }

  pendingPastedCardData = parsedCard;
  const select = qs('#paste_card_column_select');
  const previewTitle = qs('#pasteCardPreviewTitle');
  const previewMeta = qs('#pasteCardPreviewMeta');
  const previewDescription = qs('#pasteCardPreviewDescription');
  if (!select || !previewTitle || !previewMeta || !previewDescription) return;

  select.innerHTML = (KB.columns || []).map(function(col, idx) {
    return `<option value="${col.id}" ${idx === 0 ? 'selected' : ''}>${escapeHtml(capitalizeWords(col.name || 'Column'))}</option>`;
  }).join('');
  previewTitle.textContent = parsedCard.title || 'Untitled';
  previewMeta.textContent = 'Start Date: ' + (parsedCard.start_date || 'N/A') + ' | End Date: ' + (parsedCard.due_date || 'N/A');
  previewDescription.textContent = parsedCard.description || 'No description';

  const modalEl = qs('#pasteCardColumnModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function focusKanbanSurface() {
  const root = qs('#kanbanRoot');
  if (!root) return;
  root.setAttribute('tabindex', '-1');
  if (document.activeElement instanceof HTMLElement) {
    document.activeElement.blur();
  }
  root.focus({ preventScroll: true });
}

function formatOneDate(dateStr, today) {
  if (!dateStr) return '';
  const d = new Date(String(dateStr).trim());
  if (isNaN(d.getTime())) return dateStr;
  d.setHours(0, 0, 0, 0);
  const todayVal = new Date(today);
  todayVal.setHours(0, 0, 0, 0);
  const daysDiff = Math.floor((d - todayVal) / (24 * 60 * 60 * 1000));
  if (daysDiff === -1) return 'Yesterday';
  if (daysDiff === 0) return 'Today';
  if (daysDiff === 1) return 'Tomorrow';
  if (daysDiff >= -7 && daysDiff < -1) return Math.abs(daysDiff) + ' days ago';
  if (daysDiff > 1 && daysDiff <= 14) return daysDiff + ' days from now';
  const needYear = d.getFullYear() !== todayVal.getFullYear();
  const opts = { day: 'numeric', month: 'long' };
  if (needYear) opts.year = 'numeric';
  return d.toLocaleDateString('en-GB', opts);
}

function formatDateRange(s, e) {
  if (!s && !e) return null;
  const today = new Date();
  if (s && e) {
    const startLabel = formatOneDate(s, today);
    const endLabel = formatOneDate(e, today);
    if (startLabel === endLabel) return startLabel;
    return `${startLabel} → ${endLabel}`;
  }
  if (s) return 'Start: ' + formatOneDate(s, today);
  return 'Due: ' + formatOneDate(e, today);
}

function parseBoardDate(dateStr) {
  if (!dateStr) return null;
  const raw = String(dateStr).trim();
  if (!raw) return null;
  const d = new Date(raw + 'T00:00:00');
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

function getCurrentBoardMeta() {
  return (KB.boards || []).find(function(board) {
    return parseInt(board.id, 10) === parseInt(KB.board_id, 10);
  }) || null;
}

function getBoardDateRange() {
  const cardsByColumn = KB.cardsByColumn || {};
  let minDate = null;
  let maxDate = null;

  Object.values(cardsByColumn).forEach(function(cards) {
    (cards || []).forEach(function(card) {
      [card.start_date, card.due_date].forEach(function(dateStr) {
        const date = parseBoardDate(dateStr);
        if (!date) return;
        if (!minDate || date < minDate) minDate = date;
        if (!maxDate || date > maxDate) maxDate = date;
      });
    });
  });

  return { minDate, maxDate };
}

function renderBoardSubtitle() {
  const boardTitleEl = qs('#kanbanBoardTitle');
  const subtitleEl = qs('#kanbanBoardSubtitle');
  if (!boardTitleEl || !subtitleEl) return;

  const board = getCurrentBoardMeta();
  const boardName = board && board.name ? board.name : 'Untitled Board';
  const range = getBoardDateRange();

  boardTitleEl.textContent = boardName;
  subtitleEl.textContent =
    'Start Date: ' + formatBoardSummaryDate(range.minDate) +
    ' | End Date: ' + formatBoardSummaryDate(range.maxDate);
}

function getDatePillClass(dueDate) {
  if (!dueDate) return 'cg-pill--date-far';
  const due = new Date(String(dueDate).trim());
  if (isNaN(due.getTime())) return 'cg-pill--date-far';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  due.setHours(0, 0, 0, 0);
  const daysRemaining = Math.floor((due - today) / (24 * 60 * 60 * 1000));
  if (daysRemaining < 0) return 'cg-pill--date-past';
  if (daysRemaining <= 2) return 'cg-pill--date-soon';
  if (daysRemaining <= 5) return 'cg-pill--date-mid';
  return 'cg-pill--date-far';
}

let cardBubbleHideTimeout = null;
function scheduleCardBubbleHide(ms) {
  if (cardBubbleHideTimeout) clearTimeout(cardBubbleHideTimeout);
  cardBubbleHideTimeout = setTimeout(function() {
    hideCardDetailBubble();
    cardBubbleHideTimeout = null;
  }, ms || 120);
}
function cancelCardBubbleHide() {
  if (cardBubbleHideTimeout) {
    clearTimeout(cardBubbleHideTimeout);
    cardBubbleHideTimeout = null;
  }
}

function buildCardDetailBubbleContent(card) {
  if (!card) return '';
  const title = escapeHtml(capitalizeWords(card.title || 'Untitled'));
  const desc = (card.description || '').trim();
  const descHtml = desc ? '<div class="bubble-desc">' + escapeHtml(desc) + '</div>' : '';
  const priority = (card.priority || '').toString().trim();
  const priorityClass = priority ? 'cg-priority-' + priority.toLowerCase() : '';
  const priorityHtml = priority ? '<span class="cg-priority-badge ' + priorityClass + '">' + escapeHtml(priority) + '</span>' : '';
  const dateRange = formatDateRange(card.start_date, card.due_date);
  const datesHtml = dateRange ? '<div class="bubble-meta">' + escapeHtml(dateRange) + '</div>' : '';
  const progress = Math.max(0, Math.min(100, parseInt(card.progress != null ? card.progress : 0, 10)));
  const progressClass = progress >= 100 ? 'bubble-progress-done' : (progress > 50 ? 'bubble-progress-doing' : 'bubble-progress-todo');
  const progressHtml = '<div class="bubble-progress-wrap"><div class="bubble-progress-bar"><div class="bubble-progress-fill ' + progressClass + '" style="width:' + progress + '%"></div></div><span class="small fw-bold bubble-progress-pct">' + progress + '%</span></div>';
  let linksArr = [];
  try {
    const raw = card.links;
    if (typeof raw === 'string' && raw) linksArr = JSON.parse(raw);
    else if (Array.isArray(raw)) linksArr = raw;
  } catch (e) {}
  const linksHtml = linksArr.length
    ? '<div class="bubble-links mt-2"><div class="bubble-comments-title">Links</div><div class="bubble-links-list">' + linksArr.map(function(url) {
        const u = (url && url.trim) ? url.trim() : String(url);
        if (!u) return '';
        return '<a href="' + escapeHtml(u) + '" target="_blank" rel="noopener noreferrer" class="bubble-link small"><i class="fas fa-external-link-alt flex-shrink-0"></i><span class="bubble-link-text">' + escapeHtml(u) + '</span></a>';
      }).join('') + '</div></div>'
    : '';
  let attachmentsArr = [];
  try {
    const raw = card.attachments;
    if (typeof raw === 'string' && raw) attachmentsArr = JSON.parse(raw);
    else if (Array.isArray(raw)) attachmentsArr = raw;
  } catch (e) {}
  const attachmentsHtml = attachmentsArr.length
    ? '<div class="bubble-attachments mt-2"><div class="bubble-comments-title">Attachments</div><div class="bubble-attachments-list">' + attachmentsArr.map(function(att) {
        const path = att && (att.path || att);
        const name = (att && att.name) ? att.name : (path ? String(path).split('/').pop() : 'Attachment');
        const url = (path && (path.indexOf('http') === 0 || path.indexOf('/') === 0)) ? path : '/' + (path || '');
        const isImg = /\.(jpe?g|png|gif|webp)$/i.test(String(path));
        if (isImg) {
          return '<div class="d-inline-flex align-items-center me-2 mb-1"><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="bubble-attachment-img-link" title="' + escapeHtml(name) + '"><img src="' + escapeHtml(url) + '" alt="" class="rounded" style="width:28px;height:28px;object-fit:cover;cursor:pointer;" onerror="this.style.display=\'none\'"></a></div>';
        }
        return '<div class="d-inline-flex align-items-center me-2 mb-1"><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="small" title="' + escapeHtml(name) + '"><i class="fas fa-file-image me-1 text-muted"></i></a><span class="small text-muted">' + escapeHtml(name.length > 24 ? name.slice(0, 21) + '…' : name) + '</span></div>';
      }).join('') + '</div></div>'
    : '';
  const cardIdNum = parseInt(card.id, 10);
  const cardComments = (KB.comments || []).filter(function(c) {
    const cid = c.kanban_card_id != null && c.kanban_card_id !== '' ? parseInt(c.kanban_card_id, 10) : null;
    return cid === cardIdNum;
  });
  const commentsHtml = cardComments.length
    ? '<div class="bubble-comments-title">Comments</div>' + cardComments.map(function(c) {
        return '<div class="bubble-comment"><strong>' + escapeHtml(c.author_name || 'Anon') + '</strong> <span class="text-muted small">' + escapeHtml(c.created_at || '') + '</span><div class="mt-1">' + escapeHtml(c.body || '') + '</div></div>';
      }).join('')
    : '';
  return '<div class="bubble-title">' + title + '</div>' + (priorityHtml ? '<div class="mb-2">' + priorityHtml + '</div>' : '') + datesHtml + descHtml + progressHtml + linksHtml + attachmentsHtml + commentsHtml;
}

function showCardDetailBubble(cardEl) {
  const cardId = cardEl.getAttribute('data-card-id');
  if (cardId == null) return;
  const card = findCard(parseInt(cardId, 10));
  if (!card) return;
  const bubble = document.getElementById('cardDetailBubble');
  if (!bubble) return;
  bubble.innerHTML = buildCardDetailBubbleContent(card);
  bubble.classList.add('is-visible');
  bubble.setAttribute('aria-hidden', 'false');
  requestAnimationFrame(function() {
    const rect = cardEl.getBoundingClientRect();
    const bw = bubble.offsetWidth;
    const bh = bubble.offsetHeight;
    const pad = 8;
    const offsetPx = 150;
    let top = rect.top - bh - pad;
    const spaceRight = (window.innerWidth - pad) - (rect.left + offsetPx);
    const spaceLeft = (rect.right - offsetPx) - pad;
    let left;
    if (spaceRight >= bw) left = rect.left + offsetPx;
    else if (spaceLeft >= bw) left = rect.right - bw - offsetPx;
    else left = rect.left + offsetPx;
    if (top < pad) top = rect.bottom + pad;
    if (left + bw > window.innerWidth - pad) left = window.innerWidth - bw - pad;
    if (left < pad) left = pad;
    bubble.style.left = left + 'px';
    bubble.style.top = top + 'px';
  });
}

function hideCardDetailBubble() {
  if (cardBubbleHideTimeout) {
    clearTimeout(cardBubbleHideTimeout);
    cardBubbleHideTimeout = null;
  }
  const bubble = document.getElementById('cardDetailBubble');
  if (bubble) {
    bubble.classList.remove('is-visible');
    bubble.setAttribute('aria-hidden', 'true');
  }
}

function renderBoard() {
  const root = qs('#kanbanRoot');
  const cols = KB.columns || [];
  const cardsBy = KB.cardsByColumn || {};

  root.innerHTML = `
    <div class="cg-kb__board" id="kbBoard">
      ${cols.map(c => {
        const cards = cardsBy[c.id] || [];
        return `
          <div class="cg-col" data-col-id="${c.id}">
            <div class="cg-col__head">
              <div class="cg-col__title" onclick='editColumnName(${c.id}, ${JSON.stringify(c.name)})'>
                <i class="fas fa-layer-group text-danger"></i>
                <span class="col-name-${c.id}">${escapeHtml(capitalizeWords(c.name))}</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="cg-col__count">${cards.length}</span>
                <div class="cg-col__actions">
                  <button class="cg-col__actionBtn" onclick='editColumnName(${c.id}, ${JSON.stringify(c.name)})' title="Rename">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button class="cg-col__actionBtn cg-col__actionBtn--delete" onclick="deleteColumn(${c.id})" title="Delete column">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="cg-col__body" id="col_${c.id}">
              ${cards.map(card => {
                const dateRange = formatDateRange(card.start_date, card.due_date);
                const datePillClass = getDatePillClass(card.due_date || card.start_date);
                const progress = Math.max(0, Math.min(100, parseInt(card.progress || 0, 10)));
                const progressBarClass = progress >= 100 ? 'cg-card__progress-bar--done' : (progress > 50 ? 'cg-card__progress-bar--mid' : 'cg-card__progress-bar--low');
                const progressCardClass = progress > 0 ? (progress >= 100 ? 'cg-card--done' : (progress > 50 ? 'cg-card--mid' : 'cg-card--low')) : '';
                const priority = card.priority || '';
                const priorityClass = priority ? `cg-priority-${priority.toLowerCase()}` : '';
                const priorityLabel = priority || '';
                return `
                  <div class="cg-card ${progressCardClass}" data-card-id="${card.id}" onclick="openCard(${card.id})">
                    <div class="cg-card__header">
                      <div class="cg-card__title">${escapeHtml(capitalizeWords(card.title))}</div>
                      ${priority ? `<span class="cg-priority-badge ${priorityClass}">${escapeHtml(priorityLabel)}</span>` : ''}
                    </div>
                    ${progress > 0 ? `
                      <div class="cg-card__progress">
                        <div class="cg-card__progress-bar ${progressBarClass}" style="width: ${progress}%"></div>
                        <span class="cg-card__progress-text">${progress}%</span>
                      </div>
                    ` : ''}
                    <div class="cg-card__meta">
                      ${dateRange ? `<span class="cg-pill cg-pill--date ${datePillClass}"><i class="fas fa-calendar me-1"></i>${escapeHtml(dateRange)}</span>` : `<span class="cg-pill"><i class="fas fa-bolt me-1"></i>No dates</span>`}
                    </div>
                  </div>
                `;
              }).join('')}
              <button class="cg-addBtn mt-2" type="button" onclick="openCreateCardModal(${c.id})"><i class="fas fa-plus me-2"></i>Add card</button>
            </div>
          </div>
        `;
      }).join('')}
      <div class="cg-addColBtn" onclick="openColumnModal()">
        <i class="fas fa-plus" style="font-size: 24px;"></i>
        <span>Add Column</span>
      </div>
    </div>
  `;

  initSortable();
  renderBoardMembersAvatars();
  renderBoardSubtitle();
  updateBoardOwnerOnlyUI();
}

function boardMemberPicUrl(profilePic) {
  if (!profilePic || !String(profilePic).trim()) return null;
  const p = String(profilePic).trim();
  if (/^https?:\/\//i.test(p)) return p;
  if (p.startsWith('/')) return p;
  if (p.startsWith('uploads/')) return '/' + p;
  return '/uploads/profile_pics/' + p;
}

function getInitials(name) {
  if (!name || !String(name).trim()) return '?';
  return String(name).trim().split(/\s+/).map(w => w.charAt(0)).slice(0, 2).join('').toUpperCase();
}

function renderBoardMembersAvatars() {
  const container = qs('#boardMembersAvatars');
  if (!container) return;
  const members = KB.board_members || [];
  const maxVisible = 4;
  const show = members.slice(0, maxVisible);
  container.innerHTML = show.map(m => {
    const src = boardMemberPicUrl(m.profile_pic);
    const name = (m.name || '').trim() || 'User';
    const title = escapeHtml(name) + (m.role === 'owner' ? ' (Owner)' : '');
    const inner = src
      ? `<img class="cg-board-avatar" src="${escapeHtml(src)}" alt="${title}" title="${title}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="cg-board-avatar-initials" style="display:none;" title="${title}">${escapeHtml(getInitials(name))}</span>`
      : `<span class="cg-board-avatar-initials" title="${title}">${escapeHtml(getInitials(name))}</span>`;
    return `<span class="cg-board-avatar-wrap">${inner}</span>`;
  }).join('');
}

function toggleBoardMembersDropdown() {
  const dropdown = qs('#boardMembersDropdown');
  if (!dropdown) return;
  const isOpen = dropdown.classList.contains('is-open');
  if (isOpen) {
    closeBoardMembersDropdown();
    return;
  }
  const members = KB.board_members || [];
  const body = qs('#boardMembersDropdownBody');
  if (body) {
    if (members.length === 0) {
      body.innerHTML = '<p class="text-muted small mb-0 p-2">No members on this board.</p>';
    } else {
      body.innerHTML = members.map(m => {
        const src = boardMemberPicUrl(m.profile_pic);
        const name = (m.name || '').trim() || 'User';
        const roleLabel = m.role === 'owner' ? 'Owner' : 'Editor';
        const picHtml = src
          ? `<img class="cg-member-pic" src="${escapeHtml(src)}" alt="${escapeHtml(name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="cg-member-initials" style="display:none;">${escapeHtml(getInitials(name))}</span>`
          : `<span class="cg-member-initials">${escapeHtml(getInitials(name))}</span>`;
        return `<div class="cg-board-members-modal-item"><div style="position:relative;">${picHtml}</div><div><div class="cg-member-name">${escapeHtml(name)}</div><div class="cg-member-role">${escapeHtml(roleLabel)}</div></div></div>`;
      }).join('');
    }
  }
  dropdown.classList.add('is-open');
  dropdown.setAttribute('aria-hidden', 'false');
  setTimeout(() => {
    document.addEventListener('click', closeBoardMembersDropdownOnClickOutside);
  }, 0);
}

function closeBoardMembersDropdown() {
  const dropdown = qs('#boardMembersDropdown');
  if (dropdown) {
    dropdown.classList.remove('is-open');
    dropdown.setAttribute('aria-hidden', 'true');
  }
  document.removeEventListener('click', closeBoardMembersDropdownOnClickOutside);
}

function closeBoardMembersDropdownOnClickOutside(e) {
  const wrap = qs('#boardMembersWrap');
  const dropdown = qs('#boardMembersDropdown');
  if (wrap && dropdown && !wrap.contains(e.target)) {
    closeBoardMembersDropdown();
  }
}

function initSortable() {
  const boardEl = qs('#kbBoard');
  if (boardEl) {
    new Sortable(boardEl, {
      animation: 180,
      draggable: '.cg-col',
      filter: '.cg-addColBtn',
      handle: '.cg-col__head',
      onEnd: async () => {
        await persistColumnPositions();
      }
    });
  }

  KB.columns.forEach(c => {
    const el = qs(`#col_${c.id}`);
    if (!el) return;

    // make cards draggable; ignore the add button
    new Sortable(el, {
      group: 'kb',
      animation: 180,
      draggable: '.cg-card',
      filter: '.cg-addBtn',
      onEnd: async () => {
        // Ensure Add card button stays at the bottom
        const addBtn = qs('.cg-addBtn', el);
        if (addBtn && addBtn.parentNode === el) {
          el.appendChild(addBtn);
        }
        await persistPositions();
      }
    });
  });
}

async function persistColumnPositions() {
  const boardEl = qs('#kbBoard');
  if (!boardEl) return;

  const columnEls = qsa('.cg-col', boardEl);
  const moves = columnEls.map((colEl, idx) => ({
    column_id: parseInt(colEl.getAttribute('data-col-id'), 10),
    position: idx
  })).filter(move => move.column_id > 0);

  if (!moves.length) return;

  try {
    const res = await fetch('api/freelance_kanban.php?action=move_columns', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ moves })
    });

    if (!res.ok) {
      throw new Error('Failed to persist column order');
    }

    const orderMap = new Map(moves.map(move => [move.column_id, move.position]));
    KB.columns = (KB.columns || []).slice().sort((a, b) => {
      const aPos = orderMap.has(parseInt(a.id, 10)) ? orderMap.get(parseInt(a.id, 10)) : Number.MAX_SAFE_INTEGER;
      const bPos = orderMap.has(parseInt(b.id, 10)) ? orderMap.get(parseInt(b.id, 10)) : Number.MAX_SAFE_INTEGER;
      return aPos - bPos;
    });
  } catch (err) {
    console.error('Failed to persist column positions:', err);
    await loadKanbanData();
  }
}

async function persistPositions() {
  const moves = [];
  const newCardsByColumn = {};
  
  // First, ensure all Add card buttons are at the bottom of their columns
  KB.columns.forEach(c => {
    const colEl = qs(`#col_${c.id}`);
    if (!colEl) return;
    const addBtn = qs('.cg-addBtn', colEl);
    if (addBtn && addBtn.parentNode === colEl) {
      colEl.appendChild(addBtn);
    }
  });
  
  KB.columns.forEach(c => {
    const colEl = qs(`#col_${c.id}`);
    if (!colEl) return;
    // Get only cards, excluding the Add button
    const cards = qsa('.cg-card', colEl);
    newCardsByColumn[c.id] = [];
    cards.forEach((cardEl, idx) => {
      const cardId = parseInt(cardEl.dataset.cardId, 10);
      moves.push({
        card_id: cardId,
        to_column_id: c.id,
        position: idx
      });
      
      // Find the card data from current state
      let cardData = null;
      for (const colId in KB.cardsByColumn) {
        const arr = KB.cardsByColumn[colId] || [];
        const found = arr.find(card => parseInt(card.id, 10) === cardId);
        if (found) {
          cardData = found;
          break;
        }
      }
      if (cardData) {
        newCardsByColumn[c.id].push(cardData);
      }
    });
  });

  try {
    const res = await fetch('api/freelance_kanban.php?action=move_cards', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ moves })
    });
    
    if (res.ok) {
      // Update local state immediately
      KB.cardsByColumn = newCardsByColumn;
      // Update card counts in column headers
      KB.columns.forEach(c => {
        const countEl = qs(`.cg-col[data-col-id="${c.id}"] .cg-col__count`);
        if (countEl) {
          const count = newCardsByColumn[c.id]?.length || 0;
          countEl.textContent = count;
        }
      });
      
      // Ensure Add buttons stay at bottom after state update
      KB.columns.forEach(c => {
        const colEl = qs(`#col_${c.id}`);
        if (!colEl) return;
        const addBtn = qs('.cg-addBtn', colEl);
        if (addBtn && addBtn.parentNode === colEl) {
          colEl.appendChild(addBtn);
        }
      });
    }
  } catch (err) {
    console.error('Failed to persist positions:', err);
  }
}

async function loadCommentsForBoard() {
  if (!KB.board_id) {
    KB.comments = [];
    return;
  }
  try {
    const res = await fetch(`api/freelance_kanban.php?action=list_comments&board_id=${KB.board_id}`, { credentials: 'same-origin' });
    if (!res.ok) { KB.comments = []; return; }
    const data = await res.json();
    KB.comments = (data.comments || []);
  } catch (e) {
    KB.comments = [];
  }
}

async function loadKanbanData() {
  try {
    // Check if board_id is in URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlBoardId = urlParams.get('board_id');
    
    let url = 'api/freelance_kanban.php?action=bootstrap';
    if (urlBoardId) {
      url += `&board_id=${urlBoardId}`;
    }
    
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed');

    KB.project_id = data.project_id;
    KB.board_id = data.board_id;
    KB.chart_id = data.chart_id || 0;
    KB.boards = data.boards || [];
    KB.columns = data.columns || [];
    KB.cardsByColumn = data.cardsByColumn || {};
    KB.board_members = data.board_members || [];
    KB.is_board_owner = !!data.is_board_owner;
    await loadCommentsForBoard();

    // Update URL if board_id changed
    if (urlBoardId && parseInt(urlBoardId, 10) !== parseInt(data.board_id, 10)) {
      const newUrl = new URL(window.location);
      newUrl.searchParams.set('board_id', data.board_id);
      window.history.replaceState({}, '', newUrl);
    }

    renderBoard();
    updateBoardOwnerOnlyUI();
    startBoardPolling();
  } catch (err) {
    console.error('Load kanban data error:', err);
    throw err;
  }
}

const BOARD_POLL_INTERVAL_MS = 12000;

function stopBoardPolling() {
  if (KB.pollIntervalId != null) {
    clearInterval(KB.pollIntervalId);
    KB.pollIntervalId = null;
  }
}

function startBoardPolling() {
  stopBoardPolling();
  if (!KB.board_id || !KB.project_id) return;
  KB.pollIntervalId = setInterval(async () => {
    if (document.visibilityState === 'hidden') return;
    try {
      // Use only board_id so API allows both owner and collaborator (project_id would 404 for collaborators)
      const url = `api/freelance_kanban.php?action=bootstrap&board_id=${KB.board_id}&_t=${Date.now()}`;
      const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.success) return;
      KB.project_id = data.project_id;
      KB.board_id = data.board_id;
      KB.chart_id = data.chart_id || 0;
      KB.columns = data.columns || [];
      KB.cardsByColumn = data.cardsByColumn || {};
      KB.board_members = data.board_members || [];
      KB.is_board_owner = !!data.is_board_owner;
      renderBoard();
      updateBoardOwnerOnlyUI();
    } catch (_) { /* ignore poll errors */ }
  }, BOARD_POLL_INTERVAL_MS);
}

document.addEventListener('visibilitychange', function () {
  if (document.visibilityState === 'hidden') {
    stopBoardPolling();
  } else if (KB.board_id && KB.project_id) {
    startBoardPolling();
  }
});

function updateBoardOwnerOnlyUI() {
  const isOwner = !!KB.is_board_owner;
  const newBoardBtn = qs('#newBoardBtn');
  const editBoardBtn = qs('#editBoardBtn');
  const activitiesPanelBtn = qs('#activitiesPanelBtn');
  if (newBoardBtn) newBoardBtn.style.display = isOwner ? '' : 'none';
  if (editBoardBtn) editBoardBtn.style.display = isOwner ? '' : 'none';
  if (activitiesPanelBtn) activitiesPanelBtn.style.display = isOwner ? '' : 'none';
  const panel = qs('#activityPanel');
  if (panel && panel.classList.contains('is-open') && KB.board_id && KB.board_id !== KB.activityPanelBoardId) {
    loadActivityPanel();
  }
}

function openBoardModal() {
  qs('#board_name').value = 'New Board';
  const modalEl = qs('#boardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    // Fallback if Bootstrap not loaded yet
    setTimeout(() => openBoardModal(), 100);
  }
}

async function createBoard() {
  const name = qs('#board_name').value.trim();
  if (!name) return;
  
  const submitBtn = qs('#boardForm button[type="submit"]');
  const originalLabel = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
  
  try {
    const fd = new FormData();
    fd.append('action', 'create_board');
    fd.append('project_id', KB.project_id || '');
    fd.append('name', name);
    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
    
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to create board');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
      return;
    }
    
    // Close modal first
    const modalEl = qs('#boardModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
    
    // Store the new board_id to select after loadKanbanData
    const newBoardId = data.board_id;
    
    // Refresh board data
    await loadKanbanData();
    if (window.refreshSidebars) {
      window.refreshSidebars('boards');
    }
    
    // Switch view to the newly created board
    if (newBoardId) {
      try {
        const res2 = await fetch(`api/freelance_kanban.php?action=bootstrap&project_id=${KB.project_id}&board_id=${newBoardId}`, { credentials: 'same-origin' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.board_id = d2.board_id;
            KB.chart_id = d2.chart_id || 0;
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
          KB.board_members = d2.board_members || [];
          KB.is_board_owner = !!d2.is_board_owner;
          await loadCommentsForBoard();
          const newUrl = new URL(window.location);
          newUrl.searchParams.set('board_id', newBoardId);
          window.history.pushState({}, '', newUrl);
          renderBoard();
          }
        }
      } catch (err) {
        console.error('Error loading new board:', err);
        renderBoard();
      }
    }
  } catch (err) {
    console.error('Create board error:', err);
    // Only show error if it's not a success response
    const errorMsg = err.message || 'Failed to create board. Please try again.';
    // Check if board was actually created by refreshing
    try {
      await loadKanbanData();
      // If loadKanbanData succeeds, board might have been created
      const modalEl = qs('#boardModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      // Don't show error if board exists now
      return;
    } catch (bootstrapErr) {
      // If bootstrap fails, show the error
      alert(errorMsg);
    }
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalLabel;
  }
}

function openCreateCardModal(columnId) {
  qs('#create_card_column_id').value = columnId;
  qs('#create_card_title').value = 'New task';
  // Hide delete button when creating new card
  qs('#deleteCardBtn').style.display = 'none';
  
  // Reset button state when opening modal (in case it was stuck from previous creation)
  const submitBtn = qs('#createCardForm button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Card';
  }
  
  const modalEl = qs('#createCardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    // Fallback if Bootstrap not loaded yet
    setTimeout(() => openCreateCardModal(columnId), 100);
  }
}

async function createCard(columnId, options = {}) {
  const title = (options.title != null ? options.title : qs('#create_card_title').value).trim();
  if (!title) return;
  
  const submitBtn = options.submitBtn || qs('#createCardForm button[type="submit"]');
  const originalLabel = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
  }
  
  try {
    const fd = new FormData();
    fd.append('action', 'create_card');
    fd.append('column_id', columnId);
    fd.append('title', title);
    fd.append('description', options.description != null ? options.description : '');
    fd.append('start_date', options.start_date != null ? options.start_date : '');
    fd.append('due_date', options.due_date != null ? options.due_date : '');
    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
    
    const data = await res.json();
    
    if (!data.success) {
      alert(data.message || 'Failed to create card');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalLabel;
      }
      return;
    }
    
    // Close modal first
    if (!options.skipModalClose) {
      const modalEl = qs('#createCardModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
    }
    
    // Refresh the current board to show the new card
    if (KB.project_id && KB.board_id) {
      try {
        const res2 = await fetch(`api/freelance_kanban.php?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'same-origin' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            renderBoard();
            // Reset button state before returning
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalLabel;
            }
            return; // Success, exit early
          }
        }
      } catch (refreshErr) {
        console.error('Refresh error:', refreshErr);
      }
    }
    
    // Fallback: full refresh
    await loadKanbanData();
    // Reset button state after fallback refresh
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
    }
  } catch (err) {
    console.error('Create card error:', err);
    // Check if card was actually created by refreshing
    try {
      if (KB.project_id && KB.board_id) {
        await loadKanbanData();
        // If refresh succeeds, card might have been created
        if (!options.skipModalClose) {
          const modalEl = qs('#createCardModal');
          if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
          }
        }
        // Reset button state before returning
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalLabel;
        }
        return; // Don't show error if refresh succeeded
      }
    } catch (refreshErr) {
      // If refresh fails, show the error
      alert('Failed to create card. Please try again.');
    }
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
    }
  }
}

function findCard(cardId) {
  for (const colId in KB.cardsByColumn) {
    const arr = KB.cardsByColumn[colId] || [];
    for (const c of arr) if (parseInt(c.id,10) === parseInt(cardId,10)) return c;
  }
  return null;
}

// Card links and attachments state (existing attachments from server)
let cardExistingAttachments = [];

function renderCardAttachments(existing) {
  cardExistingAttachments = Array.isArray(existing) ? existing : [];
  const container = qs('#cardAttachmentsContainer');
  container.innerHTML = '';
  cardExistingAttachments.forEach((att, i) => {
    const path = att.path || att;
    const name = (att.name || path.split('/').pop() || 'Attachment');
    const url = path.startsWith('http') ? path : (path.startsWith('/') ? path : '/' + path);
    const div = document.createElement('div');
    div.className = 'd-inline-flex align-items-center gap-2 me-2 mb-2 p-2 rounded bg-light border';
    div.dataset.idx = String(i);
    const isImg = path.match(/\.(jpe?g|png|gif|webp)$/i);
    div.innerHTML = isImg
      ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="card-attachment-img-link" title="${escapeHtml(name)}"><img src="${escapeHtml(url)}" alt="" class="rounded" style="width:36px;height:36px;object-fit:cover;cursor:pointer;" onerror="this.style.display='none'"></a><span class="small text-truncate text-muted ms-1" style="max-width:180px;">${escapeHtml(name)}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 ms-1" aria-label="Remove"><i class="fas fa-times"></i></button>`
      : `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(name)}"><i class="fas fa-file-image text-muted"></i></a><span class="small text-truncate text-muted ms-1" style="max-width:180px;">${escapeHtml(name)}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 ms-1" aria-label="Remove"><i class="fas fa-times"></i></button>`;
    div.querySelector('button').onclick = () => {
      cardExistingAttachments = cardExistingAttachments.filter((_, j) => j !== i);
      div.remove();
    };
    container.appendChild(div);
  });
}

function getCardProgressColor(pct) {
  const v = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
  if (v < 10) return '#dc2626';
  if (v < 40) return '#2563eb';
  if (v < 80) return '#ea580c';
  return '#10b981';
}

function updateCardProgressSlider(el) {
  if (!el) return;
  let v = parseInt(el.value, 10);
  if (isNaN(v)) v = 0;
  v = Math.round(v / 5) * 5;
  v = Math.max(0, Math.min(100, v));
  el.value = v;
  const label = document.getElementById('progressValue');
  if (label) label.textContent = v;
  el.style.setProperty('--progress-pct', String(v));
  el.style.setProperty('--progress-color', getCardProgressColor(v));
}

function openCard(cardId) {
  const c = findCard(cardId);
  if (!c) return;
  qs('#card_id').value = c.id;
  qs('#card_title').value = c.title || '';
  qs('#card_description').value = c.description || '';
  qs('#card_start_date').value = c.start_date || '';
  qs('#card_due_date').value = c.due_date || '';
  let progressVal = Math.max(0, Math.min(100, parseInt(c.progress != null ? c.progress : 0, 10)));
  progressVal = Math.round(progressVal / 5) * 5;
  qs('#card_progress').value = progressVal;
  qs('#card_progress').style.setProperty('--progress-pct', String(progressVal));
  qs('#card_progress').style.setProperty('--progress-color', getCardProgressColor(progressVal));
  qs('#progressValue').textContent = progressVal;
  qs('#card_priority').value = c.priority || '';
  // Links and attachments
  const links = (typeof c.links === 'string' ? (() => { try { return JSON.parse(c.links); } catch (_) { return []; } })() : c.links) || [];
  const attachments = (typeof c.attachments === 'string' ? (() => { try { return JSON.parse(c.attachments); } catch (_) { return []; } })() : c.attachments) || [];
  renderCardAttachments(attachments);
  qs('#card_links_data').value = JSON.stringify(links);
  // Clear attachment drop zone and selected files list
  const fileInput = qs('#cardAttachmentFileInput');
  if (fileInput) { fileInput.value = ''; }
  qs('#cardNewAttachmentsList').innerHTML = '';
  const dropWrap = qs('#cardAttachmentDropWrap');
  if (dropWrap) dropWrap.style.display = 'none';
  const dropZone = qs('#cardAttachmentDropZone');
  if (dropZone) dropZone.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
  const progressWrap = qs('#cardAttachmentUploadProgress');
  if (progressWrap) progressWrap.style.display = 'none';
  // Show delete button for existing cards
  resetDeleteCardButton();
  qs('#deleteCardBtn').style.display = 'block';
  const modalEl = qs('#cardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function toggleCardAttachmentDropZone() {
  const wrap = qs('#cardAttachmentDropWrap');
  if (wrap) wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}

qs('#cardFooterAddAttachmentBtn').addEventListener('click', toggleCardAttachmentDropZone);

(function setupAttachmentDropZone() {
  const dropZone = qs('#cardAttachmentDropZone');
  const fileInput = qs('#cardAttachmentFileInput');
  const listEl = qs('#cardNewAttachmentsList');
  const progressWrap = qs('#cardAttachmentUploadProgress');
  const progressFill = qs('#cardAttachmentUploadProgressFill');
  const progressLabel = qs('#cardAttachmentUploadProgressLabel');
  if (!dropZone || !fileInput || !listEl) return;

  const imageTypes = ['image/jpeg','image/png','image/gif','image/webp'];
  function isImage(file) { return imageTypes.includes(file.type) || /^image\//.test(file.type); }

  function updateUploadProgressVisibility(files) {
    if (!progressWrap || !progressFill || !progressLabel) return;
    const count = files.length;
    if (count === 0) {
      progressWrap.style.display = 'none';
      progressFill.style.width = '0%';
      progressLabel.textContent = 'Uploading… 0%';
      return;
    }
    progressWrap.style.display = 'block';
    progressFill.style.width = '0%';
    progressLabel.textContent = count === 1 ? '1 image selected — upload on Save' : count + ' images selected — upload on Save';
  }

  function renderSelectedFiles() {
    const files = Array.from(fileInput.files || []);
    if (files.length === 0) {
      listEl.innerHTML = '';
      updateUploadProgressVisibility([]);
      return;
    }
    listEl.innerHTML = files.map((file, i) =>
      `<div class="d-flex align-items-center gap-2 mb-1 small">
        <i class="fas fa-image text-muted"></i>
        <span class="text-truncate" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 ms-auto card-new-attachment-remove-btn" data-index="${i}" aria-label="Remove"><i class="fas fa-times"></i></button>
      </div>`
    ).join('');
    listEl.querySelectorAll('button[data-index]').forEach(btn => {
      btn.onclick = () => {
        const idx = parseInt(btn.getAttribute('data-index'), 10);
        const dt = new DataTransfer();
        Array.from(fileInput.files).forEach((f, j) => { if (j !== idx) dt.items.add(f); });
        fileInput.files = dt.files;
        renderSelectedFiles();
      };
    });
    updateUploadProgressVisibility(files);
  }

  dropZone.addEventListener('click', (e) => { if (!e.target.closest('button')) fileInput.click(); });
  dropZone.addEventListener('dragover', (e) => { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('border-primary', 'bg-primary', 'bg-opacity-10'); });
  dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); e.stopPropagation(); if (!dropZone.contains(e.relatedTarget)) dropZone.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10'); });
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
    const files = Array.from(e.dataTransfer.files || []).filter(isImage);
    if (files.length === 0) return;
    const dt = new DataTransfer();
    (Array.from(fileInput.files || [])).forEach(f => dt.items.add(f));
    files.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
  });
  fileInput.addEventListener('change', () => renderSelectedFiles());
})();

(function sidebarCollapseInit() {
  const STORAGE_KEY = 'kanban_sidebar_collapsed';
  const btn = document.getElementById('sidebarCollapseBtn');
  const icon = document.getElementById('sidebarCollapseIcon');
  const sidebar = document.querySelector('.fsidebar.sidebar');

  function isDesktop() { return window.innerWidth >= 769; }
  function isSidebarVisible() {
    if (isDesktop()) return !document.body.classList.contains('sidebar-collapsed');
    return sidebar && sidebar.classList.contains('show');
  }
  function updateCollapseButton() {
    if (!btn || !icon) return;
    const visible = isSidebarVisible();
    btn.setAttribute('aria-label', visible ? 'Collapse sidebar' : 'Expand sidebar');
    icon.style.transform = visible ? '' : 'rotate(180deg)';
  }

  window.toggleKanbanSidebar = function () {
    if (isDesktop()) {
      document.body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem(STORAGE_KEY, document.body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (_) {}
    } else {
      if (typeof toggleSidebar === 'function') toggleSidebar();
    }
    updateCollapseButton();
  };

  if (isDesktop() && typeof localStorage !== 'undefined') {
    try {
      if (localStorage.getItem(STORAGE_KEY) === '1') document.body.classList.add('sidebar-collapsed');
    } catch (_) {}
  }
  updateCollapseButton();
  window.addEventListener('resize', function () { updateCollapseButton(); });
})();

function cardFormSubmitSuccess(data, saveBtn, originalLabel) {
  if (KB.project_id && KB.board_id) {
    return fetch(`api/freelance_kanban.php?action=bootstrap&board_id=${KB.board_id}&_t=${Date.now()}`, { credentials: 'same-origin', cache: 'no-store' })
      .then(res2 => res2.json())
      .then(d2 => {
        if (d2.success) {
          KB.columns = d2.columns || [];
          KB.cardsByColumn = d2.cardsByColumn || {};
          renderBoard();
        }
      });
  }
  return loadKanbanData();
}

qs('#cardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  let links = [];
  try {
    links = JSON.parse(qs('#card_links_data').value || '[]');
  } catch (_) {
    links = [];
  }
  if (!Array.isArray(links)) links = [];
  qs('#card_links_data').value = JSON.stringify(links);
  qs('#card_existing_attachments').value = JSON.stringify(cardExistingAttachments);

  const fd = new FormData(e.target);
  fd.append('action', 'update_card');

  const saveBtn = qs('#cardForm button[type="submit"]');
  const originalLabel = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  const fileInput = qs('#cardAttachmentFileInput');
  const hasAttachments = fileInput && fileInput.files && fileInput.files.length > 0;
  const progressWrap = qs('#cardAttachmentUploadProgress');
  const progressFill = qs('#cardAttachmentUploadProgressFill');
  const progressLabel = qs('#cardAttachmentUploadProgressLabel');

  function hideUploadProgress() {
    if (progressWrap) progressWrap.style.display = 'none';
    if (progressFill) progressFill.style.width = '0%';
    if (progressLabel) progressLabel.textContent = 'Uploading… 0%';
  }

  try {
    if (hasAttachments && progressWrap && progressFill && progressLabel) {
      await new Promise((resolve, reject) => {
        progressWrap.style.display = 'block';
        progressFill.style.width = '0%';
        progressLabel.textContent = 'Uploading… 0%';
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/freelance_kanban.php');
        xhr.withCredentials = true;
        xhr.upload.addEventListener('progress', (ev) => {
          if (ev.lengthComputable) {
            const pct = Math.round((ev.loaded / ev.total) * 100);
            progressFill.style.width = pct + '%';
            progressLabel.textContent = 'Uploading… ' + pct + '%';
          }
        });
        xhr.addEventListener('load', () => {
          hideUploadProgress();
          if (xhr.status < 200 || xhr.status >= 300) {
            reject(new Error('Save failed (HTTP ' + xhr.status + ')'));
            return;
          }
          let data;
          try { data = JSON.parse(xhr.responseText); } catch (_) { reject(new Error('Invalid response')); return; }
          if (!data.success) { reject(new Error(data.message || 'Failed to save card')); return; }
          cardFormSubmitSuccess(data, saveBtn, originalLabel).then(() => {
            const cardModalEl = qs('#cardModal');
            if (cardModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
              const modal = bootstrap.Modal.getInstance(cardModalEl);
              if (modal) modal.hide();
            }
            qs('#deleteCardBtn').style.display = 'none';
            resolve();
          }).catch(reject);
        });
        xhr.addEventListener('error', () => { hideUploadProgress(); reject(new Error('Network error')); });
        xhr.addEventListener('abort', () => { hideUploadProgress(); reject(new Error('Upload cancelled')); });
        xhr.send(fd);
      });
      return;
    }

    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`Save failed (HTTP ${res.status}): ${text.substring(0,200)}`);
    }
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to save card');

    await cardFormSubmitSuccess(data, saveBtn, originalLabel);
    const cardModalEl = qs('#cardModal');
    if (cardModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(cardModalEl);
      if (modal) modal.hide();
    }
    qs('#deleteCardBtn').style.display = 'none';
  } catch (err) {
    console.error('Save card error:', err);
    alert(err.message || 'Failed to save card');
  } finally {
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalLabel;
    hideUploadProgress();
  }
});

async function deleteCard() {
  const cardId = qs('#card_id').value;
  if (!cardId) return;
  
  if (!confirm('Are you sure you want to delete this card? This action cannot be undone.')) {
    return;
  }
  
  const deleteBtn = qs('#deleteCardBtn');
  const originalText = deleteBtn.innerHTML;
  deleteBtn.disabled = true;
  deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
  
  try {
    const fd = new FormData();
    fd.append('card_id', String(parseInt(cardId, 10)));
    const res = await fetch('api/freelance_kanban.php?action=delete_card', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });
    
    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Failed to delete card');
    }
    
    // Close modal
    const modalEl = qs('#cardModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
    
    // Hide delete button
    qs('#deleteCardBtn').style.display = 'none';
    
    // Reload board data
    await loadKanbanData();
    
    // Show success message
    alert('Card deleted successfully');
  } catch (err) {
    console.error('Delete card error:', err);
    alert('Failed to delete card: ' + err.message);
    deleteBtn.disabled = false;
    deleteBtn.innerHTML = originalText;
  }
}

function resetDeleteCardButton() {
  const deleteBtn = qs('#deleteCardBtn');
  if (!deleteBtn) return;
  deleteBtn.disabled = false;
  deleteBtn.innerHTML = '<i class="fas fa-trash me-2"></i>Delete';
}

// Hide delete button when card modal is closed
const cardModalEl = qs('#cardModal');
if (cardModalEl) {
  cardModalEl.addEventListener('hidden.bs.modal', () => {
    resetDeleteCardButton();
    qs('#deleteCardBtn').style.display = 'none';
    qs('#card_id').value = '';
  });
}

qs('#newBoardBtn').addEventListener('click', openBoardModal);

(function initBoardMembersDropdown() {
  const avatars = qs('#boardMembersAvatars');
  const wrap = qs('#boardMembersWrap');
  if (avatars) {
    avatars.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); toggleBoardMembersDropdown(); });
    avatars.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleBoardMembersDropdown(); } });
  }
})();

function loadBoardEmailsKanban(boardId) {
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
        const enc = escapeHtml(email);
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
            .then(d => { if (d.success) loadBoardEmailsKanban(bid); });
        });
      });
    })
    .catch(() => { listEl.innerHTML = '<span class="text-muted small">Failed to load emails</span>'; });
}

function loadBoardCollaboratorsKanban(boardId) {
  const wrap = qs('#editBoardCollaboratorsWrap');
  const listEl = qs('#editBoardCollaboratorsList');
  if (!wrap || !listEl || !boardId) return;
  listEl.innerHTML = '<span class="text-muted small">Loading…</span>';
  fetch('api/freelance_kanban.php?action=list_collaborators&board_id=' + encodeURIComponent(boardId), { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        wrap.style.display = 'none';
        return;
      }
      wrap.style.display = 'block';
      const collaborators = data.collaborators || [];
      const pending = data.pending || [];
      const items = [];
      collaborators.forEach(c => {
        const name = escapeHtml(c.name || c.email || 'User');
        const email = escapeHtml(c.email || '');
        items.push('<span class="d-inline-flex align-items-center gap-1 me-2 mb-1 px-2 py-1 rounded bg-light" style="font-size:13px;">' +
          '<span>' + name + (email ? ' &lt;' + email + '&gt;' : '') + '</span>' +
          '<button type="button" class="btn btn-link btn-sm p-0 text-danger remove-collab" style="font-size:12px;" data-user-id="' + (c.user_id || '') + '" data-email="" aria-label="Remove">×</button></span>');
      });
      pending.forEach(p => {
        const email = escapeHtml(p.email || '');
        items.push('<span class="d-inline-flex align-items-center gap-1 me-2 mb-1 px-2 py-1 rounded bg-light" style="font-size:13px;">' +
          '<span>' + email + '</span> <span class="badge bg-secondary">Pending</span>' +
          '<button type="button" class="btn btn-link btn-sm p-0 text-danger remove-collab" style="font-size:12px;" data-user-id="" data-email="' + email + '" aria-label="Remove">×</button></span>');
      });
      if (items.length === 0) {
        listEl.innerHTML = '<span class="text-muted small">No collaborators yet. Add by email below.</span>';
      } else {
        listEl.innerHTML = items.join('');
      }
      listEl.querySelectorAll('button.remove-collab').forEach(btn => {
        btn.addEventListener('click', () => {
          const userId = btn.getAttribute('data-user-id');
          const email = btn.getAttribute('data-email');
          const bid = qs('#edit_board_id').value;
          const fd = new FormData();
          fd.append('action', 'remove_collaborator');
          fd.append('board_id', bid);
          if (userId) fd.append('user_id', userId);
          if (email) fd.append('email', email);
          fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => { if (d.success) loadBoardCollaboratorsKanban(bid); else alert(d.message || 'Failed to remove'); });
        });
      });
    })
    .catch(() => { wrap.style.display = 'none'; });
}

function openEditBoardModal() {
  if (!KB.board_id) {
    alert('Please select a board first.');
    return;
  }
  const board = KB.boards.find(b => parseInt(b.id, 10) === parseInt(KB.board_id, 10));
  if (!board) return;
  qs('#edit_board_id').value = board.id;
  qs('#edit_board_name').value = board.name;
  loadBoardEmailsKanban(board.id);
  loadBoardCollaboratorsKanban(board.id);
  qs('#editBoardNewEmailInput').value = '';
  qs('#editBoardNewCollaboratorInput').value = '';
  const errEl = qs('#editBoardEmailError');
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  const collabErrEl = qs('#editBoardCollaboratorError');
  if (collabErrEl) { collabErrEl.style.display = 'none'; collabErrEl.textContent = ''; }
  qs('#editBoardAddCollaboratorBtn').onclick = () => {
    const email = (qs('#editBoardNewCollaboratorInput').value || '').trim().toLowerCase();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      if (collabErrEl) { collabErrEl.textContent = 'Please enter a valid email'; collabErrEl.style.display = 'block'; }
      return;
    }
    if (collabErrEl) collabErrEl.style.display = 'none';
    const fd = new FormData();
    fd.append('action', 'add_collaborator');
    fd.append('board_id', qs('#edit_board_id').value);
    fd.append('email', email);
    fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          qs('#editBoardNewCollaboratorInput').value = '';
          loadBoardCollaboratorsKanban(qs('#edit_board_id').value);
        } else if (collabErrEl) {
          collabErrEl.textContent = d.message || 'Failed to add';
          collabErrEl.style.display = 'block';
        }
      });
  };
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
        if (d.success) { qs('#editBoardNewEmailInput').value = ''; loadBoardEmailsKanban(qs('#edit_board_id').value); }
        else if (errEl) { errEl.textContent = d.message || 'Failed to add'; errEl.style.display = 'block'; }
      });
  };
  const modalEl = qs('#editBoardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

qs('#editBoardBtn').addEventListener('click', openEditBoardModal);

function openActivityPanel() {
  const panel = qs('#activityPanel');
  const backdrop = qs('#activityPanelBackdrop');
  if (!panel || !backdrop) return;
  panel.classList.add('is-open');
  panel.setAttribute('aria-hidden', 'false');
  backdrop.classList.add('is-open');
  backdrop.setAttribute('aria-hidden', 'false');
  loadActivityPanel();
}
function closeActivityPanel() {
  const panel = qs('#activityPanel');
  const backdrop = qs('#activityPanelBackdrop');
  if (panel) { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
  if (backdrop) { backdrop.classList.remove('is-open'); backdrop.setAttribute('aria-hidden', 'true'); }
}
function formatActivityMessage(a) {
  const name = (a.user_name || 'Someone').trim() || 'Someone';
  const card = (a.payload && a.payload.card_title) ? a.payload.card_title : '';
  const cardQ = card ? `"${escapeHtml(card)}"` : '';
  switch (a.action_type) {
    case 'card_moved':
      return name + ' moved the card ' + (cardQ || '(card)') + ' from ' + escapeHtml((a.payload && a.payload.from_column) || '') + ' to ' + escapeHtml((a.payload && a.payload.to_column) || '');
    case 'progress_changed':
      return name + ' changed the progress of ' + (cardQ || 'a card') + ' to ' + (a.payload && a.payload.progress != null ? a.payload.progress + '%' : '');
    case 'comment_added':
      return name + ' commented "' + escapeHtml((a.payload && a.payload.comment) || '') + '" on card ' + (cardQ || '');
    case 'comment_deleted':
    case 'card_deleted':
      return name + ' deleted ' + (a.action_type === 'card_deleted' ? 'card ' + (cardQ || '') : 'a comment on card ' + (cardQ || ''));
    case 'collaborator_added':
      return name + ' added ' + escapeHtml((a.payload && (a.payload.collaborator_name || a.payload.email)) || '') + ' as collaborator';
    case 'notification_added':
      return name + ' added ' + escapeHtml((a.payload && a.payload.email) || '') + ' on notification';
    case 'attachment_added':
      var files = (a.payload && a.payload.file_names && a.payload.file_names.length) ? a.payload.file_names.join(', ') : 'an attachment';
      return name + ' added ' + escapeHtml(files) + ' on card ' + (cardQ || '');
    default:
      return name + ' did something on this board';
  }
}
async function loadActivityPanel() {
  const listEl = qs('#activityPanelList');
  const loadingEl = qs('#activityPanelLoading');
  const emptyEl = qs('#activityPanelEmpty');
  if (!listEl || !KB.board_id) return;
  loadingEl.style.display = 'block';
  emptyEl.style.display = 'none';
  listEl.innerHTML = '';
  try {
    const res = await fetch('api/freelance_kanban.php?action=list_activities&board_id=' + encodeURIComponent(KB.board_id), { credentials: 'same-origin' });
    const data = await res.json();
    loadingEl.style.display = 'none';
    KB.activityPanelBoardId = KB.board_id;
    if (!data.success || !data.activities || !data.activities.length) {
      emptyEl.style.display = 'block';
      return;
    }
    data.activities.forEach(function (a) {
      const item = document.createElement('div');
      item.className = 'cg-activity-item';
      const timeStr = a.created_at ? new Date(a.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '';
      item.innerHTML = '<div class="cg-activity-name">' + formatActivityMessage(a) + '</div>' + (timeStr ? '<div class="cg-activity-time">' + escapeHtml(timeStr) + '</div>' : '');
      listEl.appendChild(item);
    });
  } catch (e) {
    loadingEl.style.display = 'none';
    emptyEl.style.display = 'block';
    emptyEl.textContent = 'Could not load activity.';
  }
}
qs('#activitiesPanelBtn').addEventListener('click', openActivityPanel);
qs('#themeToggleBtn').addEventListener('click', toggleKanbanTheme);
qs('#activityPanelClose').addEventListener('click', closeActivityPanel);
qs('#activityPanelBackdrop').addEventListener('click', closeActivityPanel);

function attachUserSuggest(inputEl, suggestEl) {
  if (!inputEl || !suggestEl) return;
  let timeout = null;
  let currentResults = [];
  let selectedIdx = -1;

  function showSuggestions(users) {
    currentResults = users || [];
    selectedIdx = -1;
    if (!currentResults.length) {
      suggestEl.style.display = 'none';
      suggestEl.innerHTML = '';
      return;
    }
    suggestEl.innerHTML = currentResults.map(u => {
      const name = escapeHtml(u.name || '');
      const email = escapeHtml(u.email || '');
      return '<div class="cg-user-suggest-item" data-email="' + email + '" data-name="' + name + '">' +
        '<span class="fw-semibold">' + name + '</span><br><span class="small text-muted">' + email + '</span></div>';
    }).join('');
    suggestEl.style.display = 'block';
    suggestEl.querySelectorAll('.cg-user-suggest-item').forEach((el, idx) => {
      el.addEventListener('click', () => {
        inputEl.value = el.getAttribute('data-email');
        suggestEl.style.display = 'none';
        suggestEl.innerHTML = '';
      });
    });
  }

  inputEl.addEventListener('input', () => {
    clearTimeout(timeout);
    const q = inputEl.value.trim();
    if (q.length < 2) {
      suggestEl.style.display = 'none';
      suggestEl.innerHTML = '';
      return;
    }
    timeout = setTimeout(() => {
      fetch('api/freelance_kanban.php?action=search_users&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
          if (d.success && d.users && d.users.length) showSuggestions(d.users);
          else { suggestEl.style.display = 'none'; suggestEl.innerHTML = ''; }
        })
        .catch(() => { suggestEl.style.display = 'none'; });
    }, 220);
  });

  inputEl.addEventListener('blur', () => {
    setTimeout(() => { suggestEl.style.display = 'none'; }, 180);
  });

  inputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { suggestEl.style.display = 'none'; return; }
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter') return;
    const items = suggestEl.querySelectorAll('.cg-user-suggest-item');
    if (!items.length) return;
    e.preventDefault();
    if (e.key === 'ArrowDown') { selectedIdx = Math.min(selectedIdx + 1, items.length - 1); }
    else if (e.key === 'ArrowUp') { selectedIdx = Math.max(selectedIdx - 1, -1); }
    else if (e.key === 'Enter' && selectedIdx >= 0 && items[selectedIdx]) {
      inputEl.value = items[selectedIdx].getAttribute('data-email');
      suggestEl.style.display = 'none';
      suggestEl.innerHTML = '';
      return;
    }
    items.forEach((item, i) => item.classList.toggle('selected', i === selectedIdx));
    if (selectedIdx >= 0 && items[selectedIdx]) items[selectedIdx].scrollIntoView({ block: 'nearest' });
  });
}

attachUserSuggest(qs('#editBoardNewEmailInput'), qs('#editBoardEmailSuggest'));
attachUserSuggest(qs('#editBoardNewCollaboratorInput'), qs('#editBoardCollaboratorSuggest'));

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
    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.success) {
      const modalEl = qs('#editBoardModal');
      if (modalEl && bootstrap.Modal) bootstrap.Modal.getInstance(modalEl).hide();
      await loadKanbanData();
      if (window.refreshSidebars) window.refreshSidebars('boards');
    } else {
      alert(data.message || 'Failed to update board');
    }
  } catch (err) {
    console.error('Edit board error:', err);
    alert('Failed to update board');
  }
});
qs('#boardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  await createBoard();
});

qs('#viewTimelineBtn').addEventListener('click', async () => {
  const boardId = KB.board_id;
  if (!boardId) {
    alert('Please wait for the board to load, or select a board.');
    return;
  }
  try {
    if (!KB.is_board_owner) {
      // Collaborator: open the owner's client view link in a new tab
      const res = await fetch(`api/freelance_kanban.php?action=get_client_view_url&board_id=${boardId}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success) {
        alert(data.message || 'Client link not available. Ask the board owner to share the timeline.');
        return;
      }
      window.open(data.url || '', '_blank', 'noopener,noreferrer');
      return;
    }
    const res = await fetch(`/api/freelance_timeline.php?action=get_or_create_chart&board_id=${boardId}`, { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to open timeline');
      return;
    }
    window.location.href = `project_timeline.php?chart_id=${data.chart_id}`;
  } catch (err) {
    console.error('View timeline error:', err);
    alert('Failed to open timeline.');
  }
});

function openColumnModal(columnId = null, currentName = '') {
  const modal = qs('#columnModal');
  const title = qs('#columnModalTitle');
  const submitText = qs('#columnSubmitText');
  const submitBtn = qs('#columnForm button[type="submit"]');
  const formText = qs('#columnFormText');
  const nameInput = qs('#column_name');
  const columnIdInput = qs('#column_id');

  if (!modal || !title || !formText || !nameInput || !columnIdInput) {
    console.error('openColumnModal: required modal elements not found');
    return;
  }

  const setSubmitLabel = (text) => {
    if (submitText) {
      submitText.textContent = text;
      return;
    }
    if (submitBtn) {
      const icon = submitBtn.querySelector('i');
      submitBtn.textContent = text;
      if (icon) submitBtn.prepend(icon, document.createTextNode(' '));
    }
  };
  
  if (columnId) {
    // Edit mode
    title.textContent = 'Rename Column';
    setSubmitLabel('Save');
    formText.textContent = 'Update the column name.';
    nameInput.value = currentName;
    columnIdInput.value = columnId;
  } else {
    // Create mode
    title.textContent = 'New Column';
    setSubmitLabel('Create Column');
    formText.textContent = 'Create a new column for organizing your tasks.';
    nameInput.value = 'New Column';
    columnIdInput.value = '';
  }
  
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  } else {
    // Fallback if Bootstrap not loaded yet
    setTimeout(() => openColumnModal(columnId, currentName), 100);
  }
}

async function createColumn() {
  const name = qs('#column_name').value.trim();
  if (!name) return;
  
  const submitBtn = qs('#columnForm button[type="submit"]');
  const originalLabel = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
  
  try {
    const fd = new FormData();
    fd.append('action', 'create_column');
    fd.append('board_id', KB.board_id || '');
    fd.append('name', name);
    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
    
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to create column');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
      return;
    }
    
    // Close modal first
    const columnModalEl = qs('#columnModal');
    if (columnModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(columnModalEl);
      if (modal) modal.hide();
    }
    
    // Refresh the current board to show the new column
    if (KB.project_id && KB.board_id) {
      try {
        const res2 = await fetch(`api/freelance_kanban.php?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'same-origin' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            renderBoard();
            return; // Success, exit early
          }
        }
      } catch (refreshErr) {
        console.error('Refresh error:', refreshErr);
        // Column was created, just refresh the full data
      }
    }
    
    // Fallback: full refresh
    await loadKanbanData();
  } catch (err) {
    console.error('Create column error:', err);
    // Check if column was actually created by refreshing
    try {
      if (KB.project_id && KB.board_id) {
        await loadKanbanData();
        // If refresh succeeds, column might have been created
        const columnModalEl = qs('#columnModal');
        if (columnModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          const modal = bootstrap.Modal.getInstance(columnModalEl);
          if (modal) modal.hide();
        }
        return; // Don't show error if refresh succeeded
      }
    } catch (refreshErr) {
      // If refresh fails, show the error
      alert('Failed to create column. Please try again.');
    }
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalLabel;
  }
}

async function updateColumn() {
  const columnId = parseInt(qs('#column_id').value, 10);
  const name = qs('#column_name').value.trim();
  if (!columnId || !name) return;
  
  const submitBtn = qs('#columnForm button[type="submit"]');
  const originalLabel = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  
  try {
    const fd = new FormData();
    fd.append('action', 'update_column');
    fd.append('column_id', columnId);
    fd.append('name', name);
    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
    
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to update column');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
      return;
    }
    
    // Close modal first
    const columnModalEl = qs('#columnModal');
    if (columnModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(columnModalEl);
      if (modal) modal.hide();
    }
    
    // Refresh the current board to show the updated column
    if (KB.project_id && KB.board_id) {
      try {
        const res2 = await fetch(`api/freelance_kanban.php?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'same-origin' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            renderBoard();
            return; // Success, exit early
          }
        }
      } catch (refreshErr) {
        console.error('Refresh error:', refreshErr);
      }
    }
    
    // Fallback: full refresh
    await loadKanbanData();
  } catch (err) {
    console.error('Update column error:', err);
    // Check if column was actually updated by refreshing
    try {
      if (KB.project_id && KB.board_id) {
        await loadKanbanData();
        // If refresh succeeds, column might have been updated
        const columnModalEl = qs('#columnModal');
        if (columnModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          const modal = bootstrap.Modal.getInstance(columnModalEl);
          if (modal) modal.hide();
        }
        return; // Don't show error if refresh succeeded
      }
    } catch (refreshErr) {
      // If refresh fails, show the error
      alert('Failed to update column. Please try again.');
    }
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalLabel;
  }
}

async function deleteColumn(columnId) {
  if (!confirm('Are you sure you want to delete this column? All cards in this column will need to be moved first.')) {
    return;
  }
  
  try {
    const fd = new FormData();
    fd.append('action', 'delete_column');
    fd.append('column_id', columnId);
    const res = await fetch('api/freelance_kanban.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to delete column');
      return;
    }
    
    await loadKanbanData();
  } catch (err) {
    console.error('Delete column error:', err);
    alert('Failed to delete column. Please try again.');
  }
}

function editColumnName(columnId, currentName) {
  openColumnModal(columnId, currentName);
}

qs('#columnForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const columnId = qs('#column_id').value;
  if (columnId) {
    await updateColumn();
  } else {
    await createColumn();
  }
});

qs('#createCardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const columnId = parseInt(qs('#create_card_column_id').value, 10);
  if (columnId) {
    await createCard(columnId);
  }
});

qs('#pasteCardColumnForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (!pendingPastedCardData) return;

  const select = qs('#paste_card_column_select');
  const columnId = parseInt(select ? select.value : '0', 10);
  if (!columnId) return;

  const submitBtn = qs('#pasteCardColumnForm button[type="submit"]');
  await createCard(columnId, {
    ...pendingPastedCardData,
    submitBtn,
    skipModalClose: true
  });

  const modalEl = qs('#pasteCardColumnModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  }
  pendingPastedCardData = null;
});

const pasteCardColumnModalEl = qs('#pasteCardColumnModal');
if (pasteCardColumnModalEl) {
  pasteCardColumnModalEl.addEventListener('hidden.bs.modal', () => {
    pendingPastedCardData = null;
    setTimeout(focusKanbanSurface, 0);
  });
}

// Reset button state when create card modal is shown
const createCardModalEl = qs('#createCardModal');
if (createCardModalEl) {
  createCardModalEl.addEventListener('shown.bs.modal', () => {
    const submitBtn = qs('#createCardForm button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Card';
    }
  });
}

function escapeHtml(str) {
  return (str ?? '').toString()
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function capitalizeWords(str) {
  if (!str) return '';
  return str.toString()
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ');
}

loadKanbanData().catch(err => {
  console.error('Kanban bootstrap failed:', err);
  const msg = err.message || 'Failed to load Kanban';
  qs('#kanbanRoot').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to load Kanban.<br><small class="text-muted mt-2">${escapeHtml(msg)}</small></div>`;
});

document.addEventListener('contextmenu', function(e) {
  e.preventDefault();
});

applyKanbanTheme(getStoredKanbanTheme());

document.addEventListener('paste', function(e) {
  const modalOpen = !!document.querySelector('.modal.show');
  if (modalOpen) return;

  const target = e.target instanceof Element ? e.target : null;
  if (isBlockedPasteContext(target)) return;
  if (isEditableContext(target)) return;
  if (!document.querySelector('.fmain-content.cg-kanban-page #kanbanRoot')) return;

  const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
  const parsedCard = parsePastedCardText(text);
  if (!parsedCard || !parsedCard.title) return;

  e.preventDefault();
  openPasteCardColumnModal(parsedCard);
}, true);

// Card detail bubble on hover (like client.php task bar)
(function() {
  const cardDetailBubble = document.getElementById('cardDetailBubble');
  if (!cardDetailBubble) return;
  document.addEventListener('mouseover', function(e) {
    const cardEl = e.target.closest('.cg-card');
    if (!cardEl) return;
    cancelCardBubbleHide();
    showCardDetailBubble(cardEl);
  });
  document.addEventListener('mouseout', function(e) {
    if (!e.target.closest('.cg-card')) return;
    if (e.relatedTarget && (cardDetailBubble.contains(e.relatedTarget) || e.relatedTarget.closest('.cg-card'))) return;
    scheduleCardBubbleHide();
  });
  cardDetailBubble.addEventListener('mouseenter', function() { cancelCardBubbleHide(); });
  cardDetailBubble.addEventListener('mouseleave', function(e) {
    if (e.relatedTarget && e.relatedTarget.closest('.cg-card')) return;
    scheduleCardBubbleHide();
  });
  window.addEventListener('scroll', function() { hideCardDetailBubble(); cancelCardBubbleHide(); }, true);
})();

// Click and drag scrolling for Kanban board
(function() {
  const kanbanRoot = qs('#kanbanRoot');
  if (!kanbanRoot) return;
  
  let isDown = false;
  let startX;
  let scrollLeft;
  let startY;
  let scrollTop;
  
  kanbanRoot.addEventListener('mousedown', (e) => {
    // Don't start drag if clicking on interactive elements
    if (e.target.closest('button, a, input, select, textarea, .cg-card, .cg-addBtn, .cg-addColBtn')) {
      return;
    }
    
    isDown = true;
    kanbanRoot.classList.add('dragging');
    startX = e.pageX - kanbanRoot.offsetLeft;
    startY = e.pageY - kanbanRoot.offsetTop;
    const scrollContainer = kanbanRoot.closest('.fmain-content');
    if (scrollContainer) {
      scrollLeft = scrollContainer.scrollLeft;
      scrollTop = scrollContainer.scrollTop;
    }
  });
  
  kanbanRoot.addEventListener('mouseleave', () => {
    isDown = false;
    kanbanRoot.classList.remove('dragging');
  });
  
  kanbanRoot.addEventListener('mouseup', () => {
    isDown = false;
    kanbanRoot.classList.remove('dragging');
  });
  
  kanbanRoot.addEventListener('mousemove', (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - kanbanRoot.offsetLeft;
    const y = e.pageY - kanbanRoot.offsetTop;
    const walkX = (x - startX) * 2; // Scroll speed multiplier
    const walkY = (y - startY) * 2;
    
    // Find the scrollable parent (fmain-content)
    const scrollContainer = kanbanRoot.closest('.fmain-content');
    if (scrollContainer) {
      scrollContainer.scrollLeft = scrollLeft - walkX;
      scrollContainer.scrollTop = scrollTop - walkY;
    }
  });
  
  // Touch support for mobile
  let touchStartX = 0;
  let touchStartY = 0;
  let touchScrollLeft = 0;
  let touchScrollTop = 0;
  
  kanbanRoot.addEventListener('touchstart', (e) => {
    if (e.target.closest('button, a, input, select, textarea, .cg-card, .cg-addBtn, .cg-addColBtn')) {
      return;
    }
    const touch = e.touches[0];
    touchStartX = touch.pageX - kanbanRoot.offsetLeft;
    touchStartY = touch.pageY - kanbanRoot.offsetTop;
    const scrollContainer = kanbanRoot.closest('.fmain-content');
    if (scrollContainer) {
      touchScrollLeft = scrollContainer.scrollLeft;
      touchScrollTop = scrollContainer.scrollTop;
    }
  }, { passive: true });
  
  kanbanRoot.addEventListener('touchmove', (e) => {
    if (e.target.closest('button, a, input, select, textarea, .cg-card, .cg-addBtn, .cg-addColBtn')) {
      return;
    }
    const touch = e.touches[0];
    const x = touch.pageX - kanbanRoot.offsetLeft;
    const y = touch.pageY - kanbanRoot.offsetTop;
    const walkX = (x - touchStartX) * 2;
    const walkY = (y - touchStartY) * 2;
    
    const scrollContainer = kanbanRoot.closest('.fmain-content');
    if (scrollContainer) {
      scrollContainer.scrollLeft = touchScrollLeft - walkX;
      scrollContainer.scrollTop = touchScrollTop - walkY;
    }
  }, { passive: true });
})();
</script>
