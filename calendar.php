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

cg_require_freelancer_or_linked();
cg_ensure_freelance_tables();

// Docroot bridges — /api/*.php returns empty HTTP 500 on this host.
$cg_freelance_kanban_api_url = 'kanban_api.php';
$cg_freelance_timeline_api_url = 'kanban_timeline_api.php';
$cg_kanban_static_origin = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) ? 'https://kanban.cinegrid.net' : '';
$cg_kanban_image_preview_url = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL)
    ? 'https://kanban.cinegrid.net/api/kanban_image_preview.php'
    : '/api/kanban_image_preview.php';
$cg_kanban_attachment_url = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL)
    ? 'https://kanban.cinegrid.net/api/kanban_attachment.php'
    : 'api/kanban_attachment.php';
$cg_kanban_profile_pic_url = (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL)
    ? 'https://kanban.cinegrid.net/api/profile_pic.php'
    : 'https://kanban.cinegrid.net/api/profile_pic.php';

$page_title = 'Content Calendar';
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
<div class="fmain-content cg-calendar-page cg-page--bottom-nav" style="overflow-x: hidden; overflow-y: auto;">
    <div class="cg-portal-loading-overlay" id="calendarLoadingOverlay" hidden aria-hidden="true">
        <div class="cg-portal-loading-card cg-portal-loading-card--message-only" role="status" aria-live="polite">
            <div class="cg-portal-loading-spinner" aria-hidden="true"></div>
            <div class="cg-portal-loading-subtitle">Loading your board...</div>
        </div>
    </div>
    <div class="container-fluid cg-calendar-container">
        <div class="cg-calendar-header">
            <div>
                <h2 class="mb-1" id="calendarBoardTitle" style="font-weight:800;">Content Calendar</h2>
                <div class="text-muted" id="calendarBoardSubtitle">Monthly content calendar for scheduled and posted posts.</div>
            </div>
            <div class="cg-calendar-actions">
                <div class="cg-calendar-board-controls">
                    <div class="cg-board-members-wrap d-flex align-items-center flex-shrink-0" id="boardMembersWrap">
                        <div class="cg-board-avatars d-flex align-items-center" id="boardMembersAvatars" aria-label="Board collaborators"></div>
                    </div>
                    <div class="cg-calendar-card-search-wrap" id="calendarCardSearchWrap">
                        <input class="form-control" id="calendarCardSearchInput" type="text" placeholder="Search cards..." autocomplete="off" aria-label="Search cards" />
                        <div class="cg-calendar-card-search-dropdown" id="calendarCardSearchDropdown" hidden></div>
                    </div>
                    <select class="form-select" id="calendarBoardSelect" aria-label="Select board"></select>
                </div>
            </div>
        </div>

        <div class="cg-calendar-toolbar">
            <div class="cg-calendar-month-nav" role="group" aria-label="Month navigation">
                <button class="btn btn-outline-dark" type="button" id="calendarPrevMonthBtn" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                <div class="cg-calendar-month" id="calendarMonthLabel">Month</div>
                <button class="btn btn-outline-dark" type="button" id="calendarNextMonthBtn" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="cg-calendar-toolbar-actions">
                <div class="cg-calendar-assignment-filter" id="calendarAssignmentFilter" role="group" aria-label="Card assignment filter">
                    <button type="button" class="cg-calendar-assignment-filter__btn" data-calendar-assignment-filter="mine">My cards</button>
                    <button type="button" class="cg-calendar-assignment-filter__btn" data-calendar-assignment-filter="all">All cards</button>
                </div>
                <button type="button" class="cg-calendar-hide-ongoing-btn" id="calendarHideOngoingBtn" aria-pressed="false" title="Hide On-Going (middle-day) cards">Hide On-Going</button>
                <button class="btn btn-danger" type="button" id="calendarShareBtn" title="Share (client link)" aria-label="Share (client link)">
                    <i class="fas fa-share-alt" aria-hidden="true"></i>
                </button>
                <?php cg_kanban_echo_header_procurement_button(); ?>
                <?php if (!defined('CG_KANBAN_SUBDOMAIN_PORTAL') || !CG_KANBAN_SUBDOMAIN_PORTAL): ?>
                <button class="btn btn-outline-dark" id="themeToggleBtn" type="button" title="Toggle dark mode" aria-label="Toggle dark mode"><i class="fas fa-moon" aria-hidden="true"></i></button>
                <?php endif; ?>
            </div>
        </div>

        <div class="cg-calendar-shell">
            <div class="cg-calendar-grid-wrap">
                <div class="cg-calendar-weekdays" id="calendarWeekdays"></div>
                <div class="cg-calendar-grid" id="calendarGrid"></div>
            </div>
            <div class="cg-calendar-undated" id="calendarUndatedWrap" style="display:none;">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <h3 class="mb-0">Unscheduled Content</h3>
                    <span class="text-muted small">Cards without a start or due date</span>
                </div>
                <div class="cg-calendar-undated-list" id="calendarUndatedList"></div>
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
    <button type="button" class="cg-bottom-nav__btn" id="cgBottomNavTimeline">
        <i class="fas fa-diagram-project" aria-hidden="true"></i>
        <span>Timeline</span>
    </button>
    <button type="button" class="cg-bottom-nav__btn is-active" id="cgBottomNavCalendar" aria-current="page">
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

<div class="modal fade" id="calendarCardDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content calendar-detail-modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i>Content Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="calendarCardDetailModalBody"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarAttachmentPasswordModal" tabindex="-1" aria-labelledby="calendarAttachmentPasswordModalTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="calendarAttachmentPasswordModalTitle"><i class="fas fa-lock me-2 text-danger"></i>Attachment password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-muted small mb-3" id="calendarAttachmentPasswordModalHint"></p>
        <div class="mb-3">
          <label class="form-label fw-semibold small" for="calendarAttachmentPasswordInput">Password</label>
          <input type="password" class="form-control" id="calendarAttachmentPasswordInput" autocomplete="current-password" />
        </div>
        <div class="text-danger small mt-2" id="calendarAttachmentPasswordModalError" style="display:none;"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="calendarAttachmentPasswordModalSubmit">View</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarCardPasswordModal" tabindex="-1" aria-labelledby="calendarCardPasswordModalTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="calendarCardPasswordModalTitle"><i class="fas fa-lock me-2 text-danger"></i>Enter password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-muted small mb-3" id="calendarCardPasswordModalHint"></p>
        <div class="mb-3">
          <label class="form-label fw-semibold small" for="calendarCardPasswordInput">Password</label>
          <input type="password" class="form-control" id="calendarCardPasswordInput" autocomplete="current-password" />
        </div>
        <div class="text-danger small mt-2" id="calendarCardPasswordModalError" style="display:none;"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="calendarCardPasswordModalSubmit">Open</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarAddCardModal" tabindex="-1" aria-hidden="true" aria-labelledby="calendarAddCardModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content calendar-add-card-modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title" id="calendarAddCardModalTitle"><i class="fas fa-clipboard-list me-2"></i>New card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="calendarAddCardForm">
        <p class="text-muted small mb-3" id="calendarAddCardDateHint"></p>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarAddCardColumn">Column</label>
          <select class="form-select" id="calendarAddCardColumn" name="column_id" required></select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarAddCardTitle">Title</label>
          <input type="text" class="form-control" id="calendarAddCardTitle" name="title" required placeholder="Card title" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarAddCardDescription">Description</label>
          <textarea class="form-control" id="calendarAddCardDescription" name="description" rows="4" placeholder="Add details, checklist, links…"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarAddCardPriority">Priority</label>
          <select class="form-select" id="calendarAddCardPriority" name="priority">
            <option value="">No Priority</option>
            <option value="P0">P0 - Critical</option>
            <option value="P1">P1 - High</option>
            <option value="P2">P2 - Medium</option>
            <option value="P3">P3 - Low</option>
          </select>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="calendarAddCardStart">Start date</label>
            <input type="date" class="form-control" id="calendarAddCardStart" name="start_date" />
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="calendarAddCardDue">Due date</label>
            <input type="date" class="form-control" id="calendarAddCardDue" name="due_date" />
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="calendarAddCardDueTime">Due time</label>
            <input type="time" class="form-control" id="calendarAddCardDueTime" name="due_time" step="60" />
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="calendarAddCardSubmit"><i class="fas fa-plus me-2"></i>Create card</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarEditCardModal" tabindex="-1" aria-hidden="true" aria-labelledby="calendarEditCardModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content calendar-add-card-modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title" id="calendarEditCardModalTitle"><i class="fas fa-clipboard-list me-2"></i>Edit card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="calendarEditCardForm">
        <input type="hidden" id="calendarEditCardId" name="card_id" value="" />
        <input type="hidden" id="calendarEditCardStartTimePreserved" value="" />
        <input type="hidden" id="calendarEditCardLinksPreserved" value="[]" />
        <p class="text-muted small mb-3" id="calendarEditCardHint"></p>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarEditCardTitle">Title</label>
          <input type="text" class="form-control" id="calendarEditCardTitle" name="title" required placeholder="Card title" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarEditCardDescription">Description</label>
          <textarea class="form-control" id="calendarEditCardDescription" name="description" rows="4" placeholder="Add details, checklist, links…"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarEditCardPriority">Priority</label>
          <select class="form-select" id="calendarEditCardPriority" name="priority">
            <option value="">No Priority</option>
            <option value="P0">P0 - Critical</option>
            <option value="P1">P1 - High</option>
            <option value="P2">P2 - Medium</option>
            <option value="P3">P3 - Low</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="calendarEditCardProgress">Progress: <span id="calendarEditCardProgressVal">0</span>%</label>
          <input type="range" class="form-range" id="calendarEditCardProgress" name="progress" min="0" max="100" value="0" step="5" />
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="calendarEditCardStart">Start date</label>
            <input type="date" class="form-control" id="calendarEditCardStart" name="start_date" />
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="calendarEditCardDue">Due date</label>
            <input type="date" class="form-control" id="calendarEditCardDue" name="due_date" />
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="calendarEditCardDueTime">Due time</label>
            <input type="time" class="form-control" id="calendarEditCardDueTime" name="due_time" step="60" />
          </div>
        </div>
        <input type="hidden" id="calendarEditCardAttachmentsJson" value="[]" />
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="calendarEditCardSubmit"><i class="fas fa-save me-2"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarEmptyDayModal" tabindex="-1" aria-hidden="true" aria-labelledby="calendarEmptyDayModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content calendar-add-card-modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title" id="calendarEmptyDayModalTitle">Empty slot</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2" id="calendarEmptyDayIntro"></p>
        <ul class="list-group list-group-flush mb-3 small border rounded overflow-auto" id="calendarEmptyDayList" style="max-height: 12rem;"></ul>
        <div id="calendarEmptyDayMoveSection" class="mb-3" style="display: none;">
          <label class="form-label fw-semibold mb-1" for="calendarEmptyDayMoveColumn">Move all to column</label>
          <p class="text-muted small mb-2" id="calendarEmptyDayMoveHint"></p>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <select class="form-select flex-grow-1" id="calendarEmptyDayMoveColumn" style="min-width: 12rem;"></select>
            <button type="button" class="btn btn-outline-dark flex-shrink-0" id="calendarEmptyDayMoveBtn">Move all</button>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pt-2 border-top">
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="calendarEmptyDayClearBtn">Clear from calendar</button>
            <button type="button" class="btn btn-outline-danger" id="calendarEmptyDayDeleteBtn">Delete all cards</button>
          </div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarDeleteCardConfirmModal" tabindex="-1" aria-labelledby="calendarDeleteCardConfirmTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content calendar-delete-card-confirm-modal" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="calendarDeleteCardConfirmTitle"><i class="fas fa-trash-alt me-2 text-danger"></i>Delete card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="mb-0">Are you sure you want to delete this card? This cannot be undone.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="calendarDeleteCardConfirmCancel">Cancel</button>
        <button type="button" class="btn btn-danger" id="calendarDeleteCardConfirmOk"><i class="fas fa-trash me-2"></i>Delete permanently</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarAssignCardModal" tabindex="-1" aria-hidden="true" aria-labelledby="calendarAssignCardModalTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content calendar-assign-card-modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title" id="calendarAssignCardModalTitle"><i class="fas fa-user-plus me-2"></i>Assign card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form class="modal-body" id="calendarAssignCardForm">
        <input type="hidden" id="calendar_assign_card_id" value="" />
        <div class="small text-muted mb-3" id="calendarAssignCardTitlePreview">Choose collaborators for this card.</div>
        <div class="cg-assign-list" id="calendarAssignCardMembersList"></div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fas fa-check me-2"></i>Save assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="calendarShareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content calendar-add-card-modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-share-alt me-2"></i>Share calendar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted mb-2">Share this link with your client (read-only):</div>
        <input class="form-control" id="calendarShareLink" readonly />
        <div class="d-grid mt-3">
          <button class="btn btn-danger" id="calendarCopyShareBtn"><i class="fas fa-copy me-2"></i>Copy link</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="cg-calendar-day-menu" id="calendarDayContextMenu" role="menu" hidden aria-hidden="true">
  <button type="button" class="cg-calendar-day-menu__item" role="menuitem" id="calendarDayMenuAddCard">
    <i class="fas fa-plus" aria-hidden="true"></i><span>Add card</span>
  </button>
  <button type="button" class="cg-calendar-day-menu__item" role="menuitem" id="calendarDayMenuEmptySlot">
    <i class="fas fa-eraser" aria-hidden="true"></i><span>Empty slot…</span>
  </button>
</div>

<div class="cg-calendar-day-menu cg-calendar-card-menu" id="calendarCardContextMenu" role="menu" hidden aria-hidden="true">
  <button type="button" class="cg-calendar-day-menu__item" role="menuitem" id="calendarCardMenuEdit">
    <i class="fas fa-pen" aria-hidden="true"></i><span>Edit card</span>
  </button>
  <button type="button" class="cg-calendar-day-menu__item" role="menuitem" id="calendarCardMenuAssign">
    <i class="fas fa-user-plus" aria-hidden="true"></i><span>Assign</span>
  </button>
  <button type="button" class="cg-calendar-day-menu__item cg-calendar-day-menu__item--danger" role="menuitem" id="calendarCardMenuDelete">
    <i class="fas fa-trash" aria-hidden="true"></i><span>Delete card</span>
  </button>
</div>

<style>
.cg-calendar-page {
    overflow-x: hidden;
    overflow-y: auto;
    min-height: 100vh;
    background-color: #f8fafc;
    --cg-dot-color: rgba(15, 23, 42, 0.08);
    --cg-grid-size: 18px;
    background-image: radial-gradient(circle, var(--cg-dot-color) 1.2px, transparent 1.6px);
    background-size: var(--cg-grid-size) var(--cg-grid-size);
}
body.cg-kanban-portal.cg-kanban-fullpage .fmain-content.cg-calendar-page {
    flex: 1 1 auto;
    min-height: 0 !important;
    height: auto !important;
    max-height: none !important;
    box-sizing: border-box;
}
body.cg-kanban-portal .cg-calendar-container.container-fluid {
    padding-top: 16px !important;
    padding-bottom: 12px !important;
    padding-left: max(1.25rem, env(safe-area-inset-left, 0px)) !important;
    padding-right: max(1.25rem, env(safe-area-inset-right, 0px)) !important;
}
@media (min-width: 576px) {
    body.cg-kanban-portal .cg-calendar-container.container-fluid {
        padding-left: max(1.5rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(1.5rem, env(safe-area-inset-right, 0px)) !important;
    }
}
@media (min-width: 992px) {
    body.cg-kanban-portal .cg-calendar-container.container-fluid {
        padding-left: max(2rem, env(safe-area-inset-left, 0px)) !important;
        padding-right: max(2rem, env(safe-area-inset-right, 0px)) !important;
    }
}
.cg-calendar-container {
    padding: 12px 32px;
    max-width: 100%;
    width: 100%;
}
.cg-calendar-shell {
    background: rgba(255,255,255,0.78);
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 24px;
    padding: 22px;
    box-shadow: 0 20px 40px rgba(15,23,42,0.08);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.cg-calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.cg-calendar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    justify-content: flex-end;
    min-width: 0;
    max-width: 100%;
}
.cg-calendar-board-controls {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 10px;
    min-width: 0;
    max-width: 100%;
}
/* Board members — match Kanban facepile (beats header img{height:auto}) */
.cg-calendar-page #boardMembersAvatars.cg-board-avatars {
    display: inline-flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    flex-shrink: 0;
    min-width: 0;
}
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap {
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
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap.is-active-presence {
    --presence-color: #93c5fd;
}
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap:first-child {
    margin-left: 0;
}
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap img.cg-board-avatar {
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
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap .cg-board-avatar-initials {
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
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap.is-active-presence img.cg-board-avatar,
.cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap.is-active-presence .cg-board-avatar-initials {
    border-width: 3px !important;
    border-color: var(--presence-color, #93c5fd) !important;
}
.kanban-theme-dark .cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap img.cg-board-avatar,
.kanban-theme-dark .cg-calendar-page #boardMembersAvatars .cg-board-avatar-wrap .cg-board-avatar-initials {
    border-color: #fff !important;
}
#calendarBoardSelect {
    flex: 0 1 240px;
    min-width: 180px;
    max-width: 320px;
}
.cg-calendar-card-search-wrap {
    position: relative;
    flex: 0 1 260px;
    min-width: 200px;
    max-width: 320px;
}
#calendarCardSearchInput {
    min-width: 200px;
    max-width: 320px;
}
.cg-calendar-card-search-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    z-index: 1090;
    max-height: 300px;
    overflow-y: auto;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    background: #fff;
    box-shadow: 0 16px 40px -8px rgba(15, 23, 42, 0.18);
    padding: 0.35rem 0;
}
.cg-calendar-card-search-dropdown[hidden] { display: none !important; }
.cg-calendar-card-search-item {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    display: block;
    padding: 0.5rem 0.8rem;
    line-height: 1.25;
}
.cg-calendar-card-search-item:hover,
.cg-calendar-card-search-item:focus-visible,
.cg-calendar-card-search-item.is-active {
    background: rgba(15, 23, 42, 0.06);
    outline: none;
}
.cg-calendar-card-search-item__title {
    display: block;
    font-weight: 600;
    color: #111827;
}
.cg-calendar-card-search-item__meta {
    display: block;
    font-size: 0.78rem;
    color: #475569;
    margin-top: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@keyframes cgCalendarSearchBreathing {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 8px 18px rgba(2,6,23,0.16), 0 0 0 0 rgba(220,53,69,0.28);
    }
    50% {
        transform: scale(1.012);
        box-shadow: 0 12px 26px rgba(2,6,23,0.2), 0 0 0 8px rgba(220,53,69,0.11);
    }
}
.cg-calendar-chip--search-breathing {
    animation: cgCalendarSearchBreathing 1.35s ease-in-out infinite;
    border-color: rgba(220,53,69,0.58) !important;
}
.cg-calendar-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.cg-calendar-month-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.cg-calendar-month {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.cg-calendar-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.cg-calendar-assignment-filter {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 3px;
    border: 1px solid rgba(15,23,42,0.1);
    border-radius: 999px;
    background: rgba(255,255,255,0.78);
    box-shadow: 0 8px 18px rgba(15,23,42,0.06);
}
.cg-calendar-assignment-filter__btn {
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #475569;
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.56rem 0.78rem;
    white-space: nowrap;
}
.cg-calendar-assignment-filter__btn.is-active {
    background: #0f172a;
    color: #fff;
    box-shadow: 0 8px 18px rgba(15,23,42,0.16);
}
.cg-calendar-hide-ongoing-btn {
    border: 1px solid rgba(15,23,42,0.1);
    border-radius: 999px;
    background: rgba(255,255,255,0.78);
    color: #475569;
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1;
    padding: 0.62rem 0.9rem;
    white-space: nowrap;
    box-shadow: 0 8px 18px rgba(15,23,42,0.06);
}
.cg-calendar-hide-ongoing-btn.is-active {
    background: #0f172a;
    color: #fff;
    border-color: #0f172a;
    box-shadow: 0 8px 18px rgba(15,23,42,0.16);
}
.cg-calendar-grid-wrap {
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 22px;
    overflow: hidden;
    background: rgba(255,255,255,0.92);
}
.cg-calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    background: rgba(248,250,252,0.96);
    border-bottom: 1px solid rgba(15,23,42,0.08);
}
.cg-calendar-weekday {
    padding: 12px 14px;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #475569;
}
.cg-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
}
.cg-calendar-day {
    min-height: 152px;
    padding: 12px;
    border-right: 1px solid rgba(15,23,42,0.06);
    border-bottom: 1px solid rgba(15,23,42,0.06);
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: rgba(255,255,255,0.75);
}
.cg-calendar-day:nth-child(7n) {
    border-right: none;
}
.cg-calendar-day.is-other-month {
    background: rgba(248,250,252,0.88);
}
.cg-calendar-day.is-today {
    background: rgba(239,246,255,0.95);
    box-shadow: inset 0 0 0 1px #dc2626;
}
.cg-calendar-day-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.cg-calendar-day-number {
    font-weight: 800;
    color: #0f172a;
}
.cg-calendar-day.is-other-month .cg-calendar-day-number {
    color: #94a3b8;
}
.cg-calendar-day-count {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 700;
}
.cg-calendar-day-items {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-height: 0;
    flex: 1 1 auto;
}
.cg-calendar-day-minis {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.cg-calendar-chip[draggable="true"] {
    cursor: grab;
    user-select: none;
    -webkit-user-select: none;
}
.cg-calendar-chip[draggable="true"]:active {
    cursor: grabbing;
}
.cg-calendar-chip.is-selected {
    outline: 2px solid #0ea5e9;
    outline-offset: 1px;
    box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.35);
}
.kanban-theme-dark .cg-calendar-chip.is-selected {
    outline-color: #38bdf8;
    box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.4);
}
.cg-calendar-day.is-drop-target {
    box-shadow: inset 0 0 0 2px #0ea5e9;
    background: color-mix(in srgb, #0ea5e9 8%, transparent);
    border-radius: 4px;
}
.kanban-theme-dark .cg-calendar-day.is-drop-target {
    box-shadow: inset 0 0 0 2px #38bdf8;
    background: color-mix(in srgb, #38bdf8 12%, rgba(15,23,42,0.5));
}
.cg-calendar-chip {
    --chip-accent: #6366f1;
    position: relative;
    overflow: visible;
    display: block;
    cursor: pointer;
    text-decoration: none;
    border-radius: 12px;
    padding: 8px 10px 22px;
    border: 1px solid color-mix(in srgb, var(--chip-accent) 24%, white);
    background: color-mix(in srgb, var(--chip-accent) 11%, white);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.45);
}
.cg-calendar-chip__assignees {
    position: absolute;
    right: 8px;
    bottom: 5px;
    display: flex;
    align-items: center;
}
.cg-calendar-chip__assignee-avatar,
.cg-calendar-chip__assignee-more {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    border: 2px solid rgba(255,255,255,0.95);
    margin-left: -7px;
    box-shadow: 0 2px 8px rgba(2,6,23,0.1);
    object-fit: cover;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 9px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cg-calendar-chip__assignee-avatar:first-child,
.cg-calendar-chip__assignee-more:first-child {
    margin-left: 0;
}
.cg-calendar-chip__assignee-avatar.is-initials {
    background: #dbeafe;
    color: #1d4ed8;
}
.cg-calendar-chip__assignee-more {
    background: #0f172a;
    color: #fff;
}
.bubble-assignees {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}
.bubble-assignee-avatar {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 2px solid rgba(15,23,42,0.08);
    object-fit: cover;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 12px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.bubble-assignee-avatar.is-initials {
    background: #dbeafe;
    color: #1d4ed8;
}
.cg-calendar-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 18px rgba(15,23,42,0.08);
}
/* Posted keeps per-card accent (same as scheduled); green stripe = posted status */
.cg-calendar-chip--posted {
    border-color: color-mix(in srgb, var(--chip-accent) 26%, rgba(16,185,129,0.22));
    background: color-mix(in srgb, var(--chip-accent) 11%, white);
    box-shadow: inset 3px 0 0 rgba(16, 185, 129, 0.62), inset 0 1px 0 rgba(255,255,255,0.45);
}
.cg-calendar-chip--posted:hover {
    box-shadow: 0 10px 18px rgba(15,23,42,0.08), inset 3px 0 0 rgba(16, 185, 129, 0.62), inset 0 1px 0 rgba(255,255,255,0.45);
}
.cg-calendar-chip--undated {
    --chip-accent: #94a3b8;
}
.cg-calendar-chip--mini {
    padding: 3px 8px;
    border-radius: 8px;
}
.cg-calendar-chip--mini .cg-calendar-chip-title {
    font-size: 11px;
    line-height: 1.2;
}
.cg-calendar-chip-title {
    font-family: "Inter", sans-serif;
    font-size: 0.82rem;
    line-height: 1.25;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
}
.cg-calendar-chip-title-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 1 1 auto;
}
.cg-calendar-chip-lock {
    flex-shrink: 0;
    color: #dc2626;
    font-size: 0.68rem;
    opacity: 0.9;
}
.cg-calendar-chip-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 4px;
    font-size: 0.72rem;
    color: #475569;
}
.cg-calendar-chip-meta > span:not(.cg-calendar-chip-status) {
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cg-calendar-chip-time {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    margin-top: 3px;
    letter-spacing: 0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cg-calendar-chip-status {
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.cg-calendar-empty {
    color: #cbd5e1;
    font-size: 0.8rem;
    font-weight: 700;
    padding-top: 4px;
}
.cg-calendar-undated {
    margin-top: 18px;
    background: rgba(255,255,255,0.9);
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 20px;
    padding: 18px;
}
.cg-calendar-undated h3 {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
}
.cg-calendar-undated-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
}
.cg-calendar-loading {
    grid-column: 1 / -1;
    padding: 48px 16px;
    text-align: center;
}
.calendar-detail-modal-content {
    border-radius: 16px;
}
.calendar-detail-modal-content .bubble-title { font-family: "Inter", sans-serif; font-weight: 800; color: #0f172a; margin-bottom: 10px; font-size: 18px; }
.calendar-detail-modal-content .bubble-meta { color: #64748b; font-size: 13px; margin-bottom: 8px; }
.calendar-detail-modal-content .bubble-desc { color: #334155; margin-bottom: 14px; white-space: pre-wrap; line-height: 1.5; }
.calendar-detail-modal-content .bubble-desc.bubble-desc--html {
    white-space: normal;
    word-break: break-word;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html p,
.calendar-detail-modal-content .bubble-desc.bubble-desc--html div {
    margin: 0 0 0.5rem;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html p:last-child,
.calendar-detail-modal-content .bubble-desc.bubble-desc--html div:last-child { margin-bottom: 0; }
.calendar-detail-modal-content .bubble-desc.bubble-desc--html a { color: #0d6efd; word-break: break-all; }
.calendar-detail-modal-content .bubble-desc.bubble-desc--html ul.cg-desc-checklist {
    list-style: none;
    padding-left: 0;
    margin: 0.35rem 0 0.5rem;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task {
    position: relative;
    padding-left: 1.5rem;
    min-height: 1.35rem;
    margin: 0.2rem 0;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.2rem;
    width: 1rem;
    height: 1rem;
    border: 2px solid #94a3b8;
    border-radius: 3px;
    background: #fff;
    box-sizing: border-box;
    pointer-events: none;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task[data-checked="1"]:before {
    background: #fff;
    border-color: #dc2626;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task[data-checked="1"]:after {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: calc(0.2rem + 0.5rem);
    width: 0.2rem;
    height: 0.38rem;
    border: solid #dc2626;
    border-width: 0 2px 2px 0;
    box-sizing: border-box;
    transform: translate(-50%, -50%) rotate(45deg);
    transform-origin: center center;
    pointer-events: none;
}
.calendar-detail-modal-content .bubble-desc.bubble-desc--html .cg-desc-emoji {
    font-size: 1.15em;
    line-height: 1.3;
}
.calendar-detail-modal-content .bubble-progress-wrap { margin-bottom: 14px; display: flex; align-items: center; gap: 10px; }
.calendar-detail-modal-content .bubble-progress-wrap .bubble-progress-bar { flex: 1; min-width: 0; }
.calendar-detail-modal-content .bubble-progress-wrap .bubble-progress-pct { flex-shrink: 0; }
.calendar-detail-modal-content .bubble-progress-bar { height: 8px; background: rgba(15,23,42,0.08); border-radius: 4px; overflow: hidden; }
.calendar-detail-modal-content .bubble-progress-fill { height: 100%; border-radius: 4px; transition: width 0.2s; }
.calendar-detail-modal-content .bubble-progress-fill.bubble-progress-done { background: #10b981; }
.calendar-detail-modal-content .bubble-progress-fill.bubble-progress-doing { background: #f59e0b; }
.calendar-detail-modal-content .bubble-progress-fill.bubble-progress-todo { background: #6366f1; }
.calendar-detail-modal-content .bubble-comments-title { font-weight: 700; margin-top: 12px; margin-bottom: 6px; color: #0f172a; }
.calendar-detail-modal-content .bubble-attachments-list { gap: 4px; }
.calendar-detail-modal-content .calendar-bubble-file-link {
    color: #0f172a;
    border-color: rgba(15, 23, 42, 0.12) !important;
    background: #f8fafc !important;
    max-width: 100%;
}
.calendar-detail-modal-content .calendar-bubble-file-link:hover {
    color: #0d6efd;
    border-color: rgba(13, 110, 253, 0.35) !important;
}
.calendar-detail-modal-content .bubble-link { display: flex; align-items: center; gap: 6px; max-width: 100%; min-width: 0; margin-bottom: 6px; text-decoration: none; color: #0d6efd; }
.calendar-detail-modal-content .bubble-link:hover { color: #0a58ca; text-decoration: underline; }
.calendar-detail-modal-content .bubble-link .bubble-link-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.calendar-add-card-modal-content .form-label { color: inherit; }
.kanban-theme-dark .calendar-add-card-modal-content {
    background: rgba(15,23,42,0.96);
    border-color: rgba(148,163,184,0.16);
    color: #e2e8f0;
    color-scheme: dark;
}
.kanban-theme-dark .calendar-add-card-modal-content .modal-title {
    color: #f8fafc;
}
.kanban-theme-dark .calendar-add-card-modal-content .modal-header {
    border-bottom-color: rgba(148,163,184,0.2);
}
.kanban-theme-dark .calendar-add-card-modal-content .modal-footer {
    border-top-color: rgba(148,163,184,0.2);
}
/* Bootstrap .text-muted uses !important — force readable slate on dark modal */
.kanban-theme-dark .calendar-add-card-modal-content .text-muted,
.kanban-theme-dark .calendar-add-card-modal-content .small.text-muted {
    color: #94a3b8 !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .form-label {
    color: #e2e8f0 !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .form-control,
.kanban-theme-dark .calendar-add-card-modal-content .form-select {
    background: rgba(30,41,59,0.9);
    border-color: rgba(148,163,184,0.22);
    color: #f8fafc;
}
.kanban-theme-dark .calendar-add-card-modal-content .form-control:focus,
.kanban-theme-dark .calendar-add-card-modal-content .form-select:focus {
    background: rgba(30,41,59,0.95);
    border-color: rgba(56, 189, 248, 0.45);
    color: #f8fafc;
    box-shadow: 0 0 0 0.2rem rgba(56, 189, 248, 0.15);
}
.kanban-theme-dark .calendar-add-card-modal-content .form-control::placeholder {
    color: #94a3b8;
}
.kanban-theme-dark .calendar-add-card-modal-content input.form-range {
    --bs-form-range-thumb-bg: #38bdf8;
}
.kanban-theme-dark .calendar-add-card-modal-content option {
    background-color: #1e293b;
    color: #f8fafc;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-close {
    filter: invert(1);
    opacity: 0.7;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-outline-dark {
    color: #e2e8f0 !important;
    border-color: rgba(148,163,184,0.45) !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-outline-dark:hover:not(:disabled) {
    background: rgba(255,255,255,0.08) !important;
    color: #f8fafc !important;
    border-color: rgba(248,250,252,0.35) !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-outline-dark:disabled {
    color: #64748b !important;
    border-color: rgba(148,163,184,0.22) !important;
    opacity: 0.75;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-outline-secondary {
    color: #cbd5e1 !important;
    border-color: rgba(148,163,184,0.4) !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-outline-secondary:hover:not(:disabled) {
    background: rgba(255,255,255,0.06) !important;
    color: #f8fafc !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-secondary {
    background: rgba(51,65,85,0.92) !important;
    border-color: rgba(148,163,184,0.28) !important;
    color: #f8fafc !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .btn-secondary:hover:not(:disabled) {
    background: rgba(71,85,105,0.95) !important;
    color: #fff !important;
}
.kanban-theme-dark .calendar-add-card-modal-content .border-top {
    border-top-color: rgba(148,163,184,0.2) !important;
}
.kanban-theme-dark #calendarEmptyDayList.border {
    border-color: rgba(148,163,184,0.28) !important;
    background: rgba(15,23,42,0.35);
}
.kanban-theme-dark #calendarEmptyDayList .list-group-item {
    background: rgba(30,41,59,0.55);
    border-color: rgba(148,163,184,0.18);
    color: #f1f5f9;
}
.kanban-theme-dark #calendarEmptyDayList .list-group-item .text-muted {
    color: #94a3b8 !important;
}
.kanban-theme-dark .calendar-delete-card-confirm-modal {
    background: rgba(15,23,42,0.96);
    border-color: rgba(148,163,184,0.16);
    color: #e2e8f0;
}
.kanban-theme-dark .calendar-delete-card-confirm-modal .modal-title {
    color: #f8fafc;
}
.kanban-theme-dark .calendar-delete-card-confirm-modal .modal-body p {
    color: #cbd5e1;
}
.kanban-theme-dark .calendar-delete-card-confirm-modal .btn-close {
    filter: invert(1);
    opacity: 0.7;
}
.calendar-assign-card-modal-content .cg-assign-list {
    max-height: 320px;
    overflow: auto;
    border: 1px solid rgba(15,23,42,0.08);
    border-radius: 14px;
    background: rgba(255,255,255,0.88);
}
.calendar-assign-card-modal-content .cg-assign-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(15,23,42,0.08);
}
.calendar-assign-card-modal-content .cg-assign-item:last-child {
    border-bottom: none;
}
.calendar-assign-card-modal-content .cg-assign-item__avatar,
.calendar-assign-card-modal-content .cg-assign-item__initials {
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
    flex-shrink: 0;
}
.calendar-assign-card-modal-content .cg-assign-item__meta {
    flex: 1;
    min-width: 0;
}
.calendar-assign-card-modal-content .cg-assign-item__name {
    font-weight: 700;
    color: #0f172a;
}
.calendar-assign-card-modal-content .cg-assign-item__role {
    font-size: 12px;
    color: #64748b;
}
.kanban-theme-dark .calendar-assign-card-modal-content {
    background: rgba(15,23,42,0.96);
    border-color: rgba(148,163,184,0.16);
    color: #e2e8f0;
}
.kanban-theme-dark .calendar-assign-card-modal-content .modal-title {
    color: #f8fafc;
}
.kanban-theme-dark .calendar-assign-card-modal-content .text-muted,
.kanban-theme-dark .calendar-assign-card-modal-content .small {
    color: #94a3b8 !important;
}
.kanban-theme-dark .calendar-assign-card-modal-content .cg-assign-list {
    background: rgba(30,41,59,0.85);
    border-color: rgba(148,163,184,0.22);
}
.kanban-theme-dark .calendar-assign-card-modal-content .cg-assign-item {
    border-bottom-color: rgba(148,163,184,0.12);
}
.kanban-theme-dark .calendar-assign-card-modal-content .cg-assign-item__name {
    color: #f8fafc;
}
.kanban-theme-dark .calendar-assign-card-modal-content .cg-assign-item__role {
    color: #94a3b8;
}
.kanban-theme-dark .calendar-assign-card-modal-content .cg-assign-item__avatar,
.kanban-theme-dark .calendar-assign-card-modal-content .cg-assign-item__initials {
    background: rgba(51,65,85,0.95);
    color: #e2e8f0;
}
.kanban-theme-dark .calendar-assign-card-modal-content .btn-close {
    filter: invert(1);
    opacity: 0.7;
}
.cg-calendar-day-menu {
    position: fixed;
    z-index: 10060;
    min-width: 200px;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,0.1);
    background: rgba(255,255,255,0.98);
    box-shadow: 0 14px 36px rgba(15,23,42,0.18);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.cg-calendar-day-menu__item {
    width: 100%;
    border: 0;
    background: transparent;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #0f172a;
    font-size: 14px;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: background-color 0.12s ease;
}
.cg-calendar-day-menu__item i {
    width: 1.25rem;
    text-align: center;
    color: #64748b;
}
.cg-calendar-day-menu__item:hover:not(:disabled) {
    background: rgba(15,23,42,0.06);
}
.cg-calendar-day-menu__item:disabled,
.cg-calendar-day-menu__item.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.cg-calendar-card-menu {
    z-index: 10061;
}
.cg-calendar-day-menu__item--danger {
    color: #dc2626;
}
.cg-calendar-day-menu__item--danger i {
    color: #dc2626;
}
.cg-calendar-day-menu__item--danger:hover:not(:disabled) {
    background: rgba(220, 38, 38, 0.08);
}
.kanban-theme-dark .cg-calendar-day-menu__item--danger {
    color: #fca5a5;
}
.kanban-theme-dark .cg-calendar-day-menu__item--danger i {
    color: #fca5a5;
}
.kanban-theme-dark .cg-calendar-day-menu__item--danger:hover:not(:disabled) {
    background: rgba(248, 113, 113, 0.12);
}
.kanban-theme-dark .cg-calendar-day-menu {
    background: rgba(30,41,59,0.97);
    border-color: rgba(148,163,184,0.2);
    box-shadow: 0 16px 40px rgba(0,0,0,0.4);
}
.kanban-theme-dark .cg-calendar-day-menu__item {
    color: #f1f5f9;
}
.kanban-theme-dark .cg-calendar-day-menu__item i {
    color: #94a3b8;
}
.kanban-theme-dark .cg-calendar-day-menu__item:hover:not(:disabled) {
    background: rgba(255,255,255,0.08);
}
/* Full-page calendar: no freelance sidebar; site header hidden (body.cg-kanban-fullpage in header) */
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
/* Do not zero all .container-fluid — same as kanban.php: it strips .cg-kanban-portal-header .container-fluid
   side inset so logo/username hug the edges. Only flatten main; calendar body uses .cg-calendar-container rules. */
body.cg-kanban-fullpage .container {
    padding-left: 0 !important;
    padding-right: 0 !important;
}
body.cg-kanban-fullpage .fmain-content.cg-calendar-page {
    margin-left: 0 !important;
    margin-top: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    min-height: 100vh;
    box-sizing: border-box;
}
@media (min-width: 769px) {
    body.cg-kanban-fullpage .fmain-content.cg-calendar-page {
        height: 100vh;
    }
}
@media (max-width: 991px) {
    .cg-calendar-grid {
        grid-template-columns: 1fr;
    }
    .cg-calendar-weekdays {
        display: none;
    }
    .cg-calendar-day {
        min-height: auto;
        border-right: none;
    }
    .cg-calendar-day-head::after {
        content: attr(data-weekday);
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 700;
    }
}
.kanban-theme-dark .cg-calendar-page {
    background-color: #020617;
    --cg-dot-color: rgba(255,255,255,0.11);
}
.kanban-theme-dark .cg-calendar-shell,
.kanban-theme-dark .cg-calendar-grid-wrap,
.kanban-theme-dark .cg-calendar-undated {
    background: rgba(15,23,42,0.88);
    border-color: rgba(148,163,184,0.16);
    color: #f8fafc;
}
.kanban-theme-dark .cg-calendar-month,
.kanban-theme-dark #calendarBoardTitle,
.kanban-theme-dark .cg-calendar-chip-title,
.kanban-theme-dark .cg-calendar-undated h3,
.kanban-theme-dark .cg-calendar-day-number {
    color: #f8fafc !important;
}
.kanban-theme-dark #calendarBoardSubtitle,
.kanban-theme-dark .cg-calendar-chip-meta,
.kanban-theme-dark .cg-calendar-day-count {
    color: #94a3b8 !important;
}
.kanban-theme-dark #calendarCardSearchInput {
    color: #e2e8f0;
    border-color: rgba(148,163,184,0.3);
    background: rgba(15,23,42,0.55);
}
.kanban-theme-dark #calendarCardSearchInput::placeholder {
    color: #94a3b8;
    opacity: 1;
}
.kanban-theme-dark .cg-calendar-card-search-dropdown {
    background: rgba(30, 41, 59, 0.98);
    border-color: rgba(148,163,184,0.22);
    box-shadow: 0 18px 44px -8px rgba(2,6,23,0.58);
}
.kanban-theme-dark .cg-calendar-card-search-item:hover,
.kanban-theme-dark .cg-calendar-card-search-item:focus-visible,
.kanban-theme-dark .cg-calendar-card-search-item.is-active {
    background: rgba(255,255,255,0.09);
}
.kanban-theme-dark .cg-calendar-card-search-item__title { color: #f8fafc; }
.kanban-theme-dark .cg-calendar-card-search-item__meta { color: #cbd5e1; }
.kanban-theme-dark .cg-calendar-assignment-filter {
    background: rgba(15,23,42,0.78);
    border-color: rgba(148,163,184,0.24);
    box-shadow: 0 12px 28px rgba(2,6,23,0.26);
}
.kanban-theme-dark .cg-calendar-assignment-filter__btn {
    color: #cbd5e1;
}
.kanban-theme-dark .cg-calendar-assignment-filter__btn.is-active {
    background: #f8fafc;
    color: #0f172a;
}
.kanban-theme-dark .cg-calendar-hide-ongoing-btn {
    background: rgba(15,23,42,0.78);
    border-color: rgba(148,163,184,0.24);
    color: #cbd5e1;
    box-shadow: 0 12px 28px rgba(2,6,23,0.26);
}
.kanban-theme-dark .cg-calendar-hide-ongoing-btn.is-active {
    background: #f8fafc;
    color: #0f172a;
    border-color: #f8fafc;
}
.kanban-theme-dark .cg-calendar-chip--search-breathing {
    border-color: rgba(255,255,255,0.72) !important;
    box-shadow: 0 12px 28px rgba(2,6,23,0.38), 0 0 0 8px rgba(255,255,255,0.14);
}
.kanban-theme-dark .cg-calendar-chip-time {
    color: #94a3b8 !important;
}
.kanban-theme-dark .cg-calendar-weekdays {
    background: rgba(30,41,59,0.95);
    border-bottom-color: rgba(148,163,184,0.12);
}
.kanban-theme-dark .cg-calendar-weekday {
    color: #cbd5e1;
}
.kanban-theme-dark .cg-calendar-day {
    background: rgba(15,23,42,0.80);
    border-right-color: rgba(148,163,184,0.10);
    border-bottom-color: rgba(148,163,184,0.10);
}
.kanban-theme-dark .cg-calendar-day.is-other-month {
    background: rgba(15,23,42,0.58);
}
.kanban-theme-dark .cg-calendar-day.is-today {
    background: rgba(30,41,59,0.94);
    box-shadow: inset 0 0 0 1px #f87171;
}
.kanban-theme-dark .cg-calendar-chip {
    background: color-mix(in srgb, var(--chip-accent) 18%, rgba(15,23,42,0.96));
    border-color: color-mix(in srgb, var(--chip-accent) 42%, rgba(148,163,184,0.22));
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
}
.kanban-theme-dark .cg-calendar-chip--posted {
    background: color-mix(in srgb, var(--chip-accent) 18%, rgba(15,23,42,0.96));
    border-color: color-mix(in srgb, var(--chip-accent) 42%, rgba(148,163,184,0.22));
    box-shadow: inset 3px 0 0 rgba(52, 211, 153, 0.55), inset 0 1px 0 rgba(255,255,255,0.06);
}
.kanban-theme-dark .cg-calendar-chip--posted:hover {
    box-shadow: 0 10px 18px rgba(0,0,0,0.35), inset 3px 0 0 rgba(52, 211, 153, 0.55), inset 0 1px 0 rgba(255,255,255,0.06);
}
.kanban-theme-dark .cg-calendar-chip--undated {
    background: color-mix(in srgb, #94a3b8 18%, rgba(15,23,42,0.96));
    border-color: color-mix(in srgb, #94a3b8 38%, rgba(148,163,184,0.22));
}
.kanban-theme-dark .cg-calendar-chip--mini {
    padding: 3px 8px;
    border-radius: 8px;
}
.kanban-theme-dark .cg-calendar-chip__assignee-avatar,
.kanban-theme-dark .cg-calendar-chip__assignee-more {
    border-color: rgba(30,41,59,0.98);
}
.kanban-theme-dark .cg-calendar-chip__assignee-avatar.is-initials {
    background: rgba(51,65,85,0.95);
    color: #e2e8f0;
}
.kanban-theme-dark .cg-calendar-chip__assignee-more {
    background: #f1f5f9;
    color: #0f172a;
}
.kanban-theme-dark .bubble-assignee-avatar {
    border-color: rgba(148,163,184,0.25);
}
.kanban-theme-dark .bubble-assignee-avatar.is-initials {
    background: rgba(51,65,85,0.95);
    color: #e2e8f0;
}
.kanban-theme-dark .cg-calendar-empty {
    color: #475569;
}
.kanban-theme-dark .calendar-detail-modal-content {
    background: rgba(15,23,42,0.96);
    border-color: rgba(148,163,184,0.16);
}
.kanban-theme-dark .calendar-detail-modal-content .modal-title,
.kanban-theme-dark .calendar-detail-modal-content .bubble-title,
.kanban-theme-dark .calendar-detail-modal-content .bubble-comments-title {
    color: #f8fafc;
}
.kanban-theme-dark .calendar-detail-modal-content .calendar-bubble-file-link {
    color: #e2e8f0;
    border-color: rgba(148, 163, 184, 0.25) !important;
    background: rgba(15, 23, 42, 0.55) !important;
}
.kanban-theme-dark .calendar-detail-modal-content .calendar-bubble-file-link:hover {
    color: #93c5fd;
    border-color: rgba(147, 197, 253, 0.35) !important;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-meta {
    color: #94a3b8;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-desc {
    color: #cbd5e1;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-desc.bubble-desc--html a {
    color: #60a5fa;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task:before {
    background: rgba(30, 41, 59, 0.9);
    border-color: #64748b;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task[data-checked="1"]:before {
    background: rgba(30, 41, 59, 0.95);
    border-color: #f87171;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task[data-checked="1"]:after {
    border-color: #f87171;
}
.kanban-theme-dark .calendar-detail-modal-content .bubble-progress-bar {
    background: rgba(255,255,255,0.1);
}
.kanban-theme-dark #themeToggleBtn,
.kanban-theme-dark #calendarPrevMonthBtn,
.kanban-theme-dark #calendarNextMonthBtn {
    color: #e2e8f0;
    border-color: rgba(148,163,184,0.28);
    background: rgba(30,41,59,0.72);
}
.kanban-theme-dark #themeToggleBtn:hover,
.kanban-theme-dark #calendarPrevMonthBtn:hover,
.kanban-theme-dark #calendarNextMonthBtn:hover {
    color: #f8fafc;
    border-color: rgba(248,113,113,0.34);
    background: rgba(51,65,85,0.9);
}
.kanban-theme-dark #themeToggleBtn i {
    color: #fff !important;
}
/* Password modal over card details — second backdrop + dim card modal */
#calendarAttachmentPasswordModal,
#calendarCardPasswordModal {
    z-index: 1080 !important;
}
.modal-backdrop.cg-stacked-modal-backdrop {
    z-index: 1075 !important;
}
#calendarCardDetailModal.cg-under-stacked-modal {
    pointer-events: none;
}
#calendarCardDetailModal.cg-under-stacked-modal .modal-content {
    filter: brightness(0.88);
}
</style>

<script>
const CG_FK_API = <?php echo json_encode($cg_freelance_kanban_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_FT_API = <?php echo json_encode($cg_freelance_timeline_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_STATIC_ORIGIN = <?php echo json_encode($cg_kanban_static_origin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_IMAGE_PREVIEW_URL = <?php echo json_encode($cg_kanban_image_preview_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_ATTACHMENT_URL = <?php echo json_encode($cg_kanban_attachment_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_KANBAN_PROFILE_PIC_URL = <?php echo json_encode($cg_kanban_profile_pic_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CG_CURRENT_USER_ID = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
const CG_CURRENT_USER_NAME = <?php echo json_encode($_SESSION['user_name'] ?? 'You', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
let calendarAttachmentPasswordState = { cardId: 0, path: '', name: '' };
let calendarCardPasswordState = { cardId: 0, pendingAction: 'detail' };
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
function getCalendarAttachmentImageUrl(path, width) {
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
function calendarParseAttachments(item) {
  try {
    if (Array.isArray(item.attachments)) return item.attachments;
    if (typeof item.attachments === 'string' && item.attachments.trim()) {
      const j = JSON.parse(item.attachments);
      return Array.isArray(j) ? j : [];
    }
  } catch (e) {}
  return [];
}
function calendarIsAttachmentProtected(att) {
  return !!(att && (att.protected || att.password_hash || att.protect_password));
}
function calendarIsCardProtected(item) {
  return !!(item && (parseInt(item.protected || 0, 10) || item.protected === true));
}
function calendarIsLinkCard(item) {
  return !!(item && String(item.card_type || '').toLowerCase() === 'link');
}
function calendarNormalizeLinkUrl(url) {
  const s = String(url || '').trim();
  if (!s) return '';
  if (/^https?:\/\//i.test(s)) return s;
  return 'https://' + s;
}
function calendarIsBoardOwnerForItem(item) {
  const boardId = item && item.board_id != null ? String(item.board_id) : '';
  if (!boardId) return !!(CAL.bootstrap && CAL.bootstrap.is_board_owner);
  if (CAL.bootstrap && String(CAL.bootstrap.board_id) === boardId && typeof CAL.bootstrap.is_board_owner !== 'undefined') {
    return !!CAL.bootstrap.is_board_owner;
  }
  const boot = CAL.boardBootstrapById && CAL.boardBootstrapById[boardId];
  return !!(boot && boot.is_board_owner);
}
function calendarGetAttachmentServeUrl(path, cardId, token) {
  const u = new URL(CG_KANBAN_ATTACHMENT_URL, window.location.href);
  u.searchParams.set('card_id', String(cardId || 0));
  u.searchParams.set('path', String(path || '').replace(/^\//, ''));
  if (token) u.searchParams.set('token', String(token));
  return u.href;
}
function calendarGetAttachmentOpenUrl(att, cardId) {
  const path = att && (att.path || att);
  if (!path) return '';
  if (calendarIsAttachmentProtected(att)) {
    return calendarGetAttachmentServeUrl(path, cardId);
  }
  const rawPath = String(path);
  const rel = (rawPath.indexOf('http') === 0 || rawPath.indexOf('/') === 0) ? rawPath : '/' + rawPath;
  return cgKanbanResolvePublicUrl(rel);
}
function calendarCanOpenAttachmentDirectly(att, item) {
  if (!calendarIsAttachmentProtected(att)) return true;
  return calendarIsBoardOwnerForItem(item || {});
}
function calendarCanOpenCardDirectly(item) {
  if (!item || !calendarIsCardProtected(item)) return true;
  return calendarIsBoardOwnerForItem(item);
}
function calendarShowStandaloneModal(modalEl) {
  if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
  const inst = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
  inst.show();
  return inst;
}
function calendarOpenCardPasswordModal(itemId, pendingAction) {
  const id = parseInt(itemId, 10) || 0;
  const item = findCalendarItem(id);
  if (!item) return;
  calendarCardPasswordState = { cardId: id, pendingAction: pendingAction || 'detail' };
  const title = item.title || 'Untitled';
  const isLink = calendarIsLinkCard(item);
  const hintEl = document.getElementById('calendarCardPasswordModalHint');
  const pwInput = document.getElementById('calendarCardPasswordInput');
  const errEl = document.getElementById('calendarCardPasswordModalError');
  const submitBtn = document.getElementById('calendarCardPasswordModalSubmit');
  if (hintEl) {
    hintEl.textContent = isLink
      ? `This link is protected. Enter the password to view “${title}”.`
      : `This card is protected. Enter the password to view “${title}”.`;
  }
  if (submitBtn) submitBtn.textContent = 'Open';
  if (pwInput) pwInput.value = '';
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  const modalEl = document.getElementById('calendarCardPasswordModal');
  if (modalEl) {
    calendarShowStandaloneModal(modalEl);
    setTimeout(() => pwInput && pwInput.focus(), 300);
  }
}
async function calendarSubmitCardPassword() {
  const { cardId, pendingAction } = calendarCardPasswordState;
  if (!cardId) return;
  const pwInput = document.getElementById('calendarCardPasswordInput');
  const errEl = document.getElementById('calendarCardPasswordModalError');
  const submitBtn = document.getElementById('calendarCardPasswordModalSubmit');
  const password = pwInput ? pwInput.value : '';
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  if (!password) {
    if (errEl) { errEl.textContent = 'Please enter the password.'; errEl.style.display = 'block'; }
    return;
  }
  if (submitBtn) submitBtn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action', 'verify_card_password');
    fd.append('card_id', String(cardId));
    fd.append('password', password);
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Incorrect password');
    const modalEl = document.getElementById('calendarCardPasswordModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
    }
    if (pendingAction === 'edit') {
      openCalendarEditCardModalInternal(cardId);
    } else {
      openCalendarDetailModalInternal(cardId);
    }
  } catch (e) {
    if (errEl) { errEl.textContent = e.message || 'Incorrect password'; errEl.style.display = 'block'; }
  } finally {
    if (submitBtn) submitBtn.disabled = false;
  }
}
function calendarWireStackedModalBackdrop(modalSelector, underModalSelector) {
  const modalEl = document.querySelector(modalSelector);
  if (!modalEl || modalEl.dataset.cgStackedBackdropWired === '1') return;
  modalEl.dataset.cgStackedBackdropWired = '1';
  modalEl.addEventListener('show.bs.modal', () => {
    const under = document.querySelector(underModalSelector);
    if (under && under.classList.contains('show')) {
      under.classList.add('cg-under-stacked-modal');
    }
  });
  modalEl.addEventListener('shown.bs.modal', () => {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    const topBackdrop = backdrops.length ? backdrops[backdrops.length - 1] : null;
    if (topBackdrop) topBackdrop.classList.add('cg-stacked-modal-backdrop');
  });
  modalEl.addEventListener('hidden.bs.modal', () => {
    document.querySelector(underModalSelector)?.classList.remove('cg-under-stacked-modal');
    document.querySelectorAll('.modal-backdrop.cg-stacked-modal-backdrop').forEach((el) => {
      el.classList.remove('cg-stacked-modal-backdrop');
    });
  });
}
function calendarShowModalOverDetail(modalEl) {
  if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
  const inst = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
  inst.show();
  return inst;
}
function calendarOpenAttachmentPasswordModal(cardId, path, name) {
  calendarAttachmentPasswordState = { cardId: cardId || 0, path: String(path || '').replace(/^\//, ''), name: name || 'Attachment' };
  const hintEl = document.getElementById('calendarAttachmentPasswordModalHint');
  const pwInput = document.getElementById('calendarAttachmentPasswordInput');
  const errEl = document.getElementById('calendarAttachmentPasswordModalError');
  if (hintEl) hintEl.textContent = `This file is protected. Enter the password to view “${calendarAttachmentPasswordState.name}”.`;
  if (pwInput) pwInput.value = '';
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  const modalEl = document.getElementById('calendarAttachmentPasswordModal');
  if (modalEl) {
    calendarShowModalOverDetail(modalEl);
    setTimeout(() => pwInput && pwInput.focus(), 300);
  }
}
async function calendarSubmitAttachmentPassword() {
  const { cardId, path, name } = calendarAttachmentPasswordState;
  const pwInput = document.getElementById('calendarAttachmentPasswordInput');
  const errEl = document.getElementById('calendarAttachmentPasswordModalError');
  const submitBtn = document.getElementById('calendarAttachmentPasswordModalSubmit');
  const password = pwInput ? pwInput.value : '';
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
  if (!password) {
    if (errEl) { errEl.textContent = 'Please enter the password.'; errEl.style.display = 'block'; }
    return;
  }
  if (submitBtn) submitBtn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action', 'verify_attachment_password');
    fd.append('card_id', String(cardId));
    fd.append('path', path);
    fd.append('password', password);
    const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
    const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: crossOrigin ? 'include' : 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Incorrect password');
    const modalEl = document.getElementById('calendarAttachmentPasswordModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
    }
    window.open(calendarGetAttachmentServeUrl(path, cardId, data.access_token || ''), '_blank', 'noopener,noreferrer');
  } catch (e) {
    if (errEl) { errEl.textContent = e.message || 'Incorrect password'; errEl.style.display = 'block'; }
  } finally {
    if (submitBtn) submitBtn.disabled = false;
  }
}
function calendarBuildAttachmentsHtml(attachmentsArr, item) {
  if (!attachmentsArr || !attachmentsArr.length) return '';
  const cardId = item && item.id ? parseInt(item.id, 10) : 0;
  const items = attachmentsArr.map(function(att) {
    const path = att && (att.path || att);
    const name = (att && att.name) ? att.name : (path ? String(path).split('/').pop() : 'Attachment');
    const isImg = /\.(jpe?g|png|gif|webp)$/i.test(String(path));
    const isDoc = /\.(pdf|docx?)$/i.test(String(path));
    const protectedBadge = calendarIsAttachmentProtected(att)
      ? ' <span class="badge bg-secondary" title="Password protected"><i class="fas fa-lock"></i></span>'
      : '';
    const canOpen = calendarCanOpenAttachmentDirectly(att, item || {});
    const url = calendarGetAttachmentOpenUrl(att, cardId);
    if (isImg) {
      const previewUrl = getCalendarAttachmentImageUrl(path, 480);
      if (canOpen) {
        return '<div class="calendar-bubble-attachment d-inline-flex align-items-center me-2 mb-2"><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="bubble-attachment-img-link" title="' + escapeHtml(name) + '"><img src="' + escapeHtml(previewUrl) + '" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;cursor:pointer;" onerror="this.style.display=\'none\'"></a>' + protectedBadge + '</div>';
      }
      return '<div class="calendar-bubble-attachment d-inline-flex align-items-center me-2 mb-2"><a href="#" class="bubble-attachment-img-link calendar-protected-attachment" data-card-id="' + cardId + '" data-att-path="' + escapeHtml(String(path).replace(/^\//, '')) + '" data-att-name="' + escapeHtml(name) + '" title="' + escapeHtml(name) + '"><img src="' + escapeHtml(previewUrl) + '" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;cursor:pointer;opacity:.85;" onerror="this.style.display=\'none\'"></a>' + protectedBadge + '</div>';
    }
    if (isDoc) {
      const iconClass = /\.pdf$/i.test(String(path)) ? 'fa-file-pdf text-danger' : 'fa-file-word text-primary';
      if (canOpen) {
        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="calendar-bubble-file-link d-inline-flex align-items-center gap-2 me-2 mb-2 px-2 py-1 rounded border text-decoration-none" title="View (open in new tab)"><i class="fas ' + iconClass + '"></i><span class="small text-truncate" style="max-width:180px">' + escapeHtml(name) + '</span>' + protectedBadge + '</a>';
      }
      return '<a href="#" class="calendar-bubble-file-link calendar-protected-attachment d-inline-flex align-items-center gap-2 me-2 mb-2 px-2 py-1 rounded border text-decoration-none" data-card-id="' + cardId + '" data-att-path="' + escapeHtml(String(path).replace(/^\//, '')) + '" data-att-name="' + escapeHtml(name) + '" title="Password required"><i class="fas ' + iconClass + '"></i><span class="small text-truncate" style="max-width:180px">' + escapeHtml(name) + '</span>' + protectedBadge + '</a>';
    }
    if (canOpen) {
      return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="calendar-bubble-file-link d-inline-flex align-items-center gap-2 me-2 mb-2 px-2 py-1 rounded border text-decoration-none" title="' + escapeHtml(name) + '"><i class="fas fa-file text-muted"></i><span class="small text-truncate" style="max-width:180px">' + escapeHtml(name) + '</span>' + protectedBadge + '</a>';
    }
    return '<a href="#" class="calendar-bubble-file-link calendar-protected-attachment d-inline-flex align-items-center gap-2 me-2 mb-2 px-2 py-1 rounded border text-decoration-none" data-card-id="' + cardId + '" data-att-path="' + escapeHtml(String(path).replace(/^\//, '')) + '" data-att-name="' + escapeHtml(name) + '" title="Password required"><i class="fas fa-file text-muted"></i><span class="small text-truncate" style="max-width:180px">' + escapeHtml(name) + '</span>' + protectedBadge + '</a>';
  }).join('');
  return '<div class="bubble-attachments mt-2"><div class="bubble-comments-title">Attachments</div><div class="bubble-attachments-list d-flex flex-wrap align-items-start">' + items + '</div></div>';
}
const KANBAN_THEME_STORAGE_KEY = 'kanban_theme_mode';
const CALENDAR_ASSIGNMENT_FILTER_STORAGE_KEY = 'calendar_assignment_filter';
const CALENDAR_HIDE_ONGOING_STORAGE_KEY = 'calendar_hide_ongoing';
const CAL = {
  boardId: new URLSearchParams(window.location.search).get('board_id') || '',
  month: new URLSearchParams(window.location.search).get('month') || '',
  bootstrap: null,
  /** When viewing “All boards”, each board’s bootstrap (columns + cardsByColumn) keyed by board id. */
  boardBootstrapById: {},
  board_members: [],
  assignmentFilter: 'all',
  hideOnGoing: false,
  sourceItems: [],
  sourceUndatedItems: [],
  items: [],
  undatedItems: [],
  allItems: [],
  /** Multi-select for drag (Ctrl/Cmd+click on chips). */
  selectedCardIds: new Set(),
  workspace_presence: [],
  activeMemberIds: [],
  activeMemberColors: {},
  presencePollTimerId: null,
};
let CAL_SEARCH_INDEX = [];
let CAL_SEARCH_RESULTS = [];
let CAL_SEARCH_ACTIVE_INDEX = -1;
let CAL_ACTIVE_BREATHING_IDS = new Set();
const CG_CALENDAR_CARD_DND_MIME = 'application/x-cg-calendar-card-ids';
let calendarDndFromCalendarChip = false;
/** Set on dragstart; cleared in dragend via setTimeout(0) so drop always runs before cleanup (WebKit can fire dragend before drop). */
let calendarDragSessionCardIds = null;
let calendarDragSessionSpanRole = '';
let calendarDragSessionFromKey = '';
let calendarLastChipDragEndedAt = 0;

function qs(sel, root=document){ return root.querySelector(sel); }
function qsa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }
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
function getStoredCalendarAssignmentFilter() {
  try {
    const value = localStorage.getItem(CALENDAR_ASSIGNMENT_FILTER_STORAGE_KEY);
    return value === 'mine' ? 'mine' : 'all';
  } catch (err) {
    return 'all';
  }
}
function storeCalendarAssignmentFilter(value) {
  try {
    localStorage.setItem(CALENDAR_ASSIGNMENT_FILTER_STORAGE_KEY, value === 'mine' ? 'mine' : 'all');
  } catch (err) {}
}
function getStoredCalendarHideOnGoing() {
  try {
    return localStorage.getItem(CALENDAR_HIDE_ONGOING_STORAGE_KEY) === '1';
  } catch (err) {
    return false;
  }
}
function storeCalendarHideOnGoing(value) {
  try {
    localStorage.setItem(CALENDAR_HIDE_ONGOING_STORAGE_KEY, value ? '1' : '0');
  } catch (err) {}
}
function calendarItemAssignedToCurrentUser(item) {
  const currentUserId = parseInt(CG_CURRENT_USER_ID, 10);
  if (!currentUserId || !item || !Array.isArray(item.assignees)) return false;
  return item.assignees.some((member) => parseInt(member && member.id, 10) === currentUserId);
}
function applyCalendarAssignmentFilter() {
  const showMine = CAL.assignmentFilter === 'mine';
  const sourceItems = Array.isArray(CAL.sourceItems) ? CAL.sourceItems : [];
  const sourceUndated = Array.isArray(CAL.sourceUndatedItems) ? CAL.sourceUndatedItems : [];
  CAL.items = showMine ? sourceItems.filter(calendarItemAssignedToCurrentUser) : sourceItems.slice();
  CAL.undatedItems = showMine ? sourceUndated.filter(calendarItemAssignedToCurrentUser) : sourceUndated.slice();
  CAL.allItems = CAL.items.concat(CAL.undatedItems);
  CAL.selectedCardIds.forEach((id) => {
    if (!CAL.allItems.some((item) => parseInt(item.id, 10) === parseInt(id, 10))) {
      CAL.selectedCardIds.delete(id);
    }
  });
}
function renderCalendarAssignmentFilter() {
  qsa('[data-calendar-assignment-filter]').forEach((btn) => {
    const value = btn.getAttribute('data-calendar-assignment-filter') === 'mine' ? 'mine' : 'all';
    const active = value === CAL.assignmentFilter;
    btn.classList.toggle('is-active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
}
function renderCalendarHideOnGoingBtn() {
  const btn = qs('#calendarHideOngoingBtn');
  if (!btn) return;
  const active = !!CAL.hideOnGoing;
  btn.classList.toggle('is-active', active);
  btn.setAttribute('aria-pressed', active ? 'true' : 'false');
}
function mergeCalendarBoardMembersFromBootstraps(bootstraps) {
  const seen = new Map();
  (bootstraps || []).forEach((data) => {
    (data.board_members || []).forEach((m) => {
      const id = parseInt(m.id, 10);
      if (id && !seen.has(id)) seen.set(id, m);
    });
  });
  return [...seen.values()];
}
function renderCalendarBoardMembersAvatars() {
  const container = qs('#boardMembersAvatars');
  if (!container) return;
  const members = CAL.board_members || [];
  const activeIdSet = new Set((CAL.activeMemberIds || []).map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0));
  const activeMembers = members.filter((m) => activeIdSet.has(Number(m?.id || 0)));
  const represented = new Set(activeMembers.map((m) => Number(m?.id || 0)).filter((id) => id > 0));
  const me = Number(CG_CURRENT_USER_ID || 0);
  if (me > 0 && activeIdSet.has(me) && !represented.has(me)) {
    activeMembers.unshift({
      id: me,
      name: String(CG_CURRENT_USER_NAME || 'You'),
      profile_pic: '',
      role: 'collaborator',
    });
  }
  const maxVisible = 4;
  const show = activeMembers.slice(0, maxVisible);
  container.innerHTML = show.map((m) => {
    const src = calendarBoardMemberPicUrl(m.profile_pic);
    const name = (m.name || '').trim() || 'User';
    const title = escapeHtml(name) + (m.role === 'owner' ? ' (Owner)' : '');
    const uid = Number(m?.id || 0);
    const color = (uid > 0 && CAL.activeMemberColors && CAL.activeMemberColors[String(uid)]) ? CAL.activeMemberColors[String(uid)] : '';
    const ringStyle = color ? ` style="--presence-color:${escapeHtml(color)}"` : '';
    const inner = src
      ? `<img class="cg-board-avatar" src="${escapeHtml(src)}" alt="${title}" title="${title}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="cg-board-avatar-initials" style="display:none;" title="${title}">${escapeHtml(calendarAssigneeInitials(name))}</span>`
      : `<span class="cg-board-avatar-initials" title="${title}">${escapeHtml(calendarAssigneeInitials(name))}</span>`;
    return `<span class="cg-board-avatar-wrap is-active-presence"${ringStyle}>${inner}</span>`;
  }).join('');
}
function calendarPresenceColor(seed) {
  const raw = String(seed || '');
  let hash = 0;
  for (let i = 0; i < raw.length; i++) hash = ((hash << 5) - hash + raw.charCodeAt(i)) | 0;
  const hue = Math.abs(hash) % 360;
  const sat = 72 + (Math.abs(hash >> 3) % 12);
  const light = 58 + (Math.abs(hash >> 7) % 10);
  return `hsl(${hue}deg ${sat}% ${light}%)`;
}
function syncCalendarChatPresenceGlobals() {
  window.CG_ACTIVE_MEMBER_IDS = Array.isArray(CAL.activeMemberIds) ? CAL.activeMemberIds.slice() : [];
  window.CG_ACTIVE_MEMBER_COLORS = { ...(CAL.activeMemberColors || {}) };
}
function ensureCalendarCurrentUserPresenceFallback(nextIds, nextColors) {
  const me = Number(typeof CG_CURRENT_USER_ID !== 'undefined' ? CG_CURRENT_USER_ID : 0);
  if (!(me > 0)) return;
  const isMember = (CAL.board_members || []).some((m) => Number(m?.id || 0) === me);
  if (!isMember) return;
  if (!nextIds.includes(me)) nextIds.push(me);
  if (!nextColors[String(me)]) nextColors[String(me)] = calendarPresenceColor(`u:${me}`);
}
function seedCalendarCurrentUserPresenceImmediately() {
  const nextIds = [];
  const nextColors = {};
  ensureCalendarCurrentUserPresenceFallback(nextIds, nextColors);
  if (!nextIds.length) return;
  CAL.activeMemberIds = nextIds;
  CAL.activeMemberColors = nextColors;
  syncCalendarChatPresenceGlobals();
  renderCalendarBoardMembersAvatars();
}
async function syncCalendarPresenceOnce() {
  const id = String(CAL.boardId || '');
  const bid = Number(id === 'all' ? 0 : id);
  if (!bid) {
    CAL.activeMemberIds = [];
    CAL.activeMemberColors = {};
    syncCalendarChatPresenceGlobals();
    renderCalendarBoardMembersAvatars();
    return;
  }
  try {
    const data = await fetchJson(`${CG_FK_API}?action=workspace_presence_ping`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ board_id: bid, sx: 0.5, sy: 0.5 }),
    });
    const rows = Array.isArray(data?.workspace_presence) ? data.workspace_presence : [];
    CAL.workspace_presence = rows.slice();
    const nextColors = {};
    const nextIds = [];
    rows.forEach((row) => {
      const uid = Number(row?.user_id || 0);
      const actor = String(row?.actor_id || '').trim();
      if (uid <= 0 || !actor) return;
      if (!nextColors[String(uid)]) {
        nextColors[String(uid)] = calendarPresenceColor(actor);
        nextIds.push(uid);
      }
    });
    ensureCalendarCurrentUserPresenceFallback(nextIds, nextColors);
    CAL.activeMemberIds = nextIds;
    CAL.activeMemberColors = nextColors;
    syncCalendarChatPresenceGlobals();
    renderCalendarBoardMembersAvatars();
  } catch (e) {
    const nextIds = [];
    const nextColors = {};
    ensureCalendarCurrentUserPresenceFallback(nextIds, nextColors);
    CAL.activeMemberIds = nextIds;
    CAL.activeMemberColors = nextColors;
    syncCalendarChatPresenceGlobals();
    renderCalendarBoardMembersAvatars();
  }
}
function stopCalendarPresencePolling() {
  if (CAL.presencePollTimerId != null) {
    clearInterval(CAL.presencePollTimerId);
    CAL.presencePollTimerId = null;
  }
}
function startCalendarPresencePolling() {
  stopCalendarPresencePolling();
  seedCalendarCurrentUserPresenceImmediately();
  void syncCalendarPresenceOnce();
  CAL.presencePollTimerId = setInterval(() => {
    if (document.visibilityState === 'hidden') return;
    void syncCalendarPresenceOnce();
  }, 2200);
}
function escapeHtml(str) {
  return (str ?? '').toString()
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function calendarRowFieldCI(row, wantKey) {
  if (!row || typeof row !== 'object') return undefined;
  const want = String(wantKey).toLowerCase();
  const keys = Object.keys(row);
  for (let i = 0; i < keys.length; i++) {
    if (String(keys[i]).toLowerCase() === want) return row[keys[i]];
  }
  return undefined;
}

/** Normalized description text from API/card rows (avoids literal "undefined" / empty placeholders). */
function calendarCardDescriptionText(row) {
  if (!row || typeof row !== 'object') return '';
  const keys = ['description', 'desc', 'card_description', 'body'];
  for (let i = 0; i < keys.length; i++) {
    const v = calendarRowFieldCI(row, keys[i]);
    if (v == null) continue;
    if (typeof v === 'function') continue;
    if (typeof v === 'object') continue;
    const s = typeof v === 'string' ? v : String(v);
    const t = s.trim();
    if (!t || t === 'undefined' || t === 'null') continue;
    return t;
  }
  return '';
}

function calendarLookupBootstrapCard(boardId, cardId) {
  const cid = parseInt(String(cardId), 10);
  if (!cid) return null;
  const tryData = (data) => {
    if (!data || !data.cardsByColumn) return null;
    const cols = Array.isArray(data.columns) ? data.columns : [];
    if (!cols.length) return null;
    const cbc = data.cardsByColumn;
    for (let i = 0; i < cols.length; i++) {
      const colId = cols[i].id;
      const list = cbc[colId] || cbc[String(colId)] || [];
      const c = list.find((x) => parseInt(String(x.id), 10) === cid);
      if (c) return c;
    }
    return null;
  };
  const bid = boardId != null && String(boardId).trim() !== '' ? String(boardId) : '';
  const byId = CAL.boardBootstrapById && typeof CAL.boardBootstrapById === 'object' ? CAL.boardBootstrapById : {};
  if (bid && byId[bid]) {
    const hit = tryData(byId[bid]);
    if (hit) return hit;
  }
  for (const k of Object.keys(byId)) {
    const hit = tryData(byId[k]);
    if (hit) return hit;
  }
  if (CAL.bootstrap && CAL.bootstrap.cardsByColumn) {
    const hit = tryData(CAL.bootstrap);
    if (hit) return hit;
  }
  return null;
}

/** Decode HTML entities for description display when API returns escaped markup. */
function calendarDecodeHtmlEntities(str) {
  if (str == null || str === '') return '';
  const el = document.createElement('textarea');
  el.innerHTML = String(str);
  return el.value;
}

/** Allowed tags match server-side kanban card description whitelist (read-only display). */
function calendarSanitizeDescriptionHtml(html) {
  const s = String(html || '').replace(/<script\b[\s\S]*?<\/script>/gi, '').trim();
  if (!s) return '';
  const tpl = document.createElement('template');
  tpl.innerHTML = s;
  const root = tpl.content;
  root.querySelectorAll('script, style, iframe, object, embed, link, meta, form, input, textarea, select, button').forEach(function (n) { n.remove(); });
  root.querySelectorAll('*').forEach(function (el) {
    for (let i = el.attributes.length - 1; i >= 0; i--) {
      const attr = el.attributes[i];
      const name = attr.name;
      const val = attr.value || '';
      if (/^on/i.test(name) || name === 'srcdoc' || /^javascript:/i.test(val)) {
        el.removeAttribute(name);
      }
    }
  });
  const ALLOW = new Set(['P', 'DIV', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'STRIKE', 'DEL', 'UL', 'OL', 'LI', 'A', 'SPAN']);
  function unwrapElement(el) {
    const p = el.parentNode;
    if (!p) return;
    while (el.firstChild) p.insertBefore(el.firstChild, el);
    p.removeChild(el);
  }
  let guard = 0;
  while (guard++ < 200) {
    let changed = false;
    const els = Array.from(root.querySelectorAll('*'));
    for (let i = els.length - 1; i >= 0; i--) {
      const el = els[i];
      if (!ALLOW.has(el.tagName)) {
        unwrapElement(el);
        changed = true;
        break;
      }
    }
    if (!changed) break;
  }
  root.querySelectorAll('a').forEach(function (a) {
    const href = (a.getAttribute('href') || '').trim();
    for (let i = a.attributes.length - 1; i >= 0; i--) {
      const n = a.attributes[i].name;
      if (n !== 'href' && n !== 'rel' && n !== 'target') a.removeAttribute(n);
    }
    if (!/^https?:\/\//i.test(href)) {
      unwrapElement(a);
      return;
    }
    a.setAttribute('rel', 'noopener noreferrer');
    a.setAttribute('target', '_blank');
  });
  root.querySelectorAll('ul').forEach(function (ul) {
    const cls = (ul.getAttribute('class') || '').trim();
    if (cls.includes('cg-desc-checklist')) ul.setAttribute('class', 'cg-desc-checklist');
    else ul.removeAttribute('class');
  });
  root.querySelectorAll('li').forEach(function (li) {
    const parent = li.parentElement;
    const isChecklist = parent && parent.tagName === 'UL' && (parent.getAttribute('class') || '').includes('cg-desc-checklist');
    if (isChecklist) {
      li.setAttribute('class', 'cg-desc-task');
      li.setAttribute('data-checked', li.getAttribute('data-checked') === '1' ? '1' : '0');
    } else {
      li.removeAttribute('class');
      li.removeAttribute('data-checked');
    }
  });
  root.querySelectorAll('span').forEach(function (span) {
    const cls = (span.getAttribute('class') || '').trim();
    if (cls === 'cg-desc-emoji') span.setAttribute('class', 'cg-desc-emoji');
    else span.removeAttribute('class');
  });
  const wrap = document.createElement('div');
  wrap.appendChild(root.cloneNode(true));
  return wrap.innerHTML.trim();
}

function calendarDescriptionLooksLikeHtml(raw) {
  const s = String(raw || '').trim();
  if (!s) return false;
  if (/<[a-z][\s\S]*>/i.test(s)) return true;
  if (/^[\s\uFEFF]*<\s*[a-z]/i.test(s)) return true;
  if (/<\/?[a-z][\s\S]*?>/i.test(s) && />/.test(s)) return true;
  return false;
}

function calendarFormatDescriptionForBubble(desc) {
  let raw = String(desc || '').trim();
  if (!raw) return '';
  if (/&lt;\/?[a-z]/i.test(raw)) raw = calendarDecodeHtmlEntities(raw);
  if (calendarDescriptionLooksLikeHtml(raw)) {
    const clean = calendarSanitizeDescriptionHtml(raw);
    if (String(clean || '').replace(/\s|&nbsp;/gi, '').length) return clean;
    const plain = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (plain) return '<p>' + escapeHtml(plain) + '</p>';
    return '';
  }
  return '<p>' + escapeHtml(raw).replace(/\n/g, '<br>') + '</p>';
}

/** Plain text for search / dropdown previews (no HTML parsing). */
function calendarDescPlainText(html) {
  return String(html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

/** Single-line description snippet for calendar chip meta (replaces column name). */
function calendarChipDescriptionLine(item, maxLen) {
  const raw = calendarCardDescriptionText(item) || (item && item.description != null ? String(item.description) : '');
  const plain = calendarDescPlainText(raw);
  if (!plain) return '';
  const lim = Math.max(20, parseInt(maxLen, 10) || 90);
  if (plain.length <= lim) return plain;
  return plain.slice(0, lim - 1).replace(/\s+\S*$/, '').trimEnd() + '…';
}

function parseMonthValue(value) {
  if (/^\d{4}-\d{2}$/.test(value || '')) return value;
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function parseDateInput(str) {
  if (!str) return null;
  const parts = String(str).split('-').map(Number);
  if (parts.length !== 3) return null;
  const d = new Date(parts[0], parts[1] - 1, parts[2]);
  return isNaN(d.getTime()) ? null : d;
}
function formatMonthLabel(monthValue) {
  const d = parseDateInput(`${monthValue}-01`);
  return d ? d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : monthValue;
}
function dateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function calendarDateInputValue(d) {
  const m = String(d || '').match(/^(\d{4}-\d{2}-\d{2})/);
  return m ? m[1] : '';
}
function calendarTimeInputValue(t) {
  const s = String(t || '').trim();
  const m = s.match(/^(\d{1,2}):(\d{2})/);
  if (!m) return '';
  const h = String(Math.min(23, parseInt(m[1], 10))).padStart(2, '0');
  return `${h}:${m[2]}`;
}
function calendarItemLinksArray(item) {
  let arr = [];
  try {
    if (Array.isArray(item.links)) arr = item.links;
    else if (typeof item.links === 'string' && item.links.trim()) {
      const j = JSON.parse(item.links);
      if (Array.isArray(j)) arr = j;
    }
  } catch (e) {}
  return arr.map((u) => String(u).trim()).filter(Boolean);
}
function calendarItemLinksLineText(item) {
  return calendarItemLinksArray(item).join('\n');
}
function calendarItemLinksJsonString(item) {
  return JSON.stringify(calendarItemLinksArray(item));
}
function getCardsOnCalendarDay(dateKey) {
  if (!dateKey || !CAL.items || !CAL.items.length) return [];
  return CAL.items.filter((item) => eachDateKeyInCardRange(item).includes(dateKey));
}
function positionsForBulkMoveToColumn(cardsByColumn, cardIds, toColumnId) {
  const tid = parseInt(toColumnId, 10);
  const idSet = new Set(cardIds.map((id) => parseInt(id, 10)));
  const list = cardsByColumn[tid] ?? cardsByColumn[String(tid)] ?? [];
  const base = list.filter((c) => !idSet.has(parseInt(c.id, 10))).length;
  return cardIds.map((id, idx) => ({
    card_id: parseInt(id, 10),
    to_column_id: tid,
    position: base + idx
  }));
}
function calendarItemAttachmentsPostJson(item) {
  try {
    if (Array.isArray(item.attachments)) return JSON.stringify(item.attachments);
    if (typeof item.attachments === 'string' && item.attachments.trim()) {
      const j = JSON.parse(item.attachments);
      return JSON.stringify(Array.isArray(j) ? j : []);
    }
  } catch (e) {}
  return '[]';
}
function hexToHsl(hex) {
  const n = String(hex || '').replace('#', '').trim();
  if (!/^[0-9a-fA-F]{6}$/.test(n)) return { h: 0, s: 0, l: 50 };
  const r = parseInt(n.slice(0, 2), 16) / 255;
  const g = parseInt(n.slice(2, 4), 16) / 255;
  const b = parseInt(n.slice(4, 6), 16) / 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let hue = 0;
  const light = (max + min) / 2;
  let sat = 0;
  if (max !== min) {
    const d = max - min;
    sat = light > 0.5 ? d / (2 - max - min) : d / (max - min);
    switch (max) {
      case r:
        hue = ((g - b) / d + (g < b ? 6 : 0)) / 6;
        break;
      case g:
        hue = ((b - r) / d + 2) / 6;
        break;
      default:
        hue = ((r - g) / d + 4) / 6;
    }
  }
  return { h: hue * 360, s: sat * 100, l: light * 100 };
}
function circularHueDiff(a, b) {
  const d = Math.abs(a - b) % 360;
  return Math.min(d, 360 - d);
}
/**
 * Named family hues (rose, orange, yellow, mint, blue, purple) + extras, ~45°+ apart on the wheel
 * so stacked same-day chips stay distinguishable. No separate cyan slot (avoids cyan vs mint confusion).
 */
const CALENDAR_CARD_ACCENT_PALETTE = [
  '#e11d48', // rose
  '#ea580c', // orange
  '#ca8a04', // yellow / amber
  '#0d9488', // mint / teal
  '#2563eb', // blue
  '#7c3aed', // purple
  '#db2777', // fuchsia (extra)
  '#65a30c' // lime (extra)
];

/**
 * Same card id keeps one color across all days. Cards that share any calendar day get hues
 * as far apart as possible (reduces cyan vs mint confusion when stacked).
 */
function assignCalendarChipAccents(items, undated) {
  const paletteMeta = CALENDAR_CARD_ACCENT_PALETTE.map((hex) => ({ hex, h: hexToHsl(hex).h }));
  const colorById = new Map();

  function pickBestColor(cardId, neighborIdSet) {
    const usedHues = [];
    neighborIdSet.forEach((nid) => {
      const c = colorById.get(nid);
      if (c) usedHues.push(hexToHsl(c).h);
    });
    let bestMin = -1;
    let bestHex = paletteMeta[0].hex;
    paletteMeta.forEach((cand) => {
      let minD = 180;
      usedHues.forEach((uh) => {
        const d = circularHueDiff(cand.h, uh);
        if (d < minD) minD = d;
      });
      if (usedHues.length === 0) minD = 180;
      if (minD > bestMin) {
        bestMin = minD;
        bestHex = cand.hex;
      }
    });
    const ties = paletteMeta.filter((cand) => {
      let minD = 180;
      usedHues.forEach((uh) => {
        const d = circularHueDiff(cand.h, uh);
        if (d < minD) minD = d;
      });
      if (usedHues.length === 0) minD = 180;
      return minD === bestMin;
    });
    ties.sort((a, b) => a.hex.localeCompare(b.hex));
    const idx = Math.abs(parseInt(cardId, 10) || 0) % ties.length;
    return ties[idx].hex;
  }

  const datedIds = [...new Set(items.map((i) => i.id))];
  const keySets = new Map();
  datedIds.forEach((id) => {
    const rep = items.find((x) => x.id === id);
    keySets.set(id, new Set(rep ? eachDateKeyInCardRange(rep) : []));
  });
  const neighbors = new Map(datedIds.map((id) => [id, new Set()]));
  for (let i = 0; i < datedIds.length; i += 1) {
    for (let j = i + 1; j < datedIds.length; j += 1) {
      const a = datedIds[i];
      const b = datedIds[j];
      const Sa = keySets.get(a);
      const Sb = keySets.get(b);
      let overlap = false;
      for (const k of Sa) {
        if (Sb.has(k)) {
          overlap = true;
          break;
        }
      }
      if (overlap) {
        neighbors.get(a).add(b);
        neighbors.get(b).add(a);
      }
    }
  }

  datedIds.sort((x, y) => neighbors.get(y).size - neighbors.get(x).size);
  datedIds.forEach((id) => {
    const colored = new Set();
    neighbors.get(id).forEach((nid) => {
      if (colorById.has(nid)) colored.add(nid);
    });
    colorById.set(id, pickBestColor(id, colored));
  });

  const undatedIds = [...new Set(undated.map((i) => i.id))].sort((x, y) => x - y);
  undatedIds.forEach((id) => {
    const colored = new Set();
    undatedIds.forEach((other) => {
      if (other !== id && colorById.has(other)) colored.add(other);
    });
    colorById.set(id, pickBestColor(id, colored));
  });

  const fallback = CALENDAR_CARD_ACCENT_PALETTE[0];
  items.forEach((item) => {
    item.accent = colorById.get(item.id) || fallback;
  });
  undated.forEach((item) => {
    item.accent = colorById.get(item.id) || fallback;
  });
}
/** Drag-reschedule anchor: due_date (end) if set, else start_date. */
function getCardCalendarAnchorDate(card) {
  const dueM = String(card.due_date || '').trim().match(/^(\d{4}-\d{2}-\d{2})/);
  const startM = String(card.start_date || '').trim().match(/^(\d{4}-\d{2}-\d{2})/);
  const e = dueM ? dueM[1] : '';
  const s = startM ? startM[1] : '';
  if (e && parseDateInput(e)) return e;
  if (s && parseDateInput(s)) return s;
  return '';
}
/** Inclusive YYYY-MM-DD keys from start_date through due_date (sorted). Single-date cards return one key. */
function eachDateKeyInCardRange(item) {
  const startRaw = calendarDateInputValue(item.start_date);
  const dueRaw = calendarDateInputValue(item.due_date);
  const startD = startRaw ? parseDateInput(startRaw) : null;
  const dueD = dueRaw ? parseDateInput(dueRaw) : null;
  if (startD && dueD) {
    let a = startD;
    let b = dueD;
    if (a.getTime() > b.getTime()) {
      const t = a;
      a = b;
      b = t;
    }
    const keys = [];
    const cur = new Date(a.getFullYear(), a.getMonth(), a.getDate());
    const end = new Date(b.getFullYear(), b.getMonth(), b.getDate());
    while (cur.getTime() <= end.getTime()) {
      keys.push(dateKey(cur));
      cur.setDate(cur.getDate() + 1);
    }
    return keys;
  }
  if (startD) return [dateKey(startD)];
  if (dueD) return [dateKey(dueD)];
  const m = item.date && String(item.date).match(/^(\d{4}-\d{2}-\d{2})/);
  if (m && parseDateInput(m[1])) return [m[1]];
  return [];
}
/** Chip layout role for a day in the card's date span: start | end | both | middle. */
function calendarChipSpanRole(item, dayKey) {
  const startRaw = calendarDateInputValue(item.start_date);
  const dueRaw = calendarDateInputValue(item.due_date);
  const startOk = !!(startRaw && parseDateInput(startRaw));
  const dueOk = !!(dueRaw && parseDateInput(dueRaw));
  if (startOk && dueOk) {
    let lo = startRaw;
    let hi = dueRaw;
    if (lo > hi) {
      const t = lo;
      lo = hi;
      hi = t;
    }
    if (dayKey === lo && dayKey === hi) return 'both';
    if (dayKey === lo) return 'start';
    if (dayKey === hi) return 'end';
    return 'middle';
  }
  return 'both';
}
/** Display title for calendar chips: start keeps title; middle swaps leading "Start"/"Start of" → "On-Going"; end → "End of". */
function calendarChipDisplayTitle(item, spanRole) {
  const raw = String(item.title || '').trim() || 'Untitled';
  if (spanRole === 'middle') {
    if (/^start\s+of\b/i.test(raw)) {
      return raw.replace(/^start\s+of\b/i, 'On-Going');
    }
    if (/^start\b/i.test(raw)) {
      return raw.replace(/^start\b/i, 'On-Going');
    }
    return raw;
  }
  if (spanRole !== 'end') return raw;
  if (/^start\s+of\b/i.test(raw)) {
    return raw.replace(/^start\s+of\b/i, 'End of');
  }
  if (/^start\b/i.test(raw)) {
    return raw.replace(/^start\b/i, 'End of');
  }
  return raw;
}
function calendarDateKeyAddDays(dateStr, delta) {
  const d = parseDateInput(dateStr);
  if (!d) return '';
  d.setDate(d.getDate() + delta);
  return dateKey(d);
}
function calendarDaysDelta(fromKey, toKey) {
  const a = parseDateInput(fromKey);
  const b = parseDateInput(toKey);
  if (!a || !b) return 0;
  const utcA = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
  const utcB = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
  return Math.round((utcB - utcA) / 86400000);
}
/** Date fields for update_card when dropping a chip on targetKey.
 *  start chip → move start_date only; end chip → move due_date only; middle/both → shift range. */
function calendarReschedulePatchesForItem(item, targetKey, options = {}) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(targetKey)) return null;
  const spanRole = String(options.spanRole || '');
  const fromKey = String(options.fromKey || '');
  const dueRaw = calendarDateInputValue(item.due_date);
  const startRaw = calendarDateInputValue(item.start_date);
  const hasDue = !!(dueRaw && parseDateInput(dueRaw));
  const hasStart = !!(startRaw && parseDateInput(startRaw));
  const startTime = calendarTimeInputValue(item.start_time);
  const dueTime = calendarTimeInputValue(item.due_time);

  if (!hasDue && !hasStart) {
    return {
      start_date: '',
      due_date: targetKey,
      start_time: '',
      due_time: dueTime
    };
  }

  // Drag start endpoint: only move start_date (clamp so start <= due).
  if (spanRole === 'start' && hasStart) {
    let newStart = targetKey;
    if (hasDue && newStart > dueRaw) newStart = dueRaw;
    if (newStart === startRaw) return null;
    return {
      start_date: newStart,
      due_date: dueRaw || '',
      start_time: startTime,
      due_time: dueTime
    };
  }

  // Drag end/due endpoint: only move due_date (clamp so due >= start).
  if (spanRole === 'end' && hasDue) {
    let newDue = targetKey;
    if (hasStart && newDue < startRaw) newDue = startRaw;
    if (newDue === dueRaw) return null;
    return {
      start_date: startRaw || '',
      due_date: newDue,
      start_time: startTime,
      due_time: dueTime
    };
  }

  // Single-day card (start === due): move both to target.
  if (hasStart && hasDue && startRaw === dueRaw) {
    if (targetKey === startRaw) return null;
    return {
      start_date: targetKey,
      due_date: targetKey,
      start_time: startTime,
      due_time: dueTime
    };
  }

  // Middle chip or fallback: shift whole range by delta from dragged day (or calendar anchor).
  const anchor = (fromKey && parseDateInput(fromKey)) ? fromKey : getCardCalendarAnchorDate(item);
  if (!anchor) {
    return {
      start_date: startRaw || '',
      due_date: targetKey,
      start_time: startTime,
      due_time: dueTime
    };
  }
  const delta = calendarDaysDelta(anchor, targetKey);
  if (delta === 0) return null;
  if (hasDue && hasStart) {
    return {
      start_date: calendarDateKeyAddDays(startRaw, delta),
      due_date: calendarDateKeyAddDays(dueRaw, delta),
      start_time: startTime,
      due_time: dueTime
    };
  }
  if (hasDue) {
    return {
      start_date: startRaw || '',
      due_date: targetKey,
      start_time: startTime,
      due_time: dueTime
    };
  }
  return {
    start_date: targetKey,
    due_date: dueRaw || '',
    start_time: startTime,
    due_time: dueTime
  };
}
function itemOverlapsCalendarMonth(item, monthStr) {
  return eachDateKeyInCardRange(item).some((k) => k.startsWith(monthStr));
}
function isPostedCard(card) {
  const col = String(card.column_name || '').toLowerCase();
  return parseInt(card.progress || 0, 10) >= 100 || /(done|posted|publish|published|live|complete|completed)/i.test(col);
}
function calendarBoardMemberPicUrl(profilePic) {
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
function boardMemberPicUrl(profilePic) {
  return calendarBoardMemberPicUrl(profilePic) || '';
}
function calendarAssigneeInitials(name) {
  if (!name || !String(name).trim()) return '?';
  return String(name).trim().split(/\s+/).map(w => w.charAt(0)).slice(0, 2).join('').toUpperCase();
}
function renderCalendarChipAssigneesHtml(assignees) {
  const items = Array.isArray(assignees) ? assignees.filter(Boolean) : [];
  if (!items.length) return '';
  const visible = items.map((member) => {
    const src = calendarBoardMemberPicUrl(member.profile_pic);
    const name = (member.name || '').trim() || 'User';
    if (src) {
      return `<img class="cg-calendar-chip__assignee-avatar" src="${escapeHtml(src)}" alt="${escapeHtml(name)}" title="${escapeHtml(name)}" loading="lazy" />`;
    }
    return `<span class="cg-calendar-chip__assignee-avatar is-initials" title="${escapeHtml(name)}">${escapeHtml(calendarAssigneeInitials(name))}</span>`;
  }).join('');
  return `<div class="cg-calendar-chip__assignees">${visible}</div>`;
}
function flattenCalendarItems(data, options = {}) {
  const cols = data.columns || [];
  const cardsBy = data.cardsByColumn || {};
  const boardName = options.boardName || '';
  const allBoardsMode = !!options.allBoardsMode;
  const boardIdNum = options.boardId != null && options.boardId !== ''
    ? parseInt(options.boardId, 10)
    : parseInt(data.board_id, 10);
  const resolvedBoardId = Number.isFinite(boardIdNum) ? boardIdNum : 0;

  const items = [];
  const undated = [];
  cols.forEach(col => {
    const colId = col.id;
    const cards = cardsBy[colId] || cardsBy[String(colId)] || [];
    cards.forEach(card => {
      const displayDate = getCardCalendarAnchorDate(card);
      const item = {
        id: parseInt(card.id, 10),
        title: card.title || 'Untitled',
        description: calendarCardDescriptionText(card) || '',
        date: displayDate,
        start_date: card.start_date || '',
        due_date: card.due_date || '',
        due_time: card.due_time || '',
        start_time: card.start_time || '',
        column_id: parseInt(col.id, 10),
        column_name: col.name || '',
        board_id: resolvedBoardId,
        board_name: boardName,
        progress: parseInt(card.progress || 0, 10),
        priority: card.priority || '',
        links: card.links || '',
        attachments: card.attachments || '',
        protected: parseInt(card.protected || 0, 10) ? 1 : 0,
        card_type: card.card_type || '',
        link_url: card.link_url || '',
        context_label: allBoardsMode && boardName ? `${boardName} / ${col.name || 'Column'}` : (col.name || boardName || 'Content'),
        status: isPostedCard({ ...card, column_name: col.name || '' }) ? 'posted' : 'scheduled',
        accent: '#6366f1',
        assignees: Array.isArray(card.assignees) ? card.assignees : []
      };
      if (displayDate) items.push(item);
      else undated.push(item);
    });
  });

  assignCalendarChipAccents(items, undated);

  items.sort((a, b) => {
    if (a.date !== b.date) return a.date.localeCompare(b.date);
    const ta = calendarChipTimeSortMinutes(a);
    const tb = calendarChipTimeSortMinutes(b);
    if (ta !== tb) return ta - tb;
    if (a.status !== b.status) return a.status === 'posted' ? 1 : -1;
    return a.title.localeCompare(b.title);
  });
  return { items, undated };
}
async function fetchJson(url) {
  const crossOrigin = /^https?:\/\//i.test(String(url));
  const res = await fetch(url, { credentials: crossOrigin ? 'include' : 'same-origin', cache: 'no-store' });
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (err) {
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
async function fetchBoardBootstrap(boardId) {
  return fetchJson(`${CG_FK_API}?action=bootstrap&board_id=${encodeURIComponent(boardId)}&_t=${Date.now()}`);
}
async function fetchAccessibleBoards() {
  const data = await fetchJson(`${CG_FK_API}?action=list_all&_t=${Date.now()}`);
  return data.boards || [];
}
function findCalendarItem(itemId) {
  return (CAL.allItems || []).find(item => parseInt(item.id, 10) === parseInt(itemId, 10)) || null;
}
function buildCalendarKanbanChatDragPayload(itemId) {
  const calItem = findCalendarItem(itemId);
  if (!calItem) return null;
  const cid = parseInt(calItem.id, 10) || 0;
  if (!cid) return null;
  const bid = parseInt(calItem.board_id, 10) || (String(CAL.boardId) !== 'all' ? parseInt(String(CAL.boardId), 10) : 0) || 0;
  const title = String(calItem.title || '').trim();
  const columnName = String(calItem.column_name || calItem.context_label || '').trim();
  const sourceUrl = typeof window.cgPortalKanbanBoardHref === 'function'
    ? window.cgPortalKanbanBoardHref(String(bid || ''), String(cid))
    : `/kanban?board_id=${encodeURIComponent(bid)}&card_id=${encodeURIComponent(cid)}`;
  return {
    card_id: cid,
    board_id: bid,
    title,
    column_name: columnName,
    source_url: sourceUrl
  };
}
window.cgCalendarResolveBoardChatCardPayload = function (calRaw) {
  try {
    const ids = calRaw ? JSON.parse(calRaw) : null;
    if (!Array.isArray(ids) || !ids.length) return null;
    return buildCalendarKanbanChatDragPayload(ids[0]);
  } catch (e) {
    return null;
  }
};
function formatCalendarCardTime(t) {
  if (t == null || String(t).trim() === '') return '';
  const m = String(t).trim().match(/^(\d{1,2}):(\d{2})/);
  if (!m) return '';
  let h = parseInt(m[1], 10);
  const min = m[2];
  const am = h < 12;
  const h12 = h % 12 || 12;
  return `${h12}:${min} ${am ? 'AM' : 'PM'}`;
}
/** Time on chip only when the anchor day is the due date (due time). */
function calendarChipAnchorTimeLabel(item) {
  const dueM = String(item.due_date || '').trim().match(/^(\d{4}-\d{2}-\d{2})/);
  const hasDue = dueM && parseDateInput(dueM[1]);
  if (!hasDue) return '';
  return formatCalendarCardTime(item.due_time);
}
function calendarChipTimeSortMinutes(item) {
  const dueM = String(item.due_date || '').trim().match(/^(\d{4}-\d{2}-\d{2})/);
  const hasDue = dueM && parseDateInput(dueM[1]);
  if (!hasDue) return 24 * 60 + 59;
  const m = String(item.due_time || '').trim().match(/^(\d{1,2}):(\d{2})/);
  if (!m) return 24 * 60 + 59;
  return parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
}
function formatDateRange(startDate, dueDate, dueTime) {
  if (!startDate && !dueDate) return '';
  const fmt = (value) => {
    const d = parseDateInput(value);
    return d ? d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : value;
  };
  const dt = formatCalendarCardTime(dueTime);
  const sufDue = dt ? ` ${dt}` : '';
  if (startDate && dueDate) return `${fmt(startDate)} - ${fmt(dueDate)}${sufDue}`;
  if (dueDate) return `${fmt(dueDate)}${sufDue}`;
  return fmt(startDate);
}
function buildCalendarBubbleContent(item) {
  if (!item) return '';
  const title = escapeHtml(item.title || 'Untitled');
  const desc = calendarCardDescriptionText(item);
  const descHtml = desc
    ? `<div class="bubble-desc bubble-desc--html">${calendarFormatDescriptionForBubble(desc)}</div>`
    : '';
  const priority = (item.priority || '').trim();
  const priorityHtml = priority ? `<div class="bubble-meta"><strong>Priority:</strong> ${escapeHtml(priority)}</div>` : '';
  const dateRange = formatDateRange(item.start_date, item.due_date, item.due_time);
  const dateHtml = dateRange ? `<div class="bubble-meta"><strong>Date:</strong> ${escapeHtml(dateRange)}</div>` : '';
  const assignees = Array.isArray(item.assignees) ? item.assignees.filter(Boolean) : [];
  const assigneesBubble = assignees.length
    ? `<div class="bubble-meta"><strong>Assigned</strong></div><div class="bubble-assignees">${assignees.map((m) => {
      const src = calendarBoardMemberPicUrl(m.profile_pic);
      const name = (m.name || '').trim() || 'User';
      if (src) {
        return `<img class="bubble-assignee-avatar" src="${escapeHtml(src)}" alt="" title="${escapeHtml(name)}" loading="lazy" />`;
      }
      return `<span class="bubble-assignee-avatar is-initials" title="${escapeHtml(name)}">${escapeHtml(calendarAssigneeInitials(name))}</span>`;
    }).join('')}</div>`
    : '';
  const statusHtml = `<div class="bubble-meta"><strong>Status:</strong> ${escapeHtml(item.status)} | <strong>Column:</strong> ${escapeHtml(item.column_name || 'Content')}</div>`;
  const progress = Math.max(0, Math.min(100, parseInt(item.progress != null ? item.progress : 0, 10)));
  const progressClass = progress >= 100 ? 'bubble-progress-done' : (progress > 50 ? 'bubble-progress-doing' : 'bubble-progress-todo');
  const progressHtml = `<div class="bubble-progress-wrap"><div class="bubble-progress-bar"><div class="bubble-progress-fill ${progressClass}" style="width:${progress}%"></div></div><span class="small fw-bold bubble-progress-pct">${progress}%</span></div>`;
  let linksArr = [];
  try {
    if (typeof item.links === 'string' && item.links) linksArr = JSON.parse(item.links);
    else if (Array.isArray(item.links)) linksArr = item.links;
  } catch (e) {}
  const linkCardUrl = calendarIsLinkCard(item) ? calendarNormalizeLinkUrl(item.link_url) : '';
  if (linkCardUrl && !linksArr.some((u) => calendarNormalizeLinkUrl(u) === linkCardUrl)) {
    linksArr.unshift(linkCardUrl);
  }
  const linksHtml = linksArr.length
    ? '<div class="bubble-links mt-2"><div class="bubble-comments-title">Links</div><div class="bubble-links-list">' + linksArr.map(function(url) {
        const u = (url && url.trim) ? url.trim() : String(url);
        if (!u) return '';
        return '<a href="' + escapeHtml(u) + '" target="_blank" rel="noopener noreferrer" class="bubble-link small"><i class="fas fa-external-link-alt flex-shrink-0"></i><span class="bubble-link-text">' + escapeHtml(u) + '</span></a>';
      }).join('') + '</div></div>'
    : '';
  const attachmentsHtml = calendarBuildAttachmentsHtml(calendarParseAttachments(item), item);
  return `<div class="bubble-title">${title}</div>${assigneesBubble}${statusHtml}${dateHtml}${priorityHtml}${descHtml}${progressHtml}${attachmentsHtml}${linksHtml}`;
}
function openCalendarDetailModal(itemId) {
  const item = findCalendarItem(parseInt(itemId, 10));
  if (!item) return;
  if (!calendarCanOpenCardDirectly(item)) {
    calendarOpenCardPasswordModal(itemId, 'detail');
    return;
  }
  openCalendarDetailModalInternal(itemId);
}
function openCalendarDetailModalInternal(itemId) {
  const item = findCalendarItem(parseInt(itemId, 10));
  if (!item) return;
  const body = document.getElementById('calendarCardDetailModalBody');
  const modalEl = document.getElementById('calendarCardDetailModal');
  if (!body || !modalEl) return;
  const raw = calendarLookupBootstrapCard(item.board_id, item.id);
  const merged = Object.assign({}, item);
  if (raw) {
    const dRaw = calendarCardDescriptionText(raw);
    const dItem = calendarCardDescriptionText(item);
    const dLoose = raw.description != null && typeof raw.description === 'string' ? raw.description.trim() : '';
    merged.description = dRaw || (dLoose && dLoose !== 'undefined' && dLoose !== 'null' ? dLoose : '') || dItem;
    if (raw.links != null) merged.links = raw.links;
    if (raw.attachments != null) merged.attachments = raw.attachments;
    if (raw.protected != null) merged.protected = raw.protected;
    if (raw.card_type != null) merged.card_type = raw.card_type;
    if (raw.link_url != null) merged.link_url = raw.link_url;
  }
  body.innerHTML = buildCalendarBubbleContent(merged);
  body.querySelectorAll('.calendar-protected-attachment').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const cardId = parseInt(el.getAttribute('data-card-id') || '0', 10);
      const path = el.getAttribute('data-att-path') || '';
      const name = el.getAttribute('data-att-name') || 'Attachment';
      calendarOpenAttachmentPasswordModal(cardId, path, name);
    });
  });
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}
function getCalendarBoardColumns() {
  if (!CAL.bootstrap || String(CAL.boardId) === 'all') return [];
  return Array.isArray(CAL.bootstrap.columns) ? CAL.bootstrap.columns : [];
}
function openCalendarAddCardModal(dateKey) {
  if (!dateKey || !/^\d{4}-\d{2}-\d{2}$/.test(dateKey)) return;
  if (!CAL.boardId || String(CAL.boardId) === 'all') {
    alert('Select a board (not “All boards”) to add a card.');
    return;
  }
  const cols = getCalendarBoardColumns();
  if (!cols.length) {
    alert('This board has no columns yet. Add a column in Kanban first.');
    return;
  }
  const colSel = qs('#calendarAddCardColumn');
  if (!colSel) return;
  colSel.innerHTML = cols.map((c) => `<option value="${escapeHtml(String(c.id))}">${escapeHtml(c.name || 'Column')}</option>`).join('');
  const titleIn = qs('#calendarAddCardTitle');
  const descIn = qs('#calendarAddCardDescription');
  const priIn = qs('#calendarAddCardPriority');
  const startIn = qs('#calendarAddCardStart');
  const dueIn = qs('#calendarAddCardDue');
  const dueTimeIn = qs('#calendarAddCardDueTime');
  if (titleIn) titleIn.value = '';
  if (descIn) descIn.value = '';
  if (priIn) priIn.value = '';
  if (startIn) startIn.value = dateKey;
  if (dueIn) dueIn.value = dateKey;
  if (dueTimeIn) dueTimeIn.value = '';
  const hint = qs('#calendarAddCardDateHint');
  const d = parseDateInput(dateKey);
  const nice = d ? d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : dateKey;
  if (hint) hint.textContent = `Start and due dates default to ${nice}. Change them or set a due time if you need.`;
  const submitBtn = qs('#calendarAddCardSubmit');
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create card';
  }
  const modalEl = qs('#calendarAddCardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => titleIn && titleIn.focus(), 400);
  }
}

let calendarDayMenuDateKey = '';
let calendarCardMenuItemId = '';
let calendarEmptyDayState = { dateKey: '', items: [], cardsByColumn: null };

function hideCalendarCardContextMenu() {
  const m = qs('#calendarCardContextMenu');
  if (!m) return;
  m.hidden = true;
  m.setAttribute('aria-hidden', 'true');
  calendarCardMenuItemId = '';
}

function hideCalendarDayContextMenu() {
  const m = qs('#calendarDayContextMenu');
  if (!m) return;
  m.hidden = true;
  m.setAttribute('aria-hidden', 'true');
  calendarDayMenuDateKey = '';
}

function positionCalendarFloatingMenu(menuEl, clientX, clientY) {
  if (!menuEl) return;
  menuEl.style.left = '0px';
  menuEl.style.top = '0px';
  const rect = menuEl.getBoundingClientRect();
  const pad = 8;
  let x = clientX;
  let y = clientY;
  if (x + rect.width > window.innerWidth - pad) x = Math.max(pad, window.innerWidth - rect.width - pad);
  if (y + rect.height > window.innerHeight - pad) y = Math.max(pad, window.innerHeight - rect.height - pad);
  if (x < pad) x = pad;
  if (y < pad) y = pad;
  menuEl.style.left = `${x}px`;
  menuEl.style.top = `${y}px`;
}

function positionCalendarDayContextMenu(clientX, clientY) {
  positionCalendarFloatingMenu(qs('#calendarDayContextMenu'), clientX, clientY);
}

function showCalendarCardContextMenu(clientX, clientY, itemId) {
  hideCalendarDayContextMenu();
  const m = qs('#calendarCardContextMenu');
  if (!m || !itemId) return;
  calendarCardMenuItemId = String(itemId);
  m.hidden = false;
  m.setAttribute('aria-hidden', 'false');
  requestAnimationFrame(() => {
    positionCalendarFloatingMenu(m, clientX, clientY);
  });
}

function showCalendarDayContextMenu(clientX, clientY, dateKey) {
  hideCalendarCardContextMenu();
  const m = qs('#calendarDayContextMenu');
  if (!m || !dateKey) return;
  calendarDayMenuDateKey = dateKey;
  const dayItems = getCardsOnCalendarDay(dateKey);
  const addBtn = qs('#calendarDayMenuAddCard');
  const emptyBtn = qs('#calendarDayMenuEmptySlot');
  const allBoards = !CAL.boardId || String(CAL.boardId) === 'all';
  if (addBtn) {
    addBtn.disabled = allBoards;
    addBtn.classList.toggle('is-disabled', allBoards);
    addBtn.title = allBoards ? 'Select a single board to add a card' : '';
  }
  if (emptyBtn) {
    const hasCards = dayItems.length > 0;
    emptyBtn.disabled = !hasCards;
    emptyBtn.classList.toggle('is-disabled', !hasCards);
    emptyBtn.title = hasCards ? '' : 'No cards on this day';
  }
  m.hidden = false;
  m.setAttribute('aria-hidden', 'false');
  requestAnimationFrame(() => {
    positionCalendarDayContextMenu(clientX, clientY);
  });
}

async function openCalendarEmptyDayModal(dateKey, items) {
  if (!dateKey || !items || !items.length) return;
  calendarEmptyDayState = { dateKey, items: items.slice(), cardsByColumn: null };
  const modalEl = qs('#calendarEmptyDayModal');
  const intro = qs('#calendarEmptyDayIntro');
  const listEl = qs('#calendarEmptyDayList');
  const moveSec = qs('#calendarEmptyDayMoveSection');
  const moveCol = qs('#calendarEmptyDayMoveColumn');
  const moveHint = qs('#calendarEmptyDayMoveHint');
  const d = parseDateInput(dateKey);
  const nice = d ? d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : dateKey;
  if (intro) {
    intro.textContent = `${items.length} card(s) on ${nice}. Clear dates to remove them from the calendar, move all into one column, or delete permanently.`;
  }
  if (listEl) {
    listEl.innerHTML = items.map((it) => `<li class="list-group-item d-flex justify-content-between align-items-start gap-2 px-3 py-2">
      <span class="text-break">${escapeHtml(it.title || 'Untitled')}</span>
      ${it.board_name ? `<span class="text-muted small flex-shrink-0">${escapeHtml(it.board_name)}</span>` : ''}
    </li>`).join('');
  }
  const boardIds = [...new Set(items.map((i) => parseInt(i.board_id, 10)).filter(Boolean))];
  let showMove = false;
  if (moveSec && moveCol && moveHint) {
    moveCol.innerHTML = '';
    if (boardIds.length === 1) {
      let cols = [];
      let cbc = null;
      const bid = boardIds[0];
      if (String(CAL.boardId) !== 'all' && String(bid) === String(CAL.boardId) && CAL.bootstrap && CAL.bootstrap.columns) {
        cols = CAL.bootstrap.columns;
        cbc = CAL.bootstrap.cardsByColumn || {};
      } else {
        try {
          const data = await fetchBoardBootstrap(bid);
          cols = data.columns || [];
          cbc = data.cardsByColumn || {};
        } catch (e) {
          cols = [];
          cbc = null;
        }
      }
      calendarEmptyDayState.cardsByColumn = cbc;
      if (cols.length && cbc) {
        showMove = true;
        moveCol.innerHTML = cols.map((c) => `<option value="${escapeHtml(String(c.id))}">${escapeHtml(c.name || 'Column')}</option>`).join('');
        moveHint.textContent = 'Keeps scheduled dates. Use this to move every card into one column (same as Kanban).';
      } else {
        moveHint.textContent = 'Could not load columns or board layout for moving.';
      }
    } else {
      moveHint.textContent = 'Move all is only available when every card on this day is on the same board. Filter to one board or edit cards individually.';
    }
    moveSec.style.display = showMove ? '' : 'none';
  }
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}

async function calendarPostMoveCardsPayload(moves) {
  if (!moves || !moves.length) return;
  const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
  const res = await fetch(`${CG_FK_API}?action=move_cards`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: crossOrigin ? 'include' : 'same-origin',
    body: JSON.stringify({ moves })
  });
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (err) {
    data = null;
  }
  if (!res.ok) {
    throw new Error((data && data.message) ? data.message : (raw.substring(0, 200) || `HTTP ${res.status}`));
  }
  if (!data || !data.success) {
    throw new Error((data && data.message) ? data.message : 'Failed to move card(s)');
  }
}
async function calendarUpdateCardPreserveClearDates(item) {
  const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
  const fd = new FormData();
  fd.append('action', 'update_card');
  fd.append('card_id', String(item.id));
  fd.append('title', ((item.title || '').trim() || 'Untitled'));
  fd.append('description', calendarCardDescriptionText(item) || (item.description != null ? String(item.description).trim() : ''));
  fd.append('start_date', '');
  fd.append('due_date', '');
  fd.append('start_time', '');
  fd.append('due_time', '');
  fd.append('progress', String(Math.max(0, Math.min(100, parseInt(item.progress, 10) || 0))));
  fd.append('priority', (item.priority || '').trim());
  fd.append('links', calendarItemLinksJsonString(item));
  fd.append('existing_attachments', calendarItemAttachmentsPostJson(item));
  const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: crossOrigin ? 'include' : 'same-origin' });
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    data = null;
  }
  if (!res.ok) {
    throw new Error((data && data.message) ? data.message : (raw.substring(0, 200) || `HTTP ${res.status}`));
  }
  if (!data || !data.success) {
    throw new Error((data && data.message) ? data.message : 'Failed to update card');
  }
}
async function calendarDeleteCardById(cardId) {
  const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
  const creds = crossOrigin ? 'include' : 'same-origin';
  const base = String(CG_FK_API || 'kanban_api.php');
  const url = base + (base.includes('?') ? '&' : '?') + 'action=delete_card';
  let lastErr = null;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      const res = await fetch(url, {
        method: 'POST',
        credentials: creds,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ card_id: cardId })
      });
      const raw = await res.text();
      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (e) {
        data = null;
      }
      if (!res.ok) {
        throw new Error((data && data.message) ? data.message : (raw.substring(0, 200) || `HTTP ${res.status}`));
      }
      if (!data || !data.success) {
        throw new Error((data && data.message) ? data.message : 'Failed to delete card');
      }
      return;
    } catch (err) {
      lastErr = err;
      const msg = String(err && err.message ? err.message : err || '');
      if (!(err instanceof TypeError || msg.includes('NetworkError') || msg.includes('Failed to fetch')) || attempt >= 3) {
        throw err;
      }
      await new Promise((resolve) => setTimeout(resolve, 350 * attempt));
    }
  }
  throw lastErr;
}
async function calendarPostUpdateCardWithDates(item, patch) {
  const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
  const fd = new FormData();
  fd.append('action', 'update_card');
  fd.append('card_id', String(item.id));
  fd.append('title', ((item.title || '').trim() || 'Untitled'));
  fd.append('description', calendarCardDescriptionText(item) || (item.description != null ? String(item.description).trim() : ''));
  fd.append('start_date', patch.start_date != null ? String(patch.start_date) : '');
  fd.append('due_date', patch.due_date != null ? String(patch.due_date) : '');
  fd.append('start_time', patch.start_time != null ? String(patch.start_time) : '');
  fd.append('due_time', patch.due_time != null ? String(patch.due_time) : '');
  fd.append('progress', String(Math.max(0, Math.min(100, parseInt(item.progress, 10) || 0))));
  fd.append('priority', (item.priority || '').trim());
  fd.append('links', calendarItemLinksJsonString(item));
  fd.append('existing_attachments', calendarItemAttachmentsPostJson(item));
  const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: crossOrigin ? 'include' : 'same-origin' });
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    data = null;
  }
  if (!res.ok) {
    throw new Error((data && data.message) ? data.message : (raw.substring(0, 200) || `HTTP ${res.status}`));
  }
  if (!data || !data.success) {
    throw new Error((data && data.message) ? data.message : 'Failed to update card');
  }
}
function calendarRepartitionDatedUndated() {
  if (!Array.isArray(CAL.allItems) || !CAL.allItems.length) return;
  const dated = [];
  const undated = [];
  for (let i = 0; i < CAL.allItems.length; i++) {
    const it = CAL.allItems[i];
    const anchor = getCardCalendarAnchorDate(it);
    if (anchor) {
      it.date = anchor;
      dated.push(it);
    } else {
      it.date = '';
      undated.push(it);
    }
  }
  const allBoards = String(CAL.boardId) === 'all';
  if (allBoards) {
    dated.sort((a, b) => {
      if (a.date !== b.date) return a.date.localeCompare(b.date);
      if (String(a.board_name || '') !== String(b.board_name || '')) {
        return String(a.board_name || '').localeCompare(String(b.board_name || ''));
      }
      const ta = calendarChipTimeSortMinutes(a);
      const tb = calendarChipTimeSortMinutes(b);
      if (ta !== tb) return ta - tb;
      return a.title.localeCompare(b.title);
    });
  } else {
    dated.sort((a, b) => {
      if (a.date !== b.date) return a.date.localeCompare(b.date);
      const ta = calendarChipTimeSortMinutes(a);
      const tb = calendarChipTimeSortMinutes(b);
      if (ta !== tb) return ta - tb;
      if (a.status !== b.status) return a.status === 'posted' ? 1 : -1;
      return a.title.localeCompare(b.title);
    });
  }
  CAL.items = dated;
  CAL.undatedItems = undated;
  CAL.allItems = dated.concat(undated);
}
async function calendarApplyDropReschedule(ids, targetKey, options = {}) {
  const seen = new Set();
  const work = [];
  for (let i = 0; i < ids.length; i++) {
    const nid = parseInt(ids[i], 10);
    if (!nid || seen.has(nid)) continue;
    seen.add(nid);
    const item = findCalendarItem(nid);
    if (!item) continue;
    const patch = calendarReschedulePatchesForItem(item, targetKey, options);
    if (!patch) continue;
    work.push({ item, patch });
  }
  if (!work.length) return;
  for (let w = 0; w < work.length; w++) {
    const { item, patch } = work[w];
    item.start_date = patch.start_date;
    item.due_date = patch.due_date;
    item.start_time = patch.start_time;
    item.due_time = patch.due_time;
    const anchor = getCardCalendarAnchorDate(item);
    item.date = anchor || '';
  }
  calendarRepartitionDatedUndated();
  CAL.selectedCardIds.clear();
  renderCalendar();
  try {
    await Promise.all(work.map(({ item, patch }) => calendarPostUpdateCardWithDates(item, patch)));
    await loadCalendarData({ quiet: true });
  } catch (err) {
    console.error(err);
    alert(err.message || 'Failed to move card(s).');
    await loadCalendarData();
  }
}

let calendarDeleteCardConfirmOnConfirm = null;
let calendarDeleteCardConfirmUsedOk = false;

function showCalendarDeleteCardConfirmModal(onConfirm) {
  const modalEl = qs('#calendarDeleteCardConfirmModal');
  if (typeof onConfirm !== 'function') return;
  if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
    if (window.confirm('Are you sure you want to delete this card? This cannot be undone.')) {
      onConfirm();
    }
    return;
  }
  calendarDeleteCardConfirmUsedOk = false;
  calendarDeleteCardConfirmOnConfirm = onConfirm;
  const inst = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
  inst.show();
}

function wireCalendarDeleteCardConfirmModal() {
  const modalEl = qs('#calendarDeleteCardConfirmModal');
  const okBtn = qs('#calendarDeleteCardConfirmOk');
  if (!modalEl || !okBtn || okBtn.dataset.cgCalendarDeleteConfirmWired) return;
  okBtn.dataset.cgCalendarDeleteConfirmWired = '1';
  okBtn.addEventListener('click', () => {
    calendarDeleteCardConfirmUsedOk = true;
    const cb = calendarDeleteCardConfirmOnConfirm;
    calendarDeleteCardConfirmOnConfirm = null;
    const inst = bootstrap.Modal.getInstance(modalEl);
    if (inst) inst.hide();
    try {
      if (typeof cb === 'function') cb();
    } catch (err) {
      console.error('[Calendar] delete confirm callback:', err);
    }
  });
  modalEl.addEventListener('hidden.bs.modal', () => {
    if (!calendarDeleteCardConfirmUsedOk) {
      calendarDeleteCardConfirmOnConfirm = null;
    }
    calendarDeleteCardConfirmUsedOk = false;
  });
}
wireCalendarDeleteCardConfirmModal();

function openCalendarEditCardModal(itemId) {
  const id = parseInt(itemId, 10);
  const item = findCalendarItem(id);
  if (!item) return;
  if (!calendarCanOpenCardDirectly(item)) {
    calendarOpenCardPasswordModal(id, 'edit');
    return;
  }
  openCalendarEditCardModalInternal(id);
}
function openCalendarEditCardModalInternal(itemId) {
  const id = parseInt(itemId, 10);
  const item = findCalendarItem(id);
  if (!item) return;
  const idInput = qs('#calendarEditCardId');
  if (idInput) idInput.value = String(id);
  const startTimeHid = qs('#calendarEditCardStartTimePreserved');
  if (startTimeHid) startTimeHid.value = calendarTimeInputValue(item.start_time);
  const linksHid = qs('#calendarEditCardLinksPreserved');
  if (linksHid) linksHid.value = calendarItemLinksJsonString(item);
  const hint = qs('#calendarEditCardHint');
  if (hint) {
    const bits = [];
    if (item.board_name) bits.push(item.board_name);
    if (item.column_name) bits.push(item.column_name);
    hint.textContent = bits.length ? bits.join(' · ') : '';
  }
  const titleIn = qs('#calendarEditCardTitle');
  const descIn = qs('#calendarEditCardDescription');
  const priIn = qs('#calendarEditCardPriority');
  if (titleIn) titleIn.value = item.title || '';
  if (descIn) descIn.value = calendarCardDescriptionText(item) || (item.description != null ? String(item.description) : '');
  if (priIn) priIn.value = (item.priority || '').trim();
  const prog = Math.max(0, Math.min(100, parseInt(item.progress, 10) || 0));
  const progEl = qs('#calendarEditCardProgress');
  const progVal = qs('#calendarEditCardProgressVal');
  if (progEl) progEl.value = String(prog);
  if (progVal) progVal.textContent = String(prog);
  const startIn = qs('#calendarEditCardStart');
  const dueIn = qs('#calendarEditCardDue');
  const dueTimeIn = qs('#calendarEditCardDueTime');
  if (startIn) startIn.value = calendarDateInputValue(item.start_date);
  if (dueIn) dueIn.value = calendarDateInputValue(item.due_date);
  if (dueTimeIn) dueTimeIn.value = calendarTimeInputValue(item.due_time);
  const attHid = qs('#calendarEditCardAttachmentsJson');
  if (attHid) attHid.value = calendarItemAttachmentsPostJson(item);
  const submitBtn = qs('#calendarEditCardSubmit');
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save';
  }
  const modalEl = qs('#calendarEditCardModal');
  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => titleIn && titleIn.focus(), 400);
  }
}
async function openCalendarAssignCardModal(itemId) {
  const id = parseInt(itemId, 10);
  const item = findCalendarItem(id);
  if (!item) return;
  const modalEl = qs('#calendarAssignCardModal');
  const listEl = qs('#calendarAssignCardMembersList');
  const preview = qs('#calendarAssignCardTitlePreview');
  const hiddenId = qs('#calendar_assign_card_id');
  if (!modalEl || !listEl || !hiddenId) return;

  hiddenId.value = String(id);
  if (preview) {
    preview.textContent = `Assign collaborators to "${(item.title || 'this card').replace(/"/g, '\u2019')}".`;
  }

  let members = CAL.board_members || [];
  if (String(CAL.boardId) === 'all' && item.board_id) {
    try {
      const data = await fetchBoardBootstrap(item.board_id);
      members = Array.isArray(data.board_members) ? data.board_members : [];
    } catch (err) {
      console.error(err);
      alert(err.message || 'Could not load collaborators for this board.');
      return;
    }
  }

  const assignees = Array.isArray(item.assignees) ? item.assignees : [];
  const selectedIds = new Set(assignees.map((m) => parseInt(m.id, 10)).filter(Boolean));

  if (!members.length) {
    listEl.innerHTML = '<div class="text-muted small p-3">No collaborators available on this board.</div>';
  } else {
    listEl.innerHTML = members.map((member) => {
      const mid = parseInt(member.id, 10);
      const src = calendarBoardMemberPicUrl(member.profile_pic);
      const name = (member.name || '').trim() || 'User';
      const roleLabel = member.role === 'owner' ? 'Owner' : 'Collaborator';
      const checked = selectedIds.has(mid) ? 'checked' : '';
      const avatarHtml = src
        ? `<img class="cg-assign-item__avatar" src="${escapeHtml(src)}" alt="${escapeHtml(name)}" loading="lazy">`
        : `<span class="cg-assign-item__initials">${escapeHtml(calendarAssigneeInitials(name))}</span>`;
      return `
          <label class="cg-assign-item">
            <input class="form-check-input mt-0" type="checkbox" name="calendar_assigned_user_ids[]" value="${mid}" ${checked}>
            ${avatarHtml}
            <span class="cg-assign-item__meta">
              <span class="cg-assign-item__name d-block">${escapeHtml(name)}</span>
              <span class="cg-assign-item__role">${escapeHtml(roleLabel)}</span>
            </span>
          </label>
        `;
    }).join('');
  }

  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}
function updateHistoryParams() {
  const url = new URL(window.location.href);
  if (CAL.boardId) url.searchParams.set('board_id', CAL.boardId);
  url.searchParams.set('month', CAL.month);
  window.history.replaceState({}, '', url);
}
function renderBoardSelect() {
  const select = qs('#calendarBoardSelect');
  if (!select || !CAL.bootstrap) return;
  const boards = CAL.bootstrap.boards || [];
  select.innerHTML = `
    <option value="all" ${String(CAL.boardId) === 'all' ? 'selected' : ''}>All boards</option>
  ` + boards.map(board => `
    <option value="${board.id}" ${String(board.id) === String(CAL.boardId) ? 'selected' : ''}>
      ${escapeHtml(board.name || 'Board')}
    </option>
  `).join('');
}
function calendarNormalizeSearchText(value) {
  return String(value == null ? '' : value).toLowerCase().replace(/\s+/g, ' ').trim();
}
function calendarSearchTokens(query) {
  const normalized = calendarNormalizeSearchText(query);
  return normalized ? normalized.split(' ') : [];
}
function rebuildCalendarSearchIndex() {
  const allItems = Array.isArray(CAL.allItems) ? CAL.allItems : [];
  CAL_SEARCH_INDEX = allItems.map((item) => {
    const assignees = Array.isArray(item.assignees) ? item.assignees.map((a) => (a && (a.name || a.full_name || a.email || ''))).filter(Boolean).join(' ') : '';
    const descPlain = calendarDescPlainText(calendarCardDescriptionText(item) || item.description || '');
    const blob = [
      item.title || '',
      descPlain,
      item.context_label || '',
      item.column_name || '',
      item.priority || '',
      item.status || '',
      calendarChipAnchorTimeLabel(item) || '',
      assignees
    ].join(' ');
    return {
      itemId: parseInt(item.id, 10) || 0,
      title: String(item.title || 'Untitled').trim() || 'Untitled',
      description: calendarCardDescriptionText(item) || String(item.description != null ? item.description : '').trim(),
      context: String(item.context_label || item.column_name || '').trim(),
      searchableText: calendarNormalizeSearchText(blob),
      titleNorm: calendarNormalizeSearchText(item.title || ''),
      descNorm: calendarNormalizeSearchText(descPlain)
    };
  });
}
function searchCalendarCards(query) {
  const tokens = calendarSearchTokens(query);
  if (!tokens.length) return [];
  return CAL_SEARCH_INDEX.filter((item) => tokens.every((token) => item.searchableText.includes(token)));
}
function getCalendarSearchElements() {
  return { input: qs('#calendarCardSearchInput'), dropdown: qs('#calendarCardSearchDropdown') };
}
function hideCalendarSearchDropdown() {
  const els = getCalendarSearchElements();
  if (!els.dropdown) return;
  els.dropdown.hidden = true;
  els.dropdown.innerHTML = '';
  CAL_SEARCH_RESULTS = [];
  CAL_SEARCH_ACTIVE_INDEX = -1;
}
function renderCalendarSearchDropdown(results) {
  const els = getCalendarSearchElements();
  if (!els.dropdown) return;
  CAL_SEARCH_RESULTS = results.slice(0, 24);
  CAL_SEARCH_ACTIVE_INDEX = CAL_SEARCH_RESULTS.length ? 0 : -1;
  if (!CAL_SEARCH_RESULTS.length) {
    els.dropdown.innerHTML = '<div class="cg-calendar-card-search-item__meta px-3 py-2">No matching cards</div>';
    els.dropdown.hidden = false;
    return;
  }
  els.dropdown.innerHTML = CAL_SEARCH_RESULTS.map((item, idx) => {
    const activeClass = idx === CAL_SEARCH_ACTIVE_INDEX ? ' is-active' : '';
    const descSrc = calendarCardDescriptionText(item) || (item.description != null ? String(item.description) : '');
    const descPreview = descSrc ? calendarDescPlainText(descSrc).slice(0, 140) : '';
    const meta = item.context + (descPreview ? ' - ' + descPreview : '');
    return '<button type="button" class="cg-calendar-card-search-item' + activeClass + '" data-search-index="' + idx + '">' +
      '<span class="cg-calendar-card-search-item__title">' + escapeHtml(item.title) + '</span>' +
      '<span class="cg-calendar-card-search-item__meta">' + escapeHtml(meta || 'Card') + '</span>' +
    '</button>';
  }).join('');
  els.dropdown.hidden = false;
}
function refreshCalendarSearchDropdown() {
  const els = getCalendarSearchElements();
  if (!els.input) return;
  const query = els.input.value || '';
  if (!query.trim()) {
    hideCalendarSearchDropdown();
    return;
  }
  renderCalendarSearchDropdown(searchCalendarCards(query));
}
function clearCalendarBreathingFocus() {
  qsa('.cg-calendar-chip.cg-calendar-chip--search-breathing').forEach((chip) => chip.classList.remove('cg-calendar-chip--search-breathing'));
  CAL_ACTIVE_BREATHING_IDS = new Set();
}
function groupedCalendarItemIds(selected) {
  if (!selected) return [];
  const ids = CAL_SEARCH_INDEX.filter((item) => {
    const titleMatch = selected.titleNorm && item.titleNorm && item.titleNorm === selected.titleNorm;
    const descMatch = selected.descNorm && item.descNorm && item.descNorm === selected.descNorm;
    return titleMatch || descMatch;
  }).map((item) => item.itemId);
  if (!ids.length && selected.itemId) ids.push(selected.itemId);
  return Array.from(new Set(ids.filter(Boolean)));
}
function applyCalendarBreathingFocus(itemIds) {
  clearCalendarBreathingFocus();
  const uniqueIds = Array.from(new Set((itemIds || []).map((v) => parseInt(v, 10) || 0).filter(Boolean)));
  uniqueIds.forEach((id) => {
    qsa('.cg-calendar-chip[data-item-id="' + id + '"]').forEach((chip) => chip.classList.add('cg-calendar-chip--search-breathing'));
    CAL_ACTIVE_BREATHING_IDS.add(id);
  });
  if (uniqueIds.length === 1) {
    const firstChip = qs('.cg-calendar-chip[data-item-id="' + uniqueIds[0] + '"]');
    if (firstChip) firstChip.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
  }
}
function selectCalendarSearchResult(index) {
  const picked = CAL_SEARCH_RESULTS[index];
  if (!picked) return;
  applyCalendarBreathingFocus(groupedCalendarItemIds(picked));
  const els = getCalendarSearchElements();
  if (els.input) els.input.value = '';
  hideCalendarSearchDropdown();
}
function syncCalendarBreathingAfterRender() {
  if (!CAL_ACTIVE_BREATHING_IDS.size) return;
  const ids = Array.from(CAL_ACTIVE_BREATHING_IDS);
  CAL_ACTIVE_BREATHING_IDS = new Set();
  ids.forEach((id) => {
    const chips = qsa('.cg-calendar-chip[data-item-id="' + id + '"]');
    if (!chips.length) return;
    chips.forEach((chip) => chip.classList.add('cg-calendar-chip--search-breathing'));
    CAL_ACTIVE_BREATHING_IDS.add(id);
  });
}
function initCalendarSearchUI() {
  const els = getCalendarSearchElements();
  if (!els.input || !els.dropdown || els.input.dataset.cgCalSearchInit === '1') return;
  els.input.dataset.cgCalSearchInit = '1';
  els.input.addEventListener('input', refreshCalendarSearchDropdown);
  els.input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      hideCalendarSearchDropdown();
      return;
    }
    if (!CAL_SEARCH_RESULTS.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      CAL_SEARCH_ACTIVE_INDEX = Math.min(CAL_SEARCH_ACTIVE_INDEX + 1, CAL_SEARCH_RESULTS.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      CAL_SEARCH_ACTIVE_INDEX = Math.max(CAL_SEARCH_ACTIVE_INDEX - 1, 0);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      selectCalendarSearchResult(CAL_SEARCH_ACTIVE_INDEX >= 0 ? CAL_SEARCH_ACTIVE_INDEX : 0);
      return;
    } else {
      return;
    }
    qsa('.cg-calendar-card-search-item', els.dropdown).forEach((btn, idx) => btn.classList.toggle('is-active', idx === CAL_SEARCH_ACTIVE_INDEX));
  });
  els.dropdown.addEventListener('click', (e) => {
    const btn = e.target.closest('.cg-calendar-card-search-item');
    if (!btn) return;
    const idx = parseInt(btn.getAttribute('data-search-index') || '-1', 10);
    if (idx >= 0) selectCalendarSearchResult(idx);
  });
}
function getCalendarKanbanHref() {
  if (typeof window.cgPortalKanbanBoardHref === 'function') {
    if (!CAL.boardId || String(CAL.boardId) === 'all') return window.cgPortalKanbanBoardHref('');
    return window.cgPortalKanbanBoardHref(CAL.boardId);
  }
  if (!CAL.boardId || String(CAL.boardId) === 'all') return '/boards';
  return `/kanban?board_id=${encodeURIComponent(CAL.boardId)}`;
}

function updateCalendarViewMenu() {
  const workspaceBtn = qs('#cgBottomNavWorkspace');
  const timelineBtn = qs('#cgBottomNavTimeline');
  const isAll = !CAL.boardId || String(CAL.boardId) === 'all';
  if (workspaceBtn) {
    workspaceBtn.disabled = isAll;
    workspaceBtn.classList.toggle('disabled', isAll);
    workspaceBtn.setAttribute('aria-disabled', isAll ? 'true' : 'false');
  }
  if (timelineBtn) {
    timelineBtn.disabled = isAll;
    timelineBtn.classList.toggle('disabled', isAll);
    timelineBtn.setAttribute('aria-disabled', isAll ? 'true' : 'false');
  }
}

function navigateCalendarViewKanban() {
  window.location.href = getCalendarKanbanHref();
}

function openCalendarViewWorkspace() {
  const boardId = CAL.boardId;
  if (!boardId || String(boardId) === 'all') {
    alert('Please select a board to open Workspace.');
    return;
  }
  window.open(`workspace.php?board_id=${encodeURIComponent(boardId)}`, '_blank', 'noopener,noreferrer');
}

async function openCalendarViewTimeline() {
  const boardId = CAL.boardId;
  if (!boardId || String(boardId) === 'all') {
    alert('Please select a board to open the timeline.');
    return;
  }
  try {
    let isOwner = false;
    if (CAL.bootstrap && String(CAL.bootstrap.board_id) === String(boardId) && typeof CAL.bootstrap.is_board_owner !== 'undefined') {
      isOwner = !!CAL.bootstrap.is_board_owner;
    } else {
      const boot = await fetchJson(`${CG_FK_API}?action=bootstrap&board_id=${encodeURIComponent(boardId)}&_t=${Date.now()}`);
      isOwner = !!boot.is_board_owner;
    }
    if (!isOwner) {
      const res = await fetch(`${CG_FK_API}?action=get_client_view_url&board_id=${encodeURIComponent(boardId)}`, { credentials: 'include' });
      const data = await res.json();
      if (!data.success) {
        alert(data.message || 'Client link not available. Ask the board owner to share the timeline.');
        return;
      }
      window.open(data.url || '', '_blank', 'noopener,noreferrer');
      return;
    }
    const res = await fetch(`${CG_FT_API}?action=get_or_create_chart&board_id=${encodeURIComponent(boardId)}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to open timeline');
      return;
    }
    window.location.href = `project_timeline.php?chart_id=${encodeURIComponent(data.chart_id)}`;
  } catch (err) {
    console.error('Timeline navigation error:', err);
    alert(err.message || 'Failed to open timeline.');
  }
}

async function shareCalendarClient() {
  const boardId = CAL.boardId;
  if (!boardId || String(boardId) === 'all') {
    alert('Please select a board to share its calendar preview link.');
    return;
  }
  try {
    const res = await fetch(`${CG_FK_API}?action=get_client_calendar_view_url&board_id=${encodeURIComponent(boardId)}&month=${encodeURIComponent(CAL.month || '')}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success || !data.url) {
      alert(data.message || 'Client link not available. Ask the board owner to share.');
      return;
    }
    const input = qs('#calendarShareLink');
    if (input) input.value = data.url;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(qs('#calendarShareModal')).show();
    }
  } catch (err) {
    console.error('Share calendar error:', err);
    alert(err.message || 'Failed to generate share link.');
  }
}

function renderHeader() {
  const titleEl = qs('#calendarBoardTitle');
  const subtitleEl = qs('#calendarBoardSubtitle');
  if (!CAL.bootstrap) return;
  const currentBoard = (CAL.bootstrap.boards || []).find(board => String(board.id) === String(CAL.boardId));
  const boardName = String(CAL.boardId) === 'all' ? 'All Boards' : (currentBoard ? currentBoard.name : 'Content Calendar');
  const monthItems = CAL.items.filter(item => itemOverlapsCalendarMonth(item, CAL.month));
  const postedCount = monthItems.filter(item => item.status === 'posted').length;
  const scheduledCount = monthItems.length - postedCount;
  if (titleEl) titleEl.textContent = boardName;
  if (subtitleEl) {
    subtitleEl.textContent = `${monthItems.length} posts in ${formatMonthLabel(CAL.month)} | ${scheduledCount} scheduled | ${postedCount} posted`;
  }
  updateCalendarViewMenu();
  qs('#calendarMonthLabel').textContent = formatMonthLabel(CAL.month);
  if (typeof cgSetBoardTabTitle === 'function') {
    cgSetBoardTabTitle(boardName, 'Calendar');
  }
}
function renderCalendarGrid() {
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const weekdaysEl = qs('#calendarWeekdays');
  weekdaysEl.innerHTML = weekdays.map(day => `<div class="cg-calendar-weekday">${day}</div>`).join('');

  const monthDate = parseDateInput(`${CAL.month}-01`);
  if (!monthDate) return;
  const year = monthDate.getFullYear();
  const monthIndex = monthDate.getMonth();
  const gridStart = new Date(year, monthIndex, 1);
  gridStart.setDate(gridStart.getDate() - gridStart.getDay());
  const todayKey = dateKey(new Date());
  const itemsByDate = {};
  CAL.items.forEach(item => {
    eachDateKeyInCardRange(item).forEach((key) => {
      if (!itemsByDate[key]) itemsByDate[key] = [];
      itemsByDate[key].push(item);
    });
  });

  const cells = [];
  for (let i = 0; i < 42; i += 1) {
    const cellDate = new Date(gridStart);
    cellDate.setDate(gridStart.getDate() + i);
    const key = dateKey(cellDate);
    const isOtherMonth = cellDate.getMonth() !== monthIndex;
    const dayItems = itemsByDate[key] || [];
    const weekdayLabel = weekdays[cellDate.getDay()];
    const hideOnGoing = !!CAL.hideOnGoing;
    const chips = dayItems.length ? (() => {
      const fullParts = [];
      const miniParts = [];
      dayItems.forEach(item => {
        const spanRole = calendarChipSpanRole(item, key);
        const isMini = spanRole === 'middle';
        if (isMini && hideOnGoing) return;
        const displayTitle = calendarChipDisplayTitle(item, spanRole);
        const chipLockHtml = calendarIsCardProtected(item)
          ? '<span class="cg-calendar-chip-lock" title="Password protected on Kanban"><i class="fas fa-lock" aria-hidden="true"></i></span>'
          : '';
        const descLine = calendarChipDescriptionLine(item);
        if (isMini) {
          const titleAttr = escapeHtml([displayTitle, descLine].filter(Boolean).join(' | '));
          miniParts.push(`
      <div class="cg-calendar-chip cg-calendar-chip--mini cg-calendar-chip--${item.status}" data-item-id="${item.id}" data-span-role="${spanRole}" draggable="true" style="--chip-accent:${escapeHtml(item.accent)}" title="${titleAttr}">
        <div class="cg-calendar-chip-title">${chipLockHtml}<span class="cg-calendar-chip-title-text">${escapeHtml(displayTitle)}</span></div>
      </div>
    `);
          return;
        }
        const chipTime = calendarChipAnchorTimeLabel(item);
        const chipTimeHtml = chipTime
          ? `<div class="cg-calendar-chip-time"><i class="fas fa-clock me-1" aria-hidden="true"></i>${escapeHtml(chipTime)}</div>`
          : '';
        const titleBits = [displayTitle, descLine, chipTime].filter(Boolean);
        const titleAttr = escapeHtml(titleBits.join(' | '));
        const metaHtml = descLine
          ? `<div class="cg-calendar-chip-meta"><span>${escapeHtml(descLine)}</span></div>`
          : '';
        fullParts.push(`
      <div class="cg-calendar-chip cg-calendar-chip--${item.status}" data-item-id="${item.id}" data-span-role="${spanRole}" draggable="true" style="--chip-accent:${escapeHtml(item.accent)}" title="${titleAttr}">
        <div class="cg-calendar-chip-title">${chipLockHtml}<span class="cg-calendar-chip-title-text">${escapeHtml(displayTitle)}</span></div>
        ${chipTimeHtml}
        ${metaHtml}
        ${renderCalendarChipAssigneesHtml(item.assignees || [])}
      </div>
    `);
      });
      const visibleCount = fullParts.length + miniParts.length;
      if (!visibleCount) return '<div class="cg-calendar-empty">No posts</div>';
      return fullParts.join('') + (miniParts.length
        ? `<div class="cg-calendar-day-minis">${miniParts.join('')}</div>`
        : '');
    })() : '<div class="cg-calendar-empty">No posts</div>';

    const visibleDayCount = dayItems.filter((item) => {
      if (!hideOnGoing) return true;
      return calendarChipSpanRole(item, key) !== 'middle';
    }).length;

    cells.push(`
      <div class="cg-calendar-day ${isOtherMonth ? 'is-other-month' : ''} ${key === todayKey ? 'is-today' : ''}" data-cg-date="${key}">
        <div class="cg-calendar-day-head" data-weekday="${weekdayLabel}">
          <span class="cg-calendar-day-number">${cellDate.getDate()}</span>
          <span class="cg-calendar-day-count">${visibleDayCount ? `${visibleDayCount} item${visibleDayCount === 1 ? '' : 's'}` : ''}</span>
        </div>
        <div class="cg-calendar-day-items">${chips}</div>
      </div>
    `);
  }
  qs('#calendarGrid').innerHTML = cells.join('');
}
function renderUndatedItems() {
  const wrap = qs('#calendarUndatedWrap');
  const list = qs('#calendarUndatedList');
  if (!wrap || !list) return;
  if (!CAL.undatedItems.length) {
    wrap.style.display = 'none';
    list.innerHTML = '';
    return;
  }
  wrap.style.display = '';
  list.innerHTML = CAL.undatedItems.map(item => {
    const chipLockHtml = calendarIsCardProtected(item)
      ? '<span class="cg-calendar-chip-lock" title="Password protected on Kanban"><i class="fas fa-lock" aria-hidden="true"></i></span>'
      : '';
    const descLine = calendarChipDescriptionLine(item);
    return `
    <div class="cg-calendar-chip cg-calendar-chip--undated" data-item-id="${item.id}" draggable="true" style="--chip-accent:${escapeHtml(item.accent)}">
      <div class="cg-calendar-chip-title">${chipLockHtml}<span class="cg-calendar-chip-title-text">${escapeHtml(item.title)}</span></div>
      <div class="cg-calendar-chip-meta">
        ${descLine ? `<span>${escapeHtml(descLine)}</span>` : '<span></span>'}
        <span class="cg-calendar-chip-status">No date</span>
      </div>
      ${renderCalendarChipAssigneesHtml(item.assignees || [])}
    </div>
  `;
  }).join('');
}
function calendarApplyChipSelectionDom() {
  const sel = CAL.selectedCardIds;
  qsa('.cg-calendar-chip[data-item-id]').forEach((el) => {
    const id = parseInt(el.getAttribute('data-item-id'), 10);
    if (!id) return;
    el.classList.toggle('is-selected', sel && sel.has(id));
  });
}
function calendarToggleCardSelection(cardId) {
  const id = parseInt(cardId, 10);
  if (!id) return;
  if (CAL.selectedCardIds.has(id)) CAL.selectedCardIds.delete(id);
  else CAL.selectedCardIds.add(id);
  calendarApplyChipSelectionDom();
}
function calendarClearCardSelection() {
  if (!CAL.selectedCardIds.size) return;
  CAL.selectedCardIds.clear();
  calendarApplyChipSelectionDom();
}
function calendarGetDragIdsForChip(chip) {
  const id = parseInt(chip.getAttribute('data-item-id'), 10);
  if (!id) return [];
  const sel = CAL.selectedCardIds;
  if (sel && sel.size > 0 && sel.has(id)) {
    return [...sel].map((x) => parseInt(x, 10)).filter((n) => n > 0).sort((a, b) => a - b);
  }
  return [id];
}
function calendarClearDropTargetHighlight() {
  qsa('.cg-calendar-day.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'));
}
function wireCalendarCardDnD() {
  const grid = qs('#calendarGrid');
  const undated = qs('#calendarUndatedList');
  if (!grid || grid.dataset.cgCalDndWired) return;
  grid.dataset.cgCalDndWired = '1';
  document.addEventListener('dragstart', (e) => {
    const chip = e.target && e.target.closest ? e.target.closest('.cg-calendar-chip') : null;
    if (!chip || !chip.getAttribute('data-item-id')) return;
    const inGrid = grid.contains(chip);
    const inUnd = undated && undated.contains(chip);
    if (!inGrid && !inUnd) return;
    const ids = calendarGetDragIdsForChip(chip);
    if (!ids.length) return;
    calendarDndFromCalendarChip = true;
    calendarDragSessionCardIds = ids.slice();
    calendarDragSessionSpanRole = chip.getAttribute('data-span-role') || '';
    const fromDay = chip.closest('.cg-calendar-day');
    calendarDragSessionFromKey = (fromDay && fromDay.getAttribute('data-cg-date')) || '';
    try {
      e.dataTransfer.setData(CG_CALENDAR_CARD_DND_MIME, JSON.stringify(ids));
      e.dataTransfer.setData('text/plain', JSON.stringify(ids));
      e.dataTransfer.setData('application/x-cg-calendar-span-role', calendarDragSessionSpanRole);
      e.dataTransfer.setData('application/x-cg-calendar-from-key', calendarDragSessionFromKey);
    } catch (err) {}
    const firstId = ids[0];
    const chatPayload = buildCalendarKanbanChatDragPayload(firstId);
    if (chatPayload) {
      const json = JSON.stringify(chatPayload);
      try {
        e.dataTransfer.setData('application/x-cinegrid-kanban-card', json);
      } catch (err) {}
      try {
        e.dataTransfer.setData('text/x-cinegrid-kanban-card', json);
      } catch (err) {}
      try {
        e.dataTransfer.setData('text', 'CINEGRID_KANBAN_CARD:' + json);
      } catch (err) {}
    }
    e.dataTransfer.effectAllowed = 'move';
  }, true);
  document.addEventListener('dragend', (e) => {
    const chip = e.target && e.target.closest ? e.target.closest('.cg-calendar-chip') : null;
    if (chip && (grid.contains(chip) || (undated && undated.contains(chip)))) {
      calendarLastChipDragEndedAt = Date.now();
    }
    /* Defer clearing so `drop` on #calendarGrid always sees session ids (Safari/WebKit may dispatch dragend before drop). */
    setTimeout(() => {
      calendarDndFromCalendarChip = false;
      calendarDragSessionCardIds = null;
      calendarDragSessionSpanRole = '';
      calendarDragSessionFromKey = '';
      calendarClearDropTargetHighlight();
    }, 0);
  });
  grid.addEventListener('dragenter', (e) => {
    if (!calendarDndFromCalendarChip && !(calendarDragSessionCardIds && calendarDragSessionCardIds.length)) return;
    e.preventDefault();
  });
  grid.addEventListener('dragover', (e) => {
    if (!calendarDndFromCalendarChip && !(calendarDragSessionCardIds && calendarDragSessionCardIds.length)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const day = e.target.closest('.cg-calendar-day');
    calendarClearDropTargetHighlight();
    if (day && day.getAttribute('data-cg-date')) day.classList.add('is-drop-target');
  });
  grid.addEventListener('drop', (e) => {
    let ids = [];
    try {
      const raw = e.dataTransfer.getData(CG_CALENDAR_CARD_DND_MIME) || e.dataTransfer.getData('text/plain');
      const parsed = raw ? JSON.parse(raw) : null;
      if (Array.isArray(parsed) && parsed.length) {
        ids = parsed.map((x) => parseInt(x, 10)).filter((n) => n > 0);
      }
    } catch (err) {}
    if (!ids.length && calendarDragSessionCardIds && calendarDragSessionCardIds.length) {
      ids = calendarDragSessionCardIds.slice();
    }
    const day = e.target.closest('.cg-calendar-day');
    const dk = day && day.getAttribute('data-cg-date');
    if (!dk || !ids.length) return;
    e.preventDefault();
    e.stopPropagation();
    calendarClearDropTargetHighlight();
    let spanRole = calendarDragSessionSpanRole || '';
    let fromKey = calendarDragSessionFromKey || '';
    try {
      spanRole = e.dataTransfer.getData('application/x-cg-calendar-span-role') || spanRole;
      fromKey = e.dataTransfer.getData('application/x-cg-calendar-from-key') || fromKey;
    } catch (err) {}
    void calendarApplyDropReschedule(ids, dk, { spanRole, fromKey });
  });
}
function renderCalendar() {
  updateHistoryParams();
  applyCalendarAssignmentFilter();
  renderBoardSelect();
  renderCalendarAssignmentFilter();
  renderCalendarHideOnGoingBtn();
  renderHeader();
  renderCalendarGrid();
  renderUndatedItems();
  renderCalendarBoardMembersAvatars();
  calendarApplyChipSelectionDom();
  rebuildCalendarSearchIndex();
  syncCalendarBreathingAfterRender();
}
function syncCalendarBoardChat() {
  if (typeof window.cgSyncBoardChatFromPortal !== 'function') return;
  const id = String(CAL.boardId || '');
  const bid = (id === 'all' || !id) ? 0 : id;
  syncCalendarChatPresenceGlobals();
  window.cgSyncBoardChatFromPortal(CAL.board_members || [], bid);
}
async function loadCalendarData(options) {
  const quiet = options && options.quiet === true;
  if (!quiet && typeof window.cgPortalLoadingBegin === 'function') {
    window.cgPortalLoadingBegin('calendarLoadingOverlay');
  }
  try {
  CAL.month = parseMonthValue(CAL.month);
  if (String(CAL.boardId) === 'all') {
    const boards = await fetchAccessibleBoards();
    const bootstraps = await Promise.all(boards.map(board => fetchBoardBootstrap(board.id)));
    const aggregate = { boards, board_id: 'all' };
    const allItems = [];
    const undatedItems = [];
    bootstraps.forEach((data, idx) => {
      const boardName = boards[idx] ? (boards[idx].name || 'Board') : 'Board';
      const bid = boards[idx] ? boards[idx].id : (data.board_id || '');
      const flattened = flattenCalendarItems(data, { boardName, allBoardsMode: true, boardId: bid });
      allItems.push(...flattened.items);
      undatedItems.push(...flattened.undated);
    });
    allItems.sort((a, b) => {
      if (a.date !== b.date) return a.date.localeCompare(b.date);
      if (a.board_name !== b.board_name) return String(a.board_name).localeCompare(String(b.board_name));
      const ta = calendarChipTimeSortMinutes(a);
      const tb = calendarChipTimeSortMinutes(b);
      if (ta !== tb) return ta - tb;
      return a.title.localeCompare(b.title);
    });
    CAL.bootstrap = aggregate;
    CAL.boardBootstrapById = {};
    bootstraps.forEach((data, idx) => {
      const bid = boards[idx] ? boards[idx].id : (data.board_id || '');
      if (bid != null && String(bid) !== '') CAL.boardBootstrapById[String(bid)] = data;
    });
    CAL.board_members = mergeCalendarBoardMembersFromBootstraps(bootstraps);
    CAL.sourceItems = allItems;
    CAL.sourceUndatedItems = undatedItems;
    renderCalendar();
    startCalendarPresencePolling();
    syncCalendarBoardChat();
    return;
  }

  const data = await fetchBoardBootstrap(CAL.boardId || '');
  CAL.bootstrap = data;
  CAL.boardBootstrapById = {};
  const singleBid = String(data.board_id || CAL.boardId || '');
  if (singleBid) CAL.boardBootstrapById[singleBid] = data;
  CAL.board_members = Array.isArray(data.board_members) ? data.board_members : [];
  CAL.boardId = String(data.board_id || CAL.boardId || '');
  const currentBoard = (data.boards || []).find(board => String(board.id) === String(CAL.boardId));
  const flattened = flattenCalendarItems(data, { boardName: currentBoard ? currentBoard.name : '', boardId: data.board_id });
  CAL.sourceItems = flattened.items;
  CAL.sourceUndatedItems = flattened.undated;
  renderCalendar();
  startCalendarPresencePolling();
  syncCalendarBoardChat();
  } finally {
    if (!quiet && typeof window.cgPortalLoadingEnd === 'function') {
      window.cgPortalLoadingEnd('calendarLoadingOverlay');
    }
  }
}
function stepMonth(delta) {
  const base = parseDateInput(`${CAL.month}-01`) || new Date();
  base.setMonth(base.getMonth() + delta);
  CAL.month = `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}`;
  renderCalendar();
}
const calBnKanban = qs('#cgBottomNavKanban');
const calBnTimeline = qs('#cgBottomNavTimeline');
const calBnWorkspace = qs('#cgBottomNavWorkspace');
if (calBnKanban) calBnKanban.addEventListener('click', navigateCalendarViewKanban);
if (calBnTimeline) calBnTimeline.addEventListener('click', openCalendarViewTimeline);
if (calBnWorkspace) calBnWorkspace.addEventListener('click', openCalendarViewWorkspace);
qs('#calendarShareBtn').addEventListener('click', shareCalendarClient);
qs('#calendarCopyShareBtn').addEventListener('click', async () => {
  const input = qs('#calendarShareLink');
  if (!input || !input.value) return;
  try {
    await navigator.clipboard.writeText(input.value);
    alert('Share link copied!');
  } catch (_) {
    input.select();
    document.execCommand('copy');
    alert('Share link copied!');
  }
});

qs('#calendarPrevMonthBtn').addEventListener('click', () => stepMonth(-1));
qs('#calendarNextMonthBtn').addEventListener('click', () => stepMonth(1));
document.querySelectorAll('[data-kanban-theme-toggle]').forEach((b) => {
  if (!window.__cgKanbanThemeToggleWired) b.addEventListener('click', toggleKanbanTheme);
});
const calThemeBtn = qs('#themeToggleBtn');
if (calThemeBtn && !calThemeBtn.hasAttribute('data-kanban-theme-toggle')) {
  calThemeBtn.addEventListener('click', toggleKanbanTheme);
}
qs('#calendarBoardSelect').addEventListener('change', (e) => {
  CAL.boardId = e.target.value || '';
  CAL.selectedCardIds.clear();
  loadCalendarData().catch(showCalendarError);
});
qsa('[data-calendar-assignment-filter]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const next = btn.getAttribute('data-calendar-assignment-filter') === 'mine' ? 'mine' : 'all';
    if (CAL.assignmentFilter === next) return;
    CAL.assignmentFilter = next;
    CAL.selectedCardIds.clear();
    storeCalendarAssignmentFilter(next);
    hideCalendarSearchDropdown();
    renderCalendar();
  });
});
(() => {
  const btn = qs('#calendarHideOngoingBtn');
  if (!btn || btn.dataset.cgHideOngoingWired === '1') return;
  btn.dataset.cgHideOngoingWired = '1';
  btn.addEventListener('click', () => {
    CAL.hideOnGoing = !CAL.hideOnGoing;
    storeCalendarHideOnGoing(CAL.hideOnGoing);
    hideCalendarSearchDropdown();
    renderCalendar();
  });
})();
wireCalendarCardDnD();
initCalendarSearchUI();

function showCalendarError(err) {
  console.error('Calendar load error:', err);
  qs('#calendarGrid').innerHTML = `<div class="cg-calendar-loading"><div class="alert alert-danger mb-0"><i class="fas fa-exclamation-circle me-2"></i>${escapeHtml(err.message || 'Failed to load content calendar.')}</div></div>`;
}

function calendarActivateChip(itemId) {
  openCalendarDetailModal(itemId);
}

document.addEventListener('click', function(e) {
  const chipEl = e.target.closest('.cg-calendar-chip');
  if (chipEl) {
    if (chipEl.classList.contains('cg-calendar-chip--search-breathing')) {
      clearCalendarBreathingFocus();
      return;
    }
    const itemId = chipEl.getAttribute('data-item-id');
    if (!itemId) return;
    if (e.metaKey || e.ctrlKey) {
      e.preventDefault();
      e.stopPropagation();
      calendarToggleCardSelection(parseInt(itemId, 10));
      return;
    }
    if (Date.now() - calendarLastChipDragEndedAt < 450) return;
    calendarClearCardSelection();
    calendarActivateChip(itemId);
    return;
  }
  const t = e.target;
  if (t && t.closest && t.closest('#calendarGrid') && !t.closest('.cg-calendar-chip')) {
    clearCalendarBreathingFocus();
  }
  if (!(t && t.closest && t.closest('#calendarCardSearchWrap'))) {
    hideCalendarSearchDropdown();
  }
});

document.addEventListener('contextmenu', function (e) {
  const chip = e.target.closest('.cg-calendar-chip');
  const grid = qs('#calendarGrid');
  const undated = qs('#calendarUndatedList');
  if (chip && chip.getAttribute('data-item-id')) {
    const inGrid = grid && grid.contains(chip);
    const inUndated = undated && undated.contains(chip);
    if (inGrid || inUndated) {
      e.preventDefault();
      showCalendarCardContextMenu(e.clientX, e.clientY, chip.getAttribute('data-item-id'));
    }
    return;
  }
  if (!grid || !grid.contains(e.target)) return;
  const day = e.target.closest('.cg-calendar-day');
  if (!day) return;
  const dk = day.getAttribute('data-cg-date');
  if (!dk) return;
  e.preventDefault();
  showCalendarDayContextMenu(e.clientX, e.clientY, dk);
}, true);

document.addEventListener('mousedown', (e) => {
  const dayM = qs('#calendarDayContextMenu');
  if (dayM && !dayM.hidden && !e.target.closest('#calendarDayContextMenu')) {
    hideCalendarDayContextMenu();
  }
  const cardM = qs('#calendarCardContextMenu');
  if (cardM && !cardM.hidden && !e.target.closest('#calendarCardContextMenu')) {
    hideCalendarCardContextMenu();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    hideCalendarDayContextMenu();
    hideCalendarCardContextMenu();
    calendarClearCardSelection();
  }
});

const calendarCardMenuEditBtn = qs('#calendarCardMenuEdit');
if (calendarCardMenuEditBtn) {
  calendarCardMenuEditBtn.addEventListener('click', () => {
    const id = calendarCardMenuItemId;
    hideCalendarCardContextMenu();
    if (id) openCalendarEditCardModal(id);
  });
}
const calendarCardMenuAssignBtn = qs('#calendarCardMenuAssign');
if (calendarCardMenuAssignBtn) {
  calendarCardMenuAssignBtn.addEventListener('click', () => {
    const id = calendarCardMenuItemId;
    hideCalendarCardContextMenu();
    if (id) void openCalendarAssignCardModal(id);
  });
}
const calendarCardMenuDeleteBtn = qs('#calendarCardMenuDelete');
if (calendarCardMenuDeleteBtn) {
  calendarCardMenuDeleteBtn.addEventListener('click', () => {
    const idStr = calendarCardMenuItemId;
    hideCalendarCardContextMenu();
    if (!idStr) return;
    const id = parseInt(idStr, 10);
    if (!id) return;
    showCalendarDeleteCardConfirmModal(() => {
      void (async () => {
        try {
          await calendarDeleteCardById(id);
          await loadCalendarData();
        } catch (err) {
          console.error(err);
          alert(err.message || 'Failed to delete card.');
        }
      })();
    });
  });
}

const calendarDayMenuAddBtn = qs('#calendarDayMenuAddCard');
if (calendarDayMenuAddBtn) {
  calendarDayMenuAddBtn.addEventListener('click', () => {
    const dk = calendarDayMenuDateKey;
    hideCalendarDayContextMenu();
    if (dk && CAL.boardId && String(CAL.boardId) !== 'all') {
      openCalendarAddCardModal(dk);
    }
  });
}
const calendarDayMenuEmptyBtn = qs('#calendarDayMenuEmptySlot');
if (calendarDayMenuEmptyBtn) {
  calendarDayMenuEmptyBtn.addEventListener('click', () => {
    const dk = calendarDayMenuDateKey;
    hideCalendarDayContextMenu();
    if (!dk) return;
    const list = getCardsOnCalendarDay(dk);
    if (list.length) {
      openCalendarEmptyDayModal(dk, list).catch((err) => {
        console.error(err);
        alert(err.message || 'Failed to open empty slot.');
      });
    }
  });
}

const calendarEmptyDayClearBtn = qs('#calendarEmptyDayClearBtn');
if (calendarEmptyDayClearBtn) {
  calendarEmptyDayClearBtn.addEventListener('click', async () => {
    const { items } = calendarEmptyDayState;
    if (!items.length) return;
    if (!window.confirm(`Remove ${items.length} card(s) from the calendar? Start and due dates will be cleared; cards stay on the board.`)) return;
    const btn = calendarEmptyDayClearBtn;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Working…';
    try {
      for (const it of items) {
        await calendarUpdateCardPreserveClearDates(it);
      }
      const modalEl = qs('#calendarEmptyDayModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
      }
      await loadCalendarData();
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to clear dates.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });
}

const calendarEmptyDayMoveBtn = qs('#calendarEmptyDayMoveBtn');
if (calendarEmptyDayMoveBtn) {
  calendarEmptyDayMoveBtn.addEventListener('click', async () => {
    const { items, cardsByColumn } = calendarEmptyDayState;
    const colId = parseInt(qs('#calendarEmptyDayMoveColumn').value, 10);
    if (!items.length || !colId || !cardsByColumn) return;
    if (!window.confirm(`Move ${items.length} card(s) to the selected column?`)) return;
    const btn = calendarEmptyDayMoveBtn;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>…';
    try {
      const moves = positionsForBulkMoveToColumn(cardsByColumn, items.map((i) => i.id), colId);
      await calendarPostMoveCardsPayload(moves);
      const modalEl = qs('#calendarEmptyDayModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
      }
      await loadCalendarData();
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to move cards.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });
}

const calendarEmptyDayDeleteBtn = qs('#calendarEmptyDayDeleteBtn');
if (calendarEmptyDayDeleteBtn) {
  calendarEmptyDayDeleteBtn.addEventListener('click', async () => {
    const { items } = calendarEmptyDayState;
    if (!items.length) return;
    if (!window.confirm(`Permanently delete ${items.length} card(s)? This cannot be undone.`)) return;
    const btn = calendarEmptyDayDeleteBtn;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>…';
    try {
      for (const it of items) {
        await calendarDeleteCardById(it.id);
      }
      const modalEl = qs('#calendarEmptyDayModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
      }
      await loadCalendarData();
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to delete cards.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });
}

const calendarEditProg = qs('#calendarEditCardProgress');
const calendarEditProgVal = qs('#calendarEditCardProgressVal');
if (calendarEditProg && calendarEditProgVal) {
  calendarEditProg.addEventListener('input', () => {
    calendarEditProgVal.textContent = calendarEditProg.value;
  });
}

const calendarEditCardForm = qs('#calendarEditCardForm');
if (calendarEditCardForm) {
  calendarEditCardForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const cardId = parseInt(qs('#calendarEditCardId').value, 10);
    const title = (qs('#calendarEditCardTitle').value || '').trim();
    if (!cardId || !title) return;
    const submitBtn = qs('#calendarEditCardSubmit');
    const orig = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }
    try {
      const linksRaw = (qs('#calendarEditCardLinksPreserved').value || '').trim() || '[]';
      const fd = new FormData();
      fd.append('action', 'update_card');
      fd.append('card_id', String(cardId));
      fd.append('title', title);
      fd.append('description', (qs('#calendarEditCardDescription').value || '').trim());
      fd.append('start_date', (qs('#calendarEditCardStart').value || '').trim());
      fd.append('due_date', (qs('#calendarEditCardDue').value || '').trim());
      fd.append('start_time', (qs('#calendarEditCardStartTimePreserved').value || '').trim());
      fd.append('due_time', (qs('#calendarEditCardDueTime').value || '').trim());
      fd.append('progress', String(Math.max(0, Math.min(100, parseInt(qs('#calendarEditCardProgress').value, 10) || 0))));
      fd.append('priority', (qs('#calendarEditCardPriority').value || '').trim());
      fd.append('links', linksRaw);
      fd.append('existing_attachments', (qs('#calendarEditCardAttachmentsJson').value || '').trim() || '[]');
      const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
      const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: crossOrigin ? 'include' : 'same-origin' });
      const raw = await res.text();
      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (parseErr) {
        data = null;
      }
      if (!res.ok) {
        throw new Error((data && data.message) ? data.message : (raw.substring(0, 200) || `HTTP ${res.status}`));
      }
      if (!data || !data.success) {
        throw new Error((data && data.message) ? data.message : 'Failed to save card');
      }
      const modalEl = qs('#calendarEditCardModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
      }
      await loadCalendarData();
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to save card');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = orig;
      }
    }
  });
}

const calendarAssignCardForm = qs('#calendarAssignCardForm');
if (calendarAssignCardForm) {
  calendarAssignCardForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const cardId = parseInt(qs('#calendar_assign_card_id').value || '0', 10);
    if (!cardId) return;
    const listRoot = qs('#calendarAssignCardMembersList');
    const checked = qsa('input[name="calendar_assigned_user_ids[]"]:checked', listRoot);
    const userIds = checked.map((input) => parseInt(input.value, 10)).filter(Boolean);
    const submitBtn = qs('#calendarAssignCardForm button[type="submit"]');
    const orig = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }
    try {
      const fd = new FormData();
      fd.append('card_id', String(cardId));
      fd.append('user_ids', JSON.stringify(userIds));
      const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
      const res = await fetch(`${CG_FK_API}?action=assign_card_members`, {
        method: 'POST',
        body: fd,
        credentials: crossOrigin ? 'include' : 'same-origin'
      });
      const raw = await res.text();
      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (parseErr) {
        data = null;
      }
      if (!res.ok || !data || !data.success) {
        throw new Error((data && data.message) ? data.message : 'Failed to save assignment');
      }
      const modalEl = qs('#calendarAssignCardModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
      }
      await loadCalendarData({ quiet: true });
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to assign card');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = orig;
      }
    }
  });
}

const calendarAddCardForm = qs('#calendarAddCardForm');
if (calendarAddCardForm) {
  calendarAddCardForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const columnId = parseInt(qs('#calendarAddCardColumn').value, 10);
    const title = (qs('#calendarAddCardTitle').value || '').trim();
    if (!columnId || !title) return;
    const submitBtn = qs('#calendarAddCardSubmit');
    const orig = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    }
    const fd = new FormData();
    fd.append('action', 'create_card');
    fd.append('column_id', String(columnId));
    fd.append('title', title);
    fd.append('description', (qs('#calendarAddCardDescription').value || '').trim());
    const pri = (qs('#calendarAddCardPriority').value || '').trim();
    if (pri) fd.append('priority', pri);
    fd.append('start_date', (qs('#calendarAddCardStart').value || '').trim());
    fd.append('due_date', (qs('#calendarAddCardDue').value || '').trim());
    fd.append('due_time', (qs('#calendarAddCardDueTime').value || '').trim());
    try {
      const crossOrigin = /^https?:\/\//i.test(String(CG_FK_API));
      const res = await fetch(CG_FK_API, { method: 'POST', body: fd, credentials: crossOrigin ? 'include' : 'same-origin' });
      const raw = await res.text();
      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (parseErr) {
        data = null;
      }
      if (!res.ok) {
        throw new Error((data && data.message) ? data.message : (raw.substring(0, 200) || `HTTP ${res.status}`));
      }
      if (!data || !data.success) {
        throw new Error((data && data.message) ? data.message : 'Failed to create card');
      }
      const modalEl = qs('#calendarAddCardModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
      }
      await loadCalendarData();
    } catch (err) {
      console.error(err);
      alert(err.message || 'Failed to create card');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = orig;
      }
    }
  });
}

(function wireCalendarAttachmentPasswordModal() {
  calendarWireStackedModalBackdrop('#calendarAttachmentPasswordModal', '#calendarCardDetailModal');
  const submitBtn = document.getElementById('calendarAttachmentPasswordModalSubmit');
  const pwInput = document.getElementById('calendarAttachmentPasswordInput');
  if (submitBtn) submitBtn.addEventListener('click', () => { calendarSubmitAttachmentPassword(); });
  if (pwInput) {
    pwInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        calendarSubmitAttachmentPassword();
      }
    });
  }
})();

(function wireCalendarCardPasswordModal() {
  const submitBtn = document.getElementById('calendarCardPasswordModalSubmit');
  const pwInput = document.getElementById('calendarCardPasswordInput');
  if (submitBtn) submitBtn.addEventListener('click', () => { calendarSubmitCardPassword(); });
  if (pwInput) {
    pwInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        calendarSubmitCardPassword();
      }
    });
  }
})();

applyKanbanTheme(getStoredKanbanTheme());
CAL.assignmentFilter = getStoredCalendarAssignmentFilter();
CAL.hideOnGoing = getStoredCalendarHideOnGoing();
loadCalendarData().catch(showCalendarError);
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') stopCalendarPresencePolling();
  else startCalendarPresencePolling();
});
window.addEventListener('beforeunload', () => {
  stopCalendarPresencePolling();
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
