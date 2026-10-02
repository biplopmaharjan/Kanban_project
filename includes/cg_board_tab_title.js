(function () {
  if (typeof window.__cgBoardTabTitleDefault === 'undefined') {
    window.__cgBoardTabTitleDefault = document.title;
  }
  var SITE = 'CineGrid';
  var isKanbanPortalHost = /^(www\.)?kanban\.cinegrid\.net$/i.test(window.location.hostname || '');
  /** Portal shell (cg_kanban_portal_header.php) defines this first with /kanban?board_id=…; do not replace. */
  if (typeof window.cgPortalKanbanBoardHref !== 'function') {
    window.cgPortalKanbanBoardHref = function (boardId, cardId) {
      var bid = boardId != null ? String(boardId).trim() : '';
      var cid = cardId != null ? String(cardId).trim() : '';
      if (isKanbanPortalHost) {
        var q = new URLSearchParams();
        if (bid) q.set('board_id', bid);
        if (cid) q.set('card_id', cid);
        var s = q.toString();
        return s ? ('/kanban?' + s) : '/boards';
      }
      if (cid && bid) {
        return 'kanban.php?board_id=' + encodeURIComponent(bid) + '&card_id=' + encodeURIComponent(cid);
      }
      if (bid) return 'kanban.php?board_id=' + encodeURIComponent(bid);
      return 'kanban.php';
    };
  }
  window.cgSetBoardTabTitle = function (boardName, viewLabel) {
    var name = boardName != null ? String(boardName).trim() : '';
    var view = viewLabel != null ? String(viewLabel).trim() : '';
    if (!name) {
      document.title = window.__cgBoardTabTitleDefault || document.title;
      return;
    }
    if (isKanbanPortalHost) {
      document.title = view ? (name + ' - ' + view) : name;
      return;
    }
    document.title = view ? (name + ' - ' + view + ' - ' + SITE) : (name + ' - ' + SITE);
  };
})();
