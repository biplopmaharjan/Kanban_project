(function () {
  var ACCENT_COLORS = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
  var api = window.CG_BOARDS_FK_API || 'kanban_api.php';
  var profilePicApi = window.CG_BOARDS_PROFILE_PIC_API || 'api/profile_pic.php';
  var root = document.getElementById('cgBoardsHomeRoot');
  if (!root) return;

  var state = {
    boards: [],
    loading: false,
    error: '',
    query: '',
  };
  var contextTarget = null;
  var contextMenuEl = null;
  var renameOverlayEl = null;
  var renameInputEl = null;
  var createBoardOverlayEl = null;
  var createBoardNameEl = null;
  var createBoardTemplateEl = null;
  var createBoardTemplatesLoaded = false;
  var collaboratorOverlayEl = null;
  var collaboratorListEl = null;
  var collaboratorInputEl = null;
  var collaboratorErrorEl = null;
  var collaboratorSuggestEl = null;
  var collaboratorTargetBoard = null;
  var collaboratorSuggestTimer = null;
  var collaboratorSuggestResults = [];
  var collaboratorSuggestIndex = -1;
  var collaboratorSuggestMinChars = 2;
  var collaboratorSuggestBindVersion = '2';
  var boardColorMenuEl = null;
  var suppressCardClickUntil = 0;

  var BOARD_COLOR_TONES = ['rose', 'peach', 'mint', 'sky', 'lavender', 'butter'];
  var BOARD_TONE_ACCENTS = {
    rose: '#f43f5e',
    peach: '#f97316',
    mint: '#22c55e',
    sky: '#0ea5e9',
    lavender: '#8b5cf6',
    butter: '#eab308',
  };
  var BOARD_TONE_FILLS = {
    rose: '#fce7f3',
    peach: '#ffedd5',
    mint: '#dcfce7',
    sky: '#e0f2fe',
    lavender: '#ede9fe',
    butter: '#fef9c3',
  };
  var BOARD_TONE_ICON_ON_LIGHT = {
    rose: '#9d174d',
    peach: '#9a3412',
    mint: '#166534',
    sky: '#075985',
    lavender: '#5b21b6',
    butter: '#854d0e',
  };
  var BOARD_TONE_DARK_OVERLAYS = {
    rose: { r: 251, g: 113, b: 133, a: 0.2 },
    peach: { r: 251, g: 146, b: 60, a: 0.22 },
    mint: { r: 74, g: 222, b: 128, a: 0.18 },
    sky: { r: 56, g: 189, b: 248, a: 0.2 },
    lavender: { r: 167, g: 139, b: 250, a: 0.22 },
    butter: { r: 250, g: 204, b: 21, a: 0.2 },
  };
  var BOARD_DARK_BASE = { r: 26, g: 29, b: 39 };
  var BOARD_LIGHT_BASE = { r: 255, g: 255, b: 255 };

  function isBoardOwner(board) {
    if (!board) return false;
    return board.is_owner === 1 || board.is_owner === '1' || board.is_owner === true;
  }

  function isPinned(board) {
    if (!board) return false;
    return board.is_pinned === 1 || board.is_pinned === '1' || board.is_pinned === true;
  }

  function boardById(id) {
    return state.boards.find(function (b) {
      return String(b.id) === String(id);
    });
  }

  function portalConfirm(message, opts) {
    if (typeof window.cgPortalConfirm === 'function') {
      return window.cgPortalConfirm(message, opts);
    }
    return Promise.resolve(window.confirm(message));
  }

  function portalAlert(message, opts) {
    if (typeof window.cgPortalAlert === 'function') {
      window.cgPortalAlert(message, opts);
      return;
    }
    window.alert(message);
  }

  function isNetworkFetchError(err) {
    var msg = String(err && err.message ? err.message : err || '');
    return err instanceof TypeError || msg.indexOf('NetworkError') !== -1 || msg.indexOf('Failed to fetch') !== -1;
  }

  async function readJsonResponse(res) {
    var raw = await res.text();
    var trimmed = raw.trim();
    if (!trimmed) {
      return { _empty: true };
    }
    try {
      return JSON.parse(trimmed);
    } catch (_) {
      return { _parseError: true };
    }
  }

  function friendlyKanbanApiError(res, data, err, context) {
    context = context || 'load';
    var fallback =
      context === 'create'
        ? 'We couldn\u2019t create the board right now. Please try again in a moment.'
        : context === 'resolve'
        ? 'We couldn\u2019t start a new board right now. Please try again in a moment.'
        : 'We couldn\u2019t load your boards right now. Please refresh or try again in a moment.';

    if ((data && (data._empty || data._parseError)) || (err && /JSON\.parse|unexpected end|SyntaxError/i.test(String(err.message || '')))) {
      return fallback;
    }
    if (res && res.status === 401) {
      return 'Please sign in to view your boards.';
    }
    if (res && res.status === 403) {
      return 'You don\u2019t have permission to view Kanban boards.';
    }
    if (res && (res.status === 404 || res.status >= 500)) {
      return fallback;
    }
    if (err && isNetworkFetchError(err)) {
      return 'Connection problem. Check your network and try again.';
    }
    var apiMsg = data && data.message ? String(data.message) : '';
    if (/sign in/i.test(apiMsg)) return apiMsg;
    if (/board not found|access|permission|forbidden|unauthorized/i.test(apiMsg)) {
      return 'You don\u2019t have access to view these boards. Contact the board owner if you were invited.';
    }
    if (apiMsg && !/HTTP\s*\d+|JSON\.parse|unexpected end|SyntaxError/i.test(apiMsg)) {
      return apiMsg;
    }
    return fallback;
  }

  function friendlyBoardsLoadError(res, data, err) {
    return friendlyKanbanApiError(res, data, err, 'load');
  }

  async function parseKanbanApiResponse(res, context) {
    var data = await readJsonResponse(res);
    if (data._empty || data._parseError) {
      return { success: false, message: friendlyKanbanApiError(res, data, null, context) };
    }
    if (!res.ok) {
      data.success = false;
      if (!data.message || /HTTP\s*\d+/i.test(String(data.message))) {
        data.message = friendlyKanbanApiError(res, data, null, context);
      }
    }
    return data;
  }

  async function fetchWithRetry(url, init, options) {
    var maxAttempts = (options && options.retries != null) ? options.retries : 3;
    var pauseMs = (options && options.retryDelayMs != null) ? options.retryDelayMs : 350;
    var lastErr = null;
    for (var attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        return await fetch(url, init);
      } catch (err) {
        lastErr = err;
        if (!isNetworkFetchError(err) || attempt >= maxAttempts) throw err;
        await new Promise(function (resolve) {
          setTimeout(resolve, pauseMs * attempt);
        });
      }
    }
    throw lastErr;
  }

  async function postBoardAction(action, boardId, fields) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('board_id', String(boardId));
    if (fields) {
      Object.keys(fields).forEach(function (key) {
        fd.append(key, fields[key]);
      });
    }
    var retries = action === 'delete_board' ? 4 : 3;
    var res = await fetchWithRetry(api, { method: 'POST', body: fd, credentials: 'include' }, { retries: retries });
    return parseKanbanApiResponse(res, 'action');
  }

  function ensureBoardColorMenu() {
    if (boardColorMenuEl) return;
    boardColorMenuEl = document.createElement('div');
    boardColorMenuEl.className = 'cg-boards-color-menu';
    boardColorMenuEl.id = 'cgBoardsColorMenu';
    boardColorMenuEl.setAttribute('aria-hidden', 'true');
    boardColorMenuEl.innerHTML =
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--default"></span><span>Default</span></button>' +
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="rose">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--rose"></span><span>Rose</span></button>' +
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="peach">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--peach"></span><span>Peach</span></button>' +
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="mint">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--mint"></span><span>Mint</span></button>' +
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="sky">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--sky"></span><span>Sky</span></button>' +
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="lavender">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--lavender"></span><span>Lavender</span></button>' +
      '<button type="button" class="cg-boards-color-menu__swatch" data-board-color-value="butter">' +
      '<span class="cg-boards-color-menu__dot cg-boards-color-menu__dot--butter"></span><span>Butter</span></button>';
    document.body.appendChild(boardColorMenuEl);
    boardColorMenuEl.querySelectorAll('[data-board-color-value]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!contextTarget) return;
        void setBoardColor(contextTarget.id, btn.getAttribute('data-board-color-value') || '');
      });
    });
  }

  function ensureCollaboratorSuggestPortal() {
    if (!collaboratorOverlayEl) return;
    collaboratorInputEl = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorEmail');
    var inlineSuggest = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorSuggest');
    if (inlineSuggest && inlineSuggest !== collaboratorSuggestEl) {
      inlineSuggest.remove();
    }
    if (!collaboratorSuggestEl || !collaboratorSuggestEl.classList.contains('cg-boards-user-suggest--portal')) {
      if (collaboratorSuggestEl) collaboratorSuggestEl.remove();
      collaboratorSuggestEl = document.createElement('div');
      collaboratorSuggestEl.className = 'cg-boards-user-suggest cg-boards-user-suggest--portal';
      collaboratorSuggestEl.id = 'cgBoardsCollaboratorSuggest';
      collaboratorSuggestEl.hidden = true;
      collaboratorSuggestEl.setAttribute('role', 'listbox');
      collaboratorSuggestEl.setAttribute('aria-label', 'Suggested collaborators');
      document.body.appendChild(collaboratorSuggestEl);
    }
    if (
      collaboratorInputEl &&
      collaboratorInputEl.dataset.suggestBound !== collaboratorSuggestBindVersion
    ) {
      if (collaboratorInputEl.dataset.suggestBound) {
        var freshInput = collaboratorInputEl.cloneNode(true);
        freshInput.value = collaboratorInputEl.value;
        collaboratorInputEl.parentNode.replaceChild(freshInput, collaboratorInputEl);
        collaboratorInputEl = freshInput;
      }
      collaboratorInputEl.dataset.suggestBound = '';
      attachCollaboratorSuggest();
    }
  }

  function ensureBoardOverlays() {
    if (contextMenuEl && renameOverlayEl && createBoardOverlayEl && collaboratorOverlayEl) {
      ensureBoardColorMenu();
      ensureCollaboratorSuggestPortal();
      return;
    }

    contextMenuEl = document.createElement('div');
    contextMenuEl.className = 'cg-boards-context-menu';
    contextMenuEl.id = 'cgBoardsContextMenu';
    contextMenuEl.setAttribute('role', 'menu');
    contextMenuEl.innerHTML =
      '<button type="button" class="cg-boards-context-menu__item" data-action="open" role="menuitem">' +
      '<i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Open</span></button>' +
      '<button type="button" class="cg-boards-context-menu__item" data-action="pin" role="menuitem">' +
      '<i class="fas fa-thumbtack" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Pin</span></button>' +
      '<div class="cg-boards-context-menu__divider" data-owner-only aria-hidden="true"></div>' +
      '<button type="button" class="cg-boards-context-menu__item" data-action="color" data-owner-only role="menuitem">' +
      '<i class="fas fa-palette" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Change color</span>' +
      '<i class="fas fa-chevron-right cg-boards-context-menu__subchev" aria-hidden="true"></i></button>' +
      '<button type="button" class="cg-boards-context-menu__item" data-action="edit" data-owner-only role="menuitem">' +
      '<i class="fas fa-pen" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Rename</span></button>' +
      '<button type="button" class="cg-boards-context-menu__item" data-action="collaborators" data-owner-only role="menuitem">' +
      '<i class="fas fa-user-plus" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Add collaborator</span></button>' +
      '<button type="button" class="cg-boards-context-menu__item cg-boards-context-menu__item--danger" data-action="delete" data-owner-only role="menuitem">' +
      '<i class="fas fa-trash" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Delete board</span></button>';
    document.body.appendChild(contextMenuEl);

    renameOverlayEl = document.createElement('div');
    renameOverlayEl.className = 'cg-boards-rename-overlay';
    renameOverlayEl.id = 'cgBoardsRenameOverlay';
    renameOverlayEl.innerHTML =
      '<div class="cg-boards-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="cgBoardsRenameTitle">' +
      '<div class="cg-boards-rename-dialog__head" id="cgBoardsRenameTitle">Rename board</div>' +
      '<div class="cg-boards-rename-dialog__body">' +
      '<input type="text" class="cg-boards-rename-dialog__input" id="cgBoardsRenameInput" maxlength="120" autocomplete="off" />' +
      '</div>' +
      '<div class="cg-boards-rename-dialog__actions">' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsRenameCancel">Cancel</button>' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsRenameSave">Save</button>' +
      '</div></div>';
    document.body.appendChild(renameOverlayEl);
    renameInputEl = renameOverlayEl.querySelector('#cgBoardsRenameInput');

    createBoardOverlayEl = document.createElement('div');
    createBoardOverlayEl.className = 'cg-boards-rename-overlay';
    createBoardOverlayEl.id = 'cgBoardsCreateOverlay';
    createBoardOverlayEl.innerHTML =
      '<div class="cg-boards-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="cgBoardsCreateTitle">' +
      '<div class="cg-boards-rename-dialog__head" id="cgBoardsCreateTitle">New board</div>' +
      '<div class="cg-boards-rename-dialog__body">' +
      '<label class="cg-boards-create-field" for="cgBoardsCreateTemplate">Template</label>' +
      '<select class="cg-boards-rename-dialog__input cg-boards-create-select" id="cgBoardsCreateTemplate" aria-label="Board template"></select>' +
      '<label class="cg-boards-create-field" for="cgBoardsCreateName">Board name</label>' +
      '<input type="text" class="cg-boards-rename-dialog__input" id="cgBoardsCreateName" maxlength="120" autocomplete="off" placeholder="Enter board name" />' +
      '</div>' +
      '<div class="cg-boards-rename-dialog__actions">' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsCreateCancel">Cancel</button>' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsCreateSave"><i class="fas fa-plus me-1" aria-hidden="true"></i>Create board</button>' +
      '</div></div>';
    document.body.appendChild(createBoardOverlayEl);
    createBoardNameEl = createBoardOverlayEl.querySelector('#cgBoardsCreateName');
    createBoardTemplateEl = createBoardOverlayEl.querySelector('#cgBoardsCreateTemplate');

    collaboratorOverlayEl = document.createElement('div');
    collaboratorOverlayEl.className = 'cg-boards-rename-overlay';
    collaboratorOverlayEl.id = 'cgBoardsCollaboratorOverlay';
    collaboratorOverlayEl.innerHTML =
      '<div class="cg-boards-rename-dialog cg-boards-collab-dialog" role="dialog" aria-modal="true" aria-labelledby="cgBoardsCollaboratorTitle">' +
      '<div class="cg-boards-rename-dialog__head" id="cgBoardsCollaboratorTitle">Add collaborator</div>' +
      '<div class="cg-boards-rename-dialog__body">' +
      '<p class="cg-boards-collab-dialog__hint">Invite others by email. They can open and edit this board from their Kanban.</p>' +
      '<div class="cg-boards-collab-list" id="cgBoardsCollaboratorList"><span class="cg-boards-collab-list__loading">Loading…</span></div>' +
      '<label class="cg-boards-create-field" for="cgBoardsCollaboratorEmail">Name or email</label>' +
      '<div class="cg-boards-collab-input-wrap">' +
      '<input type="text" class="cg-boards-rename-dialog__input" id="cgBoardsCollaboratorEmail" maxlength="190" autocomplete="off" placeholder="Type a name or email to search" aria-autocomplete="list" aria-controls="cgBoardsCollaboratorSuggest" aria-expanded="false" />' +
      '</div>' +
      '<div class="cg-boards-collab-dialog__error" id="cgBoardsCollaboratorError" hidden></div>' +
      '</div>' +
      '<div class="cg-boards-rename-dialog__actions">' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsCollaboratorCancel">Cancel</button>' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsCollaboratorAdd"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Add</button>' +
      '</div></div>';
    document.body.appendChild(collaboratorOverlayEl);
    collaboratorListEl = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorList');
    collaboratorInputEl = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorEmail');
    collaboratorErrorEl = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorError');
    ensureCollaboratorSuggestPortal();

    contextMenuEl.querySelectorAll('[data-action]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var action = btn.getAttribute('data-action');
        var target = contextTarget;
        if (action === 'color') {
          e.stopPropagation();
          openBoardColorMenu(btn);
          return;
        }
        closeBoardColorMenu();
        closeContextMenu();
        if (!target) return;
        if (action === 'open') openBoard(target.id);
        else if (action === 'pin') togglePin(target);
        else if (action === 'edit') openRenameDialog(target);
        else if (action === 'collaborators') openCollaboratorDialog(target);
        else if (action === 'delete') deleteBoard(target);
      });
    });

    renameOverlayEl.querySelector('#cgBoardsRenameCancel').addEventListener('click', closeRenameDialog);
    renameOverlayEl.addEventListener('click', function (e) {
      if (e.target === renameOverlayEl) closeRenameDialog();
    });
    renameOverlayEl.querySelector('#cgBoardsRenameSave').addEventListener('click', function () {
      void saveRename();
    });
    renameInputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        void saveRename();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeRenameDialog();
      }
    });

    createBoardOverlayEl.querySelector('#cgBoardsCreateCancel').addEventListener('click', closeCreateBoardDialog);
    createBoardOverlayEl.addEventListener('click', function (e) {
      if (e.target === createBoardOverlayEl) closeCreateBoardDialog();
    });
    createBoardOverlayEl.querySelector('#cgBoardsCreateSave').addEventListener('click', function () {
      void saveCreateBoard();
    });
    createBoardNameEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        void saveCreateBoard();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeCreateBoardDialog();
      }
    });

    collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorCancel').addEventListener('click', closeCollaboratorDialog);
    collaboratorOverlayEl.addEventListener('click', function (e) {
      if (e.target === collaboratorOverlayEl) closeCollaboratorDialog();
    });
    collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorAdd').addEventListener('click', function () {
      void saveCollaborator();
    });
    if (collaboratorInputEl) {
      collaboratorInputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          closeCollaboratorDialog();
        }
      });
    }

    document.addEventListener('click', function (e) {
      if (!contextMenuEl || !contextMenuEl.classList.contains('is-open')) {
        if (boardColorMenuEl && boardColorMenuEl.classList.contains('is-open')) {
          if (e.target && boardColorMenuEl.contains(e.target)) return;
          closeBoardColorMenu();
        }
        return;
      }
      if (e.target && contextMenuEl.contains(e.target)) return;
      if (boardColorMenuEl && boardColorMenuEl.contains(e.target)) return;
      closeBoardColorMenu();
      closeContextMenu();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeBoardColorMenu();
        closeContextMenu();
        closeRenameDialog();
        closeCreateBoardDialog();
        closeCollaboratorDialog();
      }
    });
    window.addEventListener('resize', function () {
      closeBoardColorMenu();
      closeContextMenu();
    });
    window.addEventListener('scroll', function () {
      closeBoardColorMenu();
      closeContextMenu();
    }, true);
    window.addEventListener('scroll', function () {
      if (collaboratorSuggestEl && !collaboratorSuggestEl.hidden) {
        positionCollaboratorSuggest();
      }
    }, true);
    window.addEventListener('resize', function () {
      if (collaboratorSuggestEl && !collaboratorSuggestEl.hidden) {
        positionCollaboratorSuggest();
      }
    });

    ensureBoardColorMenu();
  }

  function closeContextMenu() {
    if (!contextMenuEl) return;
    contextMenuEl.classList.remove('is-open');
    contextTarget = null;
  }

  function closeBoardColorMenu() {
    if (!boardColorMenuEl) return;
    boardColorMenuEl.classList.remove('is-open');
    boardColorMenuEl.setAttribute('aria-hidden', 'true');
  }

  function normalizeBoardColor(value) {
    var s = String(value || '').trim().toLowerCase();
    if (!s || s === 'default') return '';
    if (BOARD_COLOR_TONES.indexOf(s) !== -1) return s;
    var hex = s.replace(/^#/, '').replace(/[^0-9a-f]/g, '');
    if (hex.length === 3) {
      hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    if (hex.length === 6 && /^[0-9a-f]{6}$/.test(hex)) return '#' + hex;
    return '';
  }

  function refreshBoardColorMenuSelection() {
    if (!boardColorMenuEl || !contextTarget) return;
    var selected = normalizeBoardColor(contextTarget.board_color);
    boardColorMenuEl.querySelectorAll('[data-board-color-value]').forEach(function (btn) {
      var value = btn.getAttribute('data-board-color-value') || '';
      btn.classList.toggle('is-active', value === selected);
    });
  }

  function openBoardColorMenu(anchorEl) {
    ensureBoardColorMenu();
    if (!boardColorMenuEl || !anchorEl || !contextTarget) return;
    refreshBoardColorMenuSelection();
    boardColorMenuEl.classList.add('is-open');
    boardColorMenuEl.setAttribute('aria-hidden', 'false');
    boardColorMenuEl.style.left = '0px';
    boardColorMenuEl.style.top = '0px';
    var rect = anchorEl.getBoundingClientRect();
    var menuRect = boardColorMenuEl.getBoundingClientRect();
    var pad = 8;
    var maxLeft = window.innerWidth - menuRect.width - pad;
    var maxTop = window.innerHeight - menuRect.height - pad;
    var left = Math.min(Math.max(pad, rect.right + 6), maxLeft);
    var top = Math.min(Math.max(pad, rect.top), maxTop);
    boardColorMenuEl.style.left = left + 'px';
    boardColorMenuEl.style.top = top + 'px';
  }

  async function setBoardColor(boardId, colorTone) {
    var normalized = normalizeBoardColor(colorTone);
    try {
      var data = await postBoardAction('set_board_color', boardId, { board_color: normalized });
      if (!data.success) throw new Error(data.message || 'Failed to update board color');
      var saved =
        data.board_color != null && data.board_color !== undefined ? String(data.board_color) : normalized;
      var board = boardById(boardId);
      if (board) board.board_color = saved;
      closeBoardColorMenu();
      closeContextMenu();
      render();
    } catch (err) {
      portalAlert(err && err.message ? err.message : 'Failed to update board color', {
        title: 'Change color',
        variant: 'danger',
      });
    }
  }

  function openContextMenu(board, x, y) {
    ensureBoardOverlays();
    closeBoardColorMenu();
    contextTarget = board;
    var owner = isBoardOwner(board);
    contextMenuEl.querySelectorAll('[data-owner-only]').forEach(function (el) {
      el.hidden = !owner;
    });
    var pinBtn = contextMenuEl.querySelector('[data-action="pin"]');
    if (pinBtn) {
      var pinned = isPinned(board);
      pinBtn.innerHTML = pinned
        ? '<i class="fas fa-thumbtack" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Unpin</span>'
        : '<i class="fas fa-thumbtack" aria-hidden="true"></i><span class="cg-boards-context-menu__label">Pin</span>';
    }
    contextMenuEl.classList.add('is-open');
    contextMenuEl.style.left = '0px';
    contextMenuEl.style.top = '0px';
    var rect = contextMenuEl.getBoundingClientRect();
    var pad = 8;
    var left = Math.min(Math.max(pad, x), window.innerWidth - rect.width - pad);
    var top = Math.min(Math.max(pad, y), window.innerHeight - rect.height - pad);
    contextMenuEl.style.left = left + 'px';
    contextMenuEl.style.top = top + 'px';
    suppressCardClickUntil = Date.now() + 300;
  }

  function openBoard(boardId) {
    window.location.href = boardHref(boardId);
  }

  function openRenameDialog(board) {
    ensureBoardOverlays();
    contextTarget = board;
    renameInputEl.value = board.name || '';
    renameOverlayEl.classList.add('is-open');
    setTimeout(function () {
      renameInputEl.focus();
      renameInputEl.select();
    }, 0);
  }

  function closeRenameDialog() {
    if (!renameOverlayEl) return;
    renameOverlayEl.classList.remove('is-open');
  }

  function showCollaboratorError(message) {
    if (!collaboratorErrorEl) return;
    if (!message) {
      collaboratorErrorEl.hidden = true;
      collaboratorErrorEl.textContent = '';
      return;
    }
    collaboratorErrorEl.hidden = false;
    collaboratorErrorEl.textContent = message;
  }

  function renderCollaboratorList(collaborators, pending) {
    if (!collaboratorListEl) return;
    var items = [];
    (collaborators || []).forEach(function (c) {
      var name = escapeHtml(c.name || c.email || 'User');
      var email = escapeHtml(c.email || '');
      var label = email && name !== email ? name + ' &lt;' + email + '&gt;' : name;
      items.push(
        '<span class="cg-boards-collab-chip">' +
        '<span class="cg-boards-collab-chip__label">' + label + '</span>' +
        '<button type="button" class="cg-boards-collab-chip__remove" data-user-id="' + escapeHtml(String(c.user_id || '')) + '" aria-label="Remove collaborator">×</button>' +
        '</span>'
      );
    });
    (pending || []).forEach(function (p) {
      var email = escapeHtml(p.email || '');
      items.push(
        '<span class="cg-boards-collab-chip cg-boards-collab-chip--pending">' +
        '<span class="cg-boards-collab-chip__label">' + email + '</span>' +
        '<span class="cg-boards-collab-chip__badge">Pending</span>' +
        '<button type="button" class="cg-boards-collab-chip__remove" data-email="' + email + '" aria-label="Remove pending invite">×</button>' +
        '</span>'
      );
    });
    if (!items.length) {
      collaboratorListEl.innerHTML = '<span class="cg-boards-collab-list__empty">No collaborators yet. Add someone by email below.</span>';
      return;
    }
    collaboratorListEl.innerHTML = items.join('');
    collaboratorListEl.querySelectorAll('.cg-boards-collab-chip__remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        void removeCollaborator(
          btn.getAttribute('data-user-id') || '',
          btn.getAttribute('data-email') || ''
        );
      });
    });
  }

  async function loadCollaboratorList(boardId) {
    if (!collaboratorListEl) return;
    collaboratorListEl.innerHTML = '<span class="cg-boards-collab-list__loading">Loading…</span>';
    try {
      var res = await fetch(
        api + '?action=list_collaborators&board_id=' + encodeURIComponent(String(boardId)),
        { credentials: 'include', cache: 'no-store' }
      );
      var data = await parseKanbanApiResponse(res, 'action');
      if (!data.success) {
        throw new Error(data.message || 'Could not load collaborators');
      }
      renderCollaboratorList(data.collaborators || [], data.pending || []);
    } catch (err) {
      collaboratorListEl.innerHTML =
        '<span class="cg-boards-collab-list__empty">' +
        escapeHtml(err && err.message ? err.message : 'Could not load collaborators') +
        '</span>';
    }
  }

  function openCollaboratorDialog(board) {
    if (!board || !isBoardOwner(board)) return;
    ensureBoardOverlays();
    clearTimeout(collaboratorSuggestTimer);
    hideCollaboratorSuggest();
    collaboratorTargetBoard = board;
    showCollaboratorError('');
    if (collaboratorInputEl) collaboratorInputEl.value = '';
    var titleEl = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorTitle');
    if (titleEl) {
      titleEl.textContent = 'Add collaborator — ' + String(board.name || 'Board');
    }
    collaboratorOverlayEl.classList.add('is-open');
    void loadCollaboratorList(board.id);
    setTimeout(function () {
      if (collaboratorInputEl) collaboratorInputEl.focus();
    }, 0);
  }

  function closeCollaboratorDialog() {
    if (!collaboratorOverlayEl) return;
    collaboratorOverlayEl.classList.remove('is-open');
    collaboratorTargetBoard = null;
    showCollaboratorError('');
    hideCollaboratorSuggest();
  }

  function boardAssociatedUserPicUrl(profilePic) {
    var p = String(profilePic || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    var rel = p.replace(/^\/+/, '');
    if (rel.indexOf('uploads/') !== 0) {
      rel = 'uploads/profile_pics/' + rel;
    }
    return profilePicApi + '?path=' + encodeURIComponent(rel);
  }

  function hideCollaboratorSuggest() {
    if (!collaboratorSuggestEl) return;
    collaboratorSuggestEl.hidden = true;
    collaboratorSuggestEl.innerHTML = '';
    collaboratorSuggestResults = [];
    collaboratorSuggestIndex = -1;
    collaboratorSuggestEl.style.left = '';
    collaboratorSuggestEl.style.top = '';
    collaboratorSuggestEl.style.width = '';
    collaboratorSuggestEl.style.maxHeight = '';
    if (collaboratorInputEl) collaboratorInputEl.setAttribute('aria-expanded', 'false');
  }

  function positionCollaboratorSuggest() {
    if (!collaboratorSuggestEl || !collaboratorInputEl || collaboratorSuggestEl.hidden) return;
    var rect = collaboratorInputEl.getBoundingClientRect();
    var pad = 8;
    var maxHeight = 240;
    var viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
    var spaceBelow = viewportH - rect.bottom - pad;
    var spaceAbove = rect.top - pad;
    var openUp = spaceBelow < 160 && spaceAbove > spaceBelow;
    var height = Math.min(maxHeight, Math.max(120, openUp ? spaceAbove : spaceBelow));
    var width = Math.max(rect.width, 280);
    var left = Math.min(Math.max(pad, rect.left), (window.innerWidth || width) - width - pad);
    var top = openUp ? rect.top - height - 4 : rect.bottom + 4;
    collaboratorSuggestEl.style.left = left + 'px';
    collaboratorSuggestEl.style.top = Math.max(pad, top) + 'px';
    collaboratorSuggestEl.style.width = width + 'px';
    collaboratorSuggestEl.style.maxHeight = height + 'px';
  }

  function selectCollaboratorSuggestion(user) {
    if (!user || !collaboratorInputEl) return;
    collaboratorInputEl.value = String(user.email || '').trim();
    hideCollaboratorSuggest();
    showCollaboratorError('');
  }

  function collaboratorSearchQuery() {
    return collaboratorInputEl ? String(collaboratorInputEl.value || '').trim() : '';
  }

  function renderCollaboratorSuggest(users) {
    if (!collaboratorSuggestEl) return;
    if (collaboratorSearchQuery().length < collaboratorSuggestMinChars) {
      hideCollaboratorSuggest();
      return;
    }
    collaboratorSuggestResults = users || [];
    collaboratorSuggestIndex = -1;
    if (!collaboratorSuggestResults.length) {
      hideCollaboratorSuggest();
      return;
    }
    collaboratorSuggestEl.innerHTML = collaboratorSuggestResults
      .map(function (u, idx) {
        var name = escapeHtml(u.name || u.email || 'User');
        var email = escapeHtml(u.email || '');
        var pic = boardAssociatedUserPicUrl(u.profile_pic);
        var avatar = pic
          ? '<img class="cg-boards-user-suggest__avatar" src="' + escapeHtml(pic) + '" alt="" loading="lazy" />'
          : '<span class="cg-boards-user-suggest__avatar cg-boards-user-suggest__avatar--fallback" aria-hidden="true">' +
            escapeHtml((name.charAt(0) || '?').toUpperCase()) +
            '</span>';
        return (
          '<button type="button" class="cg-boards-user-suggest__item" role="option" data-index="' +
          idx +
          '" data-email="' +
          email +
          '">' +
          avatar +
          '<span class="cg-boards-user-suggest__text">' +
          '<span class="cg-boards-user-suggest__name">' +
          name +
          '</span>' +
          (email ? '<span class="cg-boards-user-suggest__email">' + email + '</span>' : '') +
          '</span></button>'
        );
      })
      .join('');
    collaboratorSuggestEl.hidden = false;
    if (collaboratorInputEl) collaboratorInputEl.setAttribute('aria-expanded', 'true');
    collaboratorSuggestEl.querySelectorAll('.cg-boards-user-suggest__item').forEach(function (btn) {
      btn.addEventListener('mousedown', function (e) {
        e.preventDefault();
        var idx = parseInt(btn.getAttribute('data-index') || '-1', 10);
        if (idx >= 0 && collaboratorSuggestResults[idx]) {
          selectCollaboratorSuggestion(collaboratorSuggestResults[idx]);
        }
      });
    });
    positionCollaboratorSuggest();
  }

  async function fetchCollaboratorSuggestions(query) {
    if (!collaboratorTargetBoard) return;
    var q = String(query || '').trim();
    if (q.length < collaboratorSuggestMinChars) {
      hideCollaboratorSuggest();
      return;
    }
    var url =
      api +
      '?action=search_board_associated_users&board_id=' +
      encodeURIComponent(String(collaboratorTargetBoard.id)) +
      '&q=' +
      encodeURIComponent(q);
    try {
      var res = await fetch(url, { credentials: 'include', cache: 'no-store' });
      var data = await parseKanbanApiResponse(res, 'action');
      if (collaboratorSearchQuery() !== q) return;
      if (!data.success) {
        hideCollaboratorSuggest();
        return;
      }
      renderCollaboratorSuggest(data.users || []);
    } catch (_) {
      hideCollaboratorSuggest();
    }
  }

  function attachCollaboratorSuggest() {
    if (!collaboratorInputEl || !collaboratorSuggestEl) return;
    if (collaboratorInputEl.dataset.suggestBound === collaboratorSuggestBindVersion) return;
    collaboratorInputEl.dataset.suggestBound = collaboratorSuggestBindVersion;

    collaboratorInputEl.addEventListener('input', function () {
      clearTimeout(collaboratorSuggestTimer);
      var q = collaboratorSearchQuery();
      if (q.length < collaboratorSuggestMinChars) {
        hideCollaboratorSuggest();
        return;
      }
      collaboratorSuggestTimer = setTimeout(function () {
        void fetchCollaboratorSuggestions(q);
      }, 180);
    });

    collaboratorInputEl.addEventListener('focus', function () {
      if (collaboratorSearchQuery().length < collaboratorSuggestMinChars) {
        hideCollaboratorSuggest();
      }
    });

    collaboratorInputEl.addEventListener('blur', function () {
      setTimeout(hideCollaboratorSuggest, 180);
    });

    collaboratorInputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (!collaboratorSuggestEl.hidden) {
          e.preventDefault();
          e.stopPropagation();
          hideCollaboratorSuggest();
          return;
        }
        return;
      }
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter') return;
      var items = collaboratorSuggestEl.querySelectorAll('.cg-boards-user-suggest__item');
      if (!items.length) {
        if (e.key === 'Enter') {
          e.preventDefault();
          void saveCollaborator();
        }
        return;
      }
      e.preventDefault();
      if (e.key === 'ArrowDown') {
        collaboratorSuggestIndex = Math.min(collaboratorSuggestIndex + 1, items.length - 1);
      } else if (e.key === 'ArrowUp') {
        collaboratorSuggestIndex = Math.max(collaboratorSuggestIndex - 1, -1);
      } else if (e.key === 'Enter') {
        if (collaboratorSuggestIndex >= 0 && collaboratorSuggestResults[collaboratorSuggestIndex]) {
          selectCollaboratorSuggestion(collaboratorSuggestResults[collaboratorSuggestIndex]);
          void saveCollaborator();
        } else {
          void saveCollaborator();
        }
        return;
      }
      items.forEach(function (item, i) {
        item.classList.toggle('is-selected', i === collaboratorSuggestIndex);
      });
      if (collaboratorSuggestIndex >= 0 && items[collaboratorSuggestIndex]) {
        items[collaboratorSuggestIndex].scrollIntoView({ block: 'nearest' });
      }
    });
  }

  async function saveCollaborator() {
    if (!collaboratorTargetBoard) return;
    var email = collaboratorInputEl ? String(collaboratorInputEl.value || '').trim().toLowerCase() : '';
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showCollaboratorError('Please enter a valid email address.');
      return;
    }
    showCollaboratorError('');
    var addBtn = collaboratorOverlayEl.querySelector('#cgBoardsCollaboratorAdd');
    if (addBtn) addBtn.disabled = true;
    try {
      var data = await postBoardAction('add_collaborator', collaboratorTargetBoard.id, { email: email });
      if (!data.success) throw new Error(data.message || 'Failed to add collaborator');
      if (collaboratorInputEl) collaboratorInputEl.value = '';
      hideCollaboratorSuggest();
      await loadCollaboratorList(collaboratorTargetBoard.id);
      portalAlert('Collaborator invited.', { title: 'Added', variant: 'success' });
    } catch (err) {
      showCollaboratorError(err && err.message ? err.message : 'Failed to add collaborator');
    } finally {
      if (addBtn) addBtn.disabled = false;
    }
  }

  async function removeCollaborator(userId, email) {
    if (!collaboratorTargetBoard) return;
    var fields = {};
    if (userId) fields.user_id = userId;
    else if (email) fields.email = email;
    else return;
    try {
      var data = await postBoardAction('remove_collaborator', collaboratorTargetBoard.id, fields);
      if (!data.success) throw new Error(data.message || 'Failed to remove collaborator');
      await loadCollaboratorList(collaboratorTargetBoard.id);
    } catch (err) {
      portalAlert(err && err.message ? err.message : 'Failed to remove collaborator', {
        title: 'Collaborators',
        variant: 'danger',
      });
    }
  }

  async function loadCreateBoardTemplates() {
    if (!createBoardTemplateEl || createBoardTemplatesLoaded) return;
    try {
      var res = await fetch(api + '?action=list_board_templates', { credentials: 'include', cache: 'no-store' });
      var data = await parseKanbanApiResponse(res, 'action');
      var templates = data && data.success && Array.isArray(data.templates) ? data.templates : [];
      if (!templates.length) {
        templates = [{ slug: 'blank', label: 'Classic', default_board_name: 'New Board' }];
      }
      createBoardTemplateEl.innerHTML = templates
        .map(function (tpl) {
          var slug = String(tpl.slug || 'blank');
          var label = String(tpl.label || slug);
          var defaultName = String(tpl.default_board_name || 'New Board');
          return (
            '<option value="' +
            escapeHtml(slug) +
            '" data-default-name="' +
            escapeHtml(defaultName) +
            '">' +
            escapeHtml(label) +
            '</option>'
          );
        })
        .join('');
      createBoardTemplatesLoaded = true;
      createBoardTemplateEl.addEventListener('change', function () {
        var selected = createBoardTemplateEl.options[createBoardTemplateEl.selectedIndex];
        if (selected && createBoardNameEl) {
          createBoardNameEl.value = selected.getAttribute('data-default-name') || 'New Board';
        }
      });
    } catch (_) {
      createBoardTemplateEl.innerHTML = '<option value="blank">Classic</option>';
      createBoardTemplatesLoaded = true;
    }
  }

  function applyCreateBoardTemplateDefault() {
    if (!createBoardTemplateEl || !createBoardNameEl) return;
    var selected = createBoardTemplateEl.options[createBoardTemplateEl.selectedIndex];
    if (!selected) return;
    createBoardNameEl.value = selected.getAttribute('data-default-name') || 'New Board';
  }

  async function resolveCreateProjectId() {
    var owned = state.boards.filter(function (b) {
      return isBoardOwner(b) && b.project_id;
    });
    if (owned.length > 0) {
      return parseInt(owned[0].project_id, 10);
    }
    // No owned boards yet (or only collab boards): bootstrap creates/returns this user's default project.
    var res = await fetch(api + '?action=bootstrap', { credentials: 'include', cache: 'no-store' });
    var data = await parseKanbanApiResponse(res, 'resolve');
    if (!data.success || !data.project_id) {
      throw new Error(data.message || friendlyKanbanApiError(res, data, null, 'resolve'));
    }
    return parseInt(data.project_id, 10);
  }

  async function openCreateBoardDialog() {
    ensureBoardOverlays();
    await loadCreateBoardTemplates();
    if (createBoardTemplateEl) {
      createBoardTemplateEl.value = 'blank';
    }
    applyCreateBoardTemplateDefault();
    createBoardOverlayEl.classList.add('is-open');
    setTimeout(function () {
      createBoardNameEl.focus();
      createBoardNameEl.select();
    }, 0);
  }

  function closeCreateBoardDialog() {
    if (!createBoardOverlayEl) return;
    createBoardOverlayEl.classList.remove('is-open');
    var saveBtn = createBoardOverlayEl.querySelector('#cgBoardsCreateSave');
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fas fa-plus me-1" aria-hidden="true"></i>Create board';
    }
  }

  async function saveCreateBoard() {
    if (!createBoardNameEl) return;
    var name = (createBoardNameEl.value || '').trim();
    if (!name) {
      portalAlert('Board name cannot be empty.', { title: 'New board', variant: 'danger' });
      return;
    }
    var saveBtn = createBoardOverlayEl.querySelector('#cgBoardsCreateSave');
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>Creating…';
    }
    try {
      var projectId = await resolveCreateProjectId();
      var template = createBoardTemplateEl ? createBoardTemplateEl.value || 'blank' : 'blank';
      var fd = new FormData();
      fd.append('action', 'create_board');
      fd.append('project_id', String(projectId));
      fd.append('name', name);
      fd.append('template', template);
      var res = await fetchWithRetry(api, { method: 'POST', body: fd, credentials: 'include' }, { retries: 3 });
      var data = await parseKanbanApiResponse(res, 'create');
      if (!data.success) {
        throw new Error(data.message || friendlyKanbanApiError(res, data, null, 'create'));
      }
      closeCreateBoardDialog();
      if (data.board_id) {
        openBoard(data.board_id);
        return;
      }
      await loadBoards();
    } catch (err) {
      var msg = err && err.message ? String(err.message) : '';
      if (/JSON\.parse|unexpected end|SyntaxError|HTTP\s*\d+/i.test(msg)) {
        msg = friendlyKanbanApiError(null, null, err, 'create');
      }
      portalAlert(msg || friendlyKanbanApiError(null, null, err, 'create'), {
        title: 'Create failed',
        variant: 'danger',
      });
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-plus me-1" aria-hidden="true"></i>Create board';
      }
    }
  }

  async function saveRename() {
    if (!contextTarget) return;
    var name = (renameInputEl.value || '').trim();
    if (!name) {
      portalAlert('Board name cannot be empty.', { title: 'Rename board', variant: 'danger' });
      return;
    }
    try {
      var data = await postBoardAction('update_board', contextTarget.id, { name: name });
      if (!data.success) throw new Error(data.message || 'Failed to rename board');
      closeRenameDialog();
      portalAlert('Board renamed.', { title: 'Saved', variant: 'success' });
      await loadBoards();
    } catch (err) {
      portalAlert(err && err.message ? err.message : 'Failed to rename board', {
        title: 'Rename failed',
        variant: 'danger',
      });
    }
  }

  async function togglePin(board) {
    var pinned = isPinned(board) ? 0 : 1;
    try {
      var data = await postBoardAction('pin_board', board.id, { pinned: String(pinned) });
      if (!data.success) throw new Error(data.message || 'Failed to update pin');
      await loadBoards();
    } catch (err) {
      portalAlert(err && err.message ? err.message : 'Failed to update pin', {
        title: 'Pin failed',
        variant: 'danger',
      });
    }
  }

  async function deleteBoard(board) {
    var label = board.name || 'this board';
    var ok = await portalConfirm('Delete "' + label + '"? This cannot be undone.', {
      title: 'Delete board',
      okText: 'Delete',
      cancelText: 'Cancel',
      variant: 'danger',
      confirmText: 'DELETE',
    });
    if (!ok) return;
    try {
      var data = await postBoardAction('delete_board', board.id);
      if (!data.success) throw new Error(data.message || 'Failed to delete board');
      portalAlert('Board deleted.', { title: 'Deleted', variant: 'success' });
      await loadBoards();
    } catch (err) {
      var msg = err && err.message ? err.message : 'Failed to delete board';
      if (isNetworkFetchError(err)) {
        msg = 'The server took too long to respond. Wait a moment, refresh, and check whether the board was removed.';
      }
      portalAlert(msg, {
        title: 'Delete failed',
        variant: 'danger',
      });
    }
  }

  function boardAccent(id) {
    return ACCENT_COLORS[Math.abs(parseInt(id, 10) || 0) % ACCENT_COLORS.length];
  }

  function hexToRgb(hex) {
    var clean = String(hex || '').replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(clean)) return null;
    return {
      r: parseInt(clean.slice(0, 2), 16),
      g: parseInt(clean.slice(2, 4), 16),
      b: parseInt(clean.slice(4, 6), 16),
    };
  }

  function boardHasCustomColor(board) {
    return !!normalizeBoardColor(board && board.board_color);
  }

  function getBoardToneClass(board) {
    var tone = normalizeBoardColor(board && board.board_color);
    if (!tone || tone.indexOf('#') === 0) return '';
    return 'cg-boards-card--colored cg-boards-card--tone-' + tone;
  }

  function getBoardCustomHexClass(board) {
    var tone = normalizeBoardColor(board && board.board_color);
    return /^#[0-9a-f]{6}$/.test(tone) ? 'cg-boards-card--colored cg-boards-card--custom-hex' : '';
  }

  function getBoardCustomStyle(board) {
    var tone = normalizeBoardColor(board && board.board_color);
    if (!/^#[0-9a-f]{6}$/.test(tone)) return '';
    var rgb = hexToRgb(tone);
    if (!rgb) return '';
    return (
      '--cg-boards-custom-r:' +
      rgb.r +
      ';--cg-boards-custom-g:' +
      rgb.g +
      ';--cg-boards-custom-b:' +
      rgb.b +
      ';'
    );
  }

  function boardIconAccent(board) {
    var tone = normalizeBoardColor(board && board.board_color);
    if (tone && BOARD_TONE_ACCENTS[tone]) return BOARD_TONE_ACCENTS[tone];
    if (/^#[0-9a-f]{6}$/.test(tone)) return tone;
    return boardAccent(board && board.id);
  }

  function boardAccentBarColor(board) {
    var tone = normalizeBoardColor(board && board.board_color);
    if (tone && BOARD_TONE_ACCENTS[tone]) return BOARD_TONE_ACCENTS[tone];
    if (/^#[0-9a-f]{6}$/.test(tone)) return tone;
    return boardAccent(board && board.id);
  }

  function accentBarArrowFg(barColor) {
    var rgb = hexToRgb(barColor);
    if (!rgb) return '#ffffff';
    return relativeLuminance(rgb) > 0.55 ? '#0f172a' : '#ffffff';
  }

  function isKanbanDarkTheme() {
    return !!(document.body && document.body.classList.contains('kanban-theme-dark'));
  }

  function blendRgb(base, overlay, alpha) {
    var inv = 1 - alpha;
    return {
      r: Math.round(base.r * inv + overlay.r * alpha),
      g: Math.round(base.g * inv + overlay.g * alpha),
      b: Math.round(base.b * inv + overlay.b * alpha),
    };
  }

  function relativeLuminance(rgb) {
    function linearize(channel) {
      var c = channel / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }
    return (
      0.2126 * linearize(rgb.r) +
      0.7152 * linearize(rgb.g) +
      0.0722 * linearize(rgb.b)
    );
  }

  function darkenHex(hex, amount) {
    var rgb = hexToRgb(hex);
    if (!rgb) return hex;
    var factor = 1 - amount;
    function clamp(v) {
      return Math.max(0, Math.min(255, Math.round(v)));
    }
    return (
      '#' +
      [clamp(rgb.r * factor), clamp(rgb.g * factor), clamp(rgb.b * factor)]
        .map(function (v) {
          return v.toString(16).padStart(2, '0');
        })
        .join('')
    );
  }

  function boardEffectiveFillRgb(board) {
    var tone = normalizeBoardColor(board && board.board_color);
    if (!tone) return null;
    var dark = isKanbanDarkTheme();

    if (/^#[0-9a-f]{6}$/.test(tone)) {
      var custom = hexToRgb(tone);
      if (!custom) return null;
      return blendRgb(dark ? BOARD_DARK_BASE : BOARD_LIGHT_BASE, custom, dark ? 0.28 : 0.22);
    }

    if (BOARD_TONE_FILLS[tone]) {
      if (!dark) return hexToRgb(BOARD_TONE_FILLS[tone]);
      var overlay = BOARD_TONE_DARK_OVERLAYS[tone];
      if (!overlay) return hexToRgb(BOARD_TONE_FILLS[tone]);
      return blendRgb(BOARD_DARK_BASE, overlay, overlay.a);
    }
    return null;
  }

  function boardContrastPalette(board) {
    var rgb = boardEffectiveFillRgb(board);
    if (!rgb) return '';
    var useDarkText = relativeLuminance(rgb) > 0.45;
    var tone = normalizeBoardColor(board && board.board_color);
    var fg;
    var fgMuted;
    var fgSoft;
    var fgDot;
    var iconFg;
    var iconBg;
    var badgeBg;
    var badgeFg;
    var footerBorder;

    if (useDarkText) {
      fg = '#0f172a';
      fgMuted = 'rgba(15, 23, 42, 0.62)';
      fgSoft = 'rgba(15, 23, 42, 0.5)';
      fgDot = 'rgba(15, 23, 42, 0.28)';
      iconFg =
        (tone && BOARD_TONE_ICON_ON_LIGHT[tone]) ||
        (/^#[0-9a-f]{6}$/.test(tone) ? darkenHex(tone, 0.35) : boardIconAccent(board));
      iconBg = 'rgba(15, 23, 42, 0.08)';
      badgeBg = 'rgba(15, 23, 42, 0.08)';
      badgeFg = 'rgba(15, 23, 42, 0.62)';
      footerBorder = 'rgba(15, 23, 42, 0.08)';
    } else {
      fg = '#f8fafc';
      fgMuted = 'rgba(248, 250, 252, 0.72)';
      fgSoft = 'rgba(248, 250, 252, 0.58)';
      fgDot = 'rgba(248, 250, 252, 0.28)';
      iconFg = '#f8fafc';
      iconBg = 'rgba(255, 255, 255, 0.14)';
      badgeBg = 'rgba(255, 255, 255, 0.12)';
      badgeFg = 'rgba(248, 250, 252, 0.78)';
      footerBorder = 'rgba(255, 255, 255, 0.12)';
    }

    return (
      '--cg-boards-fg:' +
      fg +
      ';--cg-boards-fg-muted:' +
      fgMuted +
      ';--cg-boards-fg-soft:' +
      fgSoft +
      ';--cg-boards-fg-dot:' +
      fgDot +
      ';--cg-boards-icon-fg:' +
      iconFg +
      ';--cg-boards-icon-bg:' +
      iconBg +
      ';--cg-boards-badge-bg:' +
      badgeBg +
      ';--cg-boards-badge-fg:' +
      badgeFg +
      ';--cg-boards-footer-border:' +
      footerBorder +
      ';'
    );
  }

  function getBoardCardStyle(board) {
    var parts = [];
    var custom = getBoardCustomStyle(board);
    if (custom) parts.push(custom);
    if (boardHasCustomColor(board)) {
      var contrast = boardContrastPalette(board);
      if (contrast) parts.push(contrast);
    }
    var barColor = boardAccentBarColor(board);
    var arrowFg = accentBarArrowFg(barColor);
    parts.push('--cg-boards-accent-bar:' + barColor + ';');
    parts.push('--cg-boards-arrow-bg:' + barColor + ';');
    parts.push('--cg-boards-arrow-fg:' + arrowFg + ';');
    parts.push('--cg-boards-arrow-hover-bg:' + darkenHex(barColor, 0.1) + ';');
    parts.push('--cg-boards-arrow-hover-fg:' + arrowFg + ';');
    return parts.join('');
  }

  function boardHref(boardId) {
    if (typeof window.cgPortalKanbanBoardHref === 'function') {
      return window.cgPortalKanbanBoardHref(boardId);
    }
    return '/kanban?board_id=' + encodeURIComponent(String(boardId));
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function nepalHour() {
    try {
      var parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Kathmandu',
        hour: 'numeric',
        hour12: false,
      }).formatToParts(new Date());
      for (var i = 0; i < parts.length; i += 1) {
        if (parts[i].type === 'hour') {
          return parseInt(parts[i].value, 10) || 0;
        }
      }
    } catch (_) {}
    return new Date().getHours();
  }

  function boardsGreetingLabel() {
    var hour = nepalHour();
    if (hour >= 5 && hour < 12) return 'Good Morning';
    if (hour >= 12 && hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  }

  function boardsDisplayFirstName(fullName) {
    var name = String(fullName || '').trim();
    if (!name) return '';
    return name.split(/\s+/)[0];
  }

  function boardsHeroTitle() {
    var first = boardsDisplayFirstName(window.CG_BOARDS_USER_NAME || '');
    var greeting = boardsGreetingLabel();
    if (first) return greeting + ' ' + first + ',';
    return greeting + ',';
  }

  function formatLastOpened(value) {
    if (!value) return '';
    try {
      var d = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(d.getTime())) return '';
      return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (_) {
      return '';
    }
  }

  function filteredBoards() {
    var q = state.query.trim().toLowerCase();
    if (!q) return state.boards.slice();
    return state.boards.filter(function (b) {
      return (
        String(b.name || '').toLowerCase().indexOf(q) !== -1 ||
        String(b.project_title || '').toLowerCase().indexOf(q) !== -1
      );
    });
  }

  function groupedBoards() {
    var map = new Map();
    filteredBoards().forEach(function (board) {
      var key = String(board.project_title || '').trim() || 'Other projects';
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(board);
    });
    return Array.from(map.entries())
      .sort(function (a, b) {
        return a[0].localeCompare(b[0]);
      })
      .map(function (entry) {
        entry[1].sort(function (a, b) {
          var ap = isPinned(a) ? 1 : 0;
          var bp = isPinned(b) ? 1 : 0;
          return bp - ap;
        });
        return entry;
      });
  }

  function skeletonCards(count) {
    var html = '<div class="cg-boards-grid" aria-busy="true" aria-label="Loading boards">';
    for (var i = 0; i < count; i += 1) {
      html +=
        '<div class="cg-boards-card cg-boards-card--skeleton" aria-hidden="true">' +
        '<div class="cg-boards-card__accent"></div>' +
        '<div class="cg-boards-card__body">' +
        '<div class="cg-boards-skeleton cg-boards-skeleton--icon"></div>' +
        '<div><div class="cg-boards-skeleton cg-boards-skeleton--title"></div>' +
        '<div class="cg-boards-skeleton cg-boards-skeleton--meta"></div></div>' +
        '</div></div>';
    }
    html += '</div>';
    return html;
  }

  function renderNewBoardGridCard() {
    return (
      '<button type="button" class="cg-boards-card cg-boards-card--new" data-cg-new-board-card="1" aria-label="Create new board">' +
      '<span class="cg-boards-card__accent cg-boards-card__accent--new" aria-hidden="true"></span>' +
      '<span class="cg-boards-card__body cg-boards-card__body--new">' +
      '<span class="cg-boards-card__icon cg-boards-card__icon--new" aria-hidden="true"><i class="fas fa-plus"></i></span>' +
      '<span class="cg-boards-card__content cg-boards-card__content--new">' +
      '<span class="cg-boards-card__name">New board</span>' +
      '<span class="cg-boards-card__meta"><span>Create a board</span></span>' +
      '</span>' +
      '</span>' +
      '</button>'
    );
  }

  function renderBoardCard(board, projectKey) {
    var hasColor = boardHasCustomColor(board);
    var toneClass = (getBoardToneClass(board) + ' ' + getBoardCustomHexClass(board)).trim();
    var cardStyle = getBoardCardStyle(board);
    var accent = boardIconAccent(board);
    var barColor = boardAccentBarColor(board);
    var meta = [];
    if (board.card_count != null) meta.push('<span>' + escapeHtml(board.card_count) + ' cards</span>');
    if (board.column_count != null) meta.push('<span>' + escapeHtml(board.column_count) + ' columns</span>');
    if (board.is_owner === 0 || board.is_owner === '0' || board.is_owner === false) {
      meta.push('<span>Shared</span>');
    }
    var projectLine =
      board.project_title && projectKey === 'Other projects'
        ? '<span class="cg-boards-card__project">' + escapeHtml(board.project_title) + '</span>'
        : '';
    var openedLabel = formatLastOpened(board.last_opened_at);
    var openedLine = openedLabel
      ? '<span class="cg-boards-card__opened">Last opened ' + escapeHtml(openedLabel) + '</span>'
      : '';

    var isOwner = isBoardOwner(board);
    var pinned = isPinned(board);

    return (
      '<button type="button" class="cg-boards-card' +
      (pinned ? ' cg-boards-card--pinned' : '') +
      (toneClass ? ' ' + toneClass : '') +
      '" data-board-id="' +
      escapeHtml(board.id) +
      '" data-board-name="' +
      escapeHtml(board.name) +
      '" data-is-owner="' +
      (isOwner ? '1' : '0') +
      '" data-is-pinned="' +
      (pinned ? '1' : '0') +
      '" data-board-color="' +
      escapeHtml(normalizeBoardColor(board.board_color)) +
      '" style="' +
      cardStyle +
      '">' +
      (pinned
        ? '<span class="cg-boards-card__pin" aria-label="Pinned"><i class="fas fa-thumbtack" aria-hidden="true"></i></span>'
        : '') +
      '<span class="cg-boards-card__accent" style="background:' +
      barColor +
      '" aria-hidden="true"></span>' +
      '<span class="cg-boards-card__body">' +
      '<span class="cg-boards-card__icon"' +
      (hasColor ? '' : ' style="color:' + accent + '"') +
      '><i class="fas fa-table-columns" aria-hidden="true"></i></span>' +
      '<span class="cg-boards-card__content">' +
      '<span class="cg-boards-card__name">' +
      escapeHtml(board.name) +
      '</span>' +
      projectLine +
      '<span class="cg-boards-card__meta">' +
      meta.join('') +
      '</span>' +
      openedLine +
      '</span>' +
      '<span class="cg-boards-card__footer">' +
      '<span class="cg-boards-badge"><i class="fas fa-cloud" aria-hidden="true"></i> Cloud</span>' +
      '<span class="cg-boards-card__arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>' +
      '</span>' +
      '</span>' +
      '</button>'
    );
  }

  function setRefreshingUI(isRefreshing) {
    var btn = document.getElementById('cgBoardsRefreshBtn');
    var sections = root.querySelector('.cg-boards-sections');
    if (btn) {
      btn.classList.toggle('is-refreshing', isRefreshing);
      btn.setAttribute('aria-busy', isRefreshing ? 'true' : 'false');
      var icon = btn.querySelector('.fa-rotate-right');
      if (icon) icon.classList.toggle('fa-spin', isRefreshing);
      var label = btn.querySelector('.cg-boards-refresh-label');
      if (label) label.textContent = isRefreshing ? 'Refreshing…' : 'Refresh';
    }
    if (sections) sections.classList.toggle('is-refreshing', isRefreshing);
  }

  function render() {
    var onDeviceCount = 0;
    var filtered = filteredBoards();
    var grouped = groupedBoards();

    var html =
      '<div class="cg-boards-hero">' +
      '<div><h1>' + escapeHtml(boardsHeroTitle()) + '</h1>' +
      '<p class="cg-boards-hero__lead">Open a board to plan, collaborate, and work offline when it&apos;s saved on this device.</p></div>' +
      '<div class="cg-boards-stats" aria-label="Board summary">' +
      '<button type="button" class="cg-boards-stat cg-boards-stat--action" id="cgBoardsNewBoardBtn" aria-label="Create new board">' +
      '<span class="cg-boards-stat__value" aria-hidden="true"><i class="fas fa-plus"></i></span>' +
      '<span class="cg-boards-stat__label">New board</span></button>' +
      '<div class="cg-boards-stat"><span class="cg-boards-stat__value" id="cgBoardsStatTotal">' +
      state.boards.length +
      '</span><span class="cg-boards-stat__label">Boards</span></div>' +
      '<div class="cg-boards-stat"><span class="cg-boards-stat__value" id="cgBoardsStatDevice">' +
      onDeviceCount +
      '</span><span class="cg-boards-stat__label">On device</span></div>' +
      '</div></div>' +
      '<div class="cg-boards-toolbar">' +
      '<label class="cg-boards-search"><i class="fas fa-search" aria-hidden="true"></i>' +
      '<input type="search" id="cgBoardsSearchInput" value="' +
      escapeHtml(state.query) +
      '" placeholder="Search boards or projects…" aria-label="Search boards" /></label>' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary' +
      (state.loading && state.boards.length > 0 ? ' is-refreshing' : '') +
      '" id="cgBoardsRefreshBtn" aria-busy="' +
      (state.loading ? 'true' : 'false') +
      '"><i class="fas fa-rotate-right' +
      (state.loading ? ' fa-spin' : '') +
      '" aria-hidden="true"></i><span class="cg-boards-refresh-label">' +
      (state.loading && state.boards.length > 0 ? 'Refreshing…' : 'Refresh') +
      '</span></button>' +
      '</div>';

    if (state.error) {
      html += '<div class="cg-boards-banner cg-boards-banner--error">' + escapeHtml(state.error) + '</div>';
    }

    if (state.loading && state.boards.length === 0) {
      html += skeletonCards(6);
    } else if (filtered.length === 0) {
      html +=
        '<div class="cg-boards-empty">' +
        '<div class="cg-boards-empty__icon" aria-hidden="true"><i class="fas fa-table-columns"></i></div>' +
        '<h2>' +
        (state.query ? 'No boards match your search' : 'No boards yet') +
        '</h2>' +
        '<p>' +
        (state.query
          ? 'Try a different name or clear the search.'
          : 'Boards from your CineGrid account will appear here after you sign in.') +
        '</p>' +
        '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgBoardsEmptyAction">' +
        (state.query ? 'Clear search' : 'Refresh boards') +
        '</button></div>';
    } else {
      html += '<div class="cg-boards-sections' + (state.loading ? ' is-refreshing' : '') + '">';
      grouped.forEach(function (entry) {
        var project = entry[0];
        var boards = entry[1];
        html += '<section class="cg-boards-section"><h2 class="cg-boards-section__title">' + escapeHtml(project) + '</h2>';
        html += '<div class="cg-boards-grid">';
        boards.forEach(function (board) {
          html += renderBoardCard(board, project);
        });
        if (!state.query.trim()) {
          html += renderNewBoardGridCard();
        }
        html += '</div></section>';
      });
      html += '</div>';
    }

    root.innerHTML = html;
    bindEvents();
  }

  function bindEvents() {
    var search = document.getElementById('cgBoardsSearchInput');
    if (search) {
      search.addEventListener('input', function (e) {
        state.query = e.target.value || '';
        render();
        var next = document.getElementById('cgBoardsSearchInput');
        if (next) {
          next.focus();
          var len = next.value.length;
          try {
            next.setSelectionRange(len, len);
          } catch (_) {}
        }
      });
    }

    var refreshBtn = document.getElementById('cgBoardsRefreshBtn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        loadBoards();
      });
    }

    var newBoardBtn = document.getElementById('cgBoardsNewBoardBtn');
    if (newBoardBtn) {
      newBoardBtn.addEventListener('click', function () {
        void openCreateBoardDialog();
      });
    }

    root.querySelectorAll('.cg-boards-card--new[data-cg-new-board-card]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        void openCreateBoardDialog();
      });
    });

    var emptyAction = document.getElementById('cgBoardsEmptyAction');
    if (emptyAction) {
      emptyAction.addEventListener('click', function () {
        if (state.query) {
          state.query = '';
          render();
        } else {
          loadBoards();
        }
      });
    }

    root.querySelectorAll('.cg-boards-card[data-board-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (Date.now() < suppressCardClickUntil) return;
        var id = btn.getAttribute('data-board-id');
        if (id) openBoard(id);
      });
      btn.addEventListener('contextmenu', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = btn.getAttribute('data-board-id');
        var board = boardById(id) || {
          id: id,
          name: btn.getAttribute('data-board-name') || '',
          is_owner: btn.getAttribute('data-is-owner') === '1',
          is_pinned: btn.getAttribute('data-is-pinned') === '1',
        };
        openContextMenu(board, e.clientX, e.clientY);
      });
    });
  }

  async function loadBoards() {
    state.loading = true;
    state.error = '';
    var hasBoards = state.boards.length > 0;
    if (hasBoards) {
      setRefreshingUI(true);
    } else {
      render();
    }
    var requestUrl = api + '?action=list_all';
    try {
      var res = await fetchWithRetry(requestUrl, { credentials: 'include', cache: 'no-store' }, { retries: 3 });
      var data = await readJsonResponse(res);
      if (!res.ok || !data || data._empty || data._parseError || !data.success) {
        throw new Error(friendlyBoardsLoadError(res, data));
      }
      state.boards = Array.isArray(data.boards) ? data.boards : [];
    } catch (err) {
      var msg = err && err.message ? String(err.message) : '';
      if (msg && !/JSON\.parse|unexpected end|SyntaxError|HTTP\s*\d+/i.test(msg)) {
        state.error = msg;
      } else {
        state.error = friendlyBoardsLoadError(null, null, err);
      }
      if (!state.boards.length) state.boards = [];
    } finally {
      state.loading = false;
      render();
    }
  }

  loadBoards();

  if (document.body && typeof MutationObserver === 'function') {
    var themeObserver = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i += 1) {
        if (mutations[i].attributeName === 'class') {
          render();
          break;
        }
      }
    });
    themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
  }
})();
