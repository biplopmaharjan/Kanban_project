<?php
$cg_public_root = dirname(__DIR__) . '/public_html';
require_once $cg_public_root . '/includes/db.php';
require_once __DIR__ . '/includes/freelance_projects.php';

cg_ensure_freelance_tables();

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(404);
    exit('Invalid link.');
}

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT s.project_id, p.title
    FROM freelance_project_shares s
    JOIN freelance_projects p ON p.id = s.project_id
    WHERE s.token = ?
    LIMIT 1
");
$stmt->execute([$token]);
$share = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$share) {
    http_response_code(404);
    exit('Link expired or not found.');
}

$project_id = (int)$share['project_id'];
$board_id = (int)($_GET['board_id'] ?? 0);
if ($board_id <= 0) {
    http_response_code(400);
    exit('Invalid board link.');
}

$stmt = $pdo->prepare("SELECT id, name FROM kanban_boards WHERE id = ? AND project_id = ? LIMIT 1");
$stmt->execute([$board_id, $project_id]);
$board = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$board) {
    http_response_code(404);
    exit('Board not found for this share token.');
}

$month = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$stmt = $pdo->prepare("
    SELECT
      k.id,
      k.title,
      k.description,
      k.start_date,
      k.due_date,
      k.start_time,
      k.due_time,
      k.progress,
      k.priority,
      k.links,
      k.attachments,
      c.name AS column_name
    FROM kanban_cards k
    JOIN kanban_columns c ON c.id = k.column_id
    WHERE c.board_id = ?
      AND COALESCE(k.is_trashed, 0) = 0
      AND (k.start_date IS NOT NULL OR k.due_date IS NOT NULL)
    ORDER BY COALESCE(k.start_date, k.due_date) ASC, k.id ASC
");
try {
    $stmt->execute([$board_id]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    // Older DBs without is_trashed: fall back without trash filter.
    $stmt = $pdo->prepare("
        SELECT
          k.id,
          k.title,
          k.description,
          k.start_date,
          k.due_date,
          k.start_time,
          k.due_time,
          k.progress,
          k.priority,
          k.links,
          k.attachments,
          c.name AS column_name
        FROM kanban_cards k
        JOIN kanban_columns c ON c.id = k.column_id
        WHERE c.board_id = ?
          AND (k.start_date IS NOT NULL OR k.due_date IS NOT NULL)
        ORDER BY COALESCE(k.start_date, k.due_date) ASC, k.id ASC
    ");
    $stmt->execute([$board_id]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
require_once __DIR__ . '/includes/kanban_attachment_access.php';
foreach ($cards as &$cardRow) {
    $rawAtt = $cardRow['attachments'] ?? null;
    $decoded = [];
    if (is_string($rawAtt) && trim($rawAtt) !== '') {
        $tmp = json_decode($rawAtt, true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    } elseif (is_array($rawAtt)) {
        $decoded = $rawAtt;
    }
    $sanitized = [];
    foreach ($decoded as $att) {
        if (is_string($att)) {
            $path = trim($att);
            if ($path !== '') {
                $sanitized[] = ['path' => $path, 'name' => basename($path), 'protected' => false];
            }
            continue;
        }
        if (!is_array($att)) {
            continue;
        }
        $path = isset($att['path']) ? trim((string)$att['path']) : '';
        if ($path === '') {
            continue;
        }
        $sanitized[] = [
            'path' => $path,
            'name' => (string)($att['name'] ?? basename($path)),
            'protected' => !empty($att['password_hash']),
        ];
    }
    $cardRow['attachments'] = $sanitized === [] ? null : json_encode($sanitized);
}
unset($cardRow);
$cg_static_origin = 'https://kanban.cinegrid.net';
$cg_preview_api = $cg_static_origin . '/api/kanban_image_preview.php';
$cg_attachment_api = $cg_static_origin . '/api/kanban_attachment.php';
$cg_fk_api = $cg_static_origin . '/api/freelance_kanban.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Client Calendar - <?php echo htmlspecialchars($board['name'] ?: 'Board', ENT_QUOTES, 'UTF-8'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #eef2f7; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .cg-calendar-container { max-width: 1600px; margin: 0 auto; padding: 16px 24px; }
    .cg-calendar-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
    .cg-calendar-month-nav { display: flex; align-items: center; gap: 10px; }
    .cg-calendar-month {
      font-size: 1.2rem;
      font-weight: 800;
      color: #0f172a;
      min-width: 140px;
      text-align: center;
    }
    .cg-calendar-shell {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(15,23,42,0.08);
      border-radius: 24px;
      padding: 20px;
      box-shadow: 0 20px 40px rgba(15,23,42,0.08);
      backdrop-filter: blur(10px);
    }
    .cg-calendar-grid-wrap {
      border: 1px solid rgba(15,23,42,0.08);
      border-radius: 20px;
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
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #475569;
    }
    .cg-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
    .cg-calendar-day {
      min-height: 150px;
      padding: 12px;
      border-right: 1px solid rgba(15,23,42,0.06);
      border-bottom: 1px solid rgba(15,23,42,0.06);
      background: rgba(255,255,255,0.75);
      display: flex;
      flex-direction: column;
      gap: 7px;
    }
    .cg-calendar-day:nth-child(7n) { border-right: none; }
    .cg-calendar-day.is-other-month { background: rgba(248,250,252,0.88); }
    .cg-calendar-day-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .cg-calendar-day-number { font-weight: 800; color: #0f172a; font-size: 13px; }
    .cg-calendar-day.is-other-month .cg-calendar-day-number { color: #94a3b8; }
    .cg-calendar-day-count { font-size: 11px; font-weight: 700; color: #94a3b8; }
    .cg-calendar-day-items { display:flex; flex-direction:column; gap:7px; flex: 1 1 auto; min-height: 0; }
    .cg-calendar-day-minis { margin-top: auto; display:flex; flex-direction:column; gap:4px; }
    .cg-calendar-empty { font-size: 12px; color: #cbd5e1; font-weight: 700; padding-top: 2px; }
    .cg-calendar-chip {
      --chip-accent: #6366f1;
      border-radius: 12px;
      padding: 8px 10px;
      border: 1px solid color-mix(in srgb, var(--chip-accent) 26%, white);
      background: color-mix(in srgb, var(--chip-accent) 10%, white);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.45);
      color: #0f172a;
      line-height: 1.25;
      cursor: pointer;
      transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    .cg-calendar-chip:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(15,23,42,0.08), inset 0 1px 0 rgba(255,255,255,.45);
    }
    .cg-calendar-chip--mini {
      padding: 3px 8px;
      border-radius: 8px;
    }
    .cg-calendar-chip--mini .cg-calendar-chip-title {
      font-size: 11px;
      line-height: 1.2;
    }
    .client-detail-modal-content { border-radius: 16px; border: 1px solid rgba(15,23,42,0.08); }
    .client-detail-modal-content .bubble-title { font-weight: 800; color: #0f172a; margin-bottom: 10px; font-size: 18px; }
    .client-detail-modal-content .bubble-meta { color: #64748b; font-size: 13px; margin-bottom: 8px; }
    .client-detail-modal-content .bubble-desc { color: #334155; margin-bottom: 14px; white-space: pre-wrap; line-height: 1.5; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html { white-space: normal; word-break: break-word; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html p:last-child { margin-bottom: 0; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html a { color: #0d6efd; word-break: break-all; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html ul.cg-desc-checklist { list-style: none; padding-left: 0; margin: 0.35rem 0 0.5rem; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task { position: relative; padding-left: 1.5rem; min-height: 1.35rem; margin: 0.2rem 0; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task:before { content: ''; position: absolute; left: 0; top: 0.2rem; width: 1rem; height: 1rem; border: 2px solid #94a3b8; border-radius: 4px; box-sizing: border-box; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task[data-checked="1"]:before { background: #10b981; border-color: #10b981; }
    .client-detail-modal-content .bubble-desc.bubble-desc--html li.cg-desc-task[data-checked="1"]:after { content: '✓'; position: absolute; left: 0.18rem; top: 0.05rem; color: #fff; font-size: 0.72rem; font-weight: 700; }
    .client-detail-modal-content .bubble-progress-wrap { margin-bottom: 14px; display: flex; align-items: center; gap: 10px; }
    .client-detail-modal-content .bubble-progress-wrap .bubble-progress-bar { flex: 1; min-width: 0; }
    .client-detail-modal-content .bubble-progress-bar { height: 8px; background: rgba(15,23,42,0.08); border-radius: 4px; overflow: hidden; }
    .client-detail-modal-content .bubble-progress-fill { height: 100%; border-radius: 4px; }
    .client-detail-modal-content .bubble-progress-fill.bubble-progress-done { background: #10b981; }
    .client-detail-modal-content .bubble-progress-fill.bubble-progress-doing { background: #f59e0b; }
    .client-detail-modal-content .bubble-progress-fill.bubble-progress-todo { background: #6366f1; }
    .client-detail-modal-content .bubble-section-title { font-weight: 700; margin-top: 12px; margin-bottom: 6px; color: #0f172a; font-size: 13px; }
    .client-detail-modal-content .bubble-link { display: block; margin-bottom: 6px; text-decoration: none; color: #0d6efd; font-size: 13px; word-break: break-all; }
    .client-detail-modal-content .bubble-link:hover { color: #c82333; text-decoration: underline; }
    .client-detail-youtube-wrap {
      position: relative;
      width: 100%;
      padding-bottom: 56.25%;
      margin: 10px 0 14px;
      border-radius: 12px;
      overflow: hidden;
      background: #0f172a;
    }
    .client-detail-youtube {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      border: 0;
    }
    .client-detail-attachments-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: 10px;
      margin-top: 8px;
    }
    .client-detail-attachment-image {
      display: block;
      border-radius: 10px;
      overflow: hidden;
      border: 1px solid rgba(15,23,42,0.1);
      background: #f8fafc;
    }
    .client-detail-attachment-image img {
      width: 100%;
      height: 120px;
      object-fit: cover;
      display: block;
    }
    .client-detail-file-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      margin: 0 8px 8px 0;
      border-radius: 8px;
      border: 1px solid rgba(15,23,42,0.08);
      background: #f8fafc;
      text-decoration: none;
      color: #334155;
      font-size: 12px;
      font-weight: 600;
    }
    .client-detail-file-link:hover {
      color: #0d6efd;
      border-color: rgba(13,110,253,0.25);
      text-decoration: none;
    }
    .cg-calendar-chip-title { font-weight: 700; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cg-calendar-chip-meta { margin-top: 3px; color: #475569; font-size: 11px; }
    .cg-calendar-chip-time { margin-top: 2px; color: #64748b; font-size: 10px; font-weight: 700; }
    /* Password modal over card details — second backdrop + dim card modal */
    #clientAttachmentPasswordModal {
      z-index: 1080 !important;
    }
    .modal-backdrop.cg-stacked-modal-backdrop {
      z-index: 1075 !important;
    }
    #clientCardDetailModal.cg-under-stacked-modal {
      pointer-events: none;
    }
    #clientCardDetailModal.cg-under-stacked-modal .modal-content {
      filter: brightness(0.88);
    }
  </style>
</head>
<body>
  <div class="cg-calendar-container">
    <div class="cg-calendar-toolbar">
      <div class="cg-calendar-month-nav">
        <button class="btn btn-outline-dark btn-sm" type="button" id="calendarPrevMonthBtn" aria-label="Previous month">&lsaquo;</button>
        <div class="cg-calendar-month" id="monthLabel"></div>
        <button class="btn btn-outline-dark btn-sm" type="button" id="calendarNextMonthBtn" aria-label="Next month">&rsaquo;</button>
      </div>
      <div class="small text-muted">Read-only shared view</div>
    </div>
    <div class="cg-calendar-shell">
      <div class="mb-3">
        <h5 class="mb-1"><?php echo htmlspecialchars($board['name'] ?: 'Board', ENT_QUOTES, 'UTF-8'); ?></h5>
        <div class="small text-muted">Client calendar preview</div>
      </div>
      <div class="cg-calendar-grid-wrap">
        <div class="cg-calendar-weekdays" id="calendarWeekdays"></div>
        <div class="cg-calendar-grid" id="calendarGrid"></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="clientCardDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content client-detail-modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Content Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="clientCardDetailModalBody"></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="clientAttachmentPasswordModal" tabindex="-1" aria-labelledby="clientAttachmentPasswordModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content" style="border-radius: 16px;">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="clientAttachmentPasswordModalTitle">Attachment password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-2">
          <p class="text-muted small mb-3" id="clientAttachmentPasswordModalHint"></p>
          <div class="mb-3">
            <label class="form-label fw-semibold small" for="clientAttachmentPasswordInput">Password</label>
            <input type="password" class="form-control" id="clientAttachmentPasswordInput" autocomplete="current-password" />
          </div>
          <div class="text-danger small mt-2" id="clientAttachmentPasswordModalError" style="display:none;"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="clientAttachmentPasswordModalSubmit">View</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const TOKEN = <?php echo json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const BOARD_ID = <?php echo json_encode($board_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CARDS = <?php echo json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let MONTH = <?php echo json_encode($month, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const ACCENTS = ['#6366f1', '#f59e0b', '#10b981', '#0ea5e9', '#ef4444', '#8b5cf6', '#f97316', '#14b8a6'];
    const STATIC_ORIGIN = <?php echo json_encode($cg_static_origin, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const PREVIEW_API = <?php echo json_encode($cg_preview_api, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const ATTACHMENT_API = <?php echo json_encode($cg_attachment_api, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const FK_API = <?php echo json_encode($cg_fk_api, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let clientAttachmentPasswordState = { cardId: 0, path: '', name: '' };
    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }
    function clientCalendarSanitizeDescriptionHtml(html) {
      const s = String(html || '').replace(/<script\b[\s\S]*?<\/script>/gi, '').trim();
      if (!s) return '';
      const tpl = document.createElement('template');
      tpl.innerHTML = s;
      const root = tpl.content;
      root.querySelectorAll('script, style, iframe, object, embed, link, meta, form, input, textarea, select, button').forEach((n) => n.remove());
      root.querySelectorAll('*').forEach((el) => {
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
      root.querySelectorAll('a').forEach((a) => {
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
      root.querySelectorAll('ul').forEach((ul) => {
        const cls = (ul.getAttribute('class') || '').trim();
        if (cls.includes('cg-desc-checklist')) ul.setAttribute('class', 'cg-desc-checklist');
        else ul.removeAttribute('class');
      });
      root.querySelectorAll('li').forEach((li) => {
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
      root.querySelectorAll('span').forEach((span) => {
        const cls = (span.getAttribute('class') || '').trim();
        if (cls === 'cg-desc-emoji') span.setAttribute('class', 'cg-desc-emoji');
        else span.removeAttribute('class');
      });
      const wrap = document.createElement('div');
      wrap.appendChild(root.cloneNode(true));
      return wrap.innerHTML.trim();
    }
    function clientCalendarDescriptionLooksLikeHtml(raw) {
      const s = String(raw || '').trim();
      if (!s) return false;
      return /<[a-z][\s\S]*>/i.test(s) || /<\/?[a-z][\s\S]*?>/i.test(s);
    }
    function clientCalendarFormatDescription(desc) {
      const raw = String(desc || '').trim();
      if (!raw) return '';
      if (clientCalendarDescriptionLooksLikeHtml(raw)) {
        const clean = clientCalendarSanitizeDescriptionHtml(raw);
        if (String(clean || '').replace(/\s|&nbsp;/gi, '').length) return clean;
        const plain = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (plain) return '<p>' + escapeHtml(plain) + '</p>';
        return '';
      }
      return '<p>' + escapeHtml(raw).replace(/\n/g, '<br>') + '</p>';
    }
    function pad2(n){ return String(n).padStart(2, '0'); }
    function dateKey(d){ return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; }
    function parseDate(s){
      if (!s) return null;
      const m = String(s).trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!m) return null;
      const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
      return isNaN(d.getTime()) ? null : d;
    }
    /** Inclusive YYYY-MM-DD keys from start_date through due_date. Single-date cards return one key. */
    function itemDates(card){
      const start = parseDate(card.start_date);
      const due = parseDate(card.due_date);
      if (start && due) {
        let a = start;
        let b = due;
        if (a.getTime() > b.getTime()) {
          const t = a; a = b; b = t;
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
      const anchor = start || due;
      return anchor ? [dateKey(anchor)] : [];
    }
    /** Chip layout role for a day in the card's date span: start | end | both | middle. */
    function chipSpanRole(card, dayKey){
      const start = parseDate(card.start_date);
      const due = parseDate(card.due_date);
      if (start && due) {
        let lo = dateKey(start);
        let hi = dateKey(due);
        if (lo > hi) { const t = lo; lo = hi; hi = t; }
        if (dayKey === lo && dayKey === hi) return 'both';
        if (dayKey === lo) return 'start';
        if (dayKey === hi) return 'end';
        return 'middle';
      }
      return 'both';
    }
    /** Display title: start keeps title; middle swaps leading "Start"/"Start of" → "On-Going"; end → "End of". */
    function chipDisplayTitle(card, spanRole){
      const raw = String(card.title || '').trim() || 'Untitled';
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
    function accentForCardId(id){
      const num = Number(id || 0);
      const idx = Math.abs(num) % ACCENTS.length;
      return ACCENTS[idx];
    }
    function chipTime(card){
      const t = String(card.start_time || card.due_time || '').trim();
      if (!t) return '';
      const m = t.match(/^(\d{1,2}):(\d{2})/);
      if (!m) return t;
      let h = Number(m[1]); const min = m[2];
      const suffix = h >= 12 ? 'PM' : 'AM';
      h = ((h + 11) % 12) + 1;
      return `${h}:${min} ${suffix}`;
    }
    function monthLabel(monthStr){
      const [y, m] = monthStr.split('-').map(Number);
      const d = new Date(y, m - 1, 1);
      return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    }
    function findCard(cardId) {
      return CARDS.find((card) => Number(card.id) === Number(cardId)) || null;
    }
    function formatCardTime(t) {
      const raw = String(t || '').trim();
      if (!raw) return '';
      const m = raw.match(/^(\d{1,2}):(\d{2})/);
      if (!m) return raw;
      let h = Number(m[1]);
      const suffix = h >= 12 ? 'PM' : 'AM';
      h = ((h + 11) % 12) + 1;
      return `${h}:${m[2]} ${suffix}`;
    }
    function formatDateLabel(value) {
      const d = parseDate(value);
      return d ? d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : String(value || '');
    }
    function formatDateRange(card) {
      const start = String(card.start_date || '').trim();
      const due = String(card.due_date || '').trim();
      const dueTime = formatCardTime(card.due_time);
      const dueSuffix = dueTime ? ` ${dueTime}` : '';
      if (start && due) return `${formatDateLabel(start)} - ${formatDateLabel(due)}${dueSuffix}`;
      if (due) return `${formatDateLabel(due)}${dueSuffix}`;
      if (start) return formatDateLabel(start);
      return '';
    }
    function parseJsonList(value) {
      if (Array.isArray(value)) return value;
      if (typeof value !== 'string' || !value.trim()) return [];
      try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
      } catch (err) {
        return [];
      }
    }
    function resolvePublicUrl(path) {
      const raw = String(path || '').trim();
      if (!raw) return '';
      if (/^https?:\/\//i.test(raw)) {
        if (/^https?:\/\/(www\.)?cinegrid\.net\/uploads\//i.test(raw)) {
          try {
            const u = new URL(raw);
            return STATIC_ORIGIN + u.pathname + u.search + u.hash;
          } catch (err) {}
        }
        return raw;
      }
      const rel = raw.indexOf('/') === 0 ? raw : `/${raw}`;
      if (/^\/uploads\/kanban_attachments\//i.test(rel)) {
        return STATIC_ORIGIN + rel;
      }
      return STATIC_ORIGIN ? STATIC_ORIGIN + rel : rel;
    }
    function getAttachmentImageUrl(path, width) {
      const raw = String(path || '').trim();
      if (!raw) return '';
      if (/^https?:\/\//i.test(raw)) return resolvePublicUrl(raw);
      const norm = raw.replace(/^\/+/, '');
      if (/^uploads\/kanban_attachments\//i.test(norm)) {
        const u = new URL(PREVIEW_API);
        u.searchParams.set('path', norm);
        u.searchParams.set('w', String(width || 720));
        return u.href;
      }
      return resolvePublicUrl(raw);
    }
    function isImagePath(path) {
      return /\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(String(path || ''));
    }
    function isVideoPath(path) {
      return /\.(mp4|webm|mov|m4v)$/i.test(String(path || ''));
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
      } catch (err) {}
      return '';
    }
    function isAttachmentProtected(att) {
      return !!(att && (att.protected || att.password_hash));
    }
    function getAttachmentServeUrl(path, cardId, accessToken) {
      const u = new URL(ATTACHMENT_API, window.location.href);
      u.searchParams.set('card_id', String(cardId || 0));
      u.searchParams.set('path', String(path || '').replace(/^\//, ''));
      u.searchParams.set('share_token', TOKEN);
      if (accessToken) u.searchParams.set('token', String(accessToken));
      return u.href;
    }
    function canOpenAttachmentDirectly(att) {
      // Client share viewers are never board owners — always require password for protected files.
      return !isAttachmentProtected(att);
    }
    function wireClientStackedModalBackdrop(modalSelector, underModalSelector) {
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
    function openClientAttachmentPasswordModal(cardId, path, name) {
      clientAttachmentPasswordState = {
        cardId: cardId || 0,
        path: String(path || '').replace(/^\//, ''),
        name: name || 'Attachment',
      };
      const hintEl = document.getElementById('clientAttachmentPasswordModalHint');
      const pwInput = document.getElementById('clientAttachmentPasswordInput');
      const errEl = document.getElementById('clientAttachmentPasswordModalError');
      if (hintEl) hintEl.textContent = `This file is protected. Enter the password to view "${clientAttachmentPasswordState.name}".`;
      if (pwInput) pwInput.value = '';
      if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
      const modalEl = document.getElementById('clientAttachmentPasswordModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true }).show();
        setTimeout(() => pwInput && pwInput.focus(), 300);
      }
    }
    async function submitClientAttachmentPassword() {
      const { cardId, path } = clientAttachmentPasswordState;
      const pwInput = document.getElementById('clientAttachmentPasswordInput');
      const errEl = document.getElementById('clientAttachmentPasswordModalError');
      const submitBtn = document.getElementById('clientAttachmentPasswordModalSubmit');
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
        fd.append('share_token', TOKEN);
        const res = await fetch(FK_API, { method: 'POST', body: fd, credentials: 'omit' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Incorrect password');
        const modalEl = document.getElementById('clientAttachmentPasswordModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          const inst = bootstrap.Modal.getInstance(modalEl);
          if (inst) inst.hide();
        }
        window.open(getAttachmentServeUrl(path, cardId, data.access_token || ''), '_blank', 'noopener,noreferrer');
      } catch (e) {
        if (errEl) { errEl.textContent = e.message || 'Incorrect password'; errEl.style.display = 'block'; }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    }
    function parseAttachments(value) {
      return parseJsonList(value).map((att) => {
        if (typeof att === 'string') {
          const path = att.trim();
          return path ? { path, name: path.split('/').pop() || 'Attachment', protected: false } : null;
        }
        if (att && typeof att === 'object') {
          const path = String(att.path || '').trim();
          if (!path) return null;
          return {
            path,
            name: String(att.name || path.split('/').pop() || 'Attachment'),
            protected: !!(att.protected || att.password_hash),
          };
        }
        return null;
      }).filter(Boolean);
    }
    function partitionLinks(links) {
      const youtube = [];
      const other = [];
      links.forEach((url) => {
        const id = extractYoutubeVideoId(url);
        if (id) youtube.push({ url, id });
        else other.push(url);
      });
      return { youtube, other };
    }
    function buildYoutubeSection(youtubeLinks) {
      if (!youtubeLinks.length) return '';
      return `<div class="bubble-section-title">YouTube</div>${youtubeLinks.map(({ id }) => {
        const src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?rel=0&modestbranding=1&playsinline=1`;
        return `<div class="client-detail-youtube-wrap"><iframe class="client-detail-youtube" src="${src}" title="YouTube video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>`;
      }).join('')}`;
    }
    function buildAttachmentsSection(attachments, cardId) {
      if (!attachments.length) return '';
      const images = [];
      const files = [];
      attachments.forEach((att) => {
        if (isImagePath(att.path)) images.push(att);
        else files.push(att);
      });
      let html = '<div class="bubble-section-title">Attachments</div>';
      if (images.length) {
        html += `<div class="client-detail-attachments-grid">${images.map((att) => {
          const previewUrl = getAttachmentImageUrl(att.path, 720);
          const lock = isAttachmentProtected(att) ? ' 🔒' : '';
          if (canOpenAttachmentDirectly(att)) {
            const fullUrl = resolvePublicUrl(att.path);
            return `<a href="${escapeHtml(fullUrl)}" target="_blank" rel="noopener noreferrer" class="client-detail-attachment-image" title="${escapeHtml(att.name)}${lock}"><img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(att.name)}" loading="lazy"></a>`;
          }
          return `<a href="#" class="client-detail-attachment-image client-protected-attachment" data-card-id="${cardId}" data-att-path="${escapeHtml(String(att.path).replace(/^\//, ''))}" data-att-name="${escapeHtml(att.name)}" title="${escapeHtml(att.name)} (password required)"><img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(att.name)}" loading="lazy" style="opacity:.85;"></a>`;
        }).join('')}</div>`;
      }
      if (files.length) {
        html += files.map((att) => {
          const label = isVideoPath(att.path) ? 'Video' : 'File';
          const lock = isAttachmentProtected(att) ? ' 🔒' : '';
          if (canOpenAttachmentDirectly(att)) {
            const fullUrl = resolvePublicUrl(att.path);
            return `<a href="${escapeHtml(fullUrl)}" target="_blank" rel="noopener noreferrer" class="client-detail-file-link"><span>${label}${lock}</span><span>${escapeHtml(att.name)}</span></a>`;
          }
          return `<a href="#" class="client-detail-file-link client-protected-attachment" data-card-id="${cardId}" data-att-path="${escapeHtml(String(att.path).replace(/^\//, ''))}" data-att-name="${escapeHtml(att.name)}"><span>${label}${lock}</span><span>${escapeHtml(att.name)}</span></a>`;
        }).join('');
      }
      return html;
    }
    function buildLinksSection(links) {
      if (!links.length) return '';
      return `<div class="bubble-section-title">Links</div>${links.map((url) => `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="bubble-link">${escapeHtml(url)}</a>`).join('')}`;
    }
    function buildCardDetailContent(card) {
      const title = escapeHtml(card.title || 'Untitled');
      const desc = String(card.description || '').trim();
      const descBody = clientCalendarFormatDescription(desc);
      const descHtml = descBody ? `<div class="bubble-desc bubble-desc--html">${descBody}</div>` : '';
      const columnHtml = card.column_name
        ? `<div class="bubble-meta"><strong>Column:</strong> ${escapeHtml(card.column_name)}</div>`
        : '';
      const dateRange = formatDateRange(card);
      const dateHtml = dateRange ? `<div class="bubble-meta"><strong>Date:</strong> ${escapeHtml(dateRange)}</div>` : '';
      const priority = String(card.priority || '').trim();
      const priorityHtml = priority ? `<div class="bubble-meta"><strong>Priority:</strong> ${escapeHtml(priority)}</div>` : '';
      const progress = Math.max(0, Math.min(100, Number(card.progress || 0)));
      const progressClass = progress >= 100 ? 'bubble-progress-done' : (progress > 50 ? 'bubble-progress-doing' : 'bubble-progress-todo');
      const progressHtml = `<div class="bubble-progress-wrap"><div class="bubble-progress-bar"><div class="bubble-progress-fill ${progressClass}" style="width:${progress}%"></div></div><span class="small fw-bold">${progress}%</span></div>`;
      const allLinks = parseJsonList(card.links).map((url) => String(url || '').trim()).filter(Boolean);
      const { youtube, other } = partitionLinks(allLinks);
      const attachments = parseAttachments(card.attachments);
      const youtubeHtml = buildYoutubeSection(youtube);
      const attachmentsHtml = buildAttachmentsSection(attachments, Number(card.id) || 0);
      const linksHtml = buildLinksSection(other);
      return `<div class="bubble-title">${title}</div>${columnHtml}${dateHtml}${priorityHtml}${descHtml}${progressHtml}${youtubeHtml}${attachmentsHtml}${linksHtml}`;
    }
    function openCardDetailModal(cardId) {
      const card = findCard(cardId);
      const body = document.getElementById('clientCardDetailModalBody');
      const modalEl = document.getElementById('clientCardDetailModal');
      if (!card || !body || !modalEl) return;
      body.innerHTML = buildCardDetailContent(card);
      body.querySelectorAll('.client-protected-attachment').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          openClientAttachmentPasswordModal(
            parseInt(el.getAttribute('data-card-id') || '0', 10),
            el.getAttribute('data-att-path') || '',
            el.getAttribute('data-att-name') || 'Attachment'
          );
        });
      });
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    }
    function stepMonth(delta){
      const [y, m] = MONTH.split('-').map(Number);
      const d = new Date(y, m - 1, 1);
      d.setMonth(d.getMonth() + delta);
      MONTH = `${d.getFullYear()}-${pad2(d.getMonth()+1)}`;
      const q = new URLSearchParams(window.location.search);
      q.set('token', TOKEN);
      q.set('board_id', String(BOARD_ID));
      q.set('month', MONTH);
      window.location.search = q.toString();
    }

    function render(){
      document.getElementById('monthLabel').textContent = monthLabel(MONTH);
      const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      document.getElementById('calendarWeekdays').innerHTML = weekdays.map((w) => `<div class="cg-calendar-weekday">${w}</div>`).join('');

      const [y, m] = MONTH.split('-').map(Number);
      const monthStart = new Date(y, m - 1, 1);
      const gridStart = new Date(monthStart);
      gridStart.setDate(gridStart.getDate() - gridStart.getDay());

      const byDate = {};
      CARDS.forEach((card) => {
        itemDates(card).forEach((k) => {
          if (!byDate[k]) byDate[k] = [];
          byDate[k].push(card);
        });
      });

      let html = '';
      for (let i = 0; i < 42; i++) {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const inMonth = d.getMonth() === (m - 1);
        const list = byDate[dateKey(d)] || [];
        html += `<div class="cg-calendar-day ${inMonth ? '' : 'is-other-month'}">
          <div class="cg-calendar-day-head">
            <span class="cg-calendar-day-number">${d.getDate()}</span>
            <span class="cg-calendar-day-count">${list.length ? `${list.length} item${list.length === 1 ? '' : 's'}` : ''}</span>
          </div>
          <div class="cg-calendar-day-items">
            ${list.length ? (() => {
              const shown = list.slice(0, 4);
              const fullParts = [];
              const miniParts = [];
              shown.forEach((it) => {
                const spanRole = chipSpanRole(it, dateKey(d));
                const isMini = spanRole === 'middle';
                const displayTitle = chipDisplayTitle(it, spanRole);
                if (isMini) {
                  miniParts.push(`
              <div class="cg-calendar-chip cg-calendar-chip--mini" style="--chip-accent:${accentForCardId(it.id)}" data-card-id="${Number(it.id)}" role="button" tabindex="0" aria-label="View details for ${escapeHtml(displayTitle)}" title="${escapeHtml(displayTitle)}">
                <div class="cg-calendar-chip-title">${escapeHtml(displayTitle)}</div>
              </div>`);
                  return;
                }
                fullParts.push(`
              <div class="cg-calendar-chip" style="--chip-accent:${accentForCardId(it.id)}" data-card-id="${Number(it.id)}" role="button" tabindex="0" aria-label="View details for ${escapeHtml(displayTitle)}">
                <div class="cg-calendar-chip-title">${escapeHtml(displayTitle)}</div>
                ${chipTime(it) ? `<div class="cg-calendar-chip-time">${escapeHtml(chipTime(it))}</div>` : ''}
                <div class="cg-calendar-chip-meta">${escapeHtml(it.column_name || '')}</div>
              </div>`);
              });
              return fullParts.join('')
                + (miniParts.length ? `<div class="cg-calendar-day-minis">${miniParts.join('')}</div>` : '')
                + (list.length > 4 ? `<div class="small text-muted">+${list.length - 4} more</div>` : '');
            })() : '<div class="cg-calendar-empty">No posts</div>'}
          </div>
        </div>`;
      }
      document.getElementById('calendarGrid').innerHTML = html;
    }

    document.getElementById('calendarPrevMonthBtn').addEventListener('click', () => stepMonth(-1));
    document.getElementById('calendarNextMonthBtn').addEventListener('click', () => stepMonth(1));
    const clientAttPwSubmit = document.getElementById('clientAttachmentPasswordModalSubmit');
    const clientAttPwInput = document.getElementById('clientAttachmentPasswordInput');
    wireClientStackedModalBackdrop('#clientAttachmentPasswordModal', '#clientCardDetailModal');
    if (clientAttPwSubmit) clientAttPwSubmit.addEventListener('click', () => { submitClientAttachmentPassword(); });
    if (clientAttPwInput) {
      clientAttPwInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          submitClientAttachmentPassword();
        }
      });
    }
    document.getElementById('calendarGrid').addEventListener('click', (event) => {
      const chip = event.target.closest('[data-card-id]');
      if (!chip) return;
      openCardDetailModal(chip.getAttribute('data-card-id'));
    });
    document.getElementById('calendarGrid').addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      const chip = event.target.closest('[data-card-id]');
      if (!chip) return;
      event.preventDefault();
      openCardDetailModal(chip.getAttribute('data-card-id'));
    });
    render();
  </script>
</body>
</html>
