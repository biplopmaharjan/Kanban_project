<?php
/**
 * Kanban board automation builder modal (when → then).
 * Included from kanban.php only.
 */
?>
<style>
#kanbanAutomationModal .cg-automation-builder-panel {
  background: rgba(15,23,42,0.03);
}
.kanban-theme-dark #kanbanAutomationModal .modal-content,
.kanban-theme-dark #kanbanAutomationModal .modal-header,
.kanban-theme-dark #kanbanAutomationModal .modal-body,
.kanban-theme-dark #kanbanAutomationModal .modal-footer {
  background: #1e293b !important;
  color: #f8fafc !important;
  border-color: rgba(148,163,184,0.18) !important;
}
.kanban-theme-dark #kanbanAutomationModal .btn-close {
  filter: invert(1) grayscale(100%);
}
.kanban-theme-dark #kanbanAutomationModal .cg-automation-builder-panel {
  background: rgba(15,23,42,0.45) !important;
  border-color: rgba(148,163,184,0.22) !important;
}
.kanban-theme-dark #kanbanAutomationModal .fw-semibold,
.kanban-theme-dark #kanbanAutomationModal .form-label,
.kanban-theme-dark #kanbanAutomationModal .modal-title {
  color: #f8fafc !important;
}
.kanban-theme-dark #kanbanAutomationModal .text-muted,
.kanban-theme-dark #kanbanAutomationModal .form-text {
  color: #cbd5e1 !important;
}
.kanban-theme-dark #kanbanAutomationModal .form-select,
.kanban-theme-dark #kanbanAutomationModal .form-control {
  background-color: #0f172a !important;
  color: #f8fafc !important;
  border-color: rgba(148,163,184,0.35) !important;
}
.kanban-theme-dark #kanbanAutomationModal .form-select option,
.kanban-theme-dark #kanbanAutomationModal .form-select optgroup {
  background-color: #0f172a;
  color: #f8fafc;
}
.kanban-theme-dark #kanbanAutomationModal .form-control::placeholder {
  color: #94a3b8;
  opacity: 1;
}
.kanban-theme-dark #kanbanAutomationModal #cgAutomationSavedList .list-group-item {
  background: transparent;
  color: #f8fafc;
  border-color: rgba(148,163,184,0.18);
}
</style>
<div class="modal fade" id="kanbanAutomationModal" tabindex="-1" aria-labelledby="kanbanAutomationModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="kanbanAutomationModalTitle"><i class="fas fa-robot me-2 text-danger"></i>Automations</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-muted small mb-3">Build simple <strong>when → then</strong> rules for <strong>this board</strong>. Rules are stored in this browser and run automatically for board owners (for example: when progress hits 100%, move the card to <em>Done</em>).</p>
        <div class="mb-3">
          <label class="form-label fw-semibold mb-1" for="cgAutomationName">Rule name <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" class="form-control" id="cgAutomationName" placeholder="e.g. Auto-move done work to Done" maxlength="120" autocomplete="off" />
        </div>
        <div class="border rounded-3 p-3 mb-3 cg-automation-builder-panel">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="fw-semibold text-nowrap">If</span>
            <select class="form-select flex-grow-1" id="cgAutomationWhen" style="min-width: 220px; max-width: 100%;" aria-label="When this happens">
              <optgroup label="Progress bar">
                <option value="progress_gte_100">Progress reaches or passes 100%</option>
                <option value="progress_eq_100">Progress is set exactly to 100%</option>
                <option value="progress_gte_75">Progress reaches or passes 75%</option>
                <option value="progress_gte_50">Progress reaches or passes 50%</option>
                <option value="progress_gte_25">Progress reaches or passes 25%</option>
                <option value="card_completed">Card reaches 100% progress (same as “passes 100%”)</option>
              </optgroup>
              <optgroup label="Card">
                <option value="card_added">Card is added to a column…</option>
                <option value="card_added_to_column">Card is added to a specific column…</option>
                <option value="card_moved">Card is moved to any column</option>
                <option value="card_moved_to_column">Card is moved to a specific column…</option>
                <option value="card_assigned_to_member">Card is assigned to a collaborator…</option>
                <option value="card_duplicated">Card is duplicated</option>
                <option value="card_removed">Card is removed or archived</option>
              </optgroup>
              <optgroup label="Media &amp; links">
                <option value="attachment_added">Attachment is added to a card</option>
                <option value="link_added">Link is added to a card</option>
                <option value="youtube_added">YouTube link is added to a card</option>
              </optgroup>
            </select>
          </div>
          <p class="text-muted small mb-0 mt-2 mb-3">Example: when <em>Card is moved to a specific column</em>, pick that column below, then <em>Set progress bar to 100%</em>.</p>
          <div class="mt-2 mb-3" id="cgAutomationTriggerColumnWrap" style="display: none;">
            <label class="form-label fw-semibold mb-1" for="cgAutomationTriggerColumn" id="cgAutomationTriggerColumnLabel">When moved to this column</label>
            <select class="form-select" id="cgAutomationTriggerColumn" aria-label="Column that triggers the rule"></select>
            <div class="form-text" id="cgAutomationTriggerColumnHint">Runs only when a card lands in this column (after drag or menu move).</div>
          </div>
          <div class="mt-2 mb-3" id="cgAutomationTriggerMemberWrap" style="display: none;">
            <label class="form-label fw-semibold mb-1" for="cgAutomationTriggerMember">When assigned to this collaborator</label>
            <select class="form-select" id="cgAutomationTriggerMember" aria-label="Collaborator that triggers the rule"></select>
            <div class="form-text">Runs when this collaborator is newly assigned to a card.</div>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold text-nowrap">Then</span>
            <select class="form-select flex-grow-1" id="cgAutomationAction" style="min-width: 200px; max-width: 100%;" aria-label="Automation action">
              <option value="move_to_column">Move the card to a column…</option>
              <option value="duplicate_to_column">Duplicate the card to a column…</option>
              <option value="assign_collaborator">Assign a collaborator…</option>
              <option value="set_progress_100">Set progress bar to 100%</option>
              <option value="set_priority_p0">Set card priority to P0</option>
              <option value="set_priority_p1">Set card priority to P1</option>
              <option value="set_priority_p2">Set card priority to P2</option>
              <option value="set_priority_clear">Clear card priority</option>
              <option value="notify_team">Notify collaborators (digest)</option>
              <option value="rule_only">Store rule only (no action yet)</option>
            </select>
          </div>
        </div>
        <div class="mt-2" id="cgAutomationTargetColumnWrap">
          <label class="form-label fw-semibold mb-1" for="cgAutomationTargetColumn">Target column</label>
          <select class="form-select" id="cgAutomationTargetColumn" aria-label="Target column for move or duplicate"></select>
          <div class="form-text" id="cgAutomationTargetColumnHint">Used when the action moves or duplicates a card.</div>
        </div>
        <div class="mt-3" id="cgAutomationTargetMemberWrap" style="display: none;">
          <label class="form-label fw-semibold mb-1" for="cgAutomationTargetMember">Assign collaborator</label>
          <select class="form-select" id="cgAutomationTargetMember" aria-label="Collaborator to assign"></select>
          <div class="form-text">Used when the action assigns a collaborator to the card.</div>
        </div>
        <div class="mt-4 pt-3 border-top" id="cgAutomationSavedSection" hidden>
          <div class="fw-semibold small mb-2">Saved on this device</div>
          <ul class="list-group list-group-flush small" id="cgAutomationSavedList"></ul>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="cgAutomationSaveBtn"><i class="fas fa-save me-1"></i>Save rule</button>
      </div>
    </div>
  </div>
</div>
