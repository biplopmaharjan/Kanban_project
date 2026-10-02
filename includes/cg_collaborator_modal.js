(function () {
  var api = window.CG_FK_API || window.CG_BOARDS_FK_API || 'kanban_api.php';
  var profilePicApi = window.CG_KANBAN_PROFILE_PIC_URL || window.CG_BOARDS_PROFILE_PIC_API || 'api/profile_pic.php';
  var overlayEl = null;
  var listEl = null;
  var inputEl = null;
  var errorEl = null;
  var suggestEl = null;
  var targetBoard = null;
  var onChangedCb = null;
  var suggestTimer = null;
  var suggestResults = [];
  var suggestIndex = -1;
  var suggestMinChars = 2;
  var suggestBindVersion = '1';
  var wired = false;

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function isBoardOwner(board) {
    if (!board) return false;
    return board.is_owner === 1 || board.is_owner === '1' || board.is_owner === true;
  }

  function portalAlert(message, opts) {
    if (typeof window.cgPortalAlert === 'function') {
      window.cgPortalAlert(message, opts);
      return;
    }
    window.alert(message);
  }

  async function readJsonResponse(res) {
    var raw = await res.text();
    var trimmed = raw.trim();
    if (!trimmed) return { _empty: true };
    try {
      return JSON.parse(trimmed);
    } catch (_) {
      return { _parseError: true };
    }
  }

  async function parseKanbanApiResponse(res) {
    var data = await readJsonResponse(res);
    if (data._empty || data._parseError) {
      return { success: false, message: 'Unexpected server response.' };
    }
    if (!res.ok) {
      data.success = false;
      if (!data.message) data.message = 'Request failed.';
    }
    return data;
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
    var res = await fetch(api, { method: 'POST', body: fd, credentials: 'include' });
    return parseKanbanApiResponse(res);
  }

  function ensureOverlay() {
    if (overlayEl) return;
    overlayEl = document.createElement('div');
    overlayEl.className = 'cg-boards-rename-overlay';
    overlayEl.id = 'cgCollaboratorOverlay';
    overlayEl.innerHTML =
      '<div class="cg-boards-rename-dialog cg-boards-collab-dialog" role="dialog" aria-modal="true" aria-labelledby="cgCollaboratorTitle">' +
      '<div class="cg-boards-rename-dialog__head" id="cgCollaboratorTitle">Add collaborator</div>' +
      '<div class="cg-boards-rename-dialog__body">' +
      '<p class="cg-boards-collab-dialog__hint">Invite others by email. They can open and edit this board from their Kanban.</p>' +
      '<div class="cg-boards-collab-list" id="cgCollaboratorList"><span class="cg-boards-collab-list__loading">Loading…</span></div>' +
      '<label class="cg-boards-create-field" for="cgCollaboratorEmail">Name or email</label>' +
      '<div class="cg-boards-collab-input-wrap">' +
      '<input type="text" class="cg-boards-rename-dialog__input" id="cgCollaboratorEmail" maxlength="190" autocomplete="off" placeholder="Type a name or email to search" aria-autocomplete="list" aria-controls="cgCollaboratorSuggest" aria-expanded="false" />' +
      '</div>' +
      '<div class="cg-boards-collab-dialog__error" id="cgCollaboratorError" hidden></div>' +
      '</div>' +
      '<div class="cg-boards-rename-dialog__actions">' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgCollaboratorCancel">Cancel</button>' +
      '<button type="button" class="cg-boards-btn cg-boards-btn--secondary" id="cgCollaboratorAdd"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Add</button>' +
      '</div></div>';
    document.body.appendChild(overlayEl);
    listEl = overlayEl.querySelector('#cgCollaboratorList');
    inputEl = overlayEl.querySelector('#cgCollaboratorEmail');
    errorEl = overlayEl.querySelector('#cgCollaboratorError');
    ensureSuggestPortal();
    wireOverlay();
  }

  function ensureSuggestPortal() {
    if (!overlayEl) return;
    inputEl = overlayEl.querySelector('#cgCollaboratorEmail');
    if (!suggestEl || !suggestEl.classList.contains('cg-boards-user-suggest--portal')) {
      if (suggestEl) suggestEl.remove();
      suggestEl = document.createElement('div');
      suggestEl.className = 'cg-boards-user-suggest cg-boards-user-suggest--portal';
      suggestEl.id = 'cgCollaboratorSuggest';
      suggestEl.hidden = true;
      suggestEl.setAttribute('role', 'listbox');
      suggestEl.setAttribute('aria-label', 'Suggested collaborators');
      document.body.appendChild(suggestEl);
    }
    if (inputEl && inputEl.dataset.suggestBound !== suggestBindVersion) {
      if (inputEl.dataset.suggestBound) {
        var freshInput = inputEl.cloneNode(true);
        freshInput.value = inputEl.value;
        inputEl.parentNode.replaceChild(freshInput, inputEl);
        inputEl = freshInput;
      }
      inputEl.dataset.suggestBound = '';
      attachSuggest();
    }
  }

  function wireOverlay() {
    if (wired || !overlayEl) return;
    wired = true;
    overlayEl.querySelector('#cgCollaboratorCancel').addEventListener('click', closeModal);
    overlayEl.addEventListener('click', function (e) {
      if (e.target === overlayEl) closeModal();
    });
    overlayEl.querySelector('#cgCollaboratorAdd').addEventListener('click', function () {
      void saveCollaborator();
    });
    if (inputEl) {
      inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          closeModal();
        }
      });
    }
    window.addEventListener('resize', function () {
      if (suggestEl && !suggestEl.hidden) positionSuggest();
    });
    window.addEventListener('scroll', function () {
      if (suggestEl && !suggestEl.hidden) positionSuggest();
    }, true);
  }

  function showError(message) {
    if (!errorEl) return;
    if (!message) {
      errorEl.hidden = true;
      errorEl.textContent = '';
      return;
    }
    errorEl.hidden = false;
    errorEl.textContent = message;
  }

  function notifyChanged() {
    if (typeof onChangedCb === 'function') {
      try { onChangedCb(targetBoard); } catch (_) { /* ignore */ }
    }
  }

  function renderCollaboratorList(collaborators, pending) {
    if (!listEl) return;
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
      listEl.innerHTML = '<span class="cg-boards-collab-list__empty">No collaborators yet. Add someone by email below.</span>';
      return;
    }
    listEl.innerHTML = items.join('');
    listEl.querySelectorAll('.cg-boards-collab-chip__remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        void removeCollaborator(btn.getAttribute('data-user-id') || '', btn.getAttribute('data-email') || '');
      });
    });
  }

  async function loadCollaboratorList(boardId) {
    if (!listEl) return;
    listEl.innerHTML = '<span class="cg-boards-collab-list__loading">Loading…</span>';
    try {
      var res = await fetch(api + '?action=list_collaborators&board_id=' + encodeURIComponent(String(boardId)), {
        credentials: 'include',
        cache: 'no-store',
      });
      var data = await parseKanbanApiResponse(res);
      if (!data.success) throw new Error(data.message || 'Could not load collaborators');
      renderCollaboratorList(data.collaborators || [], data.pending || []);
    } catch (err) {
      listEl.innerHTML =
        '<span class="cg-boards-collab-list__empty">' +
        escapeHtml(err && err.message ? err.message : 'Could not load collaborators') +
        '</span>';
    }
  }

  function boardAssociatedUserPicUrl(profilePic) {
    var p = String(profilePic || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    var rel = p.replace(/^\/+/, '');
    if (rel.indexOf('uploads/') !== 0) rel = 'uploads/profile_pics/' + rel;
    return profilePicApi + '?path=' + encodeURIComponent(rel);
  }

  function hideSuggest() {
    if (!suggestEl) return;
    suggestEl.hidden = true;
    suggestEl.innerHTML = '';
    suggestResults = [];
    suggestIndex = -1;
    suggestEl.style.left = '';
    suggestEl.style.top = '';
    suggestEl.style.width = '';
    suggestEl.style.maxHeight = '';
    if (inputEl) inputEl.setAttribute('aria-expanded', 'false');
  }

  function positionSuggest() {
    if (!suggestEl || !inputEl || suggestEl.hidden) return;
    var rect = inputEl.getBoundingClientRect();
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
    suggestEl.style.left = left + 'px';
    suggestEl.style.top = Math.max(pad, top) + 'px';
    suggestEl.style.width = width + 'px';
    suggestEl.style.maxHeight = height + 'px';
  }

  function searchQuery() {
    return inputEl ? String(inputEl.value || '').trim() : '';
  }

  function selectSuggestion(user) {
    if (!user || !inputEl) return;
    inputEl.value = String(user.email || '').trim();
    hideSuggest();
    showError('');
  }

  function renderSuggest(users) {
    if (!suggestEl) return;
    if (searchQuery().length < suggestMinChars) {
      hideSuggest();
      return;
    }
    suggestResults = users || [];
    suggestIndex = -1;
    if (!suggestResults.length) {
      hideSuggest();
      return;
    }
    suggestEl.innerHTML = suggestResults.map(function (u, idx) {
      var name = escapeHtml(u.name || u.email || 'User');
      var email = escapeHtml(u.email || '');
      var pic = boardAssociatedUserPicUrl(u.profile_pic);
      var avatar = pic
        ? '<img class="cg-boards-user-suggest__avatar" src="' + escapeHtml(pic) + '" alt="" loading="lazy" />'
        : '<span class="cg-boards-user-suggest__avatar cg-boards-user-suggest__avatar--fallback" aria-hidden="true">' +
          escapeHtml((name.charAt(0) || '?').toUpperCase()) + '</span>';
      return (
        '<button type="button" class="cg-boards-user-suggest__item" role="option" data-index="' + idx + '" data-email="' + email + '">' +
        avatar +
        '<span class="cg-boards-user-suggest__text">' +
        '<span class="cg-boards-user-suggest__name">' + name + '</span>' +
        (email ? '<span class="cg-boards-user-suggest__email">' + email + '</span>' : '') +
        '</span></button>'
      );
    }).join('');
    suggestEl.hidden = false;
    if (inputEl) inputEl.setAttribute('aria-expanded', 'true');
    suggestEl.querySelectorAll('.cg-boards-user-suggest__item').forEach(function (btn) {
      btn.addEventListener('mousedown', function (e) {
        e.preventDefault();
        var idx = parseInt(btn.getAttribute('data-index') || '-1', 10);
        if (idx >= 0 && suggestResults[idx]) selectSuggestion(suggestResults[idx]);
      });
    });
    positionSuggest();
  }

  async function fetchSuggestions(query) {
    if (!targetBoard) return;
    var q = String(query || '').trim();
    if (q.length < suggestMinChars) {
      hideSuggest();
      return;
    }
    var url =
      api +
      '?action=search_board_associated_users&board_id=' +
      encodeURIComponent(String(targetBoard.id)) +
      '&q=' +
      encodeURIComponent(q);
    try {
      var res = await fetch(url, { credentials: 'include', cache: 'no-store' });
      var data = await parseKanbanApiResponse(res);
      if (searchQuery() !== q) return;
      if (!data.success) {
        hideSuggest();
        return;
      }
      renderSuggest(data.users || []);
    } catch (_) {
      hideSuggest();
    }
  }

  function attachSuggest() {
    if (!inputEl || !suggestEl) return;
    if (inputEl.dataset.suggestBound === suggestBindVersion) return;
    inputEl.dataset.suggestBound = suggestBindVersion;
    inputEl.addEventListener('input', function () {
      clearTimeout(suggestTimer);
      var q = searchQuery();
      if (q.length < suggestMinChars) {
        hideSuggest();
        return;
      }
      suggestTimer = setTimeout(function () { void fetchSuggestions(q); }, 180);
    });
    inputEl.addEventListener('focus', function () {
      if (searchQuery().length < suggestMinChars) hideSuggest();
    });
    inputEl.addEventListener('blur', function () {
      setTimeout(hideSuggest, 180);
    });
    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (!suggestEl.hidden) {
          e.preventDefault();
          e.stopPropagation();
          hideSuggest();
        }
        return;
      }
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter') return;
      var items = suggestEl.querySelectorAll('.cg-boards-user-suggest__item');
      if (!items.length) {
        if (e.key === 'Enter') {
          e.preventDefault();
          void saveCollaborator();
        }
        return;
      }
      e.preventDefault();
      if (e.key === 'ArrowDown') suggestIndex = Math.min(suggestIndex + 1, items.length - 1);
      else if (e.key === 'ArrowUp') suggestIndex = Math.max(suggestIndex - 1, -1);
      else if (e.key === 'Enter') {
        if (suggestIndex >= 0 && suggestResults[suggestIndex]) {
          selectSuggestion(suggestResults[suggestIndex]);
          void saveCollaborator();
        } else {
          void saveCollaborator();
        }
        return;
      }
      items.forEach(function (item, i) {
        item.classList.toggle('is-selected', i === suggestIndex);
      });
      if (suggestIndex >= 0 && items[suggestIndex]) {
        items[suggestIndex].scrollIntoView({ block: 'nearest' });
      }
    });
  }

  async function saveCollaborator() {
    if (!targetBoard) return;
    var email = inputEl ? String(inputEl.value || '').trim().toLowerCase() : '';
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showError('Please enter a valid email address.');
      return;
    }
    showError('');
    var addBtn = overlayEl.querySelector('#cgCollaboratorAdd');
    if (addBtn) addBtn.disabled = true;
    try {
      var data = await postBoardAction('add_collaborator', targetBoard.id, { email: email });
      if (!data.success) throw new Error(data.message || 'Failed to add collaborator');
      if (inputEl) inputEl.value = '';
      hideSuggest();
      await loadCollaboratorList(targetBoard.id);
      notifyChanged();
      portalAlert('Collaborator invited.', { title: 'Added', variant: 'success' });
    } catch (err) {
      showError(err && err.message ? err.message : 'Failed to add collaborator');
    } finally {
      if (addBtn) addBtn.disabled = false;
    }
  }

  async function removeCollaborator(userId, email) {
    if (!targetBoard) return;
    var fields = {};
    if (userId) fields.user_id = userId;
    else if (email) fields.email = email;
    else return;
    try {
      var data = await postBoardAction('remove_collaborator', targetBoard.id, fields);
      if (!data.success) throw new Error(data.message || 'Failed to remove collaborator');
      await loadCollaboratorList(targetBoard.id);
      notifyChanged();
    } catch (err) {
      portalAlert(err && err.message ? err.message : 'Failed to remove collaborator', {
        title: 'Collaborators',
        variant: 'danger',
      });
    }
  }

  function closeModal() {
    if (!overlayEl) return;
    overlayEl.classList.remove('is-open');
    targetBoard = null;
    onChangedCb = null;
    showError('');
    hideSuggest();
  }

  function openModal(board, opts) {
    if (!board || !isBoardOwner(board)) return;
    ensureOverlay();
    clearTimeout(suggestTimer);
    hideSuggest();
    targetBoard = board;
    onChangedCb = opts && typeof opts.onChanged === 'function' ? opts.onChanged : null;
    showError('');
    if (inputEl) inputEl.value = '';
    var titleEl = overlayEl.querySelector('#cgCollaboratorTitle');
    if (titleEl) titleEl.textContent = 'Add collaborator — ' + String(board.name || 'Board');
    overlayEl.classList.add('is-open');
    void loadCollaboratorList(board.id);
    setTimeout(function () {
      if (inputEl) inputEl.focus();
    }, 0);
  }

  window.cgOpenBoardCollaboratorModal = openModal;
  window.cgCloseBoardCollaboratorModal = closeModal;
})();
