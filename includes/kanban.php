<?php
require_once dirname(__DIR__) . '/auth.php';
$cg_public_root = dirname(__DIR__, 2) . '/public_html';
if (!function_exists('cg_request_kanban_hostname')) {
    require_once $cg_public_root . '/includes/auth.php';
}
// Same docroot as main site: Host is kanban.cinegrid.net but portal index.php never runs — set flags for redirects.
if (cg_request_kanban_hostname() !== null) {
    if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL')) {
        define('CG_KANBAN_SUBDOMAIN_PORTAL', true);
    }
}
require_once $cg_public_root . '/includes/db.php';
require_once __DIR__ . '/freelance_projects.php';

$cg_freelance_kanban_api_url = 'api/freelance_kanban.php';
if (cg_request_kanban_hostname() !== null) {
    $cg_freelance_kanban_api_url = 'https://kanban.cinegrid.net/api/freelance_kanban.php';
}

$cg_freelance_timeline_api_url = '/api/freelance_timeline.php';
if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) {
    $cg_freelance_timeline_api_url = 'https://kanban.cinegrid.net/api/freelance_timeline.php';
}

/** On kanban subdomain, serve /uploads/... from the same host (kanban.cinegrid.net). */
$cg_kanban_static_origin = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) ? 'https://kanban.cinegrid.net' : '';
/** Same-origin preview for kanban_attachments (cross-site <img> to apex often omits cookies; see api/kanban_image_preview.php). */
$cg_kanban_image_preview_url = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL)
    ? 'https://kanban.cinegrid.net/api/kanban_image_preview.php'
    : '/api/kanban_image_preview.php';
$cg_kanban_profile_pic_url = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL)
    ? 'https://kanban.cinegrid.net/api/profile_pic.php'
    : '/api/profile_pic.php';

$cg_kanban_board_templates_list = [];
if (is_file(__DIR__ . '/includes/kanban_board_templates.php')) {
    require_once __DIR__ . '/includes/kanban_board_templates.php';
    $cg_kanban_board_templates_list = kanban_board_templates_list_for_api();
}

cg_require_freelancer_or_linked();
cg_ensure_freelance_tables();

$user_id = (int)$_SESSION['user_id'];
$pdo = getDB();

$page_title = 'Kanban';
if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) {
    require_once __DIR__ . '/includes/cg_kanban_portal_header.php';
} else {
    require_once $cg_public_root . '/includes/header.php';
}
?>
<?php if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL') || !CG_KANBAN_SUBDOMAIN_PORTAL): ?>
<style id="cg-kanban-fouc-guard">
/* Main-site kanban: portal shell injects these rules in head; keep here for cinegrid.net/kanban only. */
.cg-activity-backdrop:not(.is-open) { display: none; }
.cg-activity-panel:not(.is-open) {
    position: fixed;
    transform: translateX(100%);
    pointer-events: none;
}
#cgBoardChatFab.is-hidden { display: none !important; }
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
#cgBoardChatPanel.cg-board-chat--inline:not(.is-open) {
    display: none !important;
}
/* Floating layer panel (movable); position set by JS */
#cgBoardChatPanel.cg-board-chat--inline.is-open {
    position: fixed;
    left: 16px;
    top: 88px;
    right: auto;
    bottom: auto;
    transform: none;
    width: 320px;
    max-width: min(360px, calc(100vw - 24px));
    min-width: 0;
    display: flex;
    flex-direction: column;
    height: auto;
    max-height: min(72vh, calc(100vh - 120px));
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: rgba(255, 255, 255, 0.96);
    border-radius: 16px;
    box-shadow: 0 28px 60px rgba(2, 6, 23, 0.22);
    overflow: hidden;
    pointer-events: auto;
    z-index: 10052;
    touch-action: manipulation;
    -webkit-user-select: none;
    user-select: none;
}
#cgBoardChatPanel.cg-board-chat--inline.is-open.cg-board-chat--dragging {
    box-shadow: 0 32px 68px rgba(2, 6, 23, 0.32);
    opacity: 0.98;
}
#cgBoardChatPanel.cg-board-chat--inline .cg-board-chat__textarea,
#cgBoardChatPanel.cg-board-chat--inline .cg-board-chat__send,
#cgBoardChatPanel.cg-board-chat--inline .cg-board-chat__messages,
#cgBoardChatPanel.cg-board-chat--inline .cg-board-chat__composer-wrap {
    -webkit-user-select: text;
    user-select: text;
}
.kanban-theme-dark #cgBoardChatPanel.cg-board-chat--inline.is-open {
    border-color: rgba(148, 163, 184, 0.22);
    background: rgba(30, 41, 59, 0.96);
    box-shadow: 0 28px 60px rgba(0, 0, 0, 0.45);
}
#cgBoardChatMessages { flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; }
.cg-board-chat__composer-wrap { flex-shrink: 0; }
.cg-board-chat__header, .cg-board-chat__online { flex-shrink: 0; }
.cg-board-chat__composer-wrap .cg-board-chat__typing { flex-shrink: 0; }
@media (max-width: 576px) {
    #cgBoardChatPanel.cg-board-chat--inline.is-open {
        width: calc(100vw - 20px);
        max-width: none;
        max-height: min(70vh, calc(100vh - 100px));
    }
}
.cg-card-context-menu:not(.is-open),
.cg-card-color-menu:not(.is-open),
.cg-card-column-flyout:not(.is-open) { display: none; }
.cg-card-color-picker-popup:not(.is-open) { display: none; }
</style>
<?php endif; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_view_menu.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_portal_loading.css">
<?php if ($user_id > 0): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_kanban_board_chat.css">
<script>
window.CG_BOARD_CHAT_USER = { id: <?php echo (int)$user_id; ?>, name: <?php echo json_encode($_SESSION['user_name'] ?? 'User', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>, inline: true };
</script>
<script defer src="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_kanban_board_chat.js"></script>
<?php endif; ?>
<script src="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_board_tab_title.js"></script>
<script src="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_portal_loading.js"></script>
<?php require_once __DIR__ . '/includes/cg_page_scale_80_apply.php'; ?>

<div class="cg-page-scale-80">
<div class="fmain-content cg-kanban-page cg-page--bottom-nav" style="overflow-x: hidden; overflow-y: <?php echo (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) ? 'auto' : 'visible'; ?>;">
    <div class="cg-portal-loading-overlay" id="kanbanLoadingOverlay" hidden aria-hidden="true">
        <div class="cg-portal-loading-card cg-portal-loading-card--message-only" role="status" aria-live="polite">
            <div class="cg-portal-loading-spinner" aria-hidden="true"></div>
            <div class="cg-portal-loading-subtitle">Loading your board...</div>
        </div>
    </div>
    <div class="container-fluid cg-kanban-container" style="padding: 12px 32px; max-width: 100%; width: 100%;">
        <div class="cg-kanban-header mb-1">
            <div>
                <h2 class="mb-1" id="kanbanBoardTitle" style="font-weight:700;">Kanban</h2>
                <div class="text-muted" id="kanbanBoardSubtitle">Start Date: N/A | End Date: N/A</div>
            </div>
            <div class="cg-kanban-header-actions">
                <div class="cg-board-members-wrap d-flex align-items-center me-2" id="boardMembersWrap">
                    <div class="cg-board-avatars d-flex align-items-center" id="boardMembersAvatars" aria-label="Board collaborators"></div>
                </div>
                <button class="btn btn-outline-dark" id="newBoardBtn"><i class="fas fa-plus me-2"></i>New board</button>
                <div class="cg-card-search-wrap" id="kanbanCardSearchWrap">
                    <input class="form-control" id="kanbanCardSearchInput" type="text" placeholder="Search cards..." autocomplete="off" aria-label="Search cards" />
                    <div class="cg-card-search-dropdown" id="kanbanCardSearchDropdown" hidden></div>
                </div>
                <select class="form-select" id="kanbanBoardSelect" aria-label="Select board" title="Select board"></select>
                <div class="dropdown cg-templates-menu" id="kanbanTemplatesDropdownWrap">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" id="kanbanTemplatesMenuBtn" data-bs-toggle="dropdown" data-bs-offset="0,10" data-bs-placement="bottom-end" aria-expanded="false" aria-haspopup="true" title="Board templates">
                        <i class="fas fa-layer-group me-2"></i><span class="d-none d-sm-inline">Templates</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end cg-templates-menu__list" id="kanbanTemplatesMenuList"></ul>
                </div>
                <button class="btn btn-danger" type="button" id="kanbanStatsPreviewBtn" title="Board stats" aria-label="Preview board statistics">
                    <i class="fas fa-chart-pie text-white" aria-hidden="true"></i>
                </button>
                <button class="btn btn-danger" type="button" id="kanbanAutomationBtn" title="Automations" aria-label="Create board automation">
                    <i class="fas fa-robot text-white" aria-hidden="true"></i>
                </button>
                <?php if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL') || !CG_KANBAN_SUBDOMAIN_PORTAL): ?>
                <button class="btn btn-outline-dark" id="editBoardBtn" type="button" title="Board settings" aria-label="Board settings"><i class="fas fa-gear" aria-hidden="true"></i></button>
                <button class="btn btn-outline-dark" id="activitiesPanelBtn" type="button" title="Activities" aria-label="Open activities panel"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                <button class="btn btn-outline-dark" id="themeToggleBtn" type="button" data-kanban-theme-toggle title="Switch to dark mode" aria-label="Switch to dark mode"><i class="fas fa-moon" aria-hidden="true"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <div class="cg-kb-board-chat-row" id="cgKbBoardChatRow">
            <div class="cg-kb-board-chat-row__board">
                <div id="kanbanRoot" class="cg-kb"></div>
            </div>
        </div>
    </div>
</div>

<nav class="cg-bottom-nav" aria-label="Board views and chat">
    <button type="button" class="cg-bottom-nav__btn" id="cgBottomNavTimeline">
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
    <?php if ($user_id > 0): ?>
    <button type="button" class="cg-bottom-nav__btn" id="cgBottomNavChat" title="Board chat" aria-label="Show or hide board chat" aria-expanded="false" aria-controls="cgBoardChatPanel">
        <i class="fas fa-comments" aria-hidden="true"></i>
        <span>Chat</span>
    </button>
    <?php endif; ?>
</nav>
</div>
<?php if ($user_id > 0): ?>
<aside id="cgBoardChatPanel" class="cg-board-chat-panel-root cg-board-chat--inline" aria-hidden="true" aria-label="Board chat">
    <div class="cg-board-chat__header">
        <div class="cg-board-chat__header-main" id="cgBoardChatHeaderMain" title="Drag to move">
            <div class="cg-board-chat__header-text">
                <h3 class="cg-board-chat__title"><i class="fas fa-comments me-2" aria-hidden="true"></i>Board chat</h3>
                <p class="cg-board-chat__subtitle">Collaborators on this board. Drag a card onto the composer to reference it.</p>
            </div>
        </div>
        <button type="button" class="cg-board-chat__close" id="cgBoardChatClose" aria-label="Close board chat"><i class="fas fa-times" aria-hidden="true"></i></button>
    </div>
    <div class="cg-board-chat__online">
        <span class="cg-board-chat__online-label">Online</span>
        <div class="cg-board-chat__online-list" id="cgBoardChatOnlineList"></div>
    </div>
    <div class="cg-board-chat__messages" id="cgBoardChatMessages"></div>
    <div class="cg-board-chat__composer-wrap" id="cgBoardChatComposer">
        <div class="cg-board-chat__typing" id="cgBoardChatTyping" hidden></div>
        <div class="cg-board-chat__drop-hint" id="cgBoardChatDropHint">Drop a card on this area to attach context</div>
        <div class="cg-board-chat__attach-row" id="cgBoardChatAttachRow"></div>
        <div class="cg-board-chat__input-row">
            <textarea class="cg-board-chat__textarea" id="cgBoardChatTextarea" rows="1" placeholder="Type your message..." maxlength="4000" autocomplete="off"></textarea>
            <button type="button" class="cg-board-chat__send" id="cgBoardChatSend" title="Send" aria-label="Send message"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
            <div id="cgBoardChatMentionDropdown" class="cg-board-chat__mention-dropdown" hidden role="listbox" aria-label="Tag collaborator"></div>
        </div>
    </div>
</aside>
<button type="button" id="cgBoardChatFab" class="is-hidden" hidden aria-hidden="true" aria-label="Board chat"></button>
<?php endif; ?>

<!-- Card Modal -->
<div class="modal fade" id="cardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i>Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="row g-0">
          <div class="col-12 col-lg-7 border-bottom border-lg-bottom-0 border-lg-end">
            <form id="cardForm" class="p-3 pb-4">
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
            <div class="col-md-4">
                <label class="form-label fw-semibold">Start date</label>
                <input type="date" class="form-control" id="card_start_date" name="start_date" />
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Due date</label>
                <input type="date" class="form-control" id="card_due_date" name="due_date" />
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Due time</label>
                <input type="time" class="form-control" id="card_due_time" name="due_time" step="60" />
            </div>
        </div>
        <div class="mb-3 mt-3">
            <div class="mb-2">
                <label class="form-label fw-semibold small mb-1" for="card_youtube_url"><i class="fas fa-play-circle text-danger me-1"></i>YouTube (plays on card)</label>
                <input type="url" class="form-control form-control-sm" id="card_youtube_url" placeholder="https://www.youtube.com/watch?v=… or youtu.be/…" autocomplete="off" />
            </div>
            <label class="form-label fw-semibold small mb-1">Links</label>
            <div id="cardLinksContainer" class="mb-2"></div>
            <input type="hidden" name="links" id="card_links_data" value="[]" />
            <input type="hidden" name="existing_attachments" id="card_existing_attachments" value="[]" />
        </div>
        <div class="mt-4 pt-2 border-top border-secondary border-opacity-10">
            <button type="button" class="btn btn-outline-danger" id="deleteCardBtn" style="display: none;">
                <i class="fas fa-trash me-2"></i>Delete Card
            </button>
        </div>
            </form>
          </div>
          <div class="col-12 col-lg-5 cg-card-modal-sidebar d-flex flex-column">
            <div class="cg-card-modal-sidebar-main flex-grow-1 overflow-y-auto min-h-0">
            <div class="p-3 border-bottom border-secondary border-opacity-10">
              <h6 class="fw-semibold small text-uppercase text-muted mb-2">Activity</h6>
              <div id="cardModalActivityLoading" class="small text-muted" style="display:none"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>
              <div id="cardModalActivityEmpty" class="small text-muted" style="display:none">No activity for this card.</div>
              <div id="cardModalActivityList" class="cg-card-modal-activity-list"></div>
            </div>
            <div class="p-3">
              <h6 class="fw-semibold small text-uppercase text-muted mb-2">Comments</h6>
              <div id="cardCommentsLoading" class="small text-muted" style="display:none"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>
              <div id="cardCommentsEmpty" class="small text-muted" style="display:none">No comments yet.</div>
              <div id="cardCommentsThread" class="cg-card-comments-thread"></div>
              <form id="cardCommentForm" class="mt-2">
                <label class="visually-hidden" for="cardCommentBody">Write a comment</label>
                <textarea id="cardCommentBody" class="form-control form-control-sm mb-2" rows="3" placeholder="Write a comment…" maxlength="8000"></textarea>
                <div id="cardCommentFormError" class="small text-danger mb-2" style="display:none"></div>
                <button type="submit" class="btn btn-sm btn-danger" id="cardCommentSubmit"><i class="fas fa-paper-plane me-1"></i>Post</button>
              </form>
            </div>
            <div class="p-3 border-top border-secondary border-opacity-10 cg-card-modal-attachments">
              <h6 class="fw-semibold small text-uppercase text-muted mb-2"><i class="fas fa-paperclip me-1"></i>Attachments</h6>
              <div id="cardAttachmentsContainer" class="mb-2"></div>
              <div id="cardAttachmentDropWrap" class="mb-2">
                <div id="cardAttachmentDropZone" class="card-attachment-dropzone rounded border border-2 border-dashed d-flex flex-column align-items-center justify-content-center py-3 px-3 mb-2">
                    <input type="file" id="cardAttachmentFileInput" name="attachment[]" form="cardForm" accept="image/*" multiple class="d-none" />
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
            </div>
            </div>
            <div class="p-3 border-top border-secondary border-opacity-10 cg-card-modal-footer-actions mt-auto flex-shrink-0">
              <div class="d-flex justify-content-end gap-2 align-items-center flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm p-2" id="cardFooterAddLinkBtn" title="Add link" aria-label="Add link"><i class="fas fa-link"></i></button>
                <button type="submit" form="cardForm" class="btn btn-danger" id="cardFormSaveBtn"><i class="fas fa-save me-2"></i>Save</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Delete confirmation (native window.confirm fails when #cardModal is open — Bootstrap stacked modal) -->
<div class="modal fade" id="deleteCardConfirmModal" tabindex="-1" aria-labelledby="deleteCardConfirmTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="deleteCardConfirmTitle"><i class="fas fa-trash-alt me-2 text-danger"></i>Delete card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="mb-0">Are you sure you want to delete this card? This cannot be undone.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="deleteCardConfirmCancel">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteCardConfirmOk"><i class="fas fa-trash me-2"></i>Delete permanently</button>
      </div>
    </div>
  </div>
</div>

<!-- Delete / leave board confirm (avoid window.confirm when edit panel is open) -->
<div class="modal fade" id="editBoardDangerConfirmModal" tabindex="-1" aria-labelledby="editBoardDangerConfirmTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="editBoardDangerConfirmTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2" id="editBoardDangerConfirmBody"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="editBoardDangerConfirmCancel">Cancel</button>
        <button type="button" class="btn btn-danger" id="editBoardDangerConfirmOk"></button>
      </div>
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
        <input type="hidden" name="template" id="board_template_slug" value="blank" />
        <div class="mb-3">
            <label class="form-label fw-semibold" for="board_template_select">Template</label>
            <select class="form-select" id="board_template_select" aria-label="Board template"></select>
            <div class="form-text">Columns (and optional starter cards) match the template you pick.</div>
        </div>
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

<!-- Edit Board side panel (slides in from right; owner: full settings + delete; collaborator: leave board) -->
<div id="editBoardPanelBackdrop" class="cg-activity-backdrop" aria-hidden="true"></div>
<div id="editBoardPanel" class="cg-activity-panel cg-edit-board-panel" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="editBoardPanelTitle">
    <div class="cg-activity-panel-header">
        <h3 class="cg-activity-panel-title" id="editBoardPanelTitle"><i class="fas fa-edit me-2"></i>Edit Board</h3>
        <button type="button" class="btn btn-link btn-sm p-1 text-dark cg-activity-close" id="editBoardPanelClose" aria-label="Close edit board panel"><i class="fas fa-times"></i></button>
    </div>
    <div class="cg-activity-panel-body">
      <form id="editBoardForm">
        <input type="hidden" id="edit_board_id" />
        <div class="mb-3">
            <label class="form-label fw-semibold">Board name</label>
            <input class="form-control" id="edit_board_name" placeholder="Board name" required />
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Notification emails</label>
            <p>Send these addresses one collective board activity email every 24 hours for card updates, moves, and client timeline comments.</p>
            <div id="editBoardEmailsList" class="mb-2" style="min-height: 24px;"></div>
            <div class="d-flex gap-2 align-items-center edit-board-add-row">
                <div class="position-relative flex-grow-1">
                    <input type="text" autocomplete="off" class="form-control form-control-sm edit-board-add-input" id="editBoardNewEmailInput" placeholder="Type name or email to find CineGrid users…" />
                    <div id="editBoardEmailSuggest" class="cg-user-suggest dropdown-menu show" style="display: none; position: absolute; z-index: 1070; max-height: 220px; overflow-y: auto;"></div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm edit-board-add-btn" id="editBoardAddEmailBtn" disabled aria-disabled="true" title="Notification email add is disabled"><i class="fas fa-plus me-1"></i>Add</button>
            </div>
            <div class="invalid-feedback" id="editBoardEmailError" style="display: none;"></div>
        </div>
        <div class="mb-3" id="editBoardCollaboratorsWrap" style="display: none;">
            <label class="form-label fw-semibold">Collaborators</label>
            <p>Invite others to open and edit this board from their Kanban. They'll see it in their sidebar.</p>
            <div id="editBoardCollaboratorsList" class="mb-2" style="min-height: 24px;"></div>
            <div class="d-flex gap-2 align-items-center edit-board-add-row">
                <div class="position-relative flex-grow-1">
                    <input type="text" autocomplete="off" class="form-control form-control-sm edit-board-add-input" id="editBoardNewCollaboratorInput" placeholder="Enter collaborator email" />
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm edit-board-add-btn" id="editBoardAddCollaboratorBtn"><i class="fas fa-user-plus me-1"></i>Add</button>
            </div>
            <div class="invalid-feedback" id="editBoardCollaboratorError" style="display: none;"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-4 pt-3 border-top edit-board-panel-footer">
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-danger" id="editBoardDeleteBtn" style="display: none;"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i>Delete board</button>
                <button type="button" class="btn btn-outline-danger" id="editBoardLeaveBtn" style="display: none;"><i class="fas fa-right-from-bracket me-1" aria-hidden="true"></i>Leave board</button>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="editBoardPanelCancel">Cancel</button>
                <button type="submit" class="btn btn-danger" id="editBoardSaveBtn"><i class="fas fa-save me-2"></i>Save</button>
            </div>
        </div>
      </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/stats.php'; ?>
<?php require_once __DIR__ . '/includes/kanban_automation_modal.php'; ?>

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

<!-- Assign Card Modal -->
<div class="modal fade" id="assignCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Assign Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="assignCardForm">
        <input type="hidden" id="assign_card_id" value="" />
        <div class="small text-muted mb-3" id="assignCardTitlePreview">Choose collaborators for this card.</div>
        <div class="cg-assign-list" id="assignCardMembersList"></div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fas fa-check me-2"></i>Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="cg-card-context-menu" id="cardContextMenu" aria-hidden="true">
  <div class="cg-card-context-menu__surface shadow-lg">
  <button type="button" class="cg-card-context-menu__item" id="cardContextViewImageBtn" hidden>
    <i class="fas fa-image"></i>
    <span>View Image</span>
  </button>
  <button type="button" class="cg-card-context-menu__item" id="cardContextPinBtn">
    <i class="fas fa-thumbtack"></i>
    <span>Pin to Top</span>
  </button>
  <button type="button" class="cg-card-context-menu__item" id="cardContextAssignBtn">
    <i class="fas fa-user-plus"></i>
    <span>Assign</span>
  </button>
  <button type="button" class="cg-card-context-menu__item" id="cardContextAddToChatBtn">
    <i class="fas fa-comments"></i>
    <span>Add to chat</span>
  </button>
  <button type="button" class="cg-card-context-menu__item" id="cardContextColorBtn">
    <i class="fas fa-palette"></i>
    <span>Change Color</span>
  </button>
  <button type="button" class="cg-card-context-menu__item" id="cardContextMoveToBtn">
    <i class="fas fa-arrow-right"></i>
    <span class="cg-card-context-menu__label">Move to</span>
    <i class="fas fa-chevron-right cg-card-context-menu__subchev" aria-hidden="true"></i>
  </button>
  <button type="button" class="cg-card-context-menu__item" id="cardContextDuplicateToBtn">
    <i class="fas fa-copy"></i>
    <span class="cg-card-context-menu__label">Duplicate to</span>
    <i class="fas fa-chevron-right cg-card-context-menu__subchev" aria-hidden="true"></i>
  </button>
  <button type="button" class="cg-card-context-menu__item cg-card-context-menu__item--danger" id="cardContextDeleteBtn">
    <i class="fas fa-trash"></i>
    <span>Delete</span>
  </button>
  </div>
</div>
<div class="cg-card-color-menu shadow-lg" id="cardColorMenu" aria-hidden="true">
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value=""><span class="cg-card-color-menu__dot cg-card-color-menu__dot--default"></span><span>Default</span></button>
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value="rose"><span class="cg-card-color-menu__dot cg-card-color-menu__dot--rose"></span><span>Rose</span></button>
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value="peach"><span class="cg-card-color-menu__dot cg-card-color-menu__dot--peach"></span><span>Peach</span></button>
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value="mint"><span class="cg-card-color-menu__dot cg-card-color-menu__dot--mint"></span><span>Mint</span></button>
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value="sky"><span class="cg-card-color-menu__dot cg-card-color-menu__dot--sky"></span><span>Sky</span></button>
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value="lavender"><span class="cg-card-color-menu__dot cg-card-color-menu__dot--lavender"></span><span>Lavender</span></button>
  <button type="button" class="cg-card-color-menu__swatch" data-card-color-value="butter"><span class="cg-card-color-menu__dot cg-card-color-menu__dot--butter"></span><span>Butter</span></button>
  <div class="cg-card-color-menu__divider"></div>
  <button type="button" class="cg-card-color-menu__swatch" id="cardContextColorPickerBtn">
    <span class="cg-card-color-menu__dot cg-card-color-menu__dot--picker"><i class="fas fa-eye-dropper"></i></span>
    <span>Color Picker</span>
  </button>
</div>
<div class="cg-card-column-flyout shadow-lg" id="cardColumnFlyout" aria-hidden="true" hidden></div>

<div class="cg-card-color-picker-popup" id="cardColorPickerPopup" aria-hidden="true">
  <div class="cg-card-color-picker-popup__backdrop" id="cardColorPickerBackdrop"></div>
  <div class="cg-card-color-picker-popup__panel" role="dialog" aria-labelledby="cardColorPickerTitle">
    <div class="cg-card-color-picker-popup__title" id="cardColorPickerTitle">Custom color</div>
    <div class="cg-card-color-picker-popup__dial-wrap">
      <canvas id="cardHueDialCanvas" width="168" height="168" class="cg-card-color-picker-popup__dial-canvas"></canvas>
    </div>
    <div class="cg-card-color-picker-popup__sv-wrap" id="cardSvPlaneWrap">
      <div class="cg-card-color-picker-popup__sv-plane" id="cardSvPlane"></div>
      <div class="cg-card-color-picker-popup__sv-cursor" id="cardSvCursor"></div>
    </div>
    <label class="cg-card-color-picker-popup__hex-label" for="cardColorHexInput">Hex</label>
    <div class="cg-card-color-picker-popup__hex-row">
      <input type="text" class="cg-card-color-picker-popup__hex-input" id="cardColorHexInput" maxlength="7" placeholder="#aabbcc" autocomplete="off" spellcheck="false" />
      <span class="cg-card-color-picker-popup__hex-preview" id="cardColorHexPreview" aria-hidden="true"></span>
    </div>
    <div class="cg-card-color-picker-popup__actions">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="cardColorPickerCancel">Cancel</button>
      <button type="button" class="btn btn-sm btn-danger" id="cardColorPickerApply">Apply</button>
    </div>
  </div>
</div>

<!-- SortableJS for drag/drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>

<style>
.cg-kanban-container {
    box-sizing: border-box;
    margin-top: 4px;
}
/* Column chrome + add buttons: Montserrat. Card titles: Inter (loaded with Montserrat in portal / main header). */
.cg-kanban-page .cg-col__title,
.cg-kanban-page .cg-addBtn,
.cg-kanban-page .cg-addColBtn {
    font-family: "Montserrat", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
#kanbanBoardTitle,
#kanbanBoardSubtitle {
    margin-left: 20px;
}
.cg-kanban-header {
    width: 100%;
    max-width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    /* start: long subtitle must not vertically center the actions column (huge empty gap on mobile) */
    align-items: start;
    gap: 1rem;
}
.cg-kanban-header > div:first-child {
    min-width: 0;
    overflow: hidden;
    align-self: start;
}
.cg-kanban-header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
    align-items: center;
    align-content: flex-start;
    align-self: start;
    height: fit-content;
    margin-left: 0;
}
#kanbanBoardSelect {
    min-width: 150px;
    max-width: 210px;
}
.cg-card-search-wrap {
    position: relative;
    min-width: 150px;
    max-width: 210px;
}
#kanbanCardSearchInput {
    min-width: 150px;
    max-width: 210px;
}
.cg-card-search-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    z-index: 1090;
    max-height: 300px;
    overflow-y: auto;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: #ffffff;
    box-shadow: 0 16px 40px -8px rgba(15, 23, 42, 0.18);
    padding: 0.35rem 0;
}
.cg-card-search-dropdown[hidden] {
    display: none !important;
}
.cg-card-search-item {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    display: block;
    padding: 0.5rem 0.8rem;
    line-height: 1.25;
}
.cg-card-search-item:hover,
.cg-card-search-item:focus-visible,
.cg-card-search-item.is-active {
    background: rgba(15, 23, 42, 0.06);
    outline: none;
}
.cg-card-search-item__title {
    display: block;
    font-weight: 600;
    color: #111827;
}
.cg-card-search-item__meta {
    display: block;
    font-size: 0.78rem;
    color: #475569;
    margin-top: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Breathing highlight: keyframes + .cg-card.cg-card--search-breathing live after tone/custom rules (see below) so border wins. */
/* Opacity-only: do not animate transform here — Popper positions the menu with transform on the same element. */
@keyframes cgTemplatesMenuIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.cg-templates-menu__list {
    min-width: 300px;
    max-width: min(100vw - 24px, 420px);
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.09);
    box-shadow:
        0 4px 6px -1px rgba(15, 23, 42, 0.06),
        0 16px 40px -8px rgba(15, 23, 42, 0.14);
    padding: 0.4rem 0;
    will-change: opacity;
}
.cg-templates-menu__list.dropdown-menu.show {
    animation: cgTemplatesMenuIn 0.22s ease-out both;
}

#kanbanTemplatesMenuBtn {
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.cg-templates-menu__list .dropdown-item {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0.5rem 1rem;
    margin: 0;
    border-radius: 0;
    transition: background-color 0.12s ease;
}
.cg-templates-menu__list .dropdown-item:hover {
    background-color: rgba(15, 23, 42, 0.055);
    color: inherit;
}
.cg-templates-menu__list .dropdown-item:focus {
    background-color: rgba(15, 23, 42, 0.055);
    color: inherit;
    outline: none;
}
.cg-templates-menu__list .dropdown-item:focus-visible {
    background-color: rgba(15, 23, 42, 0.055);
    outline: 2px solid rgba(220, 53, 69, 0.35);
    outline-offset: -2px;
}
.cg-templates-menu__list .dropdown-item:active {
    background-color: rgba(15, 23, 42, 0.09);
}
.cg-templates-menu__list li > hr.dropdown-divider {
    margin: 0.2rem 0;
    opacity: 0.65;
}
@media (max-width: 767.98px) {
    .cg-kanban-header {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .cg-kanban-header-actions {
        justify-content: flex-end;
        width: 100%;
        max-width: 100%;
    }
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
.kanban-theme-dark .cg-card__progress-text {
    color: #f8fafc;
}
.kanban-theme-dark .cg-card__title {
    color: #f8fafc !important;
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
.kanban-theme-dark .cg-card--pinned {
    border-color: rgba(251, 191, 36, 0.45);
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
.kanban-theme-dark .cg-card--custom {
    box-shadow: 0 8px 20px rgba(2,6,23,0.30);
}
.kanban-theme-dark .cg-card--custom-hex {
    background: rgba(var(--cg-card-custom-r), var(--cg-card-custom-g), var(--cg-card-custom-b), 0.28) !important;
    border-color: rgba(var(--cg-card-custom-r), var(--cg-card-custom-g), var(--cg-card-custom-b), 0.60) !important;
}
.kanban-theme-dark .cg-card--tone-rose { background: rgba(251, 113, 133, 0.20) !important; border-color: rgba(251, 113, 133, 0.38) !important; }
.kanban-theme-dark .cg-card--tone-peach { background: rgba(251, 146, 60, 0.22) !important; border-color: rgba(251, 146, 60, 0.40) !important; }
.kanban-theme-dark .cg-card--tone-mint { background: rgba(74, 222, 128, 0.18) !important; border-color: rgba(74, 222, 128, 0.36) !important; }
.kanban-theme-dark .cg-card--tone-sky { background: rgba(56, 189, 248, 0.20) !important; border-color: rgba(56, 189, 248, 0.38) !important; }
.kanban-theme-dark .cg-card--tone-lavender { background: rgba(167, 139, 250, 0.22) !important; border-color: rgba(167, 139, 250, 0.40) !important; }
.kanban-theme-dark .cg-card--tone-butter { background: rgba(250, 204, 21, 0.20) !important; border-color: rgba(250, 204, 21, 0.38) !important; }
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
.kanban-theme-dark .cg-card-context-menu__surface,
.kanban-theme-dark .cg-assign-list,
.kanban-theme-dark .cg-card-color-menu,
.kanban-theme-dark .cg-card-column-flyout {
    background: rgba(15, 23, 42, 0.96);
    border-color: rgba(148,163,184,0.18);
}
.kanban-theme-dark .cg-card-column-flyout__item {
    color: #e2e8f0;
}
.kanban-theme-dark .cg-card-column-flyout__item:hover {
    background: rgba(148,163,184,0.12);
}
.kanban-theme-dark .cg-card-context-menu__subchev {
    color: #94a3b8 !important;
}
.kanban-theme-dark .cg-card-context-menu__item {
    color: #e2e8f0;
}
.kanban-theme-dark .cg-card-context-menu__item:hover {
    background: rgba(148,163,184,0.10);
}
.kanban-theme-dark .cg-card-color-menu__swatch {
    color: #e2e8f0;
}
.kanban-theme-dark .cg-card-color-menu__swatch:hover {
    background: rgba(148,163,184,0.10);
}
.kanban-theme-dark .cg-card-color-menu__swatch.is-active {
    background: rgba(248,113,113,0.18);
    color: #f8fafc;
}
.kanban-theme-dark .cg-card-color-menu__divider {
    background: rgba(148,163,184,0.24);
}
.kanban-theme-dark .cg-card-color-menu__dot {
    border-color: rgba(148,163,184,0.40);
}
.kanban-theme-dark .cg-card-color-menu__dot--picker {
    color: #e2e8f0;
}
.kanban-theme-dark .cg-card-context-menu__item i {
    background: rgba(255,255,255,0.08);
    color: #f8fafc;
}
.kanban-theme-dark .cg-card-context-menu__item--danger i {
    background: rgba(248,113,113,0.16);
    color: #fecaca;
}
.kanban-theme-dark .cg-card__assignee-avatar,
.kanban-theme-dark .cg-card__assignee-more {
    border-color: rgba(15, 23, 42, 0.9);
}
.kanban-theme-dark .cg-card__assignee-more {
    background: rgba(51, 65, 85, 0.95);
    color: #f8fafc;
}
.kanban-theme-dark .cg-card__thumb {
    border-color: rgba(148,163,184,0.24);
    background: rgba(15,23,42,0.36);
}
.kanban-theme-dark #assignCardModal .modal-content,
.kanban-theme-dark #assignCardModal .modal-header,
.kanban-theme-dark #assignCardModal .modal-body {
    background: #ffffff !important;
    color: #0f172a !important;
    border-color: rgba(15,23,42,0.08) !important;
}
.kanban-theme-dark #assignCardModal .modal-title,
.kanban-theme-dark #assignCardModal .small,
.kanban-theme-dark #assignCardModal .text-muted,
.kanban-theme-dark #assignCardModal .cg-assign-item__name,
.kanban-theme-dark #assignCardModal .cg-assign-item__role {
    color: #0f172a !important;
}
.kanban-theme-dark #assignCardModal .cg-assign-item__role,
.kanban-theme-dark #assignCardModal .text-muted,
.kanban-theme-dark #assignCardModal .small {
    opacity: 0.7;
}
.kanban-theme-dark #assignCardModal .cg-assign-list {
    background: #ffffff !important;
    border-color: rgba(15,23,42,0.08) !important;
}
.kanban-theme-dark #assignCardModal .cg-assign-item {
    border-bottom-color: rgba(15,23,42,0.08) !important;
}
.kanban-theme-dark #assignCardModal .cg-assign-item__avatar,
.kanban-theme-dark #assignCardModal .cg-assign-item__initials {
    background: #f1f5f9 !important;
    color: #0f172a !important;
}
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
.kanban-theme-dark .cg-activity-panel {
    background: rgba(15,23,42,0.94);
    border-color: rgba(148,163,184,0.18);
    color: #f8fafc;
}
.kanban-theme-dark .cg-activity-panel-header {
    background: rgba(30,41,59,0.96) !important;
    border-bottom-color: rgba(148,163,184,0.16) !important;
}
.kanban-theme-dark .cg-activity-panel-title,
.kanban-theme-dark .cg-activity-close {
    color: #f8fafc !important;
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
.kanban-theme-dark .cg-edit-board-panel .form-control,
.kanban-theme-dark .cg-edit-board-panel .form-select {
    background: rgba(15,23,42,0.55);
    border-color: rgba(148,163,184,0.28);
    color: #e2e8f0;
}
.kanban-theme-dark .cg-edit-board-panel .form-control::placeholder {
    color: #94a3b8;
    opacity: 1;
}
.kanban-theme-dark .cg-edit-board-panel .form-control::-webkit-input-placeholder {
    color: #94a3b8;
    opacity: 1;
}
.kanban-theme-dark .cg-edit-board-panel .form-control::-moz-placeholder {
    color: #94a3b8;
    opacity: 1;
}
.kanban-theme-dark .cg-edit-board-panel .form-label {
    color: #e2e8f0;
}
.kanban-theme-dark .cg-edit-board-panel .text-muted {
    color: #94a3b8 !important;
}
.kanban-theme-dark .cg-edit-board-panel .edit-board-panel-footer {
    border-top-color: rgba(148,163,184,0.16) !important;
}
.kanban-theme-dark .cg-edit-board-panel .bg-light {
    background: rgba(30,41,59,0.85) !important;
    color: #e2e8f0;
}
.kanban-theme-dark #activitiesPanelBtn,
.kanban-theme-dark #themeToggleBtn,
.kanban-theme-dark #newBoardBtn,
.kanban-theme-dark #editBoardBtn,
.kanban-theme-dark #kanbanBoardSelect,
.kanban-theme-dark #kanbanTemplatesMenuBtn {
    color: #e2e8f0;
    border-color: rgba(148,163,184,0.3);
    background: rgba(15,23,42,0.55);
}
.kanban-theme-dark #activitiesPanelBtn:hover,
.kanban-theme-dark #themeToggleBtn:hover,
.kanban-theme-dark #newBoardBtn:hover,
.kanban-theme-dark #editBoardBtn:hover,
.kanban-theme-dark #kanbanBoardSelect:hover,
.kanban-theme-dark #kanbanCardSearchInput:hover,
.kanban-theme-dark #kanbanTemplatesMenuBtn:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}
.kanban-theme-dark #kanbanCardSearchInput {
    color: #e2e8f0;
    border-color: rgba(148,163,184,0.3);
    background: rgba(15,23,42,0.55);
}
.kanban-theme-dark #kanbanCardSearchInput::placeholder {
    color: #94a3b8;
    opacity: 1;
}
.kanban-theme-dark .cg-card-search-dropdown {
    background: rgba(30, 41, 59, 0.98);
    border-color: rgba(148,163,184,0.22);
    box-shadow: 0 18px 44px -8px rgba(2,6,23,0.58);
}
.kanban-theme-dark .cg-card-search-item:hover,
.kanban-theme-dark .cg-card-search-item:focus-visible,
.kanban-theme-dark .cg-card-search-item.is-active {
    background: rgba(255,255,255,0.09);
}
.kanban-theme-dark .cg-card-search-item__title {
    color: #f8fafc;
}
.kanban-theme-dark .cg-card-search-item__meta {
    color: #cbd5e1;
}
.kanban-theme-dark .cg-templates-menu__list {
    background: rgba(30, 41, 59, 0.98);
    border-color: rgba(148, 163, 184, 0.2);
    box-shadow:
        0 4px 6px -1px rgba(2, 6, 23, 0.35),
        0 18px 44px -8px rgba(2, 6, 23, 0.55);
}
.kanban-theme-dark .cg-templates-menu__list .dropdown-item:hover,
.kanban-theme-dark .cg-templates-menu__list .dropdown-item:focus {
    background-color: rgba(255, 255, 255, 0.07);
    color: #f1f5f9;
}
.kanban-theme-dark .cg-templates-menu__list .dropdown-item:focus-visible {
    outline-color: rgba(248, 113, 113, 0.45);
}
.kanban-theme-dark .cg-templates-menu__list .dropdown-item:active {
    background-color: rgba(255, 255, 255, 0.1);
}
.kanban-theme-dark #themeToggleBtn i {
    color: #fff !important;
}

@media (prefers-reduced-motion: reduce) {
    .cg-templates-menu__list.dropdown-menu.show {
        animation: none;
    }
    .cg-card.cg-card--search-breathing {
        animation: none;
        outline: 3px solid rgba(220, 53, 69, 0.9);
        outline-offset: 2px;
        box-shadow: 0 8px 22px rgba(2, 6, 23, 0.12), 0 0 0 6px rgba(220, 53, 69, 0.22);
    }
    .kanban-theme-dark .cg-card.cg-card--search-breathing {
        outline-color: rgba(248, 113, 113, 0.95);
        box-shadow: 0 10px 26px rgba(2, 6, 23, 0.45), 0 0 0 6px rgba(248, 113, 113, 0.28);
    }
    #kanbanTemplatesMenuBtn,
    .cg-templates-menu__list .dropdown-item,
    .cg-card-search-item,
    .cg-card {
        transition: none;
    }
}
/* Board member facepile (Kanban inline; timeline/calendar/workspace use /includes/cg_board_members_ui.css) */
.cg-board-avatar-wrap {
    margin-left: -8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    overflow: hidden;
    border-radius: 50%;
    box-sizing: border-box;
}
.cg-board-avatars .cg-board-avatar-wrap:first-child { margin-left: 0; }
.cg-board-avatars img.cg-board-avatar {
    width: 32px;
    height: 32px;
    max-width: 32px;
    max-height: 32px;
    min-width: 0;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    background: #e2e8f0;
    flex-shrink: 0;
    box-sizing: border-box;
    display: block;
}
.cg-board-avatars .cg-board-avatar-initials {
    width: 32px;
    height: 32px;
    max-width: 32px;
    max-height: 32px;
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
    box-sizing: border-box;
}
/* Full-page Kanban: no freelance sidebar; site header hidden (see body.cg-kanban-fullpage in header) */
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
/* Do not zero .container-fluid globally — it removed portal nav inset and killed .cg-kanban-container horizontal padding (inline lost to !important). */
body.cg-kanban-fullpage .fmain-content.cg-kanban-page {
    margin-left: 0 !important;
    margin-top: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    min-height: 100vh;
    box-sizing: border-box;
}
@media (min-width: 769px) {
    body.cg-kanban-fullpage .fmain-content.cg-kanban-page {
        height: 100vh;
    }
}
body.cg-kanban-fullpage .cg-activity-panel,
body.cg-kanban-fullpage .cg-edit-board-panel {
    top: 0;
    height: 100vh;
}
body.cg-kanban-portal.cg-kanban-fullpage .fmain-content.cg-kanban-page {
    flex: 1 1 auto;
    min-height: 0 !important;
    height: auto !important;
    max-height: none !important;
    box-sizing: border-box;
}
body.cg-kanban-portal.cg-kanban-fullpage .cg-activity-panel,
body.cg-kanban-portal.cg-kanban-fullpage .cg-edit-board-panel {
    top: var(--cg-kanban-portal-header-h, 60px);
    height: calc(100vh - var(--cg-kanban-portal-header-h, 60px));
}
/* Match portal top bar side inset; undo global main strip for this container only */
body.cg-kanban-portal .cg-kanban-container.container-fluid {
    padding-top: 16px !important;
    padding-bottom: 12px !important;
    padding-left: max(1.25rem, env(safe-area-inset-left, 0px)) !important;
    padding-right: max(1.25rem, env(safe-area-inset-right, 0px)) !important;
}
@media (min-width: 576px) {
    body.cg-kanban-portal .cg-kanban-container.container-fluid {
        padding-left: max(1.5rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(1.5rem, env(safe-area-inset-right, 0px)) !important;
    }
}
@media (min-width: 992px) {
    body.cg-kanban-portal .cg-kanban-container.container-fluid {
        padding-left: max(2rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(2rem, env(safe-area-inset-right, 0px)) !important;
    }
}
body.cg-kanban-portal #kanbanBoardTitle,
body.cg-kanban-portal #kanbanBoardSubtitle {
    margin-left: 0;
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
/* Edit Board: wider slide-in panel (same behavior as Activities) */
.cg-edit-board-panel {
    width: min(520px, 96vw);
    max-width: 96vw;
    --cg-edit-board-red: #ff3c3c;
    --cg-edit-board-red-hover: #e62e2e;
}
/* Confirm modal must stack above edit board panel (z-index 1060) */
body:has(#editBoardDangerConfirmModal.show) .modal-backdrop {
    z-index: 1075 !important;
}
body:has(#editBoardDangerConfirmModal.show) #editBoardDangerConfirmModal {
    z-index: 1080 !important;
}
/* Edit Board: readable but tidy typography (~14px body; between default and “tiny”) */
.cg-edit-board-panel .cg-activity-panel-header {
    padding: 0.75rem 1.1rem;
}
.cg-edit-board-panel .cg-activity-panel-title {
    font-size: 1.0625rem;
    font-weight: 600;
}
.cg-edit-board-panel .cg-activity-close {
    font-size: 1.125rem;
}
.cg-edit-board-panel .cg-activity-panel-body {
    padding: 0.85rem 1.1rem;
    font-size: 0.875rem;
    line-height: 1.5;
}
.cg-edit-board-panel .form-label {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
}
.cg-edit-board-panel .form-control:not(.form-control-sm) {
    font-size: 0.875rem;
    padding: 0.4rem 0.65rem;
    line-height: 1.45;
    min-height: 2.35rem;
}
.cg-edit-board-panel .form-control-sm,
.cg-edit-board-panel .btn-sm {
    font-size: 0.875rem;
}
.cg-edit-board-panel p.text-muted.small {
    font-size: 0.8125rem !important;
    line-height: 1.5;
    margin-bottom: 0.55rem !important;
}
.cg-edit-board-panel #editBoardForm > .mb-3 {
    margin-bottom: 0.8rem !important;
}
.cg-edit-board-panel .edit-board-panel-footer {
    margin-top: 1rem !important;
    padding-top: 0.75rem !important;
}
.cg-edit-board-panel .edit-board-panel-footer .btn {
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.4rem 0.85rem;
}
.cg-edit-board-panel .edit-board-panel-footer .btn-danger {
    background-color: var(--cg-edit-board-red);
    border-color: var(--cg-edit-board-red);
    color: #fff;
}
.cg-edit-board-panel .edit-board-panel-footer .btn-danger:hover {
    background-color: var(--cg-edit-board-red-hover);
    border-color: var(--cg-edit-board-red-hover);
    color: #fff;
    filter: none;
}
.cg-edit-board-panel .edit-board-add-row .edit-board-add-input,
.cg-edit-board-panel .edit-board-add-row .edit-board-add-btn {
    height: 36px;
    min-height: 36px;
    font-size: 0.875rem;
}
.cg-edit-board-panel .edit-board-add-row .edit-board-add-input {
    padding: 0.3rem 0.65rem;
}
.cg-edit-board-panel .invalid-feedback {
    font-size: 0.8125rem;
}
/* Above global chat widget (z-index 9999) so Save / footer controls stay clickable */
#editBoardPanelBackdrop.cg-activity-backdrop {
    z-index: 10000 !important;
}
#editBoardPanel.cg-edit-board-panel {
    z-index: 10001 !important;
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

/* Dot grid background on kanban area */
.fmain-content.cg-kanban-page {
    --cg-grid-size: 18px;
    --cg-dot-color: rgba(15, 23, 42, 0.11);
    background-color: #fafbfc;
    background-image: radial-gradient(circle, var(--cg-dot-color) 1.05px, transparent 1.3px);
    background-size: var(--cg-grid-size) var(--cg-grid-size);
    background-position: calc(var(--cg-grid-size) / 2) calc(var(--cg-grid-size) / 2);
}

.cg-kb {
    background: transparent;
    border: none;
    border-radius: 18px;
    padding: 4px 12px 12px;
    margin-top: 0;
    overflow-x: visible;
    width: 100%;
    max-width: 100%;
}
.cg-kb.is-panning {
    cursor: grabbing;
    user-select: none;
}
.cg-kb.is-panning .cg-kb__board {
    cursor: grabbing;
}
.cg-kb.is-panning .cg-card,
.cg-kb.is-panning .cg-addBtn,
.cg-kb.is-panning .cg-addColBtn {
    pointer-events: none;
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
.cg-kb-board-chat-row__board .cg-kb {
    width: max-content;
    min-width: 100%;
    max-width: none;
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
    font-weight: 600;
    color: #0f172a;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cg-col__count {
    font-size: 12px;
    font-weight: 600;
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
    position: relative;
    overflow: visible;
    border: 1px solid rgba(15,23,42,0.10);
    border-radius: 14px;
    background: rgba(255,255,255,0.95);
    padding: 10px 10px 14px;
    cursor: grab;
    transition: transform .12s ease, box-shadow .12s ease;
    box-shadow: 0 6px 14px rgba(2,6,23,0.06);
}
.cg-card--pinned {
    border-color: rgba(245, 158, 11, 0.35);
    box-shadow: 0 8px 18px rgba(245, 158, 11, 0.12);
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
.cg-card--custom {
    background: rgba(255,255,255,0.95);
    box-shadow: 0 8px 18px rgba(15,23,42,0.10);
}
.cg-card--custom-hex {
    background: rgba(var(--cg-card-custom-r), var(--cg-card-custom-g), var(--cg-card-custom-b), 0.22) !important;
    border-color: rgba(var(--cg-card-custom-r), var(--cg-card-custom-g), var(--cg-card-custom-b), 0.52) !important;
}
.cg-card--tone-rose { background: #fce7f3 !important; border-color: #f9a8d4 !important; }
.cg-card--tone-peach { background: #ffedd5 !important; border-color: #fdba74 !important; }
.cg-card--tone-mint { background: #dcfce7 !important; border-color: #86efac !important; }
.cg-card--tone-sky { background: #e0f2fe !important; border-color: #7dd3fc !important; }
.cg-card--tone-lavender { background: #ede9fe !important; border-color: #c4b5fd !important; }
.cg-card--tone-butter { background: #fef9c3 !important; border-color: #fde68a !important; }
/* Search match pulse: placed after tone/custom-hex so border !important wins; no transform (avoids Sortable/hover conflicts). */
@keyframes cgCardSearchBreathing {
    0%, 100% {
        box-shadow:
            0 6px 14px rgba(2, 6, 23, 0.08),
            0 0 0 0 rgba(220, 53, 69, 0.45);
    }
    50% {
        box-shadow:
            0 12px 28px rgba(2, 6, 23, 0.16),
            0 0 0 10px rgba(220, 53, 69, 0.16);
    }
}
@keyframes cgCardSearchBreathingDark {
    0%, 100% {
        box-shadow:
            0 8px 20px rgba(2, 6, 23, 0.28),
            0 0 0 0 rgba(248, 113, 113, 0.4);
    }
    50% {
        box-shadow:
            0 14px 34px rgba(2, 6, 23, 0.38),
            0 0 0 10px rgba(248, 113, 113, 0.22);
    }
}
.cg-card.cg-card--search-breathing {
    animation: cgCardSearchBreathing 1.35s ease-in-out infinite;
    border-color: rgba(220, 53, 69, 0.88) !important;
    transition: none !important;
}
.kanban-theme-dark .cg-card.cg-card--search-breathing {
    animation-name: cgCardSearchBreathingDark;
    border-color: rgba(248, 113, 113, 0.92) !important;
}
.cg-card:active { cursor: grabbing; }
.cg-card + .cg-card { margin-top: 18px; }
.cg-card:hover { transform: translateY(-1px); box-shadow: 0 12px 22px rgba(2,6,23,0.10); }
.cg-card__thumb {
    position: relative;
    width: 100%;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,0.10);
    background: rgba(15,23,42,0.04);
    margin-bottom: 8px;
}
.cg-card__thumb-view {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    color: #334155;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.12);
    text-decoration: none;
    font-size: 0.78rem;
    transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}
.cg-card__thumb-view:hover {
    background: #fff;
    color: #dc2626;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.16);
}
.cg-card__thumb-view:focus-visible {
    outline: 2px solid #dc2626;
    outline-offset: 2px;
}
.kanban-theme-dark .cg-card__thumb-view {
    background: rgba(30, 41, 59, 0.92);
    color: #e2e8f0;
    border-color: rgba(148, 163, 184, 0.25);
    box-shadow: 0 2px 12px rgba(2, 6, 23, 0.45);
}
.kanban-theme-dark .cg-card__thumb-view:hover {
    background: rgba(51, 65, 85, 0.96);
    color: #fca5a5;
}
.cg-card__thumb--youtube {
    aspect-ratio: 16 / 9;
    padding: 0;
    background: #0f172a;
    cursor: pointer;
}
.cg-card__thumb-yt-poster {
    position: absolute;
    inset: 0;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0f172a;
}
.cg-card__thumb-yt-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.cg-card__thumb-yt-play {
    position: relative;
    z-index: 2;
    width: 44px;
    height: 44px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    pointer-events: none;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
}
.cg-card__thumb-yt-slot {
    position: absolute;
    inset: 0;
    z-index: 3;
    background: #000;
}
.cg-card__thumb-yt-slot[hidden] {
    display: none !important;
}
.cg-card__thumb-yt {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    pointer-events: none;
}
.cg-card__thumb img {
    width: 100%;
    height: auto;
    object-fit: contain;
    display: block;
}
.cg-card__thumb--youtube .cg-card__thumb-yt-img {
    object-fit: cover;
    height: 100%;
}
.cg-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
    min-width: 0;
}
/* Match .cg-calendar-chip-title (content calendar) */
.cg-card__title { 
    font-family: "Inter", sans-serif;
    font-size: 0.82rem;
    line-height: 1.25;
    font-weight: 700;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}
.cg-card__pin {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
    font-size: 10px;
    flex-shrink: 0;
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
    font-weight: 600;
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
    min-height: 200px;
    cursor: pointer;
}
#cardModal .card-attachment-dropzone .card-attachment-dropzone-text { color: #dc2626; }
#cardModal .card-attachment-dropzone:hover { background: rgba(220, 38, 38, 0.1); border-color: #dc2626 !important; }

#cardModal .card-new-attachment-remove-btn { border-radius: 0; }

#cardModal .cg-card-modal-sidebar {
    background: #f8fafc;
    min-height: 280px;
}
@media (min-width: 992px) {
    #cardModal .modal-body > .row {
        min-height: min(72vh, 640px);
    }
    #cardModal .cg-card-modal-sidebar {
        max-height: none;
    }
}
#cardModal .cg-card-modal-footer-actions {
    background: rgba(248, 250, 252, 0.95);
}
.kanban-theme-dark #cardModal .cg-card-modal-footer-actions {
    background: rgba(30, 41, 59, 0.95);
}
#cardModal .cg-card-modal-activity-list .cg-card-modal-activity-item {
    padding: 0.35rem 0;
    border-bottom: 1px solid rgba(148, 163, 184, 0.35);
    font-size: 0.8125rem;
}
#cardModal .cg-card-modal-activity-list .cg-card-modal-activity-item:last-child { border-bottom: none; }
#cardModal .cg-card-modal-activity-item .cg-activity-name { font-weight: 600; color: #1e293b; }
#cardModal .cg-card-modal-activity-item .cg-activity-time { font-size: 0.7rem; color: #94a3b8; margin-top: 0.15rem; }
#cardModal .cg-card-comment-item { margin-bottom: 0.75rem; }
#cardModal .cg-card-comment-meta { font-size: 0.7rem; color: #64748b; margin-bottom: 0.2rem; }
#cardModal .cg-card-comment-body { font-size: 0.875rem; white-space: pre-wrap; word-break: break-word; color: #0f172a; }
/* ~3 comments visible; scroll thread only */
#cardModal #cardCommentsThread.cg-card-comments-thread {
    max-height: 9.5rem;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    padding-right: 2px;
    margin-bottom: 0.75rem;
}
#cardModal #cardCommentsThread.cg-card-comments-thread::-webkit-scrollbar {
    width: 6px;
}
#cardModal #cardCommentsThread.cg-card-comments-thread::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.55);
    border-radius: 6px;
}
.kanban-theme-dark #cardModal #cardCommentsThread.cg-card-comments-thread::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
}
.kanban-theme-dark #cardModal .cg-card-modal-sidebar {
    background: #1e293b;
    border-color: rgba(148, 163, 184, 0.2) !important;
}
.kanban-theme-dark #cardModal .cg-card-modal-activity-item .cg-activity-name { color: #f1f5f9; }
.kanban-theme-dark #cardModal .cg-card-modal-activity-item .cg-activity-time,
.kanban-theme-dark #cardModal .cg-card-comment-meta { color: #94a3b8; }
.kanban-theme-dark #cardModal .cg-card-comment-body { color: #e2e8f0; }

#cardModal .cg-card-modal-attachments {
    background: rgba(248, 250, 252, 0.6);
}
#cardModal .cg-card-modal-attachments #cardAttachmentsContainer {
    padding: 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(148, 163, 184, 0.35);
    min-height: 2.5rem;
}
.kanban-theme-dark #cardModal .cg-card-modal-attachments {
    background: rgba(15, 23, 42, 0.35);
}
.kanban-theme-dark #cardModal .cg-card-modal-attachments #cardAttachmentsContainer {
    border-color: rgba(148, 163, 184, 0.25);
}

/* Card modal attachment upload progress */
#cardModal .card-attachment-upload-progress { margin-top: 8px; margin-bottom: 8px; }
#cardModal .card-attachment-upload-progress .upload-progress-bar { height: 8px; background: rgba(15,23,42,0.08); border-radius: 4px; overflow: hidden; }
#cardModal .card-attachment-upload-progress .upload-progress-fill { height: 100%; border-radius: 4px; background: #10b981; transition: width 0.15s ease; }
#cardModal .card-attachment-upload-progress .upload-progress-label { font-size: 12px; color: #64748b; margin-top: 4px; }

.cg-card__meta { margin-top: 6px; font-size: 12px; color: #64748b; display:flex; flex-wrap:wrap; gap:6px; align-items:center;}
.cg-card__assignees {
    position: absolute;
    right: 10px;
    bottom: -12px;
    display: flex;
    align-items: center;
}
.cg-card__assignee-avatar,
.cg-card__assignee-more {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    border: 2px solid rgba(255,255,255,0.95);
    margin-left: -8px;
    box-shadow: 0 4px 12px rgba(2,6,23,0.12);
    object-fit: cover;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cg-card__assignee-avatar:first-child,
.cg-card__assignee-more:first-child {
    margin-left: 0;
}
.cg-card__assignee-avatar.is-initials {
    background: #dbeafe;
    color: #1d4ed8;
}
.cg-card__assignee-more {
    background: #0f172a;
    color: #fff;
}
.cg-card-context-menu {
    position: fixed;
    z-index: 2000;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
    box-shadow: none;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    display: none;
}
.cg-card-context-menu__surface {
    min-width: 220px;
    padding: 8px;
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,0.08);
    background: rgba(255,255,255,0.96);
    box-shadow: 0 18px 40px rgba(15,23,42,0.18);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}
.cg-card-color-menu {
    position: fixed;
    z-index: 2001;
    min-width: 180px;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,0.12);
    background: rgba(255,255,255,0.97);
    display: none;
}
.cg-card-context-menu.is-open {
    display: block;
}
.cg-card-color-menu.is-open {
    display: block;
}
.cg-card-context-menu__item {
    width: 100%;
    border: 0;
    background: transparent;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 12px;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: 0.01em;
    text-align: left;
    transition: background-color 0.14s ease, color 0.14s ease, transform 0.14s ease;
}
.cg-card-context-menu__item i {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(15,23,42,0.06);
    color: #334155;
    font-size: 12px;
    flex-shrink: 0;
}
.cg-card-context-menu__item:hover {
    background: rgba(15,23,42,0.05);
    transform: translateY(-1px);
}
.cg-card-context-menu__item--danger {
    color: #dc2626;
}
.cg-card-context-menu__item--danger:hover {
    background: rgba(220,38,38,0.08);
}
.cg-card-context-menu__item--danger i {
    background: rgba(220,38,38,0.10);
    color: #dc2626;
}
.cg-card-context-menu__label {
    flex: 1 1 auto;
    min-width: 0;
    text-align: left;
}
.cg-card-context-menu__subchev {
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    padding: 0 !important;
    margin-left: 6px;
    font-size: 10px;
    opacity: 0.55;
    background: transparent !important;
    color: #64748b !important;
    flex-shrink: 0;
}
.cg-card-column-flyout {
    position: fixed;
    z-index: 2002;
    min-width: 200px;
    max-width: min(320px, 92vw);
    max-height: min(70vh, 420px);
    overflow-y: auto;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,0.12);
    background: rgba(255,255,255,0.97);
    display: none;
    box-shadow: 0 18px 40px rgba(15,23,42,0.2);
}
.cg-card-column-flyout.is-open {
    display: block;
}
.cg-card-column-flyout__item {
    width: 100%;
    border: 0;
    background: transparent;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    padding: 9px 10px;
    text-align: left;
    display: block;
    cursor: pointer;
}
.cg-card-column-flyout__item:hover {
    background: rgba(15,23,42,0.06);
}
.cg-card-color-menu__swatch {
    width: 100%;
    border: 0;
    background: transparent;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
}
.cg-card-color-menu__swatch:hover {
    background: rgba(15,23,42,0.05);
}
.cg-card-color-menu__swatch.is-active {
    background: rgba(224,49,49,0.08);
}
.cg-card-color-menu__divider {
    height: 1px;
    margin: 6px 4px;
    background: rgba(15,23,42,0.10);
}
.cg-card-color-menu__dot {
    width: 14px;
    height: 14px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,0.16);
    flex-shrink: 0;
}
.cg-card-color-menu__dot--default { background: linear-gradient(135deg, #f8fafc, #dbeafe); }
.cg-card-color-menu__dot--rose { background: #fbcfe8; }
.cg-card-color-menu__dot--peach { background: #fed7aa; }
.cg-card-color-menu__dot--mint { background: #bbf7d0; }
.cg-card-color-menu__dot--sky { background: #bae6fd; }
.cg-card-color-menu__dot--lavender { background: #ddd6fe; }
.cg-card-color-menu__dot--butter { background: #fef08a; }
.cg-card-color-menu__dot--picker {
    background: linear-gradient(135deg, #fbcfe8, #bfdbfe);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #334155;
    font-size: 9px;
}
.cg-card-color-picker-popup {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.cg-card-color-picker-popup.is-open {
    display: flex;
}
.cg-card-color-picker-popup__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
}
.cg-card-color-picker-popup__panel {
    position: relative;
    width: 100%;
    max-width: 280px;
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,0.12);
    background: #fff;
    box-shadow: 0 24px 48px rgba(2,6,23,0.22);
    padding: 16px 16px 14px;
    z-index: 1;
}
.cg-card-color-picker-popup__title {
    font-weight: 800;
    font-size: 14px;
    color: #0f172a;
    margin-bottom: 12px;
}
.cg-card-color-picker-popup__dial-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 12px;
}
.cg-card-color-picker-popup__dial-canvas {
    display: block;
    border-radius: 50%;
    cursor: crosshair;
    touch-action: none;
}
.cg-card-color-picker-popup__sv-wrap {
    position: relative;
    width: 100%;
    height: 112px;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
    border: 1px solid rgba(15,23,42,0.12);
    cursor: crosshair;
    touch-action: none;
}
.cg-card-color-picker-popup__sv-plane {
    position: absolute;
    inset: 0;
}
.cg-card-color-picker-popup__sv-cursor {
    position: absolute;
    width: 14px;
    height: 14px;
    border: 2px solid #fff;
    border-radius: 50%;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.35);
    pointer-events: none;
    transform: translate(-50%, -50%);
    margin-left: 0;
    margin-top: 0;
}
.cg-card-color-picker-popup__hex-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    display: block;
    margin-bottom: 4px;
}
.cg-card-color-picker-popup__hex-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
}
.cg-card-color-picker-popup__hex-input {
    flex: 1;
    min-width: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid rgba(15,23,42,0.14);
    background: #f8fafc;
    color: #0f172a;
}
.cg-card-color-picker-popup__hex-input:focus {
    outline: none;
    border-color: rgba(224,49,49,0.45);
    box-shadow: 0 0 0 3px rgba(224,49,49,0.12);
}
.cg-card-color-picker-popup__hex-preview {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid rgba(15,23,42,0.12);
    flex-shrink: 0;
}
.cg-card-color-picker-popup__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.kanban-theme-dark .cg-card-color-picker-popup__panel {
    background: rgba(30, 41, 59, 0.98);
    border-color: rgba(148,163,184,0.22);
    box-shadow: 0 24px 48px rgba(0,0,0,0.45);
}
.kanban-theme-dark .cg-card-color-picker-popup__title {
    color: #f8fafc;
}
.kanban-theme-dark .cg-card-color-picker-popup__hex-label {
    color: #94a3b8;
}
.kanban-theme-dark .cg-card-color-picker-popup__hex-input {
    background: rgba(15,23,42,0.65);
    border-color: rgba(148,163,184,0.25);
    color: #f8fafc;
}
.kanban-theme-dark .cg-card-color-picker-popup__sv-wrap {
    border-color: rgba(148,163,184,0.22);
}
.cg-assign-list {
    max-height: 320px;
    overflow: auto;
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 14px;
    background: rgba(255,255,255,0.88);
}
.cg-assign-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(15,23,42,0.08);
}
.cg-assign-item:last-child {
    border-bottom: none;
}
.cg-assign-item__avatar,
.cg-assign-item__initials {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e2e8f0;
    font-weight: 800;
    color: #0f172a;
}
.cg-assign-item__meta {
    flex: 1;
    min-width: 0;
}
.cg-assign-item__name {
    font-weight: 700;
    color: #0f172a;
}
.cg-assign-item__role {
    font-size: 12px;
    color: #64748b;
}
.cg-pill { font-weight: 600; font-size: 11px; padding: 4px 8px; border-radius: 999px; background: rgba(99,102,241,0.10); border:1px solid rgba(99,102,241,0.18); color:#4338ca; }
.cg-pill--date { background: rgba(16,185,129,0.10); border-color: rgba(16,185,129,0.18); color:#047857; }
.cg-pill--date-past { background: rgba(220,38,38,0.12); border-color: rgba(220,38,38,0.25); color: #b91c1c; }
.cg-pill--date-soon { background: rgba(234,88,12,0.12); border-color: rgba(234,88,12,0.25); color: #c2410c; }
.cg-pill--date-mid { background: rgba(37,99,235,0.12); border-color: rgba(37,99,235,0.25); color: #1d4ed8; }
.cg-pill--date-far { background: rgba(16,185,129,0.10); border-color: rgba(16,185,129,0.18); color: #047857; }
.cg-priority-badge {
    font-weight: 700;
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
    font-weight: 600;
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
    font-weight: 600;
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
    font-weight: 600;
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
</style>

<script>
const CG_FK_API = <?php echo json_encode($cg_freelance_kanban_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_FT_API = <?php echo json_encode($cg_freelance_timeline_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_STATIC_ORIGIN = <?php echo json_encode($cg_kanban_static_origin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_PROFILE_PIC_URL = <?php echo json_encode($cg_kanban_profile_pic_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_IMAGE_PREVIEW_URL = <?php echo json_encode($cg_kanban_image_preview_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_BOARD_TEMPLATES = <?php
$tplFlags = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $tplFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
echo json_encode($cg_kanban_board_templates_list, $tplFlags | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

function cgKanbanResolvePublicUrl(path) {
  if (path == null || path === '') return path;
  const p = String(path).trim();
  if (!p) return p;
  if (/^https?:\/\//i.test(p)) {
    if (CG_KANBAN_STATIC_ORIGIN && /^https?:\/\/(www\.)?cinegrid\.net\/uploads\//i.test(p)) {
      try {
        const u = new URL(p);
        const portal = new URL(CG_KANBAN_STATIC_ORIGIN);
        return portal.origin + u.pathname + u.search + u.hash;
      } catch (e) {
        return p;
      }
    }
    return p;
  }
  const rel = p.startsWith('/') ? p : '/' + p;
  return CG_KANBAN_STATIC_ORIGIN ? CG_KANBAN_STATIC_ORIGIN + rel : rel;
}

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

/** When unchanged, renderBoard skips replacing #kanbanRoot (avoids thumbnail flicker on poll). */
let _kanbanLastGridSig = null;
let KB_CARD_SEARCH_INDEX = [];
let KB_CARD_SEARCH_RESULTS = [];
let KB_CARD_SEARCH_ACTIVE_INDEX = -1;
let KB_ACTIVE_BREATHING_IDS = new Set();

function syncBoardChatMembersToWindow() {
  window.CG_BOARD_CHAT_MEMBERS = Array.isArray(KB.board_members) ? KB.board_members : [];
}
syncBoardChatMembersToWindow();

function kanbanBoardGridSignature() {
  try {
    const cols = KB.columns || [];
    const cardsBy = KB.cardsByColumn || {};
    return JSON.stringify(cols.map((c) => {
      const cards = cardsBy[c.id] || [];
      return [
        c.id,
        c.name || '',
        cards.map((card) => [
          card.id,
          card.column_id,
          card.title,
          card.start_date,
          card.due_date,
          card.due_time,
          card.progress,
          card.priority,
          card.is_pinned,
          card.attachments,
          card.card_color,
          card.assignees,
        ]),
      ];
    }));
  } catch (e) {
    return 'err-' + Date.now();
  }
}

const KANBAN_THEME_STORAGE_KEY = 'kanban_theme_mode';
let pendingPastedCardData = null;
let activeCardContextMenuId = null;
const CARD_COLOR_TONES = new Set(['rose', 'peach', 'mint', 'sky', 'lavender', 'butter']);

function qs(sel, root=document){ return root.querySelector(sel); }
function qsa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

let deleteCardConfirmOnConfirm = null;
let deleteCardConfirmUsedOk = false;

function showDeleteCardConfirmModal(onConfirm) {
  const modalEl = qs('#deleteCardConfirmModal');
  if (typeof onConfirm !== 'function') return;
  if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
    if (window.confirm('Are you sure you want to delete this card? This action cannot be undone.')) {
      onConfirm();
    }
    return;
  }
  deleteCardConfirmUsedOk = false;
  deleteCardConfirmOnConfirm = onConfirm;
  const inst = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
  inst.show();
}

function wireDeleteCardConfirmModal() {
  const modalEl = qs('#deleteCardConfirmModal');
  const okBtn = qs('#deleteCardConfirmOk');
  if (!modalEl || !okBtn || okBtn.dataset.cgDeleteConfirmWired) return;
  okBtn.dataset.cgDeleteConfirmWired = '1';
  okBtn.addEventListener('click', () => {
    deleteCardConfirmUsedOk = true;
    const cb = deleteCardConfirmOnConfirm;
    deleteCardConfirmOnConfirm = null;
    const inst = bootstrap.Modal.getInstance(modalEl);
    if (inst) inst.hide();
    try {
      if (typeof cb === 'function') cb();
    } catch (err) {
      console.error('[Kanban] delete confirm callback:', err);
    }
  });
  modalEl.addEventListener('hidden.bs.modal', () => {
    if (!deleteCardConfirmUsedOk) {
      deleteCardConfirmOnConfirm = null;
    }
    deleteCardConfirmUsedOk = false;
  });
}
wireDeleteCardConfirmModal();

let editBoardDangerOnConfirm = null;
let editBoardDangerUsedOk = false;

function showEditBoardDangerConfirmModal(opts) {
  const modalEl = qs('#editBoardDangerConfirmModal');
  const titleEl = qs('#editBoardDangerConfirmTitle');
  const bodyEl = qs('#editBoardDangerConfirmBody');
  const okBtn = qs('#editBoardDangerConfirmOk');
  if (!opts || typeof opts.onConfirm !== 'function') return;
  if (!modalEl || !titleEl || !bodyEl || !okBtn || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
    if (window.confirm(opts.bodyText || 'Continue?')) opts.onConfirm();
    return;
  }
  titleEl.innerHTML = opts.titleHtml || '';
  bodyEl.textContent = opts.bodyText || '';
  okBtn.innerHTML = opts.okHtml || 'OK';
  okBtn.className = opts.okBtnClass || 'btn btn-danger';
  editBoardDangerUsedOk = false;
  editBoardDangerOnConfirm = opts.onConfirm;
  const inst = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
  closeEditBoardPanel();
  inst.show();
}

function wireEditBoardDangerConfirmModal() {
  const modalEl = qs('#editBoardDangerConfirmModal');
  const okBtn = qs('#editBoardDangerConfirmOk');
  if (!modalEl || !okBtn || okBtn.dataset.cgEditBoardDangerWired) return;
  okBtn.dataset.cgEditBoardDangerWired = '1';
  okBtn.addEventListener('click', () => {
    editBoardDangerUsedOk = true;
    const cb = editBoardDangerOnConfirm;
    editBoardDangerOnConfirm = null;
    const inst = bootstrap.Modal.getInstance(modalEl);
    if (inst) inst.hide();
    try {
      if (typeof cb === 'function') cb();
    } catch (err) {
      console.error('[Kanban] edit board danger confirm:', err);
    }
  });
  modalEl.addEventListener('hidden.bs.modal', () => {
    if (!editBoardDangerUsedOk) {
      editBoardDangerOnConfirm = null;
      reopenEditBoardPanelVisually();
    }
    editBoardDangerUsedOk = false;
  });
}
wireEditBoardDangerConfirmModal();

function applyEditBoardPanelRoleUI() {
  const isOwner = !!KB.is_board_owner;
  const nameInput = qs('#edit_board_name');
  const saveBtn = qs('#editBoardSaveBtn');
  const delBtn = qs('#editBoardDeleteBtn');
  const leaveBtn = qs('#editBoardLeaveBtn');
  if (nameInput) {
    nameInput.readOnly = !isOwner;
    nameInput.classList.toggle('bg-light', !isOwner);
    nameInput.classList.toggle('text-muted', !isOwner);
  }
  if (saveBtn) saveBtn.style.display = isOwner ? '' : 'none';
  if (delBtn) delBtn.style.display = isOwner ? '' : 'none';
  if (leaveBtn) leaveBtn.style.display = !isOwner && KB.board_id ? '' : 'none';
}

function applyKanbanTheme(mode) {
  const isDark = mode === 'dark';
  document.body.classList.toggle('kanban-theme-dark', isDark);
  const statsModal = document.getElementById('kanbanStatsModal');
  if (statsModal) {
    statsModal.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
  }
  const automationModal = document.getElementById('kanbanAutomationModal');
  if (automationModal) {
    automationModal.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
  }
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
  // Only block when the paste targets a *visible* modal. Hidden modals kept in DOM no longer break board paste.
  if (target.closest('.modal.show')) return true;
  // Do not block .cg-card: focus often stays on the card surface; board-level paste should still work.
  return false;
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
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
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

function formatTimeOnCard(t) {
  if (t == null || String(t).trim() === '') return '';
  const m = String(t).trim().match(/^(\d{1,2}):(\d{2})/);
  if (!m) return '';
  let h = parseInt(m[1], 10);
  const min = m[2];
  const am = h < 12;
  const h12 = h % 12 || 12;
  return ` ${h12}:${min} ${am ? 'AM' : 'PM'}`;
}

/** MySQL TIME to HH:MM for input type="time". */
function cardTimeToInputValue(t) {
  if (t == null || String(t).trim() === '') return '';
  const s = String(t).trim();
  return s.length >= 5 ? s.slice(0, 5) : s;
}

function formatDateRange(s, e, dueTime) {
  if (!s && !e) return null;
  const today = new Date();
  const dt = formatTimeOnCard(dueTime);
  if (s && e) {
    const startLabel = formatOneDate(s, today);
    const endLabel = formatOneDate(e, today) + dt;
    if (startLabel === endLabel) {
      return endLabel;
    }
    return `${startLabel} → ${endLabel}`;
  }
  if (s) return 'Start: ' + formatOneDate(s, today);
  return 'Due: ' + formatOneDate(e, today) + dt;
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
  const board = getCurrentBoardMeta();
  const boardName = board && board.name ? board.name : 'Untitled Board';
  const range = getBoardDateRange();

  if (boardTitleEl) boardTitleEl.textContent = boardName;
  if (subtitleEl) {
    subtitleEl.textContent =
      'Start Date: ' + formatBoardSummaryDate(range.minDate) +
      ' | End Date: ' + formatBoardSummaryDate(range.maxDate);
  }
  if (typeof cgSetBoardTabTitle === 'function') {
    cgSetBoardTabTitle(boardName, 'Kanban');
  } else {
    var _h = (window.location.hostname || '').replace(/^www\./i, '').toLowerCase();
    if (_h === 'kanban.cinegrid.net') {
      document.title = boardName + ' - Kanban';
    }
  }
}

function renderKanbanBoardSelect() {
  const selectEl = qs('#kanbanBoardSelect');
  if (!selectEl) return;

  const boards = KB.boards || [];
  const currentBoardId = parseInt(KB.board_id, 10) || 0;

  if (!boards.length) {
    selectEl.innerHTML = '<option value="">No boards</option>';
    selectEl.disabled = true;
    return;
  }

  selectEl.disabled = false;
  selectEl.innerHTML = boards.map(function(board) {
    const boardId = parseInt(board.id, 10) || 0;
    const selected = boardId === currentBoardId ? ' selected' : '';
    return '<option value="' + boardId + '"' + selected + '>' + escapeHtml(board.name || 'Untitled Board') + '</option>';
  }).join('');
}

function cgNormalizeSearchText(value) {
  return String(value == null ? '' : value)
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

function cgCardSearchTokens(query) {
  const normalized = cgNormalizeSearchText(query);
  return normalized ? normalized.split(' ') : [];
}

function cgCollectCardVisibleSearchText(card) {
  const assignees = Array.isArray(card.assignees) ? card.assignees.map(function(a) {
    return a && (a.name || a.full_name || a.email || '');
  }).filter(Boolean).join(' ') : '';
  const dateRange = formatDateRange(card.start_date, card.due_date, card.due_time) || 'No dates';
  const progress = parseInt(card.progress || 0, 10);
  return [
    card.title || '',
    card.description || '',
    card.context || '',
    card.priority || '',
    assignees,
    dateRange,
    isNaN(progress) ? '' : String(progress) + '%'
  ].join(' ');
}

function rebuildCardSearchIndex() {
  const index = [];
  const cols = KB.columns || [];
  const cardsBy = KB.cardsByColumn || {};
  cols.forEach(function(col) {
    const cards = cardsBy[col.id] || [];
    cards.forEach(function(card) {
      const title = String(card.title || '').trim();
      const description = String(card.description || '').trim();
      const visibleText = cgCollectCardVisibleSearchText(card);
      index.push({
        cardId: parseInt(card.id, 10) || 0,
        columnName: col && col.name ? String(col.name) : 'Column',
        title: title || 'Untitled',
        description: description,
        searchableText: cgNormalizeSearchText(visibleText),
        titleNorm: cgNormalizeSearchText(title),
        descNorm: cgNormalizeSearchText(description),
      });
    });
  });
  KB_CARD_SEARCH_INDEX = index;
}

function searchCardsByKeywords(query) {
  const tokens = cgCardSearchTokens(query);
  if (!tokens.length) return [];
  return KB_CARD_SEARCH_INDEX.filter(function(item) {
    return tokens.every(function(token) { return item.searchableText.includes(token); });
  });
}

function getCardSearchElements() {
  return {
    wrap: qs('#kanbanCardSearchWrap'),
    input: qs('#kanbanCardSearchInput'),
    dropdown: qs('#kanbanCardSearchDropdown')
  };
}

function hideCardSearchDropdown() {
  const els = getCardSearchElements();
  if (!els.dropdown) return;
  els.dropdown.hidden = true;
  els.dropdown.innerHTML = '';
  KB_CARD_SEARCH_RESULTS = [];
  KB_CARD_SEARCH_ACTIVE_INDEX = -1;
}

function renderCardSearchDropdown(results) {
  const els = getCardSearchElements();
  if (!els.dropdown) return;
  KB_CARD_SEARCH_RESULTS = results.slice(0, 24);
  KB_CARD_SEARCH_ACTIVE_INDEX = KB_CARD_SEARCH_RESULTS.length ? 0 : -1;
  if (!KB_CARD_SEARCH_RESULTS.length) {
    els.dropdown.innerHTML = '<div class="cg-card-search-item__meta px-3 py-2">No matching cards</div>';
    els.dropdown.hidden = false;
    return;
  }
  els.dropdown.innerHTML = KB_CARD_SEARCH_RESULTS.map(function(item, idx) {
    const activeClass = idx === KB_CARD_SEARCH_ACTIVE_INDEX ? ' is-active' : '';
    const meta = item.columnName + (item.description ? ' - ' + item.description : '');
    return '<button type="button" class="cg-card-search-item' + activeClass + '" data-search-index="' + idx + '">' +
      '<span class="cg-card-search-item__title">' + escapeHtml(capitalizeWords(item.title)) + '</span>' +
      '<span class="cg-card-search-item__meta">' + escapeHtml(meta) + '</span>' +
    '</button>';
  }).join('');
  els.dropdown.hidden = false;
}

function updateCardSearchDropdown() {
  const els = getCardSearchElements();
  if (!els.input) return;
  const query = els.input.value || '';
  const matches = searchCardsByKeywords(query);
  if (!query.trim()) {
    hideCardSearchDropdown();
    return;
  }
  renderCardSearchDropdown(matches);
}

function clearCardSearchBreathingFocus() {
  qsa('.cg-card.cg-card--search-breathing').forEach(function(cardEl) {
    cardEl.classList.remove('cg-card--search-breathing');
  });
  KB_ACTIVE_BREATHING_IDS = new Set();
}

function getGroupedCardIdsForResult(selectedResult) {
  if (!selectedResult) return [];
  const titleNorm = selectedResult.titleNorm;
  const descNorm = selectedResult.descNorm;
  let grouped = KB_CARD_SEARCH_INDEX.filter(function(item) {
    const titleMatch = titleNorm && item.titleNorm && item.titleNorm === titleNorm;
    const descMatch = descNorm && item.descNorm && item.descNorm === descNorm;
    return titleMatch || descMatch;
  }).map(function(item) { return item.cardId; });
  if (!grouped.length && selectedResult.cardId) grouped = [selectedResult.cardId];
  return Array.from(new Set(grouped.filter(Boolean)));
}

function applyCardSearchBreathingFocus(cardIds) {
  clearCardSearchBreathingFocus();
  const els = getCardSearchElements();
  if (els.input) els.input.value = '';
  const uniqueIds = Array.from(new Set((cardIds || []).map(function(v) { return parseInt(v, 10) || 0; }).filter(Boolean)));
  uniqueIds.forEach(function(cardId) {
    const cardEl = qs('.cg-card[data-card-id="' + cardId + '"]');
    if (!cardEl) return;
    cardEl.classList.add('cg-card--search-breathing');
    KB_ACTIVE_BREATHING_IDS.add(cardId);
  });
  if (uniqueIds.length === 1) {
    const singleCardEl = qs('.cg-card[data-card-id="' + uniqueIds[0] + '"]');
    if (singleCardEl) {
      singleCardEl.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    }
  }
}

/** Board chat card chips: same highlight + scroll as picking a card from search. */
window.cgKanbanRevealCardLikeSearch = function(cardId) {
  const id = parseInt(cardId, 10) || 0;
  if (!id) return;
  applyCardSearchBreathingFocus([id]);
};

function selectCardSearchResultByIndex(index) {
  const picked = KB_CARD_SEARCH_RESULTS[index];
  if (!picked) return;
  const groupedIds = getGroupedCardIdsForResult(picked);
  applyCardSearchBreathingFocus(groupedIds);
  const els = getCardSearchElements();
  if (els.input) els.input.value = '';
  hideCardSearchDropdown();
}

function refreshSearchDropdownActiveItem() {
  const els = getCardSearchElements();
  if (!els.dropdown) return;
  qsa('.cg-card-search-item', els.dropdown).forEach(function(btn, idx) {
    btn.classList.toggle('is-active', idx === KB_CARD_SEARCH_ACTIVE_INDEX);
  });
}

function syncActiveBreathingCardsAfterRender() {
  if (!KB_ACTIVE_BREATHING_IDS.size) return;
  const ids = Array.from(KB_ACTIVE_BREATHING_IDS);
  KB_ACTIVE_BREATHING_IDS = new Set();
  ids.forEach(function(cardId) {
    const cardEl = qs('.cg-card[data-card-id="' + cardId + '"]');
    if (!cardEl) return;
    cardEl.classList.add('cg-card--search-breathing');
    KB_ACTIVE_BREATHING_IDS.add(cardId);
  });
}

function initCardSearchUI() {
  const els = getCardSearchElements();
  if (!els.input || !els.dropdown || els.input.dataset.cgSearchInit === '1') return;
  els.input.dataset.cgSearchInit = '1';

  els.input.addEventListener('input', function() {
    updateCardSearchDropdown();
  });

  els.input.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      hideCardSearchDropdown();
      return;
    }
    if (!KB_CARD_SEARCH_RESULTS.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      KB_CARD_SEARCH_ACTIVE_INDEX = Math.min(KB_CARD_SEARCH_ACTIVE_INDEX + 1, KB_CARD_SEARCH_RESULTS.length - 1);
      refreshSearchDropdownActiveItem();
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      KB_CARD_SEARCH_ACTIVE_INDEX = Math.max(KB_CARD_SEARCH_ACTIVE_INDEX - 1, 0);
      refreshSearchDropdownActiveItem();
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      const idx = KB_CARD_SEARCH_ACTIVE_INDEX >= 0 ? KB_CARD_SEARCH_ACTIVE_INDEX : 0;
      selectCardSearchResultByIndex(idx);
    }
  });

  els.dropdown.addEventListener('click', function(e) {
    const btn = e.target.closest('.cg-card-search-item');
    if (!btn) return;
    const idx = parseInt(btn.getAttribute('data-search-index') || '-1', 10);
    if (idx >= 0) {
      selectCardSearchResultByIndex(idx);
    }
  });
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

const KB_PRIORITY_RANK = { P0: 0, P1: 1, P2: 2, P3: 3 };

/** When any card in a column has priority, order pinned first, then P0→P3, then unset/unknown; stable within ties. Columns with no priorities stay in API order. */
function applyPrioritySortToCardsByColumn(cardsByColumn) {
  if (!cardsByColumn || typeof cardsByColumn !== 'object') return;
  Object.keys(cardsByColumn).forEach(colId => {
    const cards = cardsByColumn[colId];
    if (!Array.isArray(cards) || cards.length < 2) return;
    const hasAnyPriority = cards.some(c => String(c.priority || '').trim() !== '');
    if (!hasAnyPriority) return;
    cardsByColumn[colId] = cards
      .map((c, i) => ({ c, i }))
      .sort((a, b) => {
        const pinA = parseInt(a.c.is_pinned || 0, 10) ? 1 : 0;
        const pinB = parseInt(b.c.is_pinned || 0, 10) ? 1 : 0;
        if (pinA !== pinB) return pinB - pinA;
        const pa = String(a.c.priority || '').trim();
        const pb = String(b.c.priority || '').trim();
        const ra = Object.prototype.hasOwnProperty.call(KB_PRIORITY_RANK, pa) ? KB_PRIORITY_RANK[pa] : 999;
        const rb = Object.prototype.hasOwnProperty.call(KB_PRIORITY_RANK, pb) ? KB_PRIORITY_RANK[pb] : 999;
        if (ra !== rb) return ra - rb;
        return a.i - b.i;
      })
      .map(x => x.c);
  });
}

function renderBoard(opts) {
  const force = opts && opts.force === true;
  const gridSig = kanbanBoardGridSignature();
  if (!force && _kanbanLastGridSig === gridSig) {
    renderBoardMembersAvatars();
    renderBoardSubtitle();
    renderKanbanBoardSelect();
    rebuildCardSearchIndex();
    syncActiveBreathingCardsAfterRender();
    updateBoardOwnerOnlyUI();
    return;
  }
  _kanbanLastGridSig = gridSig;

  const root = qs('#kanbanRoot');
  const cols = KB.columns || [];
  const cardsBy = KB.cardsByColumn || {};

  if (window.__cgYtActiveThumbWrap) {
    cgYtStopCardThumbPlayback(window.__cgYtActiveThumbWrap);
  }

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
                const dateRange = formatDateRange(card.start_date, card.due_date, card.due_time);
                const datePillClass = getDatePillClass(card.due_date || card.start_date);
                const progress = Math.max(0, Math.min(100, parseInt(card.progress || 0, 10)));
                const progressBarClass = progress >= 100 ? 'cg-card__progress-bar--done' : (progress > 50 ? 'cg-card__progress-bar--mid' : 'cg-card__progress-bar--low');
                const customToneClass = getCardToneClass(card);
                const customHexClass = getCardCustomHexClass(card);
                const customStyle = getCardCustomStyle(card);
                const progressCardClass = (customToneClass || customHexClass) ? '' : (progress > 0 ? (progress >= 100 ? 'cg-card--done' : (progress > 50 ? 'cg-card--mid' : 'cg-card--low')) : '');
                const pinnedCardClass = parseInt(card.is_pinned || 0, 10) ? 'cg-card--pinned' : '';
                const priority = card.priority || '';
                const priorityClass = priority ? `cg-priority-${priority.toLowerCase()}` : '';
                const priorityLabel = priority || '';
                const assigneesHtml = renderCardAssigneesHtml(card.assignees || []);
                const boardThumbHtml = renderCardBoardThumbHtml(card);
                return `
                  <div class="cg-card ${progressCardClass} ${pinnedCardClass} ${customToneClass} ${customHexClass}" style="${customStyle}" data-card-id="${card.id}" draggable="true" onclick="openCard(${card.id})">
                    ${boardThumbHtml}
                    <div class="cg-card__header">
                      ${parseInt(card.is_pinned || 0, 10) ? `<span class="cg-card__pin" title="Pinned"><i class="fas fa-thumbtack"></i></span>` : ''}
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
                    ${assigneesHtml}
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
  renderKanbanBoardSelect();
  rebuildCardSearchIndex();
  syncActiveBreathingCardsAfterRender();
  updateBoardOwnerOnlyUI();
  initYoutubeCardThumbs();
}

function boardMemberPicUrl(profilePic) {
  if (!profilePic || !String(profilePic).trim()) return null;
  const p = String(profilePic).trim();
  if (/^https?:\/\//i.test(p)) {
    try {
      const u = new URL(p);
      if (!/(\.|^)cinegrid\.net$/i.test(u.hostname) && !/(\.|^)kanban\.cinegrid\.net$/i.test(u.hostname)) {
        return p;
      }
      const file = u.pathname.split('/').filter(Boolean).pop() || '';
      return file ? `${CG_KANBAN_PROFILE_PIC_URL}?file=${encodeURIComponent(file)}` : p;
    } catch (err) {
      return p;
    }
  }
  const file = p.split(/[\\/]/).filter(Boolean).pop() || '';
  return file ? `${CG_KANBAN_PROFILE_PIC_URL}?file=${encodeURIComponent(file)}` : null;
}

function getInitials(name) {
  if (!name || !String(name).trim()) return '?';
  return String(name).trim().split(/\s+/).map(w => w.charAt(0)).slice(0, 2).join('').toUpperCase();
}
function findColumnById(columnId) {
  return (KB.columns || []).find(col => parseInt(col.id, 10) === parseInt(columnId, 10)) || null;
}
function buildWorkspaceCardDragPayload(cardId) {
  const card = findCard(cardId);
  if (!card) return null;
  const column = findColumnById(card.column_id);
  const board = (KB.boards || []).find(entry => parseInt(entry.id, 10) === parseInt(KB.board_id, 10)) || null;
  const assignees = Array.isArray(card.assignees) ? card.assignees.filter(Boolean).map(member => ({
    id: parseInt(member.id, 10) || 0,
    name: (member.name || '').trim(),
    avatar_url: boardMemberPicUrl(member.profile_pic) || '',
    initials: getInitials(member.name || ''),
  })) : [];
  const baseUrl = window.location.origin + window.location.pathname.replace(/\/+$/, '');
  return {
    card_id: parseInt(card.id, 10) || 0,
    board_id: parseInt(KB.board_id, 10) || 0,
    board_name: (board && board.name) ? board.name : '',
    column_id: parseInt(card.column_id, 10) || 0,
    column_name: (column && column.name) ? column.name : '',
    title: (card.title || '').trim(),
    priority: (card.priority || '').trim(),
    progress: Math.max(0, Math.min(100, parseInt(card.progress != null ? card.progress : 0, 10) || 0)),
    start_date: card.start_date || '',
    due_date: card.due_date || '',
    due_time: card.due_time || '',
    is_pinned: parseInt(card.is_pinned || 0, 10) ? 1 : 0,
    assignees,
    source_url: `${baseUrl}?board_id=${encodeURIComponent(KB.board_id || '')}&card_id=${encodeURIComponent(card.id || '')}`,
  };
}

function findBoardMember(memberId) {
  return (KB.board_members || []).find(m => parseInt(m.id, 10) === parseInt(memberId, 10)) || null;
}

function renderCardAssigneesHtml(assignees) {
  const items = Array.isArray(assignees) ? assignees.filter(Boolean) : [];
  if (!items.length) return '';
  const visible = items.map(member => {
    const src = boardMemberPicUrl(member.profile_pic);
    const name = (member.name || '').trim() || 'User';
    if (src) {
      return `<img class="cg-card__assignee-avatar" src="${escapeHtml(src)}" alt="${escapeHtml(name)}" title="${escapeHtml(name)}" loading="lazy" />`;
    }
    return `<span class="cg-card__assignee-avatar is-initials" title="${escapeHtml(name)}">${escapeHtml(getInitials(name))}</span>`;
  }).join('');
  return `<div class="cg-card__assignees">${visible}</div>`;
}

function getCardAttachmentsArray(card) {
  let attachmentsArr = [];
  try {
    const raw = card && card.attachments;
    if (typeof raw === 'string' && raw) attachmentsArr = JSON.parse(raw);
    else if (Array.isArray(raw)) attachmentsArr = raw;
  } catch (e) {}
  return Array.isArray(attachmentsArr) ? attachmentsArr : [];
}

function getCardLinksArray(card) {
  let arr = [];
  try {
    const raw = card && card.links;
    if (typeof raw === 'string' && raw) arr = JSON.parse(raw);
    else if (Array.isArray(raw)) arr = raw;
  } catch (e) {}
  return Array.isArray(arr) ? arr.map((u) => String(u || '').trim()).filter(Boolean) : [];
}

function extractYoutubeVideoId(raw) {
  const s = String(raw || '').trim();
  if (!s) return '';
  try {
    const u = new URL(s, /^https?:\/\//i.test(s) ? undefined : 'https://dummy.local');
    const host = (u.hostname || '').replace(/^www\./i, '').toLowerCase();
    if (host === 'youtu.be') {
      const id = (u.pathname.replace(/^\//, '').split('/').filter(Boolean)[0]) || '';
      return /^[a-zA-Z0-9_-]{11}$/.test(id) ? id : '';
    }
    if (!/(^|\.)youtube\.com$/i.test(host) && !/(^|\.)youtube-nocookie\.com$/i.test(host) && host !== 'm.youtube.com') {
      return '';
    }
    if (u.pathname.startsWith('/embed/')) {
      const id = u.pathname.slice(7).split('/')[0];
      return /^[a-zA-Z0-9_-]{11}$/.test(id) ? id : '';
    }
    if (u.pathname.startsWith('/shorts/')) {
      const id = u.pathname.slice(8).split('/')[0];
      return /^[a-zA-Z0-9_-]{11}$/.test(id) ? id : '';
    }
    const v = u.searchParams.get('v');
    if (v && /^[a-zA-Z0-9_-]{11}$/.test(v)) return v;
  } catch (e) {}
  return '';
}

function getCardYoutubeVideoId(card) {
  for (const url of getCardLinksArray(card)) {
    const id = extractYoutubeVideoId(url);
    if (id) return id;
  }
  return '';
}

function partitionCardLinksForEditor(linksArr) {
  const normalized = (Array.isArray(linksArr) ? linksArr : []).map((u) => String(u || '').trim()).filter(Boolean);
  let youtubeUrl = '';
  const rest = [];
  for (const u of normalized) {
    if (extractYoutubeVideoId(u)) {
      if (!youtubeUrl) youtubeUrl = u;
      else rest.push(u);
    } else {
      rest.push(u);
    }
  }
  return { youtubeUrl, otherLinks: rest, allLinks: normalized };
}

function mergeYoutubeFieldIntoLinks(otherLinks, youtubeFieldValue) {
  const yt = String(youtubeFieldValue || '').trim();
  const base = (Array.isArray(otherLinks) ? otherLinks : []).map((u) => String(u || '').trim()).filter(Boolean);
  if (!yt) return base;
  const withoutYt = base.filter((u) => !extractYoutubeVideoId(u));
  return [yt, ...withoutYt];
}

function getCardThumbnailUrl(card) {
  const attachmentsArr = getCardAttachmentsArray(card);
  if (attachmentsArr.some((att) => att && att.cover_disabled)) return '';
  const ordered = attachmentsArr.slice().sort((a, b) => (b && b.is_cover ? 1 : 0) - (a && a.is_cover ? 1 : 0));
  for (const att of ordered) {
    const path = att && (att.path || att);
    if (!path) continue;
    if (!/\.(jpe?g|png|gif|webp)$/i.test(String(path))) continue;
    const raw = String(path);
    const rel = (raw.indexOf('http') === 0 || raw.indexOf('/') === 0) ? raw : '/' + raw;
    return cgKanbanResolvePublicUrl(rel);
  }
  return '';
}

function cgCardThumbImgOnError(img) {
  if (!img) return;
  const fb = img.getAttribute('data-cg-thumb-fallback');
  if (fb && img.getAttribute('data-cg-thumb-phase') !== 'fallback') {
    img.setAttribute('data-cg-thumb-phase', 'fallback');
    img.onerror = function () {
      img.onerror = null;
      if (img.parentElement) img.parentElement.style.display = 'none';
    };
    img.src = fb;
    return;
  }
  img.onerror = null;
  if (img.parentElement) img.parentElement.style.display = 'none';
}

/** Board card thumb: sharp WebP via preview API; View Image uses getCardThumbnailUrl (full original). */
function getCardThumbnailPreviewUrl(card) {
  const attachmentsArr = getCardAttachmentsArray(card);
  if (attachmentsArr.some((att) => att && att.cover_disabled)) return '';
  const ordered = attachmentsArr.slice().sort((a, b) => (b && b.is_cover ? 1 : 0) - (a && a.is_cover ? 1 : 0));
  for (const att of ordered) {
    const path = att && (att.path || att);
    if (!path) continue;
    if (!/\.(jpe?g|png|gif|webp)$/i.test(String(path))) continue;
    const raw = String(path).trim();
    if (/^https?:\/\//i.test(raw)) {
      return cgKanbanResolvePublicUrl(raw);
    }
    const norm = raw.replace(/^\/+/, '');
    if (!/^uploads\/kanban_attachments\//i.test(norm)) {
      const rel = raw.indexOf('/') === 0 ? raw : '/' + raw;
      return cgKanbanResolvePublicUrl(rel);
    }
    const endpoint = CG_KANBAN_IMAGE_PREVIEW_URL;
    const u = endpoint.indexOf('http') === 0
      ? new URL(endpoint)
      : new URL(endpoint, window.location.origin);
    u.searchParams.set('path', norm);
    u.searchParams.set('w', '560');
    u.searchParams.set('fmt', 'webp');
    u.searchParams.set('kb', '35');
    return u.href;
  }
  return '';
}

function getKanbanAttachmentImageUrl(path, width) {
  const raw = String(path || '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) {
    return cgKanbanResolvePublicUrl(raw);
  }
  const norm = raw.replace(/^\/+/, '');
  if (!/^uploads\/kanban_attachments\//i.test(norm)) {
    const rel = raw.indexOf('/') === 0 ? raw : '/' + raw;
    return cgKanbanResolvePublicUrl(rel);
  }
  const endpoint = CG_KANBAN_IMAGE_PREVIEW_URL;
  const u = endpoint.indexOf('http') === 0
    ? new URL(endpoint)
    : new URL(endpoint, window.location.origin);
  u.searchParams.set('path', norm);
  u.searchParams.set('w', String(width || 480));
  return u.href;
}

function renderCardBoardThumbHtml(card) {
  const ytId = getCardYoutubeVideoId(card);
  if (ytId) {
    const posterSrc = `https://i.ytimg.com/vi/${encodeURIComponent(ytId)}/hqdefault.jpg`;
    return `<div class="cg-card__thumb cg-card__thumb--youtube" data-cg-youtube-id="${escapeHtml(ytId)}" onclick="event.stopPropagation();" role="presentation">
      <div class="cg-card__thumb-yt-poster">
        <img class="cg-card__thumb-yt-img" src="${escapeHtml(posterSrc)}" alt="" loading="lazy" decoding="async" onerror="this.style.display='none'">
        <span class="cg-card__thumb-yt-play" aria-hidden="true"><i class="fas fa-play"></i></span>
      </div>
      <div class="cg-card__thumb-yt-slot" hidden></div>
    </div>`;
  }
  const thumbFull = getCardThumbnailUrl(card);
  const thumbnailUrl = getCardThumbnailPreviewUrl(card);
  if (!thumbnailUrl) return '';
  const thumbFallbackAttr = thumbFull && thumbFull !== thumbnailUrl ? ` data-cg-thumb-fallback="${escapeHtml(thumbFull)}"` : '';
  const fullSrcAttr = thumbFull ? ` data-cg-full-src="${escapeHtml(thumbFull)}"` : '';
  const viewLink = thumbFull
    ? `<a href="${escapeHtml(thumbFull)}" class="cg-card__thumb-view" target="_blank" rel="noopener noreferrer" title="View full image" aria-label="View full image" onclick="event.stopPropagation();"><i class="fas fa-up-right-from-square" aria-hidden="true"></i></a>`
    : '';
  return `<div class="cg-card__thumb">${viewLink}<img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(card.title || 'Attachment')}" loading="eager" decoding="async"${thumbFallbackAttr}${fullSrcAttr} onerror="cgCardThumbImgOnError(this)"></div>`;
}

function cgYoutubeEmbedSrc(videoId) {
  const id = String(videoId || '').trim();
  if (!/^[a-zA-Z0-9_-]{11}$/.test(id)) return '';
  const p = new URLSearchParams({
    autoplay: '1',
    mute: '1',
    controls: '0',
    modestbranding: '1',
    rel: '0',
    playsinline: '1',
    fs: '0',
    disablekb: '1',
    iv_load_policy: '3',
    cc_load_policy: '0',
    color: 'white',
  });
  try {
    if (typeof window !== 'undefined' && window.location && window.location.origin) {
      p.set('origin', window.location.origin);
    }
  } catch (e) {}
  return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?${p.toString()}`;
}

function cgYtStopCardThumbPlayback(wrap) {
  if (!wrap) return;
  const slot = wrap.querySelector('.cg-card__thumb-yt-slot');
  const poster = wrap.querySelector('.cg-card__thumb-yt-poster');
  if (slot) {
    const iframe = slot.querySelector('iframe.cg-card__thumb-yt');
    if (iframe) {
      try {
        iframe.src = 'about:blank';
      } catch (e) {}
      iframe.remove();
    }
    slot.hidden = true;
  }
  if (poster) poster.style.visibility = '';
  if (window.__cgYtActiveThumbWrap === wrap) window.__cgYtActiveThumbWrap = null;
}

function initYoutubeCardThumbs() {
  const root = qs('#kanbanRoot');
  if (!root) return;
  if (!window.__cgYtThumbGlobalWired) {
    window.__cgYtThumbGlobalWired = true;
    document.addEventListener(
      'pointerdown',
      function (e) {
        const active = window.__cgYtActiveThumbWrap;
        if (!active) return;
        if (!active.isConnected) {
          window.__cgYtActiveThumbWrap = null;
          return;
        }
        if (active.contains(e.target)) return;
        cgYtStopCardThumbPlayback(active);
      },
      true
    );
  }
  root.querySelectorAll('.cg-card__thumb--youtube:not([data-cg-yt-wired])').forEach((wrap) => {
    wrap.dataset.cgYtWired = '1';
    const poster = wrap.querySelector('.cg-card__thumb-yt-poster');
    const slot = wrap.querySelector('.cg-card__thumb-yt-slot');
    if (!poster || !slot) return;

    wrap.addEventListener('mouseenter', () => {
      const id = wrap.getAttribute('data-cg-youtube-id') || '';
      const src = cgYoutubeEmbedSrc(id);
      if (!src) return;
      const prev = window.__cgYtActiveThumbWrap;
      if (prev && prev !== wrap) cgYtStopCardThumbPlayback(prev);
      let iframe = slot.querySelector('iframe.cg-card__thumb-yt');
      if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.className = 'cg-card__thumb-yt';
        iframe.setAttribute('title', 'YouTube');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.loading = 'eager';
        slot.appendChild(iframe);
      }
      iframe.src = src;
      slot.hidden = false;
      poster.style.visibility = 'hidden';
      window.__cgYtActiveThumbWrap = wrap;
    });
  });
}

function getCardImageViewHref(card) {
  if (!card) return '';
  const yt = getCardYoutubeVideoId(card);
  if (yt) return `https://www.youtube.com/watch?v=${encodeURIComponent(yt)}`;
  const full = getCardThumbnailUrl(card);
  const preview = getCardThumbnailPreviewUrl(card);
  return full || preview || '';
}

function openAssignCardModal(cardId) {
  const card = findCard(cardId);
  if (!card) return;

  qs('#assign_card_id').value = String(cardId);
  const preview = qs('#assignCardTitlePreview');
  if (preview) {
    preview.textContent = `Assign collaborators to "${card.title || 'this card'}".`;
  }

  const selectedIds = new Set(((card.assignees || []).map(member => parseInt(member.id, 10))).filter(Boolean));
  const members = KB.board_members || [];
  const listEl = qs('#assignCardMembersList');
  if (listEl) {
    if (!members.length) {
      listEl.innerHTML = '<div class="text-muted small p-3">No collaborators available on this board.</div>';
    } else {
      listEl.innerHTML = members.map(member => {
        const id = parseInt(member.id, 10);
        const src = boardMemberPicUrl(member.profile_pic);
        const name = (member.name || '').trim() || 'User';
        const roleLabel = member.role === 'owner' ? 'Owner' : 'Collaborator';
        const checked = selectedIds.has(id) ? 'checked' : '';
        const avatarHtml = src
          ? `<img class="cg-assign-item__avatar" src="${escapeHtml(src)}" alt="${escapeHtml(name)}" loading="lazy">`
          : `<span class="cg-assign-item__initials">${escapeHtml(getInitials(name))}</span>`;
        return `
          <label class="cg-assign-item">
            <input class="form-check-input mt-0" type="checkbox" name="assigned_user_ids[]" value="${id}" ${checked}>
            ${avatarHtml}
            <span class="cg-assign-item__meta">
              <span class="cg-assign-item__name d-block">${escapeHtml(name)}</span>
              <span class="cg-assign-item__role">${escapeHtml(roleLabel)}</span>
            </span>
          </label>
        `;
      }).join('');
    }
  }

  const modalEl = qs('#assignCardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function closeCardContextMenu() {
  closeCardColorPickerPopup();
  closeCardColumnFlyout();
  const menu = qs('#cardContextMenu');
  if (!menu) return;
  menu.classList.remove('is-open');
  menu.setAttribute('aria-hidden', 'true');
  menu.style.left = '';
  menu.style.top = '';
  closeCardColorMenu();
  activeCardContextMenuId = null;
}

/** Position #cardContextMenu outer shell (no zoom) to the right of the pointer; remeasure after paint. */
function cgApplyCardContextMenuPosition(menu, clientX, clientY) {
  if (!menu) return;
  menu.style.position = 'fixed';
  menu.style.right = 'auto';
  menu.style.bottom = 'auto';
  const pad = 12;
  const gutter = 8;
  const menuRect = menu.getBoundingClientRect();
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  let left = clientX + gutter;
  if (left + menuRect.width > vw - pad) {
    left = clientX - gutter - menuRect.width;
  }
  left = Math.max(pad, Math.min(left, vw - menuRect.width - pad));
  const maxTop = vh - menuRect.height - pad;
  const top = Math.max(pad, Math.min(clientY, maxTop));
  menu.style.left = `${Math.round(left)}px`;
  menu.style.top = `${Math.round(top)}px`;
}

function openCardContextMenu(evt, cardId) {
  const menu = qs('#cardContextMenu');
  const card = findCard(cardId);
  if (!menu || !card) return;

  evt.preventDefault();
  evt.stopPropagation();
  closeCardColumnFlyout();
  activeCardContextMenuId = parseInt(cardId, 10);

  const pinLabel = qs('#cardContextPinBtn span');
  if (pinLabel) {
    pinLabel.textContent = parseInt(card.is_pinned || 0, 10) ? 'Unpin Card' : 'Pin to Top';
  }
  const viewImageBtn = qs('#cardContextViewImageBtn');
  if (viewImageBtn) {
    const imgHref = getCardImageViewHref(card);
    viewImageBtn.hidden = !imgHref;
  }
  refreshCardColorMenuSelection();

  menu.classList.add('is-open');
  menu.setAttribute('aria-hidden', 'false');
  cgApplyCardContextMenuPosition(menu, evt.clientX, evt.clientY);
  requestAnimationFrame(() => {
    requestAnimationFrame(() => cgApplyCardContextMenuPosition(menu, evt.clientX, evt.clientY));
  });
}

function normalizeCardColorTone(value) {
  const s = String(value || '').trim().toLowerCase();
  if (s === '' || s === 'default') return '';
  if (CARD_COLOR_TONES.has(s)) return s;
  let hex = s.replace(/^#/, '').replace(/[^0-9a-f]/g, '');
  if (hex.length === 3) {
    hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
  }
  if (hex.length === 6 && /^[0-9a-f]{6}$/.test(hex)) return `#${hex}`;
  return '';
}

function getCardToneClass(card) {
  const tone = normalizeCardColorTone(card && card.card_color);
  if (!tone || tone.startsWith('#')) return '';
  return `cg-card--custom cg-card--tone-${tone}`;
}

function getCardCustomHexClass(card) {
  const tone = normalizeCardColorTone(card && card.card_color);
  return /^#[0-9a-f]{6}$/.test(tone) ? 'cg-card--custom cg-card--custom-hex' : '';
}

function hexToRgb(hex) {
  const clean = String(hex || '').replace('#', '');
  if (!/^[0-9a-fA-F]{6}$/.test(clean)) return null;
  return {
    r: parseInt(clean.slice(0, 2), 16),
    g: parseInt(clean.slice(2, 4), 16),
    b: parseInt(clean.slice(4, 6), 16),
  };
}

function getCardCustomStyle(card) {
  const tone = normalizeCardColorTone(card && card.card_color);
  if (!/^#[0-9a-f]{6}$/.test(tone)) return '';
  const rgb = hexToRgb(tone);
  if (!rgb) return '';
  return `--cg-card-custom-r:${rgb.r};--cg-card-custom-g:${rgb.g};--cg-card-custom-b:${rgb.b};`;
}

function closeCardColorMenu() {
  const menu = qs('#cardColorMenu');
  if (!menu) return;
  menu.classList.remove('is-open');
  menu.setAttribute('aria-hidden', 'true');
}

let columnFlyoutMode = null;

function closeCardColumnFlyout() {
  const fly = qs('#cardColumnFlyout');
  if (!fly) return;
  fly.classList.remove('is-open');
  fly.setAttribute('aria-hidden', 'true');
  fly.setAttribute('hidden', '');
  fly.innerHTML = '';
  fly.style.left = '';
  fly.style.top = '';
  columnFlyoutMode = null;
}

function cloneCardsByColumnState() {
  const out = {};
  (KB.columns || []).forEach((c) => {
    const id = parseInt(c.id, 10) || 0;
    if (!id) return;
    out[id] = (KB.cardsByColumn[id] || []).map((card) => ({ ...card }));
  });
  return out;
}

/** Map card id → column id for diffing moves after drag-and-drop (persistPositions). */
function cgKanbanCardIdToColumnMap(cardsByColumn) {
  const m = new Map();
  const src = cardsByColumn || {};
  Object.keys(src).forEach((colKey) => {
    const colId = parseInt(colKey, 10) || 0;
    if (!colId) return;
    (src[colKey] || []).forEach((crd) => {
      const id = parseInt(crd.id, 10) || 0;
      if (id) m.set(id, colId);
    });
  });
  return m;
}

function openCardColumnFlyout(anchorEl, mode) {
  const fly = qs('#cardColumnFlyout');
  if (!fly || !anchorEl || !activeCardContextMenuId) return;
  const card = findCard(activeCardContextMenuId);
  if (!card) return;
  closeCardColorMenu();
  columnFlyoutMode = mode;
  const curCol = parseInt(card.column_id, 10) || 0;
  const cols = (KB.columns || []).slice();
  const items = cols
    .filter((c) => {
      const id = parseInt(c.id, 10) || 0;
      if (!id) return false;
      if (mode === 'move' && id === curCol) return false;
      return true;
    })
    .map((c) => {
      const id = parseInt(c.id, 10) || 0;
      const name = escapeHtml((c.name || '').trim() || 'Column');
      return `<button type="button" class="cg-card-column-flyout__item" data-column-id="${id}">${name}</button>`;
    })
    .join('');
  fly.innerHTML = items || '<div class="small text-muted px-2 py-1">No columns</div>';
  fly.removeAttribute('hidden');
  fly.classList.add('is-open');
  fly.setAttribute('aria-hidden', 'false');
  const rect = anchorEl.getBoundingClientRect();
  const pad = 12;
  const flyRect = fly.getBoundingClientRect();
  const maxLeft = window.innerWidth - flyRect.width - pad;
  const maxTop = window.innerHeight - flyRect.height - pad;
  fly.style.position = 'fixed';
  fly.style.zIndex = '2002';
  fly.style.left = `${Math.max(pad, Math.min(rect.right + 6, maxLeft))}px`;
  fly.style.top = `${Math.max(pad, Math.min(rect.top, maxTop))}px`;
}

async function moveCardToColumnFromMenu(cardId, toColumnId, opts) {
  opts = opts || {};
  const cid = parseInt(cardId, 10) || 0;
  const tid = parseInt(toColumnId, 10) || 0;
  const card = findCard(cid);
  if (!card || !tid) return;
  const fromCol = parseInt(card.column_id, 10) || 0;
  if (fromCol === tid) return;
  const state = cloneCardsByColumnState();
  let moving = null;
  state[fromCol] = (state[fromCol] || []).filter((c) => {
    if (parseInt(c.id, 10) === cid) {
      moving = c;
      return false;
    }
    return true;
  });
  if (!moving) return;
  if (!state[tid]) state[tid] = [];
  state[tid].push(moving);
  const moves = [];
  (KB.columns || []).forEach((c) => {
    const colId = parseInt(c.id, 10) || 0;
    if (!colId) return;
    (state[colId] || []).forEach((crd, idx) => {
      moves.push({
        card_id: parseInt(crd.id, 10),
        to_column_id: colId,
        position: idx,
      });
    });
  });
  try {
    const res = await fetch(`${CG_FK_API}?action=move_cards`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ moves }),
    });
    if (!res.ok) throw new Error('Move failed');
    KB.cardsByColumn = state;
    (KB.columns || []).forEach((c) => {
      const countEl = qs(`.cg-col[data-col-id="${c.id}"] .cg-col__count`);
      if (countEl) {
        const colId = parseInt(c.id, 10);
        const count = (state[colId] || []).length;
        countEl.textContent = count;
      }
    });
    renderBoard();
    if (!opts.skipAutomation && !(window.__cgAutomationDepth > 0)) {
      void cgAutomationRunAfterCardMoved(cid, fromCol, tid);
    }
  } catch (err) {
    console.error(err);
    await loadKanbanData();
    alert(err.message || 'Could not move card');
  }
}

async function duplicateCardToColumn(cardId, columnId, opts) {
  opts = opts || {};
  try {
    const fd = new FormData();
    fd.append('action', 'duplicate_card');
    fd.append('card_id', String(parseInt(cardId, 10) || 0));
    fd.append('column_id', String(parseInt(columnId, 10) || 0));
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Could not duplicate card');
      return;
    }
    if (KB.project_id && KB.board_id) {
      const res2 = await fetch(`${CG_FK_API}?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'include' });
      if (res2.ok) {
        const d2 = await res2.json();
        if (d2.success) {
          KB.columns = d2.columns || [];
          KB.cardsByColumn = d2.cardsByColumn || {};
          applyPrioritySortToCardsByColumn(KB.cardsByColumn);
          renderBoard();
          const newCid = parseInt(data.card_id || data.cardId || '0', 10) || 0;
          if (newCid && !opts.skipAutomation && !(window.__cgAutomationDepth > 0)) {
            void cgAutomationRunAfterDuplicate(parseInt(cardId, 10) || 0, newCid);
          }
        }
      }
    }
  } catch (err) {
    console.error(err);
    alert(err.message || 'Network error');
  }
}

function refreshCardColorMenuSelection() {
  const card = activeCardContextMenuId ? findCard(activeCardContextMenuId) : null;
  const selectedTone = normalizeCardColorTone(card && card.card_color);
  qsa('#cardColorMenu [data-card-color-value]').forEach(btn => {
    const value = normalizeCardColorTone(btn.getAttribute('data-card-color-value'));
    btn.classList.toggle('is-active', value === selectedTone);
  });
}

let cardColorPickerTargetId = null;
let cardPickerHsv = { h: 210, s: 0.25, v: 1 };
let cardPickerDialDragging = false;
let cardPickerSvDragging = false;

function rgbToHsv(r, g, b) {
  r /= 255;
  g /= 255;
  b /= 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const d = max - min;
  let h = 0;
  if (d !== 0) {
    if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
    else if (max === g) h = ((b - r) / d + 2) / 6;
    else h = ((r - g) / d + 4) / 6;
  }
  h *= 360;
  const s = max === 0 ? 0 : d / max;
  const v = max;
  return { h, s, v };
}

function hsvToRgb(h, s, v) {
  const hh = ((h % 360) + 360) % 360;
  const c = v * s;
  const x = c * (1 - Math.abs(((hh / 60) % 2) - 1));
  const m = v - c;
  let rp = 0;
  let gp = 0;
  let bp = 0;
  if (hh < 60) {
    rp = c;
    gp = x;
  } else if (hh < 120) {
    rp = x;
    gp = c;
  } else if (hh < 180) {
    gp = c;
    bp = x;
  } else if (hh < 240) {
    gp = x;
    bp = c;
  } else if (hh < 300) {
    rp = x;
    bp = c;
  } else {
    rp = c;
    bp = x;
  }
  return {
    r: Math.round((rp + m) * 255),
    g: Math.round((gp + m) * 255),
    b: Math.round((bp + m) * 255),
  };
}

function rgbToHex(r, g, b) {
  const toHex = (n) => Math.max(0, Math.min(255, n | 0)).toString(16).padStart(2, '0');
  return `#${toHex(r)}${toHex(g)}${toHex(b)}`.toLowerCase();
}

function hexToHsv(hex) {
  const rgb = hexToRgb(hex);
  if (!rgb) return { h: 210, s: 0.2, v: 1 };
  return rgbToHsv(rgb.r, rgb.g, rgb.b);
}

const CARD_HUE_DIAL = { cx: 84, cy: 84, rOuter: 78, rInner: 52 };

function drawCardHueDial() {
  const canvas = qs('#cardHueDialCanvas');
  if (!canvas || !canvas.getContext) return;
  const ctx = canvas.getContext('2d');
  const { cx, cy, rOuter, rInner } = CARD_HUE_DIAL;
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  if (ctx.createConicGradient) {
    const g = ctx.createConicGradient(0, cx, cy);
    for (let i = 0; i <= 360; i++) {
      g.addColorStop(i / 360, `hsl(${i}, 100%, 50%)`);
    }
    ctx.beginPath();
    ctx.arc(cx, cy, rOuter, 0, Math.PI * 2);
    ctx.arc(cx, cy, rInner, 0, Math.PI * 2, true);
    ctx.fillStyle = g;
    ctx.fill('evenodd');
  } else {
    for (let i = 0; i < 360; i++) {
      const a0 = ((i - 0.5) / 360) * Math.PI * 2;
      const a1 = ((i + 0.5) / 360) * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(cx, cy, rOuter, a0, a1);
      ctx.arc(cx, cy, rInner, a1, a0, true);
      ctx.closePath();
      ctx.fillStyle = `hsl(${i}, 100%, 50%)`;
      ctx.fill();
    }
  }
  ctx.strokeStyle = 'rgba(15,23,42,0.12)';
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.arc(cx, cy, rOuter + 0.5, 0, Math.PI * 2);
  ctx.stroke();
  ctx.beginPath();
  ctx.arc(cx, cy, rInner - 0.5, 0, Math.PI * 2);
  ctx.stroke();
  const R = (rOuter + rInner) / 2;
  const rad = (cardPickerHsv.h * Math.PI) / 180;
  const kx = cx + R * Math.cos(rad);
  const ky = cy + R * Math.sin(rad);
  ctx.beginPath();
  ctx.arc(kx, ky, 8, 0, Math.PI * 2);
  ctx.fillStyle = '#fff';
  ctx.fill();
  ctx.strokeStyle = 'rgba(15,23,42,0.35)';
  ctx.lineWidth = 2;
  ctx.stroke();
}

function updateCardSvPlaneBackground() {
  const plane = qs('#cardSvPlane');
  if (!plane) return;
  const pure = hsvToRgb(cardPickerHsv.h, 1, 1);
  const hexPure = rgbToHex(pure.r, pure.g, pure.b);
  plane.style.background = [
    'linear-gradient(to bottom, transparent, #000)',
    `linear-gradient(to right, #fff, ${hexPure})`,
  ].join(', ');
}

function updateCardSvCursor() {
  const wrap = qs('#cardSvPlaneWrap');
  const cur = qs('#cardSvCursor');
  if (!wrap || !cur) return;
  const w = wrap.clientWidth || 1;
  const h = wrap.clientHeight || 1;
  const x = cardPickerHsv.s * w;
  const y = (1 - cardPickerHsv.v) * h;
  cur.style.left = `${x}px`;
  cur.style.top = `${y}px`;
}

function syncCardPickerHexField() {
  const rgb = hsvToRgb(cardPickerHsv.h, cardPickerHsv.s, cardPickerHsv.v);
  const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
  const input = qs('#cardColorHexInput');
  const prev = qs('#cardColorHexPreview');
  if (input) input.value = hex;
  if (prev) prev.style.background = hex;
}

function setCardPickerFromHexString(raw) {
  const norm = normalizeCardColorTone(raw);
  if (!/^#[0-9a-f]{6}$/.test(norm)) return false;
  cardPickerHsv = hexToHsv(norm);
  drawCardHueDial();
  updateCardSvPlaneBackground();
  updateCardSvCursor();
  syncCardPickerHexField();
  return true;
}

function initCardPickerFromCard(card) {
  const tone = normalizeCardColorTone(card && card.card_color);
  if (/^#[0-9a-f]{6}$/.test(tone)) {
    cardPickerHsv = hexToHsv(tone);
  } else {
    cardPickerHsv = { h: 210, s: 0.22, v: 1 };
  }
  drawCardHueDial();
  updateCardSvPlaneBackground();
  updateCardSvCursor();
  syncCardPickerHexField();
}

function closeCardColorPickerPopup() {
  const popup = qs('#cardColorPickerPopup');
  if (popup) {
    popup.classList.remove('is-open');
    popup.setAttribute('aria-hidden', 'true');
  }
  cardColorPickerTargetId = null;
  cardPickerDialDragging = false;
  cardPickerSvDragging = false;
}

function isCardColorPickerOpen() {
  const popup = qs('#cardColorPickerPopup');
  return !!(popup && popup.classList.contains('is-open'));
}

function openCardColorPickerPopup() {
  const cardId = activeCardContextMenuId;
  if (!cardId) return;
  closeCardContextMenu();
  cardColorPickerTargetId = cardId;
  const card = findCard(cardId);
  initCardPickerFromCard(card);
  const popup = qs('#cardColorPickerPopup');
  if (popup) {
    popup.classList.add('is-open');
    popup.setAttribute('aria-hidden', 'false');
  }
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      updateCardSvCursor();
      drawCardHueDial();
    });
  });
}

function pickHueFromDial(clientX, clientY) {
  const canvas = qs('#cardHueDialCanvas');
  if (!canvas) return;
  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width / rect.width;
  const scaleY = canvas.height / rect.height;
  const x = (clientX - rect.left) * scaleX;
  const y = (clientY - rect.top) * scaleY;
  const { cx, cy, rOuter, rInner } = CARD_HUE_DIAL;
  const dx = x - cx;
  const dy = y - cy;
  const dist = Math.sqrt(dx * dx + dy * dy);
  if (dist < rInner - 2 || dist > rOuter + 2) return;
  let deg = (Math.atan2(dy, dx) * 180) / Math.PI;
  if (deg < 0) deg += 360;
  cardPickerHsv.h = deg;
  drawCardHueDial();
  updateCardSvPlaneBackground();
  syncCardPickerHexField();
}

function pickSvFromPlane(clientX, clientY) {
  const wrap = qs('#cardSvPlaneWrap');
  if (!wrap) return;
  const rect = wrap.getBoundingClientRect();
  let sx = (clientX - rect.left) / rect.width;
  let sy = (clientY - rect.top) / rect.height;
  sx = Math.max(0, Math.min(1, sx));
  sy = Math.max(0, Math.min(1, sy));
  cardPickerHsv.s = sx;
  cardPickerHsv.v = 1 - sy;
  updateCardSvCursor();
  syncCardPickerHexField();
}

function openCardColorMenu(anchorEl) {
  const menu = qs('#cardColorMenu');
  if (!menu || !anchorEl) return;
  refreshCardColorMenuSelection();
  menu.classList.add('is-open');
  menu.setAttribute('aria-hidden', 'false');
  const rect = anchorEl.getBoundingClientRect();
  const pad = 12;
  const menuRect = menu.getBoundingClientRect();
  const maxLeft = window.innerWidth - menuRect.width - pad;
  const maxTop = window.innerHeight - menuRect.height - pad;
  menu.style.left = Math.max(pad, Math.min(rect.right + 6, maxLeft)) + 'px';
  menu.style.top = Math.max(pad, Math.min(rect.top, maxTop)) + 'px';
}

function applyCardColorLocally(cardId, colorTone) {
  const card = findCard(cardId);
  if (!card) return;
  card.card_color = normalizeCardColorTone(colorTone);
  renderBoard();
}

async function setCardColor(cardId, colorTone) {
  const normalized = normalizeCardColorTone(colorTone);
  const fd = new FormData();
  fd.append('action', 'set_card_color');
  fd.append('card_id', String(parseInt(cardId, 10) || 0));
  fd.append('card_color', normalized);
  const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'Failed to update card color');
  const saved = data.card_color != null && data.card_color !== undefined
    ? String(data.card_color)
    : normalized;
  applyCardColorLocally(cardId, saved);
}

function kanbanBoardMembersSignature(members) {
  try {
    return JSON.stringify((members || []).map((m) => [
      String(m && m.id != null ? m.id : ''),
      String(m && m.name != null ? m.name : ''),
      String(m && m.profile_pic != null ? m.profile_pic : ''),
      String(m && m.role != null ? m.role : '')
    ]));
  } catch (err) {
    return '';
  }
}

function renderBoardMembersAvatars() {
  const container = qs('#boardMembersAvatars');
  if (!container) return;
  const members = KB.board_members || [];
  const sig = kanbanBoardMembersSignature(members);
  if (sig && container.dataset.cgMembersSignature === sig && container.childElementCount > 0) {
    return;
  }
  container.dataset.cgMembersSignature = sig;
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
    const res = await fetch(CG_FK_API + '?action=move_columns', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
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
  const beforeMap = cgKanbanCardIdToColumnMap(KB.cardsByColumn);
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
    const res = await fetch(CG_FK_API + '?action=move_cards', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
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

      // Drag-and-drop only hits persistPositions (not moveCardToColumnFromMenu); run move-based automations.
      if (KB.is_board_owner && !(window.__cgAutomationDepth > 0)) {
        const afterMap = cgKanbanCardIdToColumnMap(newCardsByColumn);
        const columnChanges = [];
        afterMap.forEach((toCol, cardId) => {
          const fromCol = beforeMap.get(cardId);
          if (fromCol === undefined || !fromCol || !toCol) return;
          if (String(fromCol) !== String(toCol)) {
            columnChanges.push({ cardId, fromCol, toCol });
          }
        });
        for (const { cardId, fromCol, toCol } of columnChanges) {
          await cgAutomationRunAfterCardMoved(cardId, fromCol, toCol);
        }
      }
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
    const res = await fetch(`${CG_FK_API}?action=list_comments&board_id=${KB.board_id}`, { credentials: 'include' });
    if (!res.ok) { KB.comments = []; return; }
    const data = await res.json();
    KB.comments = (data.comments || []);
  } catch (e) {
    KB.comments = [];
  }
}

async function loadKanbanData() {
  _kanbanLastGridSig = null;
  if (typeof window.cgPortalLoadingBegin === 'function') {
    window.cgPortalLoadingBegin('kanbanLoadingOverlay');
  }
  try {
    // Check if board_id is in URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlBoardId = urlParams.get('board_id');
    
    let url = CG_FK_API + '?action=bootstrap';
    if (urlBoardId) {
      url += `&board_id=${urlBoardId}`;
    }
    
    const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
    const rawText = await res.text();
    if (!res.ok) {
      let detail = rawText.trim().substring(0, 500);
      try {
        const j = JSON.parse(rawText);
        if (j && typeof j.message === 'string' && j.message.trim() !== '') {
          detail = j.message.trim();
        }
      } catch (_) { /* not JSON */ }
      throw new Error(`HTTP ${res.status}: ${detail}`);
    }
    const data = JSON.parse(rawText);
    if (!data.success) throw new Error(data.message || 'Failed');

    KB.project_id = data.project_id;
    KB.board_id = data.board_id;
    KB.chart_id = data.chart_id || 0;
    KB.boards = data.boards || [];
    KB.columns = data.columns || [];
    KB.cardsByColumn = data.cardsByColumn || {};
    applyPrioritySortToCardsByColumn(KB.cardsByColumn);
    KB.board_members = data.board_members || [];
    syncBoardChatMembersToWindow();
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
    if (typeof window.cgBoardChatOnBoardLoad === 'function') {
      window.cgBoardChatOnBoardLoad(KB.board_id);
    }
  } catch (err) {
    console.error('Load kanban data error:', err);
    throw err;
  } finally {
    if (typeof window.cgPortalLoadingEnd === 'function') {
      window.cgPortalLoadingEnd('kanbanLoadingOverlay');
    }
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
      const url = `${CG_FK_API}?action=bootstrap&board_id=${KB.board_id}&_t=${Date.now()}`;
      const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.success) return;
      KB.project_id = data.project_id;
      KB.board_id = data.board_id;
      KB.chart_id = data.chart_id || 0;
      KB.columns = data.columns || [];
      KB.cardsByColumn = data.cardsByColumn || {};
      applyPrioritySortToCardsByColumn(KB.cardsByColumn);
      KB.board_members = data.board_members || [];
      syncBoardChatMembersToWindow();
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
  const templatesWrap = qs('#kanbanTemplatesDropdownWrap');
  const automationBtn = qs('#kanbanAutomationBtn');
  if (newBoardBtn) newBoardBtn.style.display = isOwner ? '' : 'none';
  if (templatesWrap) templatesWrap.style.display = isOwner ? '' : 'none';
  /* Automations: keep visible for everyone (like stats); only owners can save rules (see openKanbanAutomationModal). */
  if (automationBtn) automationBtn.style.display = '';
  if (editBoardBtn) editBoardBtn.style.display = KB.board_id ? '' : 'none';
  if (activitiesPanelBtn) activitiesPanelBtn.style.display = isOwner ? '' : 'none';
  const panel = qs('#activityPanel');
  if (panel && panel.classList.contains('is-open') && KB.board_id && KB.board_id !== KB.activityPanelBoardId) {
    loadActivityPanel();
  }
  const editPanel = qs('#editBoardPanel');
  if (editPanel && editPanel.classList.contains('is-open')) {
    applyEditBoardPanelRoleUI();
  }
}

function kanbanBoardTemplateMeta(slug) {
  const list = Array.isArray(CG_KANBAN_BOARD_TEMPLATES) ? CG_KANBAN_BOARD_TEMPLATES : [];
  return list.find(t => t.slug === slug) || null;
}

function openBoardModal(templateSlug) {
  const slug = templateSlug != null ? templateSlug : 'blank';
  const meta = kanbanBoardTemplateMeta(slug);
  const nameEl = qs('#board_name');
  const slugEl = qs('#board_template_slug');
  const sel = qs('#board_template_select');
  if (slugEl) slugEl.value = slug;
  if (sel) sel.value = slug;
  if (nameEl) {
    if (slug === 'blank' || !meta) {
      nameEl.value = 'New Board';
    } else {
      nameEl.value = meta.default_board_name || 'New Board';
    }
  }
  const submitBtn = qs('#boardForm button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Board';
  }
  const modalEl = qs('#boardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    setTimeout(() => { openBoardModal(templateSlug); }, 100);
  }
}

function openBoardModalFromTemplate(slug) {
  openBoardModal(slug);
}

const boardModalEl = qs('#boardModal');
if (boardModalEl) {
  boardModalEl.addEventListener('hidden.bs.modal', () => {
    const submitBtn = qs('#boardForm button[type="submit"]');
    if (!submitBtn) return;
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Board';
  });
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
    const tplSlug = (qs('#board_template_slug') && qs('#board_template_slug').value) ? qs('#board_template_slug').value.trim() : 'blank';
    fd.append('template', tplSlug || 'blank');
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    
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
        const res2 = await fetch(`${CG_FK_API}?action=bootstrap&project_id=${KB.project_id}&board_id=${newBoardId}`, { credentials: 'include' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.board_id = d2.board_id;
            KB.chart_id = d2.chart_id || 0;
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            applyPrioritySortToCardsByColumn(KB.cardsByColumn);
          KB.board_members = d2.board_members || [];
          syncBoardChatMembersToWindow();
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
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    
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
        const res2 = await fetch(`${CG_FK_API}?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'include' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            applyPrioritySortToCardsByColumn(KB.cardsByColumn);
            renderBoard();
            const newCid = parseInt(data.card_id || data.cardId || '0', 10) || 0;
            if (newCid && !options.skipAutomation) {
              void cgAutomationRunAfterCardCreated(newCid);
            }
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
    const newCidFallback = parseInt(data.card_id || data.cardId || '0', 10) || 0;
    if (newCidFallback && !options.skipAutomation) {
      void cgAutomationRunAfterCardCreated(newCidFallback);
    }
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

function setCardAttachmentCover(index) {
  cardExistingAttachments = cardExistingAttachments.map((att, i) => {
    const obj = (att && typeof att === 'object') ? { ...att } : { path: String(att || ''), name: String(att || '').split('/').pop() || 'Attachment' };
    delete obj.cover_disabled;
    obj.is_cover = i === index;
    return obj;
  });
  renderCardAttachments(cardExistingAttachments);
}

function clearCardAttachmentCover() {
  cardExistingAttachments = cardExistingAttachments.map((att) => {
    const obj = (att && typeof att === 'object') ? { ...att } : { path: String(att || ''), name: String(att || '').split('/').pop() || 'Attachment' };
    obj.is_cover = false;
    obj.cover_disabled = true;
    return obj;
  });
  renderCardAttachments(cardExistingAttachments);
}

function deleteCardAttachmentAt(index) {
  cardExistingAttachments = cardExistingAttachments.filter((_, j) => j !== index);
  renderCardAttachments(cardExistingAttachments);
}

function renderCardLinks(links) {
  const container = qs('#cardLinksContainer');
  container.innerHTML = '';
  (links || []).forEach((url, i) => {
    const row = document.createElement('div');
    row.className = 'input-group input-group-sm mb-2';
    row.innerHTML = `
      <span class="input-group-text"><i class="fas fa-link text-muted"></i></span>
      <input type="url" class="form-control card-link-input" placeholder="Paste link URL" value="${escapeHtml(url)}" data-idx="${i}" />
      <button type="button" class="btn btn-outline-danger" aria-label="Remove link"><i class="fas fa-times"></i></button>
    `;
    row.querySelector('button').onclick = () => { row.remove(); };
    container.appendChild(row);
  });
}

function renderCardAttachments(existing) {
  cardExistingAttachments = Array.isArray(existing) ? existing : [];
  const container = qs('#cardAttachmentsContainer');
  if (!container) return;
  container.innerHTML = '';
  container.classList.toggle('d-none', cardExistingAttachments.length === 0);
  cardExistingAttachments.forEach((att, i) => {
    const path = att.path || att;
    const name = (att.name || path.split('/').pop() || 'Attachment');
    const div = document.createElement('div');
    div.className = 'd-inline-flex align-items-center gap-2 me-2 mb-2 p-2 rounded bg-light border';
    div.dataset.idx = String(i);
    const isImg = path.match(/\.(jpe?g|png|gif|webp)$/i);
    const url = isImg
      ? getKanbanAttachmentImageUrl(path, 480)
      : cgKanbanResolvePublicUrl(path.startsWith('http') ? path : (path.startsWith('/') ? path : '/' + path));
    const isCover = !!(att && att.is_cover) && !cardExistingAttachments.some((item) => item && item.cover_disabled);
    if (isCover) {
      div.classList.remove('border');
      div.style.border = '1px solid #dc3545';
    }
    const imageCoverItems = isCover
      ? '<li><button class="dropdown-item" type="button" data-attachment-action="remove-cover">Remove cover</button></li>'
      : '<li><button class="dropdown-item" type="button" data-attachment-action="cover">Make cover</button></li><li><button class="dropdown-item" type="button" data-attachment-action="remove-cover">Remove cover</button></li>';
    div.innerHTML = isImg
      ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="card-attachment-img-link" title="${escapeHtml(name)}"><img src="${escapeHtml(url)}" alt="" class="rounded" style="width:36px;height:36px;object-fit:cover;cursor:pointer;" onerror="this.style.display='none'"></a><div class="dropdown ms-1"><button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Attachment options"><i class="fas fa-ellipsis-v"></i></button><ul class="dropdown-menu dropdown-menu-end">${imageCoverItems}<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-attachment-action="delete">Delete</button></li></ul></div>`
      : `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(name)}"><i class="fas fa-file-image text-muted"></i></a><div class="dropdown ms-1"><button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Attachment options"><i class="fas fa-ellipsis-v"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item disabled" type="button" disabled>Make cover</button></li><li><button class="dropdown-item disabled" type="button" disabled>Remove cover</button></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-attachment-action="delete">Delete</button></li></ul></div>`;
    div.querySelectorAll('[data-attachment-action]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const action = btn.getAttribute('data-attachment-action');
        if (action === 'cover') setCardAttachmentCover(i);
        else if (action === 'remove-cover') clearCardAttachmentCover();
        else if (action === 'delete') deleteCardAttachmentAt(i);
      });
    });
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

function resetCardModalSidebar() {
  const actList = qs('#cardModalActivityList');
  const actLoad = qs('#cardModalActivityLoading');
  const actEmpty = qs('#cardModalActivityEmpty');
  const comThread = qs('#cardCommentsThread');
  const comLoad = qs('#cardCommentsLoading');
  const comEmpty = qs('#cardCommentsEmpty');
  const ta = qs('#cardCommentBody');
  const err = qs('#cardCommentFormError');
  if (actList) actList.innerHTML = '';
  if (comThread) comThread.innerHTML = '';
  if (ta) ta.value = '';
  if (err) { err.style.display = 'none'; err.textContent = ''; }
  if (actLoad) actLoad.style.display = 'none';
  if (comLoad) comLoad.style.display = 'none';
  if (actEmpty) { actEmpty.style.display = 'none'; actEmpty.textContent = 'No activity for this card.'; }
  if (comEmpty) { comEmpty.style.display = 'none'; comEmpty.textContent = 'No comments yet.'; }
}

async function loadCardModalSidebar(cardId) {
  const nid = parseInt(cardId, 10) || 0;
  const actList = qs('#cardModalActivityList');
  const actLoad = qs('#cardModalActivityLoading');
  const actEmpty = qs('#cardModalActivityEmpty');
  const comThread = qs('#cardCommentsThread');
  const comLoad = qs('#cardCommentsLoading');
  const comEmpty = qs('#cardCommentsEmpty');
  if (!actList || !comThread) return;
  if (!nid || !KB.board_id) {
    resetCardModalSidebar();
    return;
  }
  if (actLoad) { actLoad.style.display = 'block'; }
  if (actEmpty) actEmpty.style.display = 'none';
  if (comLoad) { comLoad.style.display = 'block'; }
  if (comEmpty) comEmpty.style.display = 'none';
  actList.innerHTML = '';
  comThread.innerHTML = '';
  try {
    const [resAct, resCom] = await Promise.all([
      fetch(CG_FK_API + '?action=list_activities&board_id=' + encodeURIComponent(KB.board_id) + '&card_id=' + encodeURIComponent(nid), { credentials: 'include' }),
      fetch(CG_FK_API + '?action=list_card_comments&card_id=' + encodeURIComponent(nid), { credentials: 'include' }),
    ]);
    const dataAct = await resAct.json();
    const dataCom = await resCom.json();
    if (actLoad) actLoad.style.display = 'none';
    if (comLoad) comLoad.style.display = 'none';
    if (dataAct.success && dataAct.activities && dataAct.activities.length) {
      dataAct.activities.forEach(function (a) {
        const item = document.createElement('div');
        item.className = 'cg-card-modal-activity-item';
        const timeStr = a.created_at ? new Date(a.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '';
        item.innerHTML = '<div class="cg-activity-name">' + formatActivityMessage(a) + '</div>' + (timeStr ? '<div class="cg-activity-time">' + escapeHtml(timeStr) + '</div>' : '');
        actList.appendChild(item);
      });
      if (actEmpty) actEmpty.style.display = 'none';
    } else if (actEmpty) {
      actEmpty.style.display = 'block';
    }
    if (dataCom.success && dataCom.comments && dataCom.comments.length) {
      dataCom.comments.forEach(function (c) {
        const wrap = document.createElement('div');
        wrap.className = 'cg-card-comment-item';
        const meta = document.createElement('div');
        meta.className = 'cg-card-comment-meta';
        const author = ((c.author_name || '') + '').trim() || 'Someone';
        const t = c.created_at ? new Date(c.created_at).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '';
        meta.textContent = author + (t ? ' · ' + t : '');
        const body = document.createElement('div');
        body.className = 'cg-card-comment-body';
        body.textContent = (c.body != null ? String(c.body) : '');
        wrap.appendChild(meta);
        wrap.appendChild(body);
        comThread.appendChild(wrap);
      });
      if (comEmpty) comEmpty.style.display = 'none';
    } else if (comEmpty) {
      comEmpty.style.display = 'block';
    }
  } catch (e) {
    if (actLoad) actLoad.style.display = 'none';
    if (comLoad) comLoad.style.display = 'none';
    if (actEmpty) { actEmpty.style.display = 'block'; actEmpty.textContent = 'Could not load activity.'; }
    if (comEmpty) { comEmpty.style.display = 'block'; comEmpty.textContent = 'Could not load comments.'; }
  }
}

(function wireCardCommentFormOnce() {
  const form = qs('#cardCommentForm');
  if (!form || form.dataset.cgWired) return;
  form.dataset.cgWired = '1';
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = qs('#cardCommentFormError');
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    const cid = parseInt(qs('#card_id').value, 10) || 0;
    const ta = qs('#cardCommentBody');
    const body = ta ? ta.value.trim() : '';
    if (!cid || !body) return;
    const btn = qs('#cardCommentSubmit');
    if (btn) btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('action', 'add_card_comment');
      fd.append('card_id', String(cid));
      fd.append('body', body);
      const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
      const raw = await res.text();
      let data;
      try { data = raw ? JSON.parse(raw) : {}; } catch (_) { data = {}; }
      if (!data.success) {
        if (errEl) { errEl.textContent = data.message || 'Could not post comment.'; errEl.style.display = 'block'; }
        return;
      }
      if (ta) ta.value = '';
      await loadCardModalSidebar(cid);
    } catch (_) {
      if (errEl) { errEl.textContent = 'Network error.'; errEl.style.display = 'block'; }
    } finally {
      if (btn) btn.disabled = false;
    }
  });
})();

function openCard(cardId) {
  const c = findCard(cardId);
  if (!c) return;
  qs('#card_id').value = c.id;
  qs('#card_title').value = c.title || '';
  qs('#card_description').value = c.description || '';
  qs('#card_start_date').value = c.start_date || '';
  qs('#card_due_date').value = c.due_date || '';
  const dtIn = qs('#card_due_time');
  if (dtIn) dtIn.value = cardTimeToInputValue(c.due_time);
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
  const { youtubeUrl, otherLinks, allLinks } = partitionCardLinksForEditor(links);
  const ytInput = qs('#card_youtube_url');
  if (ytInput) ytInput.value = youtubeUrl;
  renderCardLinks(otherLinks);
  renderCardAttachments(attachments);
  qs('#card_links_data').value = JSON.stringify(allLinks);
  // Clear attachment drop zone and selected files list
  const fileInput = qs('#cardAttachmentFileInput');
  if (fileInput) { fileInput.value = ''; }
  qs('#cardNewAttachmentsList').innerHTML = '';
  const dropWrap = qs('#cardAttachmentDropWrap');
  if (dropWrap) dropWrap.style.display = '';
  const dropZone = qs('#cardAttachmentDropZone');
  if (dropZone) dropZone.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
  const progressWrap = qs('#cardAttachmentUploadProgress');
  if (progressWrap) progressWrap.style.display = 'none';
  // Show delete button for existing cards
  resetDeleteCardButton();
  qs('#deleteCardBtn').style.display = 'block';
  window.__cgKanbanCardEditBaseline = {
    cardId: parseInt(c.id, 10) || 0,
    progress: progressVal,
    columnId: parseInt(c.column_id, 10) || 0,
    linksJson: JSON.stringify(allLinks || []),
    attCount: Array.isArray(attachments) ? attachments.length : 0,
  };
  loadCardModalSidebar(c.id);
  const modalEl = qs('#cardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function addCardLinkRow() {
  const container = qs('#cardLinksContainer');
  if (!container) return;
  const idx = container.querySelectorAll('.card-link-input').length;
  const row = document.createElement('div');
  row.className = 'input-group input-group-sm mb-2';
  row.innerHTML = `
    <span class="input-group-text"><i class="fas fa-link text-muted"></i></span>
    <input type="url" class="form-control card-link-input" placeholder="Paste link URL" value="" data-idx="${idx}" />
    <button type="button" class="btn btn-outline-danger" aria-label="Remove link"><i class="fas fa-times"></i></button>
  `;
  row.querySelector('button').onclick = () => row.remove();
  container.appendChild(row);
}

qs('#cardFooterAddLinkBtn').addEventListener('click', addCardLinkRow);
const deleteCardBtnEl = qs('#deleteCardBtn');
if (deleteCardBtnEl) {
  deleteCardBtnEl.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    const hid = qs('#card_id');
    const cardId = hid ? String(hid.value || '').trim() : '';
    if (!cardId) {
      alert('Delete: no card is open (card id empty). Close the dialog and open the card again.');
      return;
    }
    const numericId = parseInt(cardId, 10);
    if (!numericId) {
      alert('Delete: invalid or missing card id (' + String(cardId) + ').');
      return;
    }
    showDeleteCardConfirmModal(() => {
      void deleteCardById(numericId, { useModalButton: true, closeModal: true, skipConfirm: true });
    });
  });
}

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

function cardFormSubmitSuccess(data, saveBtn, originalLabel) {
  if (KB.project_id && KB.board_id) {
    return fetch(`${CG_FK_API}?action=bootstrap&board_id=${KB.board_id}&_t=${Date.now()}`, { credentials: 'include', cache: 'no-store' })
      .then(res2 => res2.json())
      .then(d2 => {
        if (d2.success) {
          KB.columns = d2.columns || [];
          KB.cardsByColumn = d2.cardsByColumn || {};
          applyPrioritySortToCardsByColumn(KB.cardsByColumn);
          renderBoard();
        }
      });
  }
  return loadKanbanData();
}

qs('#cardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const linkInputs = qs('#cardLinksContainer').querySelectorAll('.card-link-input');
  const otherLinks = Array.from(linkInputs).map(inp => inp.value.trim()).filter(Boolean);
  const ytField = qs('#card_youtube_url');
  const ytVal = ytField ? ytField.value.trim() : '';
  const links = mergeYoutubeFieldIntoLinks(otherLinks, ytVal);
  qs('#card_links_data').value = JSON.stringify(links);
  qs('#card_existing_attachments').value = JSON.stringify(cardExistingAttachments);

  const fd = new FormData(e.target);
  fd.append('action', 'update_card');

  const saveBtn = qs('#cardFormSaveBtn');
  const originalLabel = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  const fileInput = qs('#cardAttachmentFileInput');
  const hasAttachments = fileInput && fileInput.files && fileInput.files.length > 0;
  const newFileCount = hasAttachments ? fileInput.files.length : 0;
  const automationSnap = {
    cardId: parseInt(qs('#card_id').value, 10) || 0,
    newProg: Math.max(0, Math.min(100, parseInt(qs('#card_progress').value, 10) || 0)),
    linksJson: JSON.stringify(links),
    newFileCount,
    newAttCount: (Array.isArray(cardExistingAttachments) ? cardExistingAttachments.length : 0) + newFileCount,
  };
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
        xhr.open('POST', CG_FK_API);
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
          cardFormSubmitSuccess(data, saveBtn, originalLabel).then(async () => {
            const base = window.__cgKanbanCardEditBaseline;
            if (base && String(base.cardId) === String(automationSnap.cardId)) {
              await cgAutomationRunAfterCardSave(automationSnap, base);
            }
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

    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`Save failed (HTTP ${res.status}): ${text.substring(0,200)}`);
    }
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to save card');

    await cardFormSubmitSuccess(data, saveBtn, originalLabel);
    const base = window.__cgKanbanCardEditBaseline;
    if (base && String(base.cardId) === String(automationSnap.cardId)) {
      await cgAutomationRunAfterCardSave(automationSnap, base);
    }
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

async function deleteCardById(cardId, options = {}) {
  const numericId = parseInt(cardId, 10);
  if (!numericId) {
    const msg = 'Delete: invalid or missing card id (' + String(cardId) + ').';
    console.error('[Kanban]', msg);
    alert(msg);
    return;
  }
  if (!options.skipConfirm) {
    showDeleteCardConfirmModal(() => {
      void deleteCardById(numericId, Object.assign({}, options, { skipConfirm: true }));
    });
    return;
  }

  const deleteBtn = options.useModalButton ? qs('#deleteCardBtn') : null;
  const originalText = deleteBtn ? deleteBtn.innerHTML : '';
  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
  }

  try {
    const fd = new FormData();
    fd.append('card_id', String(numericId));
    const res = await fetch(CG_FK_API + '?action=delete_card', {
      method: 'POST',
      credentials: 'include',
      body: fd
    });

    const raw = await res.text();
    let data = null;
    try {
      data = raw ? JSON.parse(raw) : null;
    } catch (_) {
      data = null;
    }
    if (!res.ok || !data || !data.success) {
      throw new Error((data && data.message) ? data.message : (raw ? raw.substring(0, 200) : 'Failed to delete card'));
    }

    closeCardContextMenu();
    if (options.closeModal) {
      const modalEl = qs('#cardModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
    }

    if (deleteBtn) {
      qs('#deleteCardBtn').style.display = 'none';
    }
    await loadKanbanData();
  } catch (err) {
    console.error('Delete card error:', err);
    alert('Failed to delete card: ' + err.message);
    if (deleteBtn) {
      deleteBtn.disabled = false;
      deleteBtn.innerHTML = originalText;
    }
  }
}

/** @deprecated Legacy entry; prefer #deleteCardBtn. Uses Bootstrap delete confirm modal. */
function deleteCard() {
  const hid = qs('#card_id');
  const cardId = hid ? String(hid.value || '').trim() : '';
  if (!cardId) {
    alert('Delete: no card is open (card id empty). Close the dialog and open the card again.');
    return;
  }
  const numericId = parseInt(cardId, 10);
  if (!numericId) return;
  showDeleteCardConfirmModal(() => {
    void deleteCardById(numericId, { useModalButton: true, closeModal: true, skipConfirm: true });
  });
}

async function toggleCardPin(cardId) {
  const card = findCard(cardId);
  if (!card) return;
  closeCardContextMenu();

  const fd = new FormData();
  fd.append('card_id', String(parseInt(cardId, 10)));
  fd.append('is_pinned', parseInt(card.is_pinned || 0, 10) ? '0' : '1');
  const res = await fetch(CG_FK_API + '?action=set_card_pin', {
    method: 'POST',
    credentials: 'include',
    body: fd
  });
  const raw = await res.text();
  const trimmed = typeof raw === 'string' ? raw.trim() : '';
  let data = null;
  try {
    data = trimmed ? JSON.parse(trimmed) : null;
  } catch (err) {
    throw new Error('Failed to update pin state');
  }
  if (!res.ok || !data.success) {
    alert(data.message || 'Failed to update pin state');
    return;
  }
  await loadKanbanData();
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
    resetCardModalSidebar();
  });
}

qs('#newBoardBtn').addEventListener('click', () => openBoardModal('blank'));

function initKanbanBoardTemplateSelect() {
  const sel = qs('#board_template_select');
  if (!sel || !Array.isArray(CG_KANBAN_BOARD_TEMPLATES)) return;
  sel.innerHTML = '';
  CG_KANBAN_BOARD_TEMPLATES.forEach(t => {
    const opt = document.createElement('option');
    opt.value = t.slug;
    opt.textContent = t.label + (t.column_count ? ' (' + t.column_count + ' columns)' : '');
    sel.appendChild(opt);
  });
}

function initKanbanTemplatesMenu() {
  initKanbanBoardTemplateSelect();
  const ul = qs('#kanbanTemplatesMenuList');
  if (!ul || !Array.isArray(CG_KANBAN_BOARD_TEMPLATES)) return;
  ul.innerHTML = '';
  CG_KANBAN_BOARD_TEMPLATES.forEach((t, idx) => {
    if (idx > 0) {
      const sepLi = document.createElement('li');
      const hr = document.createElement('hr');
      hr.className = 'dropdown-divider';
      hr.setAttribute('role', 'separator');
      sepLi.appendChild(hr);
      ul.appendChild(sepLi);
    }
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dropdown-item text-start cg-templates-menu__item';
    btn.textContent = t.label || t.slug || 'Template';
    btn.setAttribute('title', t.label || '');
    btn.addEventListener('click', () => {
      openBoardModalFromTemplate(t.slug);
    });
    li.appendChild(btn);
    ul.appendChild(li);
  });
}

const boardTemplateSelectEl = qs('#board_template_select');
if (boardTemplateSelectEl) {
  boardTemplateSelectEl.addEventListener('change', function() {
    const slug = this.value || 'blank';
    const slugEl = qs('#board_template_slug');
    if (slugEl) slugEl.value = slug;
    const meta = kanbanBoardTemplateMeta(slug);
    const nameEl = qs('#board_name');
    if (nameEl && meta) {
      nameEl.value = meta.default_board_name || 'New Board';
    }
  });
}
qs('#kanbanBoardSelect').addEventListener('change', async function() {
  const selectedBoardId = parseInt(this.value, 10) || 0;
  const currentBoardId = parseInt(KB.board_id, 10) || 0;
  if (!selectedBoardId || selectedBoardId === currentBoardId) return;

  const newUrl = new URL(window.location);
  newUrl.searchParams.set('board_id', selectedBoardId);
  window.history.replaceState({}, '', newUrl);
  await loadKanbanData();
});

function loadBoardEmailsKanban(boardId) {
  const listEl = qs('#editBoardEmailsList');
  if (!listEl || !boardId) return;
  listEl.innerHTML = '<span class="text-muted small">Loading…</span>';
  fetch(CG_FK_API + '?action=list_board_emails&board_id=' + encodeURIComponent(boardId), { credentials: 'include' })
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
          fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' })
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
  fetch(CG_FK_API + '?action=list_collaborators&board_id=' + encodeURIComponent(boardId), { credentials: 'include' })
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
          fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' })
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
    fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' })
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
    if (qs('#editBoardAddEmailBtn')?.disabled) return;
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
    fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' })
      .then(r => r.json())
      .then(d => {
        if (d.success) { qs('#editBoardNewEmailInput').value = ''; loadBoardEmailsKanban(qs('#edit_board_id').value); }
        else if (errEl) { errEl.textContent = d.message || 'Failed to add'; errEl.style.display = 'block'; }
      });
  };
  closeActivityPanel();
  applyEditBoardPanelRoleUI();
  const panel = qs('#editBoardPanel');
  const backdrop = qs('#editBoardPanelBackdrop');
  if (!panel || !backdrop) return;
  panel.classList.add('is-open');
  panel.setAttribute('aria-hidden', 'false');
  backdrop.classList.add('is-open');
  backdrop.setAttribute('aria-hidden', 'false');
}

(function () {
  const el = qs('#editBoardBtn');
  if (el) el.addEventListener('click', openEditBoardModal);
})();

function closeEditBoardPanel() {
  const panel = qs('#editBoardPanel');
  const backdrop = qs('#editBoardPanelBackdrop');
  if (panel) { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
  if (backdrop) { backdrop.classList.remove('is-open'); backdrop.setAttribute('aria-hidden', 'true'); }
}

/** Restore edit panel after delete/leave confirm was dismissed without confirming (no data reload). */
function reopenEditBoardPanelVisually() {
  const panel = qs('#editBoardPanel');
  const backdrop = qs('#editBoardPanelBackdrop');
  if (!panel || !backdrop) return;
  panel.classList.add('is-open');
  panel.setAttribute('aria-hidden', 'false');
  backdrop.classList.add('is-open');
  backdrop.setAttribute('aria-hidden', 'false');
}

function openActivityPanel() {
  closeEditBoardPanel();
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
    case 'card_created':
      return name + ' created card ' + (cardQ || '');
    case 'card_duplicated':
      if (a.payload && a.payload.new_card_id) {
        return name + ' duplicated card ' + (cardQ || '') + ' (new card #' + String(a.payload.new_card_id) + ')';
      }
      if (a.payload && a.payload.source_card_id) {
        return name + ' created this card as a duplicate' + (cardQ ? ' — ' + cardQ : '');
      }
      return name + ' duplicated a card';
    case 'pin_changed':
      return name + ' ' + (a.payload && a.payload.pinned ? 'pinned' : 'unpinned') + ' ' + (cardQ || 'a card');
    case 'assignments_updated':
      return name + ' updated assignees on ' + (cardQ || 'a card');
    case 'card_color_changed':
      return name + ' changed the color of ' + (cardQ || 'a card');
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
    const res = await fetch(CG_FK_API + '?action=list_activities&board_id=' + encodeURIComponent(KB.board_id), { credentials: 'include' });
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
(function () {
  const a = qs('#activitiesPanelBtn');
  if (a) a.addEventListener('click', openActivityPanel);
  document.querySelectorAll('[data-kanban-theme-toggle]').forEach((t) => {
    t.addEventListener('click', toggleKanbanTheme);
  });
})();
qs('#activityPanelClose').addEventListener('click', closeActivityPanel);
qs('#activityPanelBackdrop').addEventListener('click', closeActivityPanel);
qs('#editBoardPanelClose').addEventListener('click', closeEditBoardPanel);
qs('#editBoardPanelBackdrop').addEventListener('click', closeEditBoardPanel);
qs('#editBoardPanelCancel').addEventListener('click', closeEditBoardPanel);

(function wireEditBoardDeleteLeaveOnce() {
  const delBtn = qs('#editBoardDeleteBtn');
  const leaveBtn = qs('#editBoardLeaveBtn');
  if (delBtn && !delBtn.dataset.cgWired) {
    delBtn.dataset.cgWired = '1';
    delBtn.addEventListener('click', () => {
      const boardId = qs('#edit_board_id').value;
      const board = KB.boards.find(b => parseInt(b.id, 10) === parseInt(boardId, 10));
      const boardLabel = board && board.name ? board.name : 'this board';
      showEditBoardDangerConfirmModal({
        titleHtml: '<i class="fas fa-trash-alt me-2 text-danger"></i>Delete board',
        bodyText: 'Delete “' + boardLabel + '” and all its columns and cards? This cannot be undone.',
        okHtml: '<i class="fas fa-trash me-2"></i>Delete board',
        onConfirm: async () => {
          try {
            const fd = new FormData();
            fd.append('action', 'delete_board');
            fd.append('board_id', boardId);
            const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
            const raw = await res.text();
            let data;
            try {
              data = raw ? JSON.parse(raw) : null;
            } catch (_) {
              alert('Delete board: invalid response from server.');
              return;
            }
            if (!data || !data.success) {
              alert((data && data.message) || 'Failed to delete board');
              return;
            }
            closeEditBoardPanel();
            const newUrl = new URL(window.location);
            newUrl.searchParams.delete('board_id');
            window.history.replaceState({}, '', newUrl);
            try {
              await loadKanbanData();
            } catch (reloadErr) {
              console.error('Reload after delete:', reloadErr);
              window.location.assign(newUrl.pathname + (newUrl.search || '') + (newUrl.hash || ''));
              return;
            }
            if (window.refreshSidebars) window.refreshSidebars('boards');
          } catch (err) {
            console.error(err);
            alert('Failed to delete board');
          }
        }
      });
    });
  }
  if (leaveBtn && !leaveBtn.dataset.cgWired) {
    leaveBtn.dataset.cgWired = '1';
    leaveBtn.addEventListener('click', () => {
      const boardId = qs('#edit_board_id').value;
      const board = KB.boards.find(b => parseInt(b.id, 10) === parseInt(boardId, 10));
      const boardLabel = board && board.name ? board.name : 'this board';
      showEditBoardDangerConfirmModal({
        titleHtml: '<i class="fas fa-right-from-bracket me-2 text-danger"></i>Leave board',
        bodyText: 'You will lose access to “' + boardLabel + '” until the owner invites you again.',
        okHtml: '<i class="fas fa-right-from-bracket me-2"></i>Leave board',
        onConfirm: async () => {
          try {
            const fd = new FormData();
            fd.append('action', 'leave_board');
            fd.append('board_id', boardId);
            const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
            const data = await res.json();
            if (!data.success) {
              alert(data.message || 'Failed to leave board');
              return;
            }
            closeEditBoardPanel();
            const newUrl = new URL(window.location);
            newUrl.searchParams.delete('board_id');
            window.history.replaceState({}, '', newUrl);
            await loadKanbanData();
            if (window.refreshSidebars) window.refreshSidebars('boards');
          } catch (err) {
            console.error(err);
            alert('Failed to leave board');
          }
        }
      });
    });
  }
})();

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
      fetch(CG_FK_API + '?action=search_users&q=' + encodeURIComponent(q), { credentials: 'include' })
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

qs('#editBoardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (!KB.is_board_owner) return;
  const boardId = qs('#edit_board_id').value;
  const name = qs('#edit_board_name').value.trim();
  if (!name) return;
  try {
    const fd = new FormData();
    fd.append('action', 'update_board');
    fd.append('board_id', boardId);
    fd.append('name', name);
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
      closeEditBoardPanel();
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

qs('#assignCardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const cardId = parseInt(qs('#assign_card_id').value || '0', 10);
  if (!cardId) return;

  const checked = qsa('input[name="assigned_user_ids[]"]:checked', qs('#assignCardMembersList'));
  const userIds = checked.map(input => parseInt(input.value, 10)).filter(Boolean);
  const beforeCard = findCard(cardId);
  const beforeUserIds = beforeCard && Array.isArray(beforeCard.assignees)
    ? beforeCard.assignees.map((m) => parseInt(m && m.id, 10)).filter(Boolean)
    : [];
  const submitBtn = qs('#assignCardForm button[type="submit"]');
  const originalLabel = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
  }

  try {
    const fd = new FormData();
    fd.append('card_id', String(cardId));
    fd.append('user_ids', JSON.stringify(userIds));
    const res = await fetch(CG_FK_API + '?action=assign_card_members', {
      method: 'POST',
      credentials: 'include',
      body: fd
    });
    const raw = await res.text();
    let data = null;
    try {
      data = raw ? JSON.parse(raw) : null;
    } catch (parseErr) {
      throw new Error('Failed to update assignments');
    }
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Failed to save assignment');
    }

    const modalEl = qs('#assignCardModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
    await loadKanbanData();
    await cgAutomationRunAfterCardAssigned(cardId, beforeUserIds, Array.isArray(data.user_ids) ? data.user_ids : userIds);
  } catch (err) {
    console.error('Assign card error:', err);
    alert(err.message || 'Failed to assign card');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
    }
  }
});

(function wireCardContextMenuActions() {
  const viewImageBtn = qs('#cardContextViewImageBtn');
  if (viewImageBtn) {
    viewImageBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!activeCardContextMenuId) return;
      const c = findCard(activeCardContextMenuId);
      const href = getCardImageViewHref(c);
      if (!href) return;
      closeCardContextMenu();
      window.open(href, '_blank', 'noopener,noreferrer');
    });
  } else {
    console.warn('[Kanban] #cardContextViewImageBtn not found.');
  }

  const pinBtn = qs('#cardContextPinBtn');
  if (pinBtn) {
    pinBtn.addEventListener('click', async () => {
      if (!activeCardContextMenuId) return;
      await toggleCardPin(activeCardContextMenuId);
    });
  } else {
    console.warn('[Kanban] #cardContextPinBtn not found; context menu pin disabled.');
  }

  const assignBtn = qs('#cardContextAssignBtn');
  if (assignBtn) {
    assignBtn.addEventListener('click', () => {
      if (!activeCardContextMenuId) return;
      const cardId = activeCardContextMenuId;
      closeCardContextMenu();
      openAssignCardModal(cardId);
    });
  } else {
    console.warn('[Kanban] #cardContextAssignBtn not found.');
  }

  const addToChatBtn = qs('#cardContextAddToChatBtn');
  if (addToChatBtn) {
    addToChatBtn.addEventListener('click', () => {
      if (!activeCardContextMenuId) return;
      const cardId = activeCardContextMenuId;
      const payload = buildWorkspaceCardDragPayload(cardId);
      closeCardContextMenu();
      if (payload && typeof window.cgBoardChatAttachCardPayload === 'function') {
        window.cgBoardChatAttachCardPayload(payload);
      }
    });
  }

  const colorBtn = qs('#cardContextColorBtn');
  if (colorBtn) {
    colorBtn.addEventListener('click', (e) => {
      if (!activeCardContextMenuId) return;
      e.stopPropagation();
      openCardColorMenu(e.currentTarget);
    });
  } else {
    console.warn('[Kanban] #cardContextColorBtn not found.');
  }

  const moveToBtn = qs('#cardContextMoveToBtn');
  if (moveToBtn) {
    moveToBtn.addEventListener('click', (e) => {
      if (!activeCardContextMenuId) return;
      e.stopPropagation();
      openCardColumnFlyout(moveToBtn, 'move');
    });
  }

  const dupToBtn = qs('#cardContextDuplicateToBtn');
  if (dupToBtn) {
    dupToBtn.addEventListener('click', (e) => {
      if (!activeCardContextMenuId) return;
      e.stopPropagation();
      openCardColumnFlyout(dupToBtn, 'dup');
    });
  }

  const colFly = qs('#cardColumnFlyout');
  if (colFly && !colFly.dataset.cgColumnFlyoutWired) {
    colFly.dataset.cgColumnFlyoutWired = '1';
    colFly.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-column-id]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      const toCol = parseInt(btn.getAttribute('data-column-id') || '0', 10);
      const cardId = activeCardContextMenuId;
      const mode = columnFlyoutMode;
      closeCardColumnFlyout();
      closeCardContextMenu();
      if (!cardId || !toCol) return;
      if (mode === 'move') await moveCardToColumnFromMenu(cardId, toCol);
      else if (mode === 'dup') await duplicateCardToColumn(cardId, toCol);
    });
  }

  const colorMenu = qs('#cardColorMenu');
  if (colorMenu) {
    colorMenu.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-card-color-value]');
      if (!btn || !activeCardContextMenuId) return;
      const colorTone = btn.getAttribute('data-card-color-value') || '';
      try {
        await setCardColor(activeCardContextMenuId, colorTone);
        closeCardColorMenu();
        closeCardContextMenu();
      } catch (err) {
        alert(err.message || 'Failed to update card color');
      }
    });
  }

  const pickerOpenBtn = qs('#cardContextColorPickerBtn');
  if (pickerOpenBtn) {
    pickerOpenBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      openCardColorPickerPopup();
    });
  }

  const delBtn = qs('#cardContextDeleteBtn');
  if (delBtn) {
    delBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!activeCardContextMenuId) {
        alert('Could not delete: right-click the card again and choose Delete.');
        return;
      }
      const cardId = activeCardContextMenuId;
      closeCardContextMenu();
      showDeleteCardConfirmModal(() => {
        void deleteCardById(cardId, { skipConfirm: true });
      });
    });
  } else {
    console.error('[Kanban] #cardContextDeleteBtn not found; right-click delete will not work.');
  }
})();

function wireCardColorPickerControls() {
  const canvas = qs('#cardHueDialCanvas');
  const svWrap = qs('#cardSvPlaneWrap');
  const hexInput = qs('#cardColorHexInput');
  const applyBtn = qs('#cardColorPickerApply');
  const cancelBtn = qs('#cardColorPickerCancel');
  const backdrop = qs('#cardColorPickerBackdrop');

  if (canvas && !canvas.dataset.cgHueDialWired) {
    canvas.dataset.cgHueDialWired = '1';
    const onHuePointer = (ev) => {
      if (!isCardColorPickerOpen()) return;
      pickHueFromDial(ev.clientX, ev.clientY);
    };
    canvas.addEventListener('pointerdown', (ev) => {
      if (!isCardColorPickerOpen()) return;
      ev.preventDefault();
      canvas.setPointerCapture(ev.pointerId);
      cardPickerDialDragging = true;
      onHuePointer(ev);
    });
    canvas.addEventListener('pointermove', (ev) => {
      if (!cardPickerDialDragging || !isCardColorPickerOpen()) return;
      ev.preventDefault();
      onHuePointer(ev);
    });
    canvas.addEventListener('pointerup', (ev) => {
      cardPickerDialDragging = false;
      try { canvas.releasePointerCapture(ev.pointerId); } catch (_) {}
    });
    canvas.addEventListener('pointercancel', () => { cardPickerDialDragging = false; });
  }

  if (svWrap && !svWrap.dataset.cgSvWired) {
    svWrap.dataset.cgSvWired = '1';
    const onSvPointer = (ev) => {
      if (!isCardColorPickerOpen()) return;
      pickSvFromPlane(ev.clientX, ev.clientY);
    };
    svWrap.addEventListener('pointerdown', (ev) => {
      if (!isCardColorPickerOpen()) return;
      ev.preventDefault();
      svWrap.setPointerCapture(ev.pointerId);
      cardPickerSvDragging = true;
      onSvPointer(ev);
    });
    svWrap.addEventListener('pointermove', (ev) => {
      if (!cardPickerSvDragging || !isCardColorPickerOpen()) return;
      ev.preventDefault();
      onSvPointer(ev);
    });
    svWrap.addEventListener('pointerup', (ev) => {
      cardPickerSvDragging = false;
      try { svWrap.releasePointerCapture(ev.pointerId); } catch (_) {}
    });
    svWrap.addEventListener('pointercancel', () => { cardPickerSvDragging = false; });
  }

  if (hexInput && !hexInput.dataset.cgHexWired) {
    hexInput.dataset.cgHexWired = '1';
    hexInput.addEventListener('input', () => {
      const ok = setCardPickerFromHexString(hexInput.value);
      if (ok) drawCardHueDial();
    });
    hexInput.addEventListener('change', () => {
      const ok = setCardPickerFromHexString(hexInput.value);
      if (!ok) syncCardPickerHexField();
    });
  }

  if (applyBtn && !applyBtn.dataset.cgApplyWired) {
    applyBtn.dataset.cgApplyWired = '1';
    applyBtn.addEventListener('click', async () => {
      const cardId = cardColorPickerTargetId;
      if (!cardId) return;
      const rgb = hsvToRgb(cardPickerHsv.h, cardPickerHsv.s, cardPickerHsv.v);
      const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
      try {
        await setCardColor(cardId, hex);
        closeCardColorPickerPopup();
      } catch (err) {
        alert(err.message || 'Failed to update card color');
      }
    });
  }

  if (cancelBtn && !cancelBtn.dataset.cgCancelWired) {
    cancelBtn.dataset.cgCancelWired = '1';
    cancelBtn.addEventListener('click', () => closeCardColorPickerPopup());
  }

  if (backdrop && !backdrop.dataset.cgBackdropWired) {
    backdrop.dataset.cgBackdropWired = '1';
    backdrop.addEventListener('click', () => closeCardColorPickerPopup());
  }
}
wireCardColorPickerControls();

async function openCurrentBoardTimeline() {
  const boardId = KB.board_id;
  if (!boardId) {
    alert('Please wait for the board to load, or select a board.');
    return;
  }
  try {
    if (!KB.is_board_owner) {
      // Collaborator: open the owner's client view link in a new tab
      const res = await fetch(`${CG_FK_API}?action=get_client_view_url&board_id=${boardId}`, { credentials: 'include' });
      const data = await res.json();
      if (!data.success) {
        alert(data.message || 'Client link not available. Ask the board owner to share the timeline.');
        return;
      }
      window.open(data.url || '', '_blank', 'noopener,noreferrer');
      return;
    }
    const res = await fetch(`${CG_FT_API}?action=get_or_create_chart&board_id=${boardId}`, { credentials: 'include', cache: 'no-store' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to open timeline');
      return;
    }
    window.open(`project_timeline.php?chart_id=${data.chart_id}`, '_blank', 'noopener,noreferrer');
  } catch (err) {
    console.error('View timeline error:', err);
    alert('Failed to open timeline.');
  }
}

function openCurrentBoardCalendar() {
  const boardId = KB.board_id;
  if (!boardId) {
    alert('Please wait for the board to load, or select a board.');
    return;
  }
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  window.open(`calendar.php?board_id=${encodeURIComponent(boardId)}&month=${encodeURIComponent(month)}`, '_blank', 'noopener,noreferrer');
}
function openCurrentBoardWorkspace() {
  const boardId = KB.board_id;
  if (!boardId) {
    alert('Please wait for the board to load, or select a board.');
    return;
  }
  window.open(`workspace.php?board_id=${encodeURIComponent(boardId)}`, '_blank', 'noopener,noreferrer');
}

const cgBnTimeline = qs('#cgBottomNavTimeline');
const cgBnCalendar = qs('#cgBottomNavCalendar');
const cgBnWorkspace = qs('#cgBottomNavWorkspace');
if (cgBnTimeline) cgBnTimeline.addEventListener('click', openCurrentBoardTimeline);
if (cgBnCalendar) cgBnCalendar.addEventListener('click', openCurrentBoardCalendar);
if (cgBnWorkspace) cgBnWorkspace.addEventListener('click', openCurrentBoardWorkspace);

function cgStatsYmdLocal(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function cgStatsCardProgress(card) {
  return Math.max(0, Math.min(100, parseInt(card.progress || 0, 10)));
}

function cgStatsCardDone(card) {
  return cgStatsCardProgress(card) >= 100;
}

function cgStatsDueDateStr(card) {
  const d = card.due_date;
  if (!d || !String(d).trim()) return null;
  const s = String(d).trim().slice(0, 10);
  return /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : null;
}

function cgStatsCardDueMs(card) {
  const ds = cgStatsDueDateStr(card);
  if (!ds) return null;
  const t = card.due_time && String(card.due_time).trim() ? String(card.due_time).trim() : '23:59:59';
  const ms = Date.parse(`${ds}T${t}`);
  return Number.isNaN(ms) ? null : ms;
}

function cgStatsIsDoingColumnName(name) {
  const n = String(name || '').toLowerCase();
  return /\b(doing|in progress|wip|in review|progress)\b/.test(n);
}

function cgStatsParseUpdatedAt(card) {
  const u = card.updated_at;
  if (!u) return null;
  const t = Date.parse(String(u).replace(' ', 'T'));
  return Number.isNaN(t) ? null : t;
}

/** Same tier colors as Kanban card face .cg-card__progress-bar--low|--mid|--done */
function cgStatsCardFaceProgressColor(pct) {
  const v = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
  if (v >= 100) return '#10b981';
  if (v > 50) return '#f59e0b';
  return '#6366f1';
}

/** Stats modal bars start at 0% width; cgStatsAnimateBarsInModal runs on shown.bs.modal */
function cgStatsPrepareBar(el, pct) {
  if (!el) return;
  const p = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
  el.classList.add('cg-stat-progress-fill', 'cg-stat-progress-fill--textured');
  el.setAttribute('data-cg-width', String(p));
  el.style.width = '0%';
  el.style.backgroundColor = cgStatsCardFaceProgressColor(p);
}

function cgStatsAnimateBarsInModal() {
  const root = qs('#kanbanStatsModal');
  if (!root) return;
  const nodes = root.querySelectorAll('[data-cg-width]');
  const reduce = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const apply = () => {
    nodes.forEach((el) => {
      const w = el.getAttribute('data-cg-width');
      if (w != null && w !== '') el.style.width = `${w}%`;
    });
  };
  if (reduce) {
    apply();
    return;
  }
  /* First paint after open: 0% → target (transition on .cg-stat-progress-fill) */
  requestAnimationFrame(() => {
    requestAnimationFrame(apply);
  });
}

function refreshKanbanStatsModalContent() {
  const cols = KB.columns || [];
  const cardsBy = KB.cardsByColumn || {};
  const allCards = [];
  cols.forEach((c) => {
    (cardsBy[c.id] || []).forEach((card) => {
      allCards.push({ card, col: c });
    });
  });
  const total = allCards.length;
  const todayStr = cgStatsYmdLocal(new Date());
  const nowMs = Date.now();
  const threeDaysMs = 3 * 24 * 60 * 60 * 1000;

  let completed = 0;
  let overdue = 0;
  let dueToday = 0;
  let highPriorityOverdue = 0;
  let maxOverdueMs = 0;
  let stuckCount = 0;

  const teamMap = {};
  const teamMemberKey = (a) => {
    if (!a) return '__unassigned__';
    const id = parseInt(a.id, 10);
    if (id) return `id:${id}`;
    const n = String(a.name || '').trim();
    return n ? `name:${n.toLowerCase()}` : '__noname__';
  };
  const bumpTeamMember = (a) => {
    const key = teamMemberKey(a);
    if (!teamMap[key]) {
      teamMap[key] = {
        count: 0,
        name: a ? (String(a.name || '').trim() || 'User') : 'Unassigned',
        profile_pic: a && a.profile_pic ? a.profile_pic : null,
      };
    } else if (a && a.profile_pic && !teamMap[key].profile_pic) {
      teamMap[key].profile_pic = a.profile_pic;
    }
    teamMap[key].count += 1;
  };

  allCards.forEach(({ card, col }) => {
    const prog = cgStatsCardProgress(card);
    if (prog >= 100) completed += 1;
    const ds = cgStatsDueDateStr(card);
    if (ds && !cgStatsCardDone(card)) {
      if (ds < todayStr) {
        overdue += 1;
        const dueMs = cgStatsCardDueMs(card);
        if (dueMs != null && dueMs < nowMs) {
          const lateMs = nowMs - dueMs;
          if (lateMs > maxOverdueMs) maxOverdueMs = lateMs;
        }
        const pr = String(card.priority || '').toUpperCase();
        if (pr === 'P0' || pr === 'P1') highPriorityOverdue += 1;
      } else if (ds === todayStr) dueToday += 1;
    }
    const assignees = card.assignees || [];
    if (assignees.length) assignees.forEach((a) => bumpTeamMember(a));
    else bumpTeamMember(null);

    const doing = cgStatsIsDoingColumnName(col.name);
    const u = cgStatsParseUpdatedAt(card);
    if (doing && prog > 0 && prog < 100 && u != null && nowMs - u > threeDaysMs) stuckCount += 1;
  });

  const pctDone = total ? Math.round((completed / total) * 100) : 0;
  const pctOverdue = total ? Math.min(100, Math.round((overdue / total) * 100)) : 0;
  const pctDueToday = total ? Math.min(100, Math.round((dueToday / total) * 100)) : 0;

  const subEl = qs('#kanbanBoardSubtitle');
  const statsSub = qs('#cgStatsBoardSubtitle');
  if (statsSub) statsSub.textContent = subEl ? (subEl.textContent || '').trim() || '—' : '—';

  const setText = (id, val) => {
    const el = qs(id);
    if (el) el.textContent = val;
  };
  setText('#cgStatsTotal', String(total));
  setText('#cgStatsCompletedNum', String(completed));
  setText('#cgStatsOverdue', String(overdue));
  setText('#cgStatsDueToday', String(dueToday));

  cgStatsPrepareBar(qs('#cgStatsTotalBar'), total ? 100 : 0);
  cgStatsPrepareBar(qs('#cgStatsCompletedBar'), pctDone);
  cgStatsPrepareBar(qs('#cgStatsOverdueBar'), pctOverdue);
  cgStatsPrepareBar(qs('#cgStatsDueTodayBar'), pctDueToday);

  const colCategoryEl = qs('#cgStatsCategoryCards');
  if (colCategoryEl) {
    colCategoryEl.innerHTML = '';
    const colsWithCards = cols.filter((c) => (cardsBy[c.id] || []).length > 0);
    if (!cols.length) {
      colCategoryEl.innerHTML = '<p class="cg-stat-empty col-12 mb-0">No columns</p>';
    } else if (!colsWithCards.length) {
      colCategoryEl.innerHTML = '<p class="cg-stat-empty col-12 mb-0">No cards in any column</p>';
    } else {
      const catIcons = ['fa-briefcase', 'fa-folder', 'fa-check-double', 'fa-inbox', 'fa-list-check', 'fa-layer-group', 'fa-clipboard-list', 'fa-diagram-project'];
      colsWithCards.forEach((c, slot) => {
        const cards = cardsBy[c.id] || [];
        let sum = 0;
        cards.forEach((card) => { sum += cgStatsCardProgress(card); });
        const avg = cards.length ? Math.round(sum / cards.length) : 0;
        const color = cgStatsCardFaceProgressColor(avg);
        const name = escapeHtml(capitalizeWords(c.name || 'Column'));
        const icon = catIcons[slot % catIcons.length];
        colCategoryEl.innerHTML += `<div class="col-12 col-sm-6 col-md-4">
          <div class="cg-stat-category-card">
            <div class="cg-stat-category-card__head">
              <div class="cg-stat-category-card__titlewrap">
                <span class="cg-stat-category-card__icon" style="background:rgba(15,23,42,.06);color:${color}"><i class="fas ${icon}"></i></span>
                <span class="cg-stat-category-card__name">${name}</span>
              </div>
              <button type="button" class="cg-stat-category-card__menu" tabindex="-1" aria-hidden="true"><i class="fas fa-ellipsis-vertical"></i></button>
            </div>
            <div class="cg-stat-category-card__barline">
              <div class="cg-stat-category-card__track"><span class="cg-stat-progress-fill cg-stat-progress-fill--textured" data-cg-width="${avg}" style="width:0%;background-color:${color}"></span></div>
              <span class="cg-stat-category-card__pct">${avg}%</span>
            </div>
          </div>
        </div>`;
      });
    }
  }

  const teamEl = qs('#cgStatsTeamActivity');
  if (teamEl) {
    teamEl.innerHTML = '';
    const teamEntries = Object.values(teamMap).sort((a, b) => b.count - a.count).slice(0, 8);
    const maxTeam = teamEntries.length ? Math.max(...teamEntries.map((e) => e.count)) : 1;
    teamEntries.forEach((entry, i) => {
      const { name, profile_pic: pic, count } = entry;
      const w = Math.max(10, Math.round((count / maxTeam) * 100));
      const color = cgStatsCardFaceProgressColor(w);
      const title = escapeHtml(name);
      const src = boardMemberPicUrl(pic);
      const initials = escapeHtml(getInitials(name));
      let avatarInner;
      if (src) {
        avatarInner = `<img class="cg-stat-team-row__avatar" src="${escapeHtml(src)}" alt="${title}" title="${title}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="cg-stat-team-row__initials" style="display:none;" title="${title}">${initials}</span>`;
      } else {
        avatarInner = `<span class="cg-stat-team-row__initials" title="${title}">${initials}</span>`;
      }
      const label = `${name} ${count} card${count === 1 ? '' : 's'}`;
      teamEl.innerHTML += `<div class="cg-stat-team-row" role="group" aria-label="${escapeHtml(label)}">
        <span class="cg-stat-team-row__avatar-wrap">${avatarInner}</span>
        <div class="cg-stat-team-row__track"><div class="cg-stat-team-row__fill cg-stat-progress-fill cg-stat-progress-fill--textured" data-cg-width="${w}" style="width:0%;background-color:${color};color:#fff">${count}</div></div>
      </div>`;
    });
    if (!teamEntries.length) teamEl.innerHTML = '<p class="cg-stat-empty">No assignees</p>';
  }

  let sumProg = 0;
  allCards.forEach(({ card }) => { sumProg += cgStatsCardProgress(card); });
  const overall = total ? Math.round(sumProg / total) : 0;
  setText('#cgStatsOverallPct', `${overall}%`);
  cgStatsPrepareBar(qs('#cgStatsOverallFill'), overall);

  const alertHigh = qs('#cgStatsAlertHigh');
  const alertStuck = qs('#cgStatsAlertStuck');
  const alertEmpty = qs('#cgStatsAlertsEmpty');
  if (alertHigh && alertStuck && alertEmpty) {
    if (highPriorityOverdue > 0) {
      alertHigh.classList.remove('d-none');
      const ht = qs('#cgStatsAlertHighTitle');
      const hs = qs('#cgStatsAlertHighSub');
      if (ht) ht.textContent = `${highPriorityOverdue} high priority task${highPriorityOverdue === 1 ? '' : 's'} overdue`;
      if (hs) {
        if (maxOverdueMs > 0) {
          const hours = Math.floor(maxOverdueMs / (60 * 60 * 1000));
          hs.textContent = hours >= 1 ? `Oldest ~${hours} hour${hours === 1 ? '' : 's'} past due` : 'Past due date';
        } else hs.textContent = 'Past due date';
      }
    } else alertHigh.classList.add('d-none');

    if (stuckCount > 0) {
      alertStuck.classList.remove('d-none');
      const st = qs('#cgStatsAlertStuckTitle');
      const ss = qs('#cgStatsAlertStuckSub');
      if (st) st.textContent = `${stuckCount} task${stuckCount === 1 ? '' : 's'} stuck in progress`;
      if (ss) ss.textContent = 'Doing / In Progress columns, no update in 3+ days';
    } else alertStuck.classList.add('d-none');

    if (highPriorityOverdue === 0 && stuckCount === 0) alertEmpty.classList.remove('d-none');
    else alertEmpty.classList.add('d-none');
  }
}

function openKanbanStatsModal() {
  if (!KB.board_id) {
    alert('Please wait for the board to load, or select a board.');
    return;
  }
  refreshKanbanStatsModalContent();
  const el = qs('#kanbanStatsModal');
  if (el) {
    const isDark = document.body.classList.contains('kanban-theme-dark');
    el.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
  }
  if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(el).show();
  }
}

const kanbanStatsPreviewBtn = qs('#kanbanStatsPreviewBtn');
if (kanbanStatsPreviewBtn) kanbanStatsPreviewBtn.addEventListener('click', openKanbanStatsModal);

const CG_AUTOMATION_LS_KEY = 'cg_kanban_automations_v1';

function cgAutomationLoadAll() {
  try {
    const raw = localStorage.getItem(CG_AUTOMATION_LS_KEY);
    const o = raw ? JSON.parse(raw) : {};
    return o && typeof o === 'object' ? o : {};
  } catch (e) {
    return {};
  }
}

function cgAutomationSaveAll(data) {
  try {
    localStorage.setItem(CG_AUTOMATION_LS_KEY, JSON.stringify(data));
  } catch (e) {}
}

function cgAutomationTriggerColumnName(colId) {
  const c = (KB.columns || []).find((x) => String(x.id) === String(colId));
  return c ? String(c.name || 'Column').trim() || 'Column' : 'column';
}

function cgAutomationMemberName(memberId) {
  const m = (KB.board_members || []).find((x) => String(x.id) === String(memberId));
  return m ? String(m.name || 'Collaborator').trim() || 'Collaborator' : 'collaborator';
}

function cgAutomationTriggerLabel(value, rule) {
  if ((value === 'card_moved_to_column' || value === 'card_added_to_column' || value === 'card_added') && rule && rule.triggerColumnId) {
    const nm = cgAutomationTriggerColumnName(rule.triggerColumnId);
    return (value === 'card_added_to_column' || value === 'card_added') ? `Card is added to “${nm}”` : `Card is moved to “${nm}”`;
  }
  if (value === 'card_assigned_to_member' && rule && rule.triggerMemberId) {
    const nm = cgAutomationMemberName(rule.triggerMemberId);
    return `Card is assigned to “${nm}”`;
  }
  const map = {
    card_added: 'Card is added to a column',
    card_added_to_column: 'Card is added to a specific column',
    card_moved: 'Card is moved to any column',
    card_assigned_to_member: 'Card is assigned to a collaborator',
    card_duplicated: 'Card is duplicated',
    card_removed: 'Card is removed or archived',
    card_completed: 'Progress reaches 100%',
    progress_gte_100: 'Progress reaches or passes 100%',
    progress_eq_100: 'Progress is set exactly to 100%',
    progress_gte_75: 'Progress reaches or passes 75%',
    progress_gte_50: 'Progress reaches or passes 50%',
    progress_gte_25: 'Progress reaches or passes 25%',
    attachment_added: 'Attachment is added to a card',
    link_added: 'Link is added to a card',
    youtube_added: 'YouTube link is added to a card',
  };
  return map[value] || value;
}

function cgAutomationActionLabel(value) {
  const map = {
    move_to_column: 'Move the card to a column',
    duplicate_to_column: 'Duplicate the card to a column',
    assign_collaborator: 'Assign a collaborator',
    set_progress_100: 'Set progress bar to 100%',
    set_priority_p0: 'Set priority to P0',
    set_priority_p1: 'Set priority to P1',
    set_priority_p2: 'Set priority to P2',
    set_priority_clear: 'Clear priority',
    notify_team: 'Notify collaborators',
    rule_only: 'Store rule only',
  };
  return map[value] || value;
}

function cgAutomationFillTargetColumns() {
  const cols = KB.columns || [];
  const optsHtml = cols.length
    ? cols.map((c) => `<option value="${String(c.id)}">${escapeHtml(c.name || 'Column')}</option>`).join('')
    : '<option value="">No columns on this board</option>';
  const fill = (sel) => {
    if (!sel) return;
    const prev = sel.value;
    sel.innerHTML = optsHtml;
    if (prev && [...sel.options].some((o) => o.value === prev)) sel.value = prev;
  };
  fill(qs('#cgAutomationTargetColumn'));
  fill(qs('#cgAutomationTriggerColumn'));
}

function cgAutomationFillMembers() {
  const members = KB.board_members || [];
  const optsHtml = members.length
    ? members.map((m) => `<option value="${String(m.id)}">${escapeHtml(m.name || 'Collaborator')}</option>`).join('')
    : '<option value="">No collaborators on this board</option>';
  const fill = (sel) => {
    if (!sel) return;
    const prev = sel.value;
    sel.innerHTML = optsHtml;
    if (prev && [...sel.options].some((o) => o.value === prev)) sel.value = prev;
  };
  fill(qs('#cgAutomationTargetMember'));
  fill(qs('#cgAutomationTriggerMember'));
}

function cgAutomationToggleTriggerColumnVisibility() {
  const trig = qs('#cgAutomationWhen');
  const wrap = qs('#cgAutomationTriggerColumnWrap');
  const label = qs('#cgAutomationTriggerColumnLabel');
  const hint = qs('#cgAutomationTriggerColumnHint');
  if (!trig || !wrap) return;
  const isMoved = trig.value === 'card_moved_to_column';
  const isAdded = trig.value === 'card_added_to_column' || trig.value === 'card_added';
  wrap.style.display = (isMoved || isAdded) ? '' : 'none';
  if (label) label.textContent = isAdded ? 'When added to this column' : 'When moved to this column';
  if (hint) hint.textContent = isAdded ? 'Runs only when a new card is created in this column.' : 'Runs only when a card lands in this column (after drag or menu move).';
}

function cgAutomationToggleTriggerMemberVisibility() {
  const trig = qs('#cgAutomationWhen');
  const wrap = qs('#cgAutomationTriggerMemberWrap');
  if (!trig || !wrap) return;
  wrap.style.display = trig.value === 'card_assigned_to_member' ? '' : 'none';
}

function cgAutomationToggleTargetColumnVisibility() {
  const act = qs('#cgAutomationAction');
  const wrap = qs('#cgAutomationTargetColumnWrap');
  if (!act || !wrap) return;
  const v = act.value;
  const need = v === 'move_to_column' || v === 'duplicate_to_column';
  wrap.style.display = need ? '' : 'none';
  const hint = qs('#cgAutomationTargetColumnHint');
  if (hint) hint.textContent = need ? 'Used when the action moves or duplicates a card.' : '';
}

function cgAutomationToggleTargetMemberVisibility() {
  const act = qs('#cgAutomationAction');
  const wrap = qs('#cgAutomationTargetMemberWrap');
  if (!act || !wrap) return;
  wrap.style.display = act.value === 'assign_collaborator' ? '' : 'none';
}

function cgAutomationRenderSavedList() {
  const section = qs('#cgAutomationSavedSection');
  const ul = qs('#cgAutomationSavedList');
  if (!section || !ul) return;
  const bid = String(KB.board_id || '');
  if (!bid) {
    section.hidden = true;
    return;
  }
  const all = cgAutomationLoadAll();
  const list = Array.isArray(all[bid]) ? all[bid] : [];
  if (!list.length) {
    section.hidden = true;
    ul.innerHTML = '';
    return;
  }
  section.hidden = false;
  ul.innerHTML = list
    .map((r) => {
      const title = escapeHtml(r.name || 'Untitled rule');
      const line = escapeHtml(
        `If ${cgAutomationTriggerLabel(r.trigger, r)} → ${cgAutomationActionLabel(r.action)}`
      );
      return `<li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">
        <div class="min-w-0"><div class="fw-semibold text-truncate">${title}</div><div class="text-muted small">${line}</div></div>
        <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0 cg-automation-delete-btn" data-cg-automation-id="${escapeHtml(String(r.id))}" title="Remove rule">×</button>
      </li>`;
    })
    .join('');
  ul.querySelectorAll('.cg-automation-delete-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-cg-automation-id');
      const fresh = cgAutomationLoadAll();
      const b = String(KB.board_id || '');
      fresh[b] = (Array.isArray(fresh[b]) ? fresh[b] : []).filter((x) => String(x.id) !== String(id));
      cgAutomationSaveAll(fresh);
      cgAutomationRenderSavedList();
    });
  });
}

function openKanbanAutomationModal() {
  if (!KB.board_id) {
    alert('Please wait for the board to load, or select a board.');
    return;
  }
  if (!KB.is_board_owner) {
    alert('Only the board owner can create automations.');
    return;
  }
  cgAutomationFillTargetColumns();
  cgAutomationFillMembers();
  cgAutomationToggleTargetColumnVisibility();
  cgAutomationToggleTargetMemberVisibility();
  cgAutomationToggleTriggerColumnVisibility();
  cgAutomationToggleTriggerMemberVisibility();
  cgAutomationRenderSavedList();
  const nameInput = qs('#cgAutomationName');
  if (nameInput) nameInput.value = '';
  const el = qs('#kanbanAutomationModal');
  if (el) {
    const isDark = document.body.classList.contains('kanban-theme-dark');
    el.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
  }
  if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(el).show();
  }
}

function cgAutomationSaveRule() {
  const triggerEl = qs('#cgAutomationWhen');
  const actionEl = qs('#cgAutomationAction');
  const colEl = qs('#cgAutomationTargetColumn');
  const trigColEl = qs('#cgAutomationTriggerColumn');
  const memberEl = qs('#cgAutomationTargetMember');
  const trigMemberEl = qs('#cgAutomationTriggerMember');
  const nameEl = qs('#cgAutomationName');
  if (!triggerEl || !actionEl) return;
  const trigger = triggerEl.value;
  const action = actionEl.value;
  const bid = String(KB.board_id || '');
  if (!bid) return;
  if ((trigger === 'card_moved_to_column' || trigger === 'card_added_to_column' || trigger === 'card_added') && !(trigColEl && trigColEl.value)) {
    alert('Choose which column should trigger this rule.');
    return;
  }
  if (trigger === 'card_assigned_to_member' && !(trigMemberEl && trigMemberEl.value)) {
    alert('Choose which collaborator should trigger this rule.');
    return;
  }
  if ((action === 'move_to_column' || action === 'duplicate_to_column') && !(colEl && colEl.value)) {
    alert('Choose a target column for this action.');
    return;
  }
  if (action === 'assign_collaborator' && !(memberEl && memberEl.value)) {
    alert('Choose a collaborator for this action.');
    return;
  }
  const rule = {
    id: String(Date.now()),
    name: (nameEl && nameEl.value.trim()) || '',
    trigger,
    action,
    targetColumnId: colEl && colEl.value ? colEl.value : '',
    targetMemberId: memberEl && memberEl.value ? memberEl.value : '',
    triggerColumnId: (trigger === 'card_moved_to_column' || trigger === 'card_added_to_column' || trigger === 'card_added') && trigColEl && trigColEl.value ? trigColEl.value : '',
    triggerMemberId: trigger === 'card_assigned_to_member' && trigMemberEl && trigMemberEl.value ? trigMemberEl.value : '',
    createdAt: new Date().toISOString(),
  };
  const all = cgAutomationLoadAll();
  if (!Array.isArray(all[bid])) all[bid] = [];
  all[bid].push(rule);
  cgAutomationSaveAll(all);
  cgAutomationRenderSavedList();
  if (nameEl) nameEl.value = '';
}

function cgKanbanLinksArrFromJson(raw) {
  try {
    const a = JSON.parse(raw || '[]');
    return Array.isArray(a) ? a.map((x) => String(x || '').trim()).filter(Boolean) : [];
  } catch (e) {
    return [];
  }
}

function cgKanbanLinksAddedSince(oldArr, newArr) {
  const oldSet = new Set((oldArr || []).map((u) => String(u || '').toLowerCase()));
  return (newArr || []).filter((u) => u && !oldSet.has(String(u).toLowerCase()));
}

function cgAutomationRuleMatchesSave(rule, snap, baseline) {
  const t = rule.trigger;
  const oldP = baseline && typeof baseline.progress === 'number' ? baseline.progress : 0;
  const newP = snap.newProg;
  const oldLinks = cgKanbanLinksArrFromJson(baseline && baseline.linksJson);
  const newLinks = cgKanbanLinksArrFromJson(snap.linksJson);
  const addedLinks = cgKanbanLinksAddedSince(oldLinks, newLinks);
  if (t === 'card_completed' || t === 'progress_gte_100') {
    return newP >= 100 && oldP < 100;
  }
  if (t === 'progress_eq_100') {
    return newP === 100 && oldP !== 100;
  }
  const gte = /^progress_gte_(\d+)$/.exec(t);
  if (gte) {
    const n = parseInt(gte[1], 10);
    if (Number.isNaN(n)) return false;
    return newP >= n && oldP < n;
  }
  if (t === 'attachment_added') {
    const oldN = baseline && typeof baseline.attCount === 'number' ? baseline.attCount : 0;
    return snap.newAttCount > oldN || snap.newFileCount > 0;
  }
  if (t === 'link_added') {
    return addedLinks.some((u) => u && !extractYoutubeVideoId(u));
  }
  if (t === 'youtube_added') {
    return addedLinks.some((u) => !!extractYoutubeVideoId(u));
  }
  return false;
}

function cgAutomationRuleMatchesMove(rule, cardId, fromCol, toCol) {
  const cid = parseInt(cardId, 10) || 0;
  if (cid <= 0 || fromCol === toCol) return false;
  if (rule.trigger === 'card_moved') return true;
  if (rule.trigger === 'card_moved_to_column') {
    return String(rule.triggerColumnId || '') === String(toCol);
  }
  return false;
}

function cgAutomationRuleMatchesDuplicate(rule, sourceId, newId) {
  if (rule.trigger !== 'card_duplicated') return false;
  return (parseInt(sourceId, 10) || 0) > 0 && (parseInt(newId, 10) || 0) > 0;
}

function cgAutomationRuleMatchesCreated(rule, newCardId) {
  const cid = parseInt(newCardId, 10) || 0;
  if (cid <= 0) return false;
  if (rule.trigger === 'card_added' || rule.trigger === 'card_added_to_column') {
    if (!rule.triggerColumnId) return rule.trigger === 'card_added';
    const c = findCard(cid);
    return c && String(c.column_id || '') === String(rule.triggerColumnId || '');
  }
  return false;
}

function cgAutomationRuleMatchesAssignment(rule, cardId, oldUserIds, newUserIds) {
  const cid = parseInt(cardId, 10) || 0;
  if (cid <= 0 || rule.trigger !== 'card_assigned_to_member') return false;
  const targetId = parseInt(rule.triggerMemberId, 10) || 0;
  if (!targetId) return false;
  const oldSet = new Set((oldUserIds || []).map((id) => parseInt(id, 10)).filter(Boolean));
  const newSet = new Set((newUserIds || []).map((id) => parseInt(id, 10)).filter(Boolean));
  return newSet.has(targetId) && !oldSet.has(targetId);
}

async function cgAutomationSetCardProgress(cardId, progressVal) {
  const cid = parseInt(cardId, 10) || 0;
  if (!cid) return;
  const c = findCard(cid);
  if (!c) return;
  const p = Math.max(0, Math.min(100, parseInt(progressVal, 10) || 0));
  const cur = Math.max(0, Math.min(100, parseInt(c.progress != null ? c.progress : 0, 10) || 0));
  if (cur === p) return;
  const fd = new FormData();
  fd.append('action', 'update_card');
  fd.append('card_id', String(cid));
  fd.append('title', (c.title || '').trim());
  fd.append('description', (c.description || '').trim());
  fd.append('start_date', c.start_date || '');
  fd.append('due_date', c.due_date || '');
  fd.append('due_time', c.due_time || '');
  fd.append('start_time', c.start_time || '');
  fd.append('progress', String(p));
  fd.append('priority', (c.priority || '').trim());
  fd.append('links', JSON.stringify(getCardLinksArray(c)));
  fd.append('existing_attachments', JSON.stringify(getCardAttachmentsArray(c)));
  const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
  }
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'Failed to update card');
  await cardFormSubmitSuccess(data, null, null);
}

async function cgAutomationAssignCollaborator(cardId, memberId) {
  const cid = parseInt(cardId, 10) || 0;
  const mid = parseInt(memberId, 10) || 0;
  if (!cid || !mid) return;
  const c = findCard(cid);
  if (!c) return;
  const existingIds = Array.isArray(c.assignees)
    ? c.assignees.map((m) => parseInt(m && m.id, 10)).filter(Boolean)
    : [];
  if (existingIds.includes(mid)) return;
  const userIds = Array.from(new Set(existingIds.concat([mid])));
  const fd = new FormData();
  fd.append('card_id', String(cid));
  fd.append('user_ids', JSON.stringify(userIds));
  const res = await fetch(CG_FK_API + '?action=assign_card_members', {
    method: 'POST',
    credentials: 'include',
    body: fd
  });
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || 'Failed to assign collaborator');
  await loadKanbanData();
}

async function cgAutomationApplyRule(rule, cardId) {
  const cid = parseInt(cardId, 10) || 0;
  if (!cid) return;
  if (rule.action === 'move_to_column' && rule.targetColumnId) {
    await moveCardToColumnFromMenu(cid, rule.targetColumnId, { skipAutomation: true });
  } else if (rule.action === 'duplicate_to_column' && rule.targetColumnId) {
    await duplicateCardToColumn(cid, rule.targetColumnId, { skipAutomation: true });
  } else if (rule.action === 'assign_collaborator' && rule.targetMemberId) {
    try {
      await cgAutomationAssignCollaborator(cid, rule.targetMemberId);
    } catch (err) {
      console.error('Automation: assign collaborator failed', err);
    }
  } else if (rule.action === 'set_progress_100') {
    try {
      await cgAutomationSetCardProgress(cid, 100);
    } catch (err) {
      console.error('Automation: set progress failed', err);
    }
  }
}

async function cgAutomationRunAfterCardSave(snap, baseline) {
  if (!KB.is_board_owner || window.__cgAutomationDepth > 0) return;
  const bid = String(KB.board_id || '');
  if (!bid || !snap || !snap.cardId) return;
  const rules = cgAutomationLoadAll()[bid];
  if (!Array.isArray(rules) || !rules.length) return;
  window.__cgAutomationDepth = (window.__cgAutomationDepth || 0) + 1;
  try {
    for (const rule of rules) {
      if (!cgAutomationRuleMatchesSave(rule, snap, baseline)) continue;
      await cgAutomationApplyRule(rule, snap.cardId);
    }
  } finally {
    window.__cgAutomationDepth = Math.max(0, (window.__cgAutomationDepth || 1) - 1);
  }
}

async function cgAutomationRunAfterCardMoved(cardId, fromCol, toCol) {
  if (!KB.is_board_owner || window.__cgAutomationDepth > 0) return;
  const bid = String(KB.board_id || '');
  const rules = cgAutomationLoadAll()[bid];
  if (!Array.isArray(rules) || !rules.length) return;
  window.__cgAutomationDepth = (window.__cgAutomationDepth || 0) + 1;
  try {
    for (const rule of rules) {
      if (!cgAutomationRuleMatchesMove(rule, cardId, fromCol, toCol)) continue;
      await cgAutomationApplyRule(rule, cardId);
    }
  } finally {
    window.__cgAutomationDepth = Math.max(0, (window.__cgAutomationDepth || 1) - 1);
  }
}

async function cgAutomationRunAfterCardAssigned(cardId, oldUserIds, newUserIds) {
  if (!KB.is_board_owner || window.__cgAutomationDepth > 0) return;
  const bid = String(KB.board_id || '');
  const rules = cgAutomationLoadAll()[bid];
  if (!Array.isArray(rules) || !rules.length) return;
  window.__cgAutomationDepth = (window.__cgAutomationDepth || 0) + 1;
  try {
    for (const rule of rules) {
      if (!cgAutomationRuleMatchesAssignment(rule, cardId, oldUserIds, newUserIds)) continue;
      await cgAutomationApplyRule(rule, cardId);
    }
  } finally {
    window.__cgAutomationDepth = Math.max(0, (window.__cgAutomationDepth || 1) - 1);
  }
}

async function cgAutomationRunAfterDuplicate(sourceCardId, newCardId) {
  if (!KB.is_board_owner || window.__cgAutomationDepth > 0) return;
  const bid = String(KB.board_id || '');
  const rules = cgAutomationLoadAll()[bid];
  if (!Array.isArray(rules) || !rules.length) return;
  window.__cgAutomationDepth = (window.__cgAutomationDepth || 0) + 1;
  try {
    for (const rule of rules) {
      if (!cgAutomationRuleMatchesDuplicate(rule, sourceCardId, newCardId)) continue;
      await cgAutomationApplyRule(rule, newCardId);
    }
  } finally {
    window.__cgAutomationDepth = Math.max(0, (window.__cgAutomationDepth || 1) - 1);
  }
}

async function cgAutomationRunAfterCardCreated(newCardId) {
  if (!KB.is_board_owner || window.__cgAutomationDepth > 0) return;
  const bid = String(KB.board_id || '');
  const rules = cgAutomationLoadAll()[bid];
  if (!Array.isArray(rules) || !rules.length) return;
  window.__cgAutomationDepth = (window.__cgAutomationDepth || 0) + 1;
  try {
    for (const rule of rules) {
      if (!cgAutomationRuleMatchesCreated(rule, newCardId)) continue;
      await cgAutomationApplyRule(rule, newCardId);
    }
  } finally {
    window.__cgAutomationDepth = Math.max(0, (window.__cgAutomationDepth || 1) - 1);
  }
}

const kanbanAutomationBtn = qs('#kanbanAutomationBtn');
if (kanbanAutomationBtn) kanbanAutomationBtn.addEventListener('click', openKanbanAutomationModal);

const cgAutomationActionSelect = qs('#cgAutomationAction');
if (cgAutomationActionSelect) {
  cgAutomationActionSelect.addEventListener('change', () => {
    cgAutomationToggleTargetColumnVisibility();
    cgAutomationToggleTargetMemberVisibility();
  });
}
const cgAutomationWhenSelect = qs('#cgAutomationWhen');
if (cgAutomationWhenSelect) {
  cgAutomationWhenSelect.addEventListener('change', () => {
    cgAutomationToggleTriggerColumnVisibility();
    cgAutomationToggleTriggerMemberVisibility();
  });
}

const cgAutomationSaveBtn = qs('#cgAutomationSaveBtn');
if (cgAutomationSaveBtn) cgAutomationSaveBtn.addEventListener('click', cgAutomationSaveRule);

const kanbanStatsModalEl = qs('#kanbanStatsModal');
if (kanbanStatsModalEl) {
  kanbanStatsModalEl.addEventListener('shown.bs.modal', cgStatsAnimateBarsInModal);
}

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

  const resetSubmitState = (labelText) => {
    if (!submitBtn) return;
    submitBtn.disabled = false;
    submitBtn.innerHTML = `<i class="fas fa-save me-2"></i><span id="columnSubmitText">${labelText}</span>`;
  };
  
  if (columnId) {
    // Edit mode
    title.textContent = 'Rename Column';
    resetSubmitState('Save');
    setSubmitLabel('Save');
    formText.textContent = 'Update the column name.';
    nameInput.value = currentName;
    columnIdInput.value = columnId;
  } else {
    // Create mode
    title.textContent = 'New Column';
    resetSubmitState('Create Column');
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

const columnModalEl = qs('#columnModal');
if (columnModalEl) {
  columnModalEl.addEventListener('hidden.bs.modal', () => {
    const submitBtn = qs('#columnForm button[type="submit"]');
    const submitText = qs('#columnSubmitText');
    const isEdit = !!String(qs('#column_id')?.value || '').trim();
    if (submitBtn) {
      submitBtn.disabled = false;
      const label = isEdit ? 'Save' : 'Create Column';
      submitBtn.innerHTML = `<i class="fas fa-save me-2"></i><span id="columnSubmitText">${label}</span>`;
      if (submitText) submitText.textContent = label;
    }
  });
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
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    
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
        const res2 = await fetch(`${CG_FK_API}?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'include' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            applyPrioritySortToCardsByColumn(KB.cardsByColumn);
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
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    
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
        const res2 = await fetch(`${CG_FK_API}?action=bootstrap&project_id=${KB.project_id}&board_id=${KB.board_id}`, { credentials: 'include' });
        if (res2.ok) {
          const d2 = await res2.json();
          if (d2.success) {
            KB.columns = d2.columns || [];
            KB.cardsByColumn = d2.cardsByColumn || {};
            applyPrioritySortToCardsByColumn(KB.cardsByColumn);
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
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
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
  pasteCardColumnModalEl.addEventListener('hide.bs.modal', () => {
    focusKanbanSurface();
  });
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

initKanbanTemplatesMenu();
initCardSearchUI();

loadKanbanData().catch(err => {
  console.error('Kanban bootstrap failed:', err);
  const msg = err.message || 'Failed to load Kanban';
  qs('#kanbanRoot').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to load Kanban.<br><small class="text-muted mt-2">${escapeHtml(msg)}</small></div>`;
});

document.addEventListener('contextmenu', function(e) {
  if (e.target && e.target.closest && e.target.closest('.modal')) {
    return;
  }
  const cardEl = e.target.closest('.cg-card');
  if (cardEl) {
    const cardId = parseInt(cardEl.getAttribute('data-card-id') || '0', 10);
    if (cardId) {
      closeCardColorPickerPopup();
      openCardContextMenu(e, cardId);
      return;
    }
  }
  closeCardContextMenu();
  e.preventDefault();
});

document.addEventListener('click', function(e) {
  const t = e.target;
  if (t && t.nodeType === 1) {
    if (t.closest('#cardColorPickerPopup')) return;
    if (t.closest('#cardContextMenu')) return;
    if (t.closest('#cardColorMenu')) return;
    if (t.closest('#cardColumnFlyout')) return;
  }
  closeCardColorMenu();
  closeCardColumnFlyout();
  closeCardContextMenu();
  if (t && t.closest && t.closest('.cg-card.cg-card--search-breathing')) {
    clearCardSearchBreathingFocus();
    return;
  }
  if (t && t.closest && t.closest('#kanbanRoot') && !t.closest('.cg-card')) {
    clearCardSearchBreathingFocus();
  }
  if (!(t && t.closest && t.closest('#kanbanCardSearchWrap'))) {
    hideCardSearchDropdown();
  }
});
document.addEventListener('scroll', () => {
  closeCardColorPickerPopup();
  closeCardColorMenu();
  closeCardColumnFlyout();
  closeCardContextMenu();
}, true);
window.addEventListener('resize', () => {
  closeCardColorPickerPopup();
  closeCardColorMenu();
  closeCardContextMenu();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (isCardColorPickerOpen()) {
      closeCardColorPickerPopup();
      return;
    }
    closeCardColorMenu();
    closeCardContextMenu();
  }
});
document.addEventListener('dragstart', function(e) {
  const cardEl = e.target.closest('.cg-card');
  if (!cardEl || !e.dataTransfer) return;
  const cardId = parseInt(cardEl.getAttribute('data-card-id') || '0', 10);
  if (!cardId) return;
  const payload = buildWorkspaceCardDragPayload(cardId);
  if (!payload) return;
  const json = JSON.stringify(payload);
  try {
    localStorage.setItem('cgWorkspaceDraggedKanbanCard', JSON.stringify({
      at: Date.now(),
      payload
    }));
  } catch (err) {}
  e.dataTransfer.effectAllowed = 'copyMove';
  try {
    e.dataTransfer.setData('application/x-cinegrid-kanban-card', json);
  } catch (err) {}
  try {
    e.dataTransfer.setData('text/x-cinegrid-kanban-card', json);
  } catch (err) {}
  try {
    e.dataTransfer.setData('text/plain', 'CINEGRID_KANBAN_CARD:' + json);
  } catch (err) {}
  try {
    e.dataTransfer.setData('text', 'CINEGRID_KANBAN_CARD:' + json);
  } catch (err) {}
});

applyKanbanTheme(getStoredKanbanTheme());

document.addEventListener('paste', function(e) {
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

// Click and drag scrolling for Kanban board
(function() {
  const kanbanRoot = qs('#kanbanRoot');
  if (!kanbanRoot) return;

  let isPanning = false;
  let activePointerId = null;
  let startX = 0;
  let startY = 0;
  let panBoardStrip = null;
  let panPageMain = null;
  let scrollLeftStart = 0;
  let scrollTopStart = 0;

  function endPan(pointerId) {
    if (pointerId != null && activePointerId != null && pointerId !== activePointerId) return;
    isPanning = false;
    activePointerId = null;
    panBoardStrip = null;
    panPageMain = null;
    kanbanRoot.classList.remove('is-panning');
  }

  kanbanRoot.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    if (e.target.closest('button, a, input, select, textarea, .cg-card, .cg-addBtn, .cg-addColBtn')) return;

    panPageMain = kanbanRoot.closest('.fmain-content');
    panBoardStrip = kanbanRoot.closest('.cg-kb-board-chat-row__board');
    if (!panPageMain && !panBoardStrip) return;

    isPanning = true;
    activePointerId = e.pointerId;
    startX = e.clientX;
    startY = e.clientY;
    scrollLeftStart = panBoardStrip ? panBoardStrip.scrollLeft : (panPageMain ? panPageMain.scrollLeft : 0);
    scrollTopStart = panPageMain ? panPageMain.scrollTop : (panBoardStrip ? panBoardStrip.scrollTop : 0);
    kanbanRoot.classList.add('is-panning');

    try { kanbanRoot.setPointerCapture(e.pointerId); } catch (_) {}
  });

  window.addEventListener('pointermove', (e) => {
    if (!isPanning || activePointerId !== e.pointerId) return;
    if (!panPageMain && !panBoardStrip) return;

    e.preventDefault();
    const walkX = (e.clientX - startX) * 2;
    const walkY = (e.clientY - startY) * 2;
    if (panBoardStrip) panBoardStrip.scrollLeft = scrollLeftStart - walkX;
    else if (panPageMain) panPageMain.scrollLeft = scrollLeftStart - walkX;
    if (panPageMain) panPageMain.scrollTop = scrollTopStart - walkY;
    else if (panBoardStrip) panBoardStrip.scrollTop = scrollTopStart - walkY;
  }, { passive: false });

  window.addEventListener('pointerup', (e) => {
    try { kanbanRoot.releasePointerCapture(e.pointerId); } catch (_) {}
    endPan(e.pointerId);
  });

  window.addEventListener('pointercancel', (e) => {
    try { kanbanRoot.releasePointerCapture(e.pointerId); } catch (_) {}
    endPan(e.pointerId);
  });
})();
</script>
<?php if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL): ?>
</main>
<?php endif; ?>
<?php if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL): ?>
</body>
</html>
<?php endif; ?>
