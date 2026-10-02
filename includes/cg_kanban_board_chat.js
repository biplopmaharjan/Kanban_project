/**
 * Kanban board collaborator chat (poll + presence + typing).
 * Expects: CG_FK_API, optional window.escapeHtml, optional boardMemberPicUrl(), window.CG_BOARD_CHAT_USER
 */
(function () {
  'use strict';

  var cfg = window.CG_BOARD_CHAT_USER || {};
  var myId = parseInt(cfg.id, 10) || 0;
  if (myId <= 0) return;
  var inlineChat = !!cfg.inline;

  var api = typeof CG_FK_API !== 'undefined' ? CG_FK_API : '';
  if (!api) return;

  function esc(s) {
    if (typeof window.escapeHtml === 'function') {
      return window.escapeHtml(s);
    }
    return (s == null ? '' : String(s))
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  var POLL_MS = 2000;
  var pollTimer = null;
  var typingTimer = null;
  var lastAfterId = 0;
  var lastSinceUpd = 0;
  var msgBodyCache = Object.create(null);
  var composerEditingMsgId = 0;
  var composerEditingHadCard = false;
  var boardId = 0;
  var panelOpen = false;
  var chatBackgroundInitialized = false;
  var pendingTyping = false;
  var attachedCard = null;
  var seenIds = Object.create(null);
  var lastTypingSent = false;
  var chatPanelDrag = null;
  var CHAT_POS_STORAGE = 'cgKanbanBoardChatPos';
  var chatResizeTimer = null;
  var mentionPendingIds = [];
  var mentionListShown = [];
  var mentionActiveIndex = 0;
  var mentionMouseDownOnDropdown = false;

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function uuid() {
    try {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
      }
    } catch (e) {}
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      var v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function picUrl(profilePic) {
    if (typeof boardMemberPicUrl === 'function') return boardMemberPicUrl(profilePic) || '';
    return profilePic ? String(profilePic) : '';
  }

  function initials(name) {
    var s = (name || '').trim();
    if (!s) return '?';
    var p = s.split(/\s+/);
    if (p.length >= 2) return (p[0][0] + p[1][0]).toUpperCase();
    return s.slice(0, 2).toUpperCase();
  }
  function colorFromSeed(seed) {
    var raw = String(seed || '');
    var hash = 0;
    for (var i = 0; i < raw.length; i++) hash = ((hash << 5) - hash + raw.charCodeAt(i)) | 0;
    var hue = Math.abs(hash) % 360;
    return 'hsl(' + hue + 'deg 68% 72%)';
  }
  function getActiveMemberColor(userId, fallbackName) {
    var map = window.CG_ACTIVE_MEMBER_COLORS || {};
    var uid = parseInt(userId, 10) || 0;
    if (uid > 0 && map && map[String(uid)]) return String(map[String(uid)]);
    if (window.cgPresenceColorForSeed && typeof window.cgPresenceColorForSeed === 'function') {
      try {
        return String(window.cgPresenceColorForSeed(uid > 0 ? ('u:' + uid) : String(fallbackName || '')));
      } catch (e) {}
    }
    return colorFromSeed(uid > 0 ? ('u:' + uid) : String(fallbackName || ''));
  }
  function softColor(color, alpha) {
    var c = String(color || '').trim();
    var a = typeof alpha === 'number' ? alpha : 0.18;
    var hsl = c.match(/^hsl\(\s*([0-9.+-]+)(?:deg)?\s+([0-9.+-]+)%\s+([0-9.+-]+)%\s*\)$/i);
    if (hsl) {
      return 'hsla(' + hsl[1] + ', ' + hsl[2] + '%, ' + hsl[3] + '%, ' + a + ')';
    }
    return c;
  }

  function fmtDayLabel(iso) {
    if (!iso) return '';
    var d = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(d.getTime())) {
      d = new Date(iso);
    }
    if (isNaN(d.getTime())) return '';
    var today = new Date();
    var yest = new Date(today);
    yest.setDate(yest.getDate() - 1);
    function sameDay(a, b) {
      return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }
    if (sameDay(d, today)) return 'Today';
    if (sameDay(d, yest)) return 'Yesterday';
    return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  }

  function dayKey(iso) {
    var d = new Date(String(iso || '').replace(' ', 'T'));
    if (isNaN(d.getTime())) d = new Date(iso || '');
    if (isNaN(d.getTime())) return '';
    return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
  }

  function getChatCollaborators() {
    var m = window.CG_BOARD_CHAT_MEMBERS;
    if (!Array.isArray(m)) return [];
    return m.filter(function (x) {
      var id = parseInt(x.id, 10) || 0;
      return id > 0 && id !== myId;
    });
  }

  function getMentionTrigger(ta) {
    if (!ta) return null;
    var pos = ta.selectionStart;
    var val = ta.value;
    var before = val.slice(0, pos);
    var at = before.lastIndexOf('@');
    if (at < 0) return null;
    var prev = at > 0 ? before.charAt(at - 1) : ' ';
    if (prev && !/\s/.test(prev)) return null;
    var afterAt = before.slice(at + 1);
    if (/\n/.test(afterAt)) return null;
    return { at: at, query: afterAt, replaceEnd: pos };
  }

  function hideMentionDropdown() {
    var dd = qs('#cgBoardChatMentionDropdown');
    if (!dd) return;
    dd.hidden = true;
    dd.innerHTML = '';
    mentionListShown = [];
    mentionActiveIndex = 0;
  }

  function positionMentionDropdown() {
    var ta = qs('#cgBoardChatTextarea');
    var dd = qs('#cgBoardChatMentionDropdown');
    if (!ta || !dd || dd.hidden) return;
    var r = ta.getBoundingClientRect();
    var w = Math.min(320, Math.max(220, window.innerWidth - 24));
    var estH = Math.min(mentionListShown.length * 44 + 8, 220);
    var topAbove = r.top - estH - 8;
    var placeBelow = topAbove < 8;
    var top = placeBelow ? (r.bottom + 6) : topAbove;
    dd.style.position = 'fixed';
    dd.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
    dd.style.width = w + 'px';
    dd.style.top = top + 'px';
    dd.style.zIndex = '10060';
  }

  function refreshMentionActiveClass() {
    var dd = qs('#cgBoardChatMentionDropdown');
    if (!dd) return;
    qsa('.cg-board-chat__mention-item', dd).forEach(function (btn, i) {
      btn.classList.toggle('is-active', i === mentionActiveIndex);
    });
  }

  function confirmMentionPick() {
    var m = mentionListShown[mentionActiveIndex];
    if (m) insertMentionChoice(m);
  }

  function getMentionAllChoice() {
    return { id: 0, name: 'ALL', isAll: true };
  }

  function mentionQueryMatchesAll(query) {
    var q = String(query || '').trim().toLowerCase();
    return !q || q === 'all' || 'all'.indexOf(q) === 0;
  }

  function addMentionPendingId(uid) {
    uid = parseInt(uid, 10) || 0;
    if (uid > 0 && mentionPendingIds.indexOf(uid) < 0) {
      mentionPendingIds.push(uid);
    }
  }

  function insertMentionChoice(member) {
    var ta = qs('#cgBoardChatTextarea');
    if (!ta || !member) return;
    var trigger = getMentionTrigger(ta);
    if (!trigger) return;
    var name = String(member.name || 'User').trim();
    if (!name) return;
    var val = ta.value;
    var newVal = val.slice(0, trigger.at) + '@' + name + ' ' + val.slice(trigger.replaceEnd);
    ta.value = newVal;
    var newPos = trigger.at + 1 + name.length + 1;
    ta.setSelectionRange(newPos, newPos);
    ta.dispatchEvent(new Event('input', { bubbles: true }));
    if (member.isAll) {
      getChatCollaborators().forEach(function (mem) {
        addMentionPendingId(mem.id);
      });
    } else {
      addMentionPendingId(member.id);
    }
    hideMentionDropdown();
    ta.focus();
  }

  function maybeShowMentionDropdown() {
    var ta = qs('#cgBoardChatTextarea');
    var dd = qs('#cgBoardChatMentionDropdown');
    if (!ta || !dd || !panelOpen) return;
    var trigger = getMentionTrigger(ta);
    if (!trigger) {
      hideMentionDropdown();
      return;
    }
    var q = String(trigger.query || '').trim().toLowerCase();
    var collaborators = getChatCollaborators();
    var filtered = collaborators.filter(function (mem) {
      var nm = String(mem.name || '').toLowerCase();
      return !q || nm.indexOf(q) >= 0;
    }).slice(0, 12);
    var list = [];
    if (collaborators.length && mentionQueryMatchesAll(trigger.query)) {
      list.push(getMentionAllChoice());
    }
    list = list.concat(filtered);
    if (!list.length) {
      hideMentionDropdown();
      return;
    }
    mentionListShown = list;
    mentionActiveIndex = 0;
    dd.innerHTML = list.map(function (mem, idx) {
      var nm = esc(String(mem.name || 'User'));
      var role = mem.isAll
        ? '<span class="cg-board-chat__mention-role">Everyone</span>'
        : (mem.role ? '<span class="cg-board-chat__mention-role">' + esc(String(mem.role)) + '</span>' : '');
      var extraClass = mem.isAll ? ' cg-board-chat__mention-item--all' : '';
      return '<button type="button" class="cg-board-chat__mention-item' + extraClass + (idx === 0 ? ' is-active' : '') + '" data-mention-index="' + idx + '">' +
        '<span class="cg-board-chat__mention-name">' + nm + '</span>' + role + '</button>';
    }).join('');
    dd.hidden = false;
    requestAnimationFrame(function () {
      positionMentionDropdown();
      refreshMentionActiveClass();
    });
  }

  function clampBoardChatPosition(left, top, panel) {
    var pad = 8;
    var rect = panel.getBoundingClientRect();
    var w = rect.width || 320;
    var h = rect.height || 280;
    var maxL = Math.max(pad, window.innerWidth - w - pad);
    var maxT = Math.max(pad, window.innerHeight - h - pad);
    return {
      left: Math.min(Math.max(pad, left), maxL),
      top: Math.min(Math.max(pad, top), maxT)
    };
  }

  function loadBoardChatPos() {
    try {
      var raw = localStorage.getItem(CHAT_POS_STORAGE);
      if (!raw) return null;
      var o = JSON.parse(raw);
      if (o && typeof o.left === 'number' && typeof o.top === 'number') {
        return { left: o.left, top: o.top };
      }
    } catch (e) {}
    return null;
  }

  function saveBoardChatPos(left, top) {
    try {
      localStorage.setItem(CHAT_POS_STORAGE, JSON.stringify({ left: left, top: top }));
    } catch (e) {}
  }

  function applyBoardChatPanelPosition(panel) {
    if (!panel || !panel.classList.contains('is-open')) return;
    panel.style.position = 'fixed';
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
    var saved = loadBoardChatPos();
    var pad = 8;
    var navReserve = document.querySelector('.cg-bottom-nav') ? 108 : 20;
    var headerReserve = 72;
    if (saved) {
      var c = clampBoardChatPosition(saved.left, saved.top, panel);
      panel.style.left = c.left + 'px';
      panel.style.top = c.top + 'px';
      return;
    }
    var rect = panel.getBoundingClientRect();
    var w = rect.width || 320;
    var h = Math.max(rect.height, 200);
    var defaultLeft = window.innerWidth - w - 16;
    var defaultTop = window.innerHeight - h - navReserve;
    defaultLeft = Math.max(pad, defaultLeft);
    defaultTop = Math.max(headerReserve, Math.min(defaultTop, window.innerHeight - h - pad));
    var c2 = clampBoardChatPosition(defaultLeft, defaultTop, panel);
    panel.style.left = c2.left + 'px';
    panel.style.top = c2.top + 'px';
  }

  function initBoardChatPanelDrag() {
    var dragRegion = qs('#cgBoardChatHeaderMain');
    var panel = qs('#cgBoardChatPanel');
    if (!dragRegion || !panel || dragRegion.getAttribute('data-cg-chat-drag-init') === '1') return;
    dragRegion.setAttribute('data-cg-chat-drag-init', '1');
    dragRegion.addEventListener('pointerdown', function (e) {
      if (e.button !== 0) return;
      e.preventDefault();
      if (!panel.classList.contains('is-open')) return;
      var rect = panel.getBoundingClientRect();
      chatPanelDrag = {
        pointerId: e.pointerId,
        startClientX: e.clientX,
        startClientY: e.clientY,
        startLeft: rect.left,
        startTop: rect.top
      };
      panel.classList.add('cg-board-chat--dragging');
      try {
        dragRegion.setPointerCapture(e.pointerId);
      } catch (err) {}
    });
    var endDrag = function (e) {
      if (!chatPanelDrag || chatPanelDrag.pointerId !== e.pointerId) return;
      panel.classList.remove('cg-board-chat--dragging');
      try {
        dragRegion.releasePointerCapture(e.pointerId);
      } catch (err2) {}
      var r = panel.getBoundingClientRect();
      saveBoardChatPos(r.left, r.top);
      chatPanelDrag = null;
    };
    dragRegion.addEventListener('pointermove', function (e) {
      if (!chatPanelDrag || chatPanelDrag.pointerId !== e.pointerId) return;
      e.preventDefault();
      var d = chatPanelDrag;
      var nextLeft = d.startLeft + (e.clientX - d.startClientX);
      var nextTop = d.startTop + (e.clientY - d.startClientY);
      var c = clampBoardChatPosition(nextLeft, nextTop, panel);
      panel.style.left = c.left + 'px';
      panel.style.top = c.top + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      panel.style.position = 'fixed';
    });
    dragRegion.addEventListener('pointerup', endDrag);
    dragRegion.addEventListener('pointercancel', endDrag);
  }

  function scheduleBoardChatResizeClamp() {
    if (!inlineChat) return;
    if (chatResizeTimer) clearTimeout(chatResizeTimer);
    chatResizeTimer = setTimeout(function () {
      chatResizeTimer = null;
      var panel = qs('#cgBoardChatPanel');
      if (!panel || !panel.classList.contains('is-open')) return;
      var r = panel.getBoundingClientRect();
      var c = clampBoardChatPosition(r.left, r.top, panel);
      panel.style.left = c.left + 'px';
      panel.style.top = c.top + 'px';
      saveBoardChatPos(c.left, c.top);
    }, 120);
  }

  function openPanel() {
    var panel = qs('#cgBoardChatPanel');
    var back = qs('#cgBoardChatBackdrop');
    var fab = qs('#cgBoardChatFab');
    var navChat = document.getElementById('cgBottomNavChat');
    if (!panel) return;
    if (inlineChat) {
      panelOpen = true;
      lastTypingSent = false;
      pendingTyping = false;
      if (navChat) {
        navChat.classList.add('is-active');
        navChat.setAttribute('aria-expanded', 'true');
      }
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      if (fab) fab.classList.remove('has-unread');
      initBoardChatPanelDrag();
      requestAnimationFrame(function () {
        applyBoardChatPanelPosition(panel);
        requestAnimationFrame(function () {
          applyBoardChatPanelPosition(panel);
        });
      });
      startPoll();
      syncNow(true);
      var taInline = qs('#cgBoardChatTextarea');
      if (taInline) {
        try {
          taInline.focus({ preventScroll: false });
        } catch (e) {
          taInline.focus();
        }
      }
      return;
    }
    if (!back) return;
    lastTypingSent = false;
    pendingTyping = false;
    if (navChat) {
      navChat.classList.add('is-active');
      navChat.setAttribute('aria-expanded', 'true');
    }
    panel.classList.add('is-open');
    back.classList.add('is-open');
    back.setAttribute('aria-hidden', 'false');
    panel.setAttribute('aria-hidden', 'false');
    if (fab) fab.classList.remove('has-unread');
    panelOpen = true;
    lastAfterId = 0;
    lastSinceUpd = 0;
    seenIds = Object.create(null);
    var list = qs('#cgBoardChatMessages');
    if (list) list.innerHTML = '';
    syncNow(true);
    if (!boardId) {
      var waitUntil = Date.now() + 5000;
      var tick = function () {
        if (!panelOpen) return;
        if (boardId) {
          syncNow(true);
          startPoll();
          return;
        }
        if (Date.now() < waitUntil) {
          setTimeout(tick, 100);
        }
      };
      setTimeout(tick, 50);
    }
    startPoll();
    var ta = qs('#cgBoardChatTextarea');
    if (ta) setTimeout(function () { ta.focus(); }, 220);
  }

  function closePanel() {
    if (inlineChat) {
      var panel = qs('#cgBoardChatPanel');
      var navChatOnly = document.getElementById('cgBottomNavChat');
      lastTypingSent = false;
      pendingTyping = false;
      if (navChatOnly) {
        navChatOnly.classList.remove('is-active');
        navChatOnly.setAttribute('aria-expanded', 'false');
      }
      if (panel) {
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
      }
      panelOpen = false;
      hideMentionDropdown();
      cancelAllMsgEdits();
      closeAllMsgMenus();
      stopPoll();
      sendTypingFlag(false);
      return;
    }
    var panel = qs('#cgBoardChatPanel');
    var back = qs('#cgBoardChatBackdrop');
    var navChat = document.getElementById('cgBottomNavChat');
    if (!panel || !back) return;
    lastTypingSent = false;
    pendingTyping = false;
    if (navChat) {
      navChat.classList.remove('is-active');
      navChat.setAttribute('aria-expanded', 'false');
    }
    panel.classList.remove('is-open');
    back.classList.remove('is-open');
    back.setAttribute('aria-hidden', 'true');
    panel.setAttribute('aria-hidden', 'true');
    panelOpen = false;
    hideMentionDropdown();
    cancelAllMsgEdits();
    closeAllMsgMenus();
    stopPoll();
    sendTypingFlag(false);
  }

  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    if (typingTimer) {
      clearTimeout(typingTimer);
      typingTimer = null;
    }
    sendTypingFlag(false);
  }

  function startPoll() {
    stopPoll();
    if (!boardId) return;
    pollTimer = setInterval(function () { syncNow(false); }, POLL_MS);
  }

  function sendTypingFlag(on) {
    if (!boardId || !panelOpen) return;
    if (on === lastTypingSent) return;
    lastTypingSent = on;
    var url = api + '?action=chat_sync&board_id=' + encodeURIComponent(boardId) + '&after_id=' + encodeURIComponent(String(lastAfterId)) + '&since_upd=' + encodeURIComponent(String(lastSinceUpd)) + '&typing=' + (on ? '1' : '0');
    fetch(url, { credentials: 'include', cache: 'no-store' }).catch(function () {});
  }

  function scheduleTypingPing() {
    if (!panelOpen || !boardId) return;
    pendingTyping = true;
    if (typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(function () {
      typingTimer = null;
      if (pendingTyping) {
        pendingTyping = false;
        sendTypingFlag(true);
      }
    }, 320);
  }

  function clearTypingPing() {
    pendingTyping = false;
    if (typingTimer) {
      clearTimeout(typingTimer);
      typingTimer = null;
    }
    sendTypingFlag(false);
  }

  function renderOnline(online) {
    var wrap = qs('#cgBoardChatOnlineList');
    if (!wrap) return;
    if (!online || !online.length) {
      wrap.innerHTML = '<span class="text-muted small">Nobody online</span>';
      return;
    }
    wrap.innerHTML = online.map(function (u) {
      var src = picUrl(u.profile_pic);
      var name = esc(u.name || 'User');
      var uid = parseInt(u.id || u.user_id, 10) || 0;
      var color = getActiveMemberColor(uid, u.name || '');
      var style = ' style="--cg-chat-user-color:' + esc(color) + '"';
      if (src) {
        return '<img class="cg-board-chat__avatar" src="' + esc(src) + '" alt="" title="' + name + '" loading="lazy"' + style + ' />';
      }
      return '<span class="cg-board-chat__avatar-initials" title="' + name + '"' + style + '>' + esc(initials(u.name)) + '</span>';
    }).join('');
  }

  function msgTimestampUnix(m) {
    var ts = (m && (m.updated_at || m.created_at)) || '';
    var d = new Date(String(ts).replace(' ', 'T'));
    if (isNaN(d.getTime())) return 0;
    return d.getTime() / 1000;
  }

  function bumpSinceUpd(m) {
    var u = msgTimestampUnix(m);
    if (u > 0) lastSinceUpd = Math.max(lastSinceUpd, u);
  }

  function bubbleInnerHtml(m) {
    var inner = esc(m.body || '');
    if (m.card_context && m.card_context.card_id) {
      var cc = m.card_context;
      var href = typeof window.cgPortalKanbanBoardHref === 'function'
        ? window.cgPortalKanbanBoardHref(boardId, cc.card_id)
        : ('?board_id=' + encodeURIComponent(boardId) + '&card_id=' + encodeURIComponent(cc.card_id));
      var cidAttr = parseInt(cc.card_id, 10) || 0;
      inner += '<a class="cg-board-chat__card-chip" href="' + esc(href) + '" data-card-id="' + String(cidAttr) + '">' + esc(cc.title || 'Card') + '<small>' + esc(cc.column_name || '') + '</small></a>';
    }
    return inner;
  }

  function messageActionsHtml(m) {
    var ec = parseInt(m.edit_count, 10) || 0;
    var canEdit = ec < 2;
    var editBtn = canEdit
      ? '<button type="button" class="cg-board-chat__msg-dd-item" data-cg-chat-msg-action="edit">Edit</button>'
      : '';
    return '<div class="cg-board-chat__msg-actions">' +
      '<button type="button" class="cg-board-chat__msg-menu-btn" aria-label="Message options" aria-expanded="false" data-cg-chat-msg-menu>\u22ee</button>' +
      '<div class="cg-board-chat__msg-dropdown" hidden data-cg-chat-msg-dropdown>' +
      editBtn +
      '<button type="button" class="cg-board-chat__msg-dd-item" data-cg-chat-msg-action="unsend">Unsend</button>' +
      '</div></div>';
  }

  function setMessageRowContent(row, m) {
    var id = parseInt(m.id, 10) || 0;
    if (id <= 0) return;
    msgBodyCache[id] = m.body != null ? String(m.body) : '';
    var self = parseInt(m.user_id, 10) === myId;
    var color = getActiveMemberColor(m.user_id, m.user_name || '');
    row.className = 'cg-board-chat__msg' + (self ? ' is-self' : '');
    if (color) {
      row.classList.add('has-user-color');
      row.style.setProperty('--cg-chat-user-color', color);
      row.style.setProperty('--cg-chat-user-color-soft', softColor(color, self ? 0.22 : 0.16));
    } else {
      row.classList.remove('has-user-color');
      row.style.removeProperty('--cg-chat-user-color');
      row.style.removeProperty('--cg-chat-user-color-soft');
    }
    row.removeAttribute('data-client-id');
    row.setAttribute('data-msg-id', String(id));
    var time = '';
    try {
      var dt = new Date(String(m.created_at || '').replace(' ', 'T'));
      if (!isNaN(dt.getTime())) {
        time = dt.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
      }
    } catch (e) {}
    var meta = '<div class="cg-board-chat__msg-meta"><strong>' + esc(m.user_name || 'User') + '</strong> &middot; ' + esc(time) + '</div>';
    var bubbleInner = bubbleInnerHtml(m);
    if (self) {
      row.innerHTML = meta + '<div class="cg-board-chat__msg-main"><div class="cg-board-chat__bubble">' + bubbleInner + '</div>' + messageActionsHtml(m) + '</div>';
    } else {
      row.innerHTML = meta + '<div class="cg-board-chat__bubble">' + bubbleInner + '</div>';
    }
  }

  function closeAllMsgMenus() {
    qsa('.cg-board-chat__msg-actions.is-open').forEach(function (w) {
      w.classList.remove('is-open');
      var b = w.querySelector('[data-cg-chat-msg-menu]');
      var d = w.querySelector('[data-cg-chat-msg-dropdown]');
      if (b) b.setAttribute('aria-expanded', 'false');
      if (d) d.hidden = true;
    });
  }

  function ensureComposerEditBanner() {
    var wrap = qs('#cgBoardChatComposer');
    if (!wrap) return null;
    var b = qs('#cgBoardChatEditBanner');
    if (!b) {
      b = document.createElement('div');
      b.id = 'cgBoardChatEditBanner';
      b.className = 'cg-board-chat__edit-banner';
      b.innerHTML = '<span class="cg-board-chat__edit-banner-text">Editing message</span>' +
        '<button type="button" class="cg-board-chat__edit-banner-cancel" data-cg-chat-cancel-composer-edit>Cancel</button>';
      var typing = qs('#cgBoardChatTyping');
      if (typing && typing.parentNode === wrap) {
        wrap.insertBefore(b, typing.nextSibling);
      } else {
        wrap.insertBefore(b, wrap.firstChild);
      }
      b.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-cg-chat-cancel-composer-edit]')) {
          clearComposerEditMode(true);
        }
      });
    }
    return b;
  }

  function clearComposerEditMode(clearTa) {
    composerEditingMsgId = 0;
    composerEditingHadCard = false;
    var wrap = qs('#cgBoardChatComposer');
    if (wrap) wrap.classList.remove('cg-board-chat__composer-wrap--editing-msg');
    qsa('.cg-board-chat__msg.is-composer-edit-target').forEach(function (n) {
      n.classList.remove('is-composer-edit-target');
    });
    var banner = qs('#cgBoardChatEditBanner');
    if (banner) banner.hidden = true;
    if (clearTa) {
      var ta = qs('#cgBoardChatTextarea');
      if (ta) {
        ta.value = '';
        ta.style.height = '';
      }
    }
  }

  function beginComposerEditFromRow(row) {
    var id = parseInt(row.getAttribute('data-msg-id') || '0', 10);
    if (id <= 0) return;
    closeAllMsgMenus();
    var ta = qs('#cgBoardChatTextarea');
    if (!ta) return;
    composerEditingMsgId = id;
    composerEditingHadCard = !!row.querySelector('.cg-board-chat__card-chip');
    ta.value = msgBodyCache[id] != null ? msgBodyCache[id] : '';
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    var wrap = qs('#cgBoardChatComposer');
    if (wrap) wrap.classList.add('cg-board-chat__composer-wrap--editing-msg');
    qsa('.cg-board-chat__msg.is-composer-edit-target').forEach(function (n) {
      n.classList.remove('is-composer-edit-target');
    });
    row.classList.add('is-composer-edit-target');
    var banner = ensureComposerEditBanner();
    if (banner) banner.hidden = false;
    try {
      ta.focus({ preventScroll: false });
    } catch (e) {
      ta.focus();
    }
  }

  function cancelAllMsgEdits() {
    clearComposerEditMode(true);
  }

  function unsendRow(row) {
    var id = parseInt(row.getAttribute('data-msg-id') || '0', 10);
    if (!boardId || id <= 0) return;
    if (composerEditingMsgId === id) {
      clearComposerEditMode(true);
    }
    closeAllMsgMenus();
    fetch(api + '?action=chat_unsend', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ board_id: boardId, message_id: id })
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (res.ok && res.data && res.data.success) {
          row.remove();
          delete seenIds[id];
          delete msgBodyCache[id];
          if (composerEditingMsgId === id) {
            clearComposerEditMode(true);
          }
          return;
        }
        var msg = (res.data && res.data.message) ? String(res.data.message) : 'Could not remove message.';
        window.alert(msg);
      })
      .catch(function () {
        window.alert('Network error.');
      });
  }

  function renderTyping(typing) {
    var el = qs('#cgBoardChatTyping');
    if (!el) return;
    var names = (typing || []).map(function (t) { return t.name || 'Someone'; }).filter(Boolean);
    if (!names.length) {
      el.innerHTML = '';
      el.setAttribute('hidden', '');
      return;
    }
    el.removeAttribute('hidden');
    var label = names.length === 1
      ? esc(names[0]) + ' is typing'
      : esc(names.slice(0, 2).join(', ')) + (names.length > 2 ? ' +' + (names.length - 2) : '') + ' typing';
    el.innerHTML = '<span>' + label + '</span><span class="cg-board-chat__typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>';
  }

  function appendMessages(messages, reset) {
    var list = qs('#cgBoardChatMessages');
    if (!list) return;
    var atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
    var prevDay = reset ? '' : (list.getAttribute('data-last-day') || '');
    var pendingEls = [];
    if (reset) {
      pendingEls = qsa('.cg-board-chat__msg.is-pending', list);
      pendingEls.forEach(function (node) { node.remove(); });
      list.innerHTML = '';
      seenIds = Object.create(null);
      lastSinceUpd = 0;
      msgBodyCache = Object.create(null);
      clearComposerEditMode(true);
    }
    var maxId = reset ? 0 : lastAfterId;
    (messages || []).forEach(function (m) {
      var id = parseInt(m.id, 10) || 0;
      if (id <= 0) return;
      bumpSinceUpd(m);

      if (m.is_deleted) {
        var tomb = list.querySelector('.cg-board-chat__msg[data-msg-id="' + id + '"]');
        if (tomb) tomb.remove();
        delete seenIds[id];
        delete msgBodyCache[id];
        if (composerEditingMsgId === id) {
          clearComposerEditMode(true);
        }
        return;
      }

      if (seenIds[id]) {
        var existing = list.querySelector('.cg-board-chat__msg[data-msg-id="' + id + '"]');
        if (existing && !existing.classList.contains('is-pending')) {
          if (composerEditingMsgId > 0 && id === composerEditingMsgId) {
            return;
          }
          setMessageRowContent(existing, m);
        }
        if (id > maxId) maxId = id;
        return;
      }

      seenIds[id] = 1;
      if (id > maxId) maxId = id;
      var dk = dayKey(m.created_at);
      if (dk && dk !== prevDay) {
        prevDay = dk;
        var dayEl = document.createElement('div');
        dayEl.className = 'cg-board-chat__day';
        dayEl.innerHTML = '<span>' + esc(fmtDayLabel(m.created_at)) + '</span>';
        list.appendChild(dayEl);
      }
      var wrap = document.createElement('div');
      setMessageRowContent(wrap, m);
      list.appendChild(wrap);
    });
    list.setAttribute('data-last-day', prevDay);
    lastAfterId = Math.max(lastAfterId, maxId);
    if (reset && pendingEls.length) {
      pendingEls.forEach(function (node) { list.appendChild(node); });
    }
    if (atBottom || reset) {
      list.scrollTop = list.scrollHeight;
    }
  }

  function mergeOptimistic(serverMsg) {
    var list = qs('#cgBoardChatMessages');
    if (!list || !serverMsg) return;
    var cid = serverMsg.client_msg_id;
    if (!cid) return;
    var pending = list.querySelector('.cg-board-chat__msg.is-pending[data-client-id="' + cid + '"]');
    if (pending) pending.remove();
  }

  function syncNow(isFull) {
    if (!boardId) return;
    var after = isFull ? 0 : lastAfterId;
    var url = api + '?action=chat_sync&board_id=' + encodeURIComponent(boardId) + '&after_id=' + encodeURIComponent(String(after)) + '&since_upd=' + encodeURIComponent(String(lastSinceUpd)) + '&typing=' + (pendingTyping || lastTypingSent ? '1' : '0');
    fetch(url, { credentials: 'include', cache: 'no-store' })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try {
            data = text ? JSON.parse(text) : null;
          } catch (e) {
            data = null;
          }
          return { ok: r.ok, status: r.status, data: data };
        });
      })
      .then(function (res) {
        var data = res.data;
        var list = qs('#cgBoardChatMessages');
        if (!data || !data.success) {
          if (isFull && list) {
            var hint = (data && typeof data.message === 'string' && data.message.trim() !== '')
              ? data.message.trim()
              : (!res.ok ? ('Could not load chat (HTTP ' + String(res.status || '') + ').') : 'Could not load chat.');
            list.innerHTML = '<div class="text-muted small p-2" data-cg-chat-sync-error role="status">' + esc(hint) + '</div>';
          }
          return;
        }
        if (list) {
          var errNode = list.querySelector('[data-cg-chat-sync-error]');
          if (errNode) errNode.remove();
        }
        renderOnline(data.online || []);
        renderTyping(data.typing || []);
        var incomingMessages = Array.isArray(data.messages) ? data.messages : [];
        if (!panelOpen && !isFull && chatBackgroundInitialized && incomingMessages.length) {
          openPanel();
          return;
        }
        try {
          if (isFull) {
            appendMessages(incomingMessages, true);
          } else {
            appendMessages(incomingMessages, false);
          }
          chatBackgroundInitialized = true;
        } catch (e) {
          if (isFull && list) {
            list.innerHTML = '<div class="text-muted small p-2" data-cg-chat-sync-error role="status">Could not display messages.</div>';
          }
        }
      })
      .catch(function () {
        if (isFull) {
          var list = qs('#cgBoardChatMessages');
          if (list) {
            list.innerHTML = '<div class="text-muted small p-2" data-cg-chat-sync-error role="status">Network error loading chat.</div>';
          }
        }
      });
  }

  function parseCardPayload(str) {
    if (!str || typeof str !== 'string') return null;
    var raw = str;
    if (raw.indexOf('CINEGRID_KANBAN_CARD:') === 0) {
      raw = raw.slice('CINEGRID_KANBAN_CARD:'.length);
    }
    try {
      var o = JSON.parse(raw);
      if (o && typeof o === 'object' && o.card_id) return o;
    } catch (e) {}
    return null;
  }

  function setAttachedCard(payload) {
    if (!payload || !payload.card_id) return;
    var bid = parseInt(payload.board_id, 10) || 0;
    if (boardId && bid && bid !== boardId) return;
    attachedCard = {
      card_id: parseInt(payload.card_id, 10) || 0,
      board_id: bid || boardId,
      title: (payload.title || '').trim(),
      column_name: (payload.column_name || '').trim(),
      source_url: (payload.source_url || '').trim()
    };
    var row = qs('#cgBoardChatAttachRow');
    var hint = qs('#cgBoardChatDropHint');
    if (!row) return;
    row.innerHTML = '<span class="cg-board-chat__attach-pill"><span>' + esc(attachedCard.title || 'Card') + '</span><button type="button" data-cg-chat-remove-attach aria-label="Remove">&times;</button></span>';
    var btn = row.querySelector('[data-cg-chat-remove-attach]');
    if (btn) btn.addEventListener('click', function () {
      attachedCard = null;
      row.innerHTML = '';
    });
    if (hint) hint.classList.remove('is-active');
  }

  function sendMessage() {
    var ta = qs('#cgBoardChatTextarea');
    var sendBtn = qs('#cgBoardChatSend');
    if (!ta || !boardId) return;
    hideMentionDropdown();

    if (composerEditingMsgId > 0) {
      var editId = composerEditingMsgId;
      var editText = (ta.value || '').trim();
      if (editText === '' && !composerEditingHadCard) return;
      if (sendBtn) sendBtn.disabled = true;
      clearTypingPing();
      fetch(api + '?action=chat_edit', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ board_id: boardId, message_id: editId, body: editText })
      })
        .then(function (r) {
          return r.text().then(function (text) {
            var data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
            return { ok: r.ok, data: data };
          });
        })
        .then(function (res) {
          if (res.ok && res.data && res.data.success && res.data.message) {
            var row = qs('.cg-board-chat__msg[data-msg-id="' + editId + '"]');
            clearComposerEditMode(true);
            if (row) setMessageRowContent(row, res.data.message);
            return;
          }
          var msg = (res.data && res.data.message) ? String(res.data.message) : 'Could not save edit.';
          window.alert(msg);
        })
        .catch(function () {
          window.alert('Network error.');
        })
        .then(function () {
          if (sendBtn) sendBtn.disabled = false;
        });
      return;
    }

    var text = (ta.value || '').trim();
    if (!text && !attachedCard) return;
    var clientId = uuid();
    var mentionedSnap = [];
    var seenM = {};
    mentionPendingIds.forEach(function (uid) {
      uid = parseInt(uid, 10) || 0;
      if (uid > 0 && !seenM[uid]) {
        seenM[uid] = 1;
        mentionedSnap.push(uid);
      }
    });
    var list = qs('#cgBoardChatMessages');
    if (list) {
      var optimistic = document.createElement('div');
      optimistic.className = 'cg-board-chat__msg is-self is-pending';
      optimistic.setAttribute('data-client-id', clientId);
      var dk = dayKey(new Date().toISOString());
      var prevDay = list.getAttribute('data-last-day') || '';
      if (dk && dk !== prevDay) {
        var dayEl = document.createElement('div');
        dayEl.className = 'cg-board-chat__day';
        dayEl.innerHTML = '<span>' + esc(fmtDayLabel(new Date().toISOString())) + '</span>';
        list.appendChild(dayEl);
        list.setAttribute('data-last-day', dk);
      }
      var bubble = esc(text);
      if (attachedCard && attachedCard.card_id) {
        bubble += '<span class="cg-board-chat__card-chip" style="pointer-events:none;">' + esc(attachedCard.title || 'Card') + '<small>' + esc(attachedCard.column_name || '') + '</small></span>';
      }
      optimistic.innerHTML = '<div class="cg-board-chat__msg-meta"><strong>You</strong></div><div class="cg-board-chat__bubble">' + bubble + '</div>';
      list.appendChild(optimistic);
      list.scrollTop = list.scrollHeight;
    }
    if (sendBtn) sendBtn.disabled = true;
    clearTypingPing();
    ta.value = '';
    ta.style.height = '';

    fetch(api + '?action=chat_send', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        board_id: boardId,
        body: text,
        client_msg_id: clientId,
        card_context: attachedCard,
        mentioned_user_ids: mentionedSnap
      })
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try {
            data = text ? JSON.parse(text) : null;
          } catch (e) {
            data = null;
          }
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        var data = res.data;
        if (res.ok && data && data.success && data.message) {
          attachedCard = null;
          mentionPendingIds = [];
          var row = qs('#cgBoardChatAttachRow');
          if (row) row.innerHTML = '';
          mergeOptimistic(data.message);
          appendMessages([data.message], false);
          return;
        }
        var pend = qs('.cg-board-chat__msg.is-pending[data-client-id="' + clientId + '"]');
        if (pend) pend.remove();
      })
      .catch(function () {
        var pend = qs('.cg-board-chat__msg.is-pending[data-client-id="' + clientId + '"]');
        if (pend) pend.remove();
      })
      .then(function () {
        if (sendBtn) sendBtn.disabled = false;
      });
  }

  function onBoardChange(bid) {
    var nextBoard = parseInt(bid, 10) || 0;
    var changed = nextBoard !== boardId;
    if (changed) {
      clearComposerEditMode(true);
      lastSinceUpd = 0;
      lastAfterId = 0;
      chatBackgroundInitialized = false;
    }
    boardId = nextBoard;
    var fab = qs('#cgBoardChatFab');
    var hasBottomNavChat = !!document.getElementById('cgBottomNavChat');
    if (fab) {
      if (boardId) {
        if (hasBottomNavChat) fab.classList.add('is-hidden');
        else fab.classList.remove('is-hidden');
      } else {
        fab.classList.add('is-hidden');
      }
    }
    if (!boardId) {
      stopPoll();
      return;
    }
    if (changed || panelOpen) {
      syncNow(true);
    }
    startPoll();
  }

  function bindDom() {
    var fab = qs('#cgBoardChatFab');
    var back = qs('#cgBoardChatBackdrop');
    var closeBtn = qs('#cgBoardChatClose');
    var ta = qs('#cgBoardChatTextarea');
    var sendBtn = qs('#cgBoardChatSend');
    var wrap = qs('#cgBoardChatComposer');

    if (fab) fab.addEventListener('click', openPanel);
    if (back) back.addEventListener('click', closePanel);
    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closePanel();
      });
    }

    if (ta) {
      ta.addEventListener('input', function () {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
        if ((ta.value || '').trim()) scheduleTypingPing();
        else clearTypingPing();
        maybeShowMentionDropdown();
      });
      ta.addEventListener('blur', function () {
        clearTypingPing();
        setTimeout(function () {
          if (!mentionMouseDownOnDropdown) hideMentionDropdown();
        }, 200);
      });
      ta.addEventListener('keydown', function (e) {
        if (composerEditingMsgId > 0 && e.key === 'Escape') {
          e.preventDefault();
          clearComposerEditMode(true);
          return;
        }
        var ddEl = qs('#cgBoardChatMentionDropdown');
        var menuOpen = ddEl && !ddEl.hidden && mentionListShown.length;
        if (menuOpen) {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionActiveIndex = Math.min(mentionActiveIndex + 1, mentionListShown.length - 1);
            refreshMentionActiveClass();
            return;
          }
          if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionActiveIndex = Math.max(mentionActiveIndex - 1, 0);
            refreshMentionActiveClass();
            return;
          }
          if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            confirmMentionPick();
            return;
          }
          if (e.key === 'Escape') {
            e.preventDefault();
            hideMentionDropdown();
            return;
          }
        }
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });
    }

    var mentionDd = qs('#cgBoardChatMentionDropdown');
    if (mentionDd) {
      mentionDd.addEventListener('mousedown', function (e) {
        mentionMouseDownOnDropdown = true;
        var btn = e.target.closest('.cg-board-chat__mention-item');
        if (btn) {
          e.preventDefault();
          var idx = parseInt(btn.getAttribute('data-mention-index') || '0', 10);
          var mem = mentionListShown[idx];
          if (mem) insertMentionChoice(mem);
        }
        setTimeout(function () { mentionMouseDownOnDropdown = false; }, 0);
      });
    }
    if (sendBtn) sendBtn.addEventListener('click', function () { sendMessage(); });

    if (inlineChat) {
      initBoardChatPanelDrag();
      window.addEventListener('resize', scheduleBoardChatResizeClamp);
    }

    var msgList = qs('#cgBoardChatMessages');
    if (msgList) {
      msgList.addEventListener('click', function (e) {
        var menuBtn = e.target.closest('[data-cg-chat-msg-menu]');
        if (menuBtn && msgList.contains(menuBtn)) {
          e.stopPropagation();
          var actions = menuBtn.closest('.cg-board-chat__msg-actions');
          if (!actions) return;
          var open = !actions.classList.contains('is-open');
          closeAllMsgMenus();
          if (open) {
            actions.classList.add('is-open');
            menuBtn.setAttribute('aria-expanded', 'true');
            var dd = actions.querySelector('[data-cg-chat-msg-dropdown]');
            if (dd) dd.hidden = false;
          }
          return;
        }
        var act = e.target.closest('[data-cg-chat-msg-action]');
        if (act && msgList.contains(act)) {
          e.stopPropagation();
          var row = act.closest('.cg-board-chat__msg');
          if (!row) return;
          var action = act.getAttribute('data-cg-chat-msg-action') || '';
          if (action === 'edit') beginComposerEditFromRow(row);
          else if (action === 'unsend') unsendRow(row);
          return;
        }
        var a = e.target.closest('a.cg-board-chat__card-chip');
        if (!a || !msgList.contains(a)) return;
        var cid = parseInt(a.getAttribute('data-card-id') || '0', 10);
        if (!cid) return;
        if (typeof window.cgKanbanRevealCardLikeSearch === 'function') {
          e.preventDefault();
          window.cgKanbanRevealCardLikeSearch(cid);
        }
      });
    }

    document.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('.cg-board-chat__msg-actions')) return;
      closeAllMsgMenus();
    });

    if (wrap) {
      ['dragenter', 'dragover'].forEach(function (ev) {
        wrap.addEventListener(ev, function (e) {
          if (!e.dataTransfer) return;
          var types = e.dataTransfer.types || [];
          var ok = types.indexOf('application/x-cinegrid-kanban-card') >= 0
            || types.indexOf('text/x-cinegrid-kanban-card') >= 0
            || types.indexOf('application/x-cg-calendar-card-ids') >= 0
            || types.indexOf('text/plain') >= 0;
          if (!ok) return;
          e.preventDefault();
          e.dataTransfer.dropEffect = 'copy';
          var hint = qs('#cgBoardChatDropHint');
          if (hint) hint.classList.add('is-active');
        });
      });
      wrap.addEventListener('dragleave', function (e) {
        if (e.currentTarget.contains(e.relatedTarget)) return;
        var hint = qs('#cgBoardChatDropHint');
        if (hint) hint.classList.remove('is-active');
      });
      wrap.addEventListener('drop', function (e) {
        e.preventDefault();
        var hint = qs('#cgBoardChatDropHint');
        if (hint) hint.classList.remove('is-active');
        var dt = e.dataTransfer;
        if (!dt) return;
        var raw = '';
        try { raw = dt.getData('application/x-cinegrid-kanban-card') || dt.getData('text/x-cinegrid-kanban-card') || ''; } catch (err) {}
        var payload = parseCardPayload(raw);
        if (!payload) {
          try {
            var calRaw = dt.getData('application/x-cg-calendar-card-ids');
            if (calRaw && typeof window.cgCalendarResolveBoardChatCardPayload === 'function') {
              payload = window.cgCalendarResolveBoardChatCardPayload(calRaw);
            }
          } catch (eCal) {}
        }
        if (!payload) {
          try { raw = dt.getData('text/plain') || ''; } catch (e2) {}
          payload = parseCardPayload(raw);
        }
        if (payload) setAttachedCard(payload);
      });
    }

    var portalBtn = document.getElementById('cgKanbanPortalChatBtn');
    if (portalBtn) portalBtn.addEventListener('click', function () {
      openPanel();
    });

    var bottomNavChat = document.getElementById('cgBottomNavChat');
    if (bottomNavChat) bottomNavChat.addEventListener('click', function () {
      var p = qs('#cgBoardChatPanel');
      if (inlineChat && p) {
        if (p.classList.contains('is-open')) {
          closePanel();
        } else {
          openPanel();
        }
        return;
      }
      openPanel();
    });

    var headerBtn = document.getElementById('boardChatPanelBtn');
    if (headerBtn) headerBtn.addEventListener('click', function () {
      openPanel();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (qsa('.cg-board-chat__msg-actions.is-open').length) {
        closeAllMsgMenus();
        return;
      }
      if (composerEditingMsgId > 0) {
        clearComposerEditMode(true);
        return;
      }
      if (panelOpen) closePanel();
    });
  }

  window.cgBoardChatOnBoardLoad = function (bid) {
    onBoardChange(bid);
  };
  window.cgBoardChatOpen = openPanel;
  window.cgBoardChatClose = closePanel;

  /**
   * Kanban card menu: open board chat and attach card context (same fields as drag payload).
   * @param {{ card_id: number, board_id?: number, title?: string, column_name?: string, source_url?: string }} payload
   * @return {boolean}
   */
  window.cgBoardChatAttachCardPayload = function (payload) {
    if (!payload || !(parseInt(payload.card_id, 10) || 0)) return false;
    if (composerEditingMsgId > 0) {
      clearComposerEditMode(true);
    } else {
      clearComposerEditMode(false);
    }
    openPanel();
    setAttachedCard(payload);
    var ta = qs('#cgBoardChatTextarea');
    if (ta) {
      try {
        ta.focus({ preventScroll: false });
      } catch (e) {
        ta.focus();
      }
    }
    return true;
  };

  /** Timeline / calendar / workspace: refresh mention list + chat board after bootstrap. */
  window.cgSyncBoardChatFromPortal = function (members, boardId) {
    window.CG_BOARD_CHAT_MEMBERS = Array.isArray(members) ? members : [];
    if (typeof window.cgBoardChatOnBoardLoad === 'function') {
      window.cgBoardChatOnBoardLoad(parseInt(boardId, 10) || 0);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindDom);
  } else {
    bindDom();
  }
})();
