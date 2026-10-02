(function () {
  function el(id) {
    return document.getElementById(id);
  }
  window.cgPortalLoadingBegin = function (overlayId, delayMs) {
    var node = el(overlayId);
    if (!node) return;
    if (node._cgPlTimer) {
      clearTimeout(node._cgPlTimer);
      node._cgPlTimer = null;
    }
    var d = delayMs == null ? 280 : delayMs;
    node._cgPlTimer = setTimeout(function () {
      node._cgPlTimer = null;
      node.hidden = false;
      node.setAttribute('aria-hidden', 'false');
      node._cgPlVisible = true;
    }, d);
  };
  window.cgPortalLoadingEnd = function (overlayId) {
    var node = el(overlayId);
    if (!node) return;
    if (node._cgPlTimer) {
      clearTimeout(node._cgPlTimer);
      node._cgPlTimer = null;
    }
    if (node._cgPlVisible) {
      node.hidden = true;
      node.setAttribute('aria-hidden', 'true');
      node._cgPlVisible = false;
    }
  };
})();
