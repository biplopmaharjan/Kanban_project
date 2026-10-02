<?php
/**
 * Kanban portal settings — signed-in devices & persistent login (same user_sessions as cinegrid.net).
 */
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
require_once $cg_public_root . '/includes/security.php';
require_once __DIR__ . '/includes/freelance_projects.php';

cg_require_freelancer_or_linked();

$settings_csrf = CSRFProtection::generateToken();
$stay_signed_days = function_exists('cgPersistentSessionTtlSeconds')
    ? (int) round(cgPersistentSessionTtlSeconds() / 86400)
    : 30;
$has_persistent_cookie = defined('CG_SESSION_COOKIE') && !empty($_COOKIE[CG_SESSION_COOKIE]);
$has_legacy_remember = !empty($_COOKIE['remember_token']);
$persistent_ok = $has_persistent_cookie || $has_legacy_remember;

$page_title = 'Settings';
$cg_kph_board_menu_actions = false;
$cg_kph_body_extra_class = 'cg-settings-route';

if (defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL) {
    require_once __DIR__ . '/includes/cg_kanban_portal_header.php';
} else {
    require_once $cg_public_root . '/includes/header.php';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(cg_portal_includes_base(), ENT_QUOTES, 'UTF-8'); ?>cg_settings.css">
<div class="cg-settings-page">
    <h1>Settings</h1>
    <p class="cg-settings-page__lead">
        Manage how you stay signed in to Kanban. You remain logged in until you sign out manually or revoke a device below.
    </p>

    <div class="cg-settings-card">
        <div class="cg-settings-card__header">
            <h2 class="cg-settings-card__title"><i class="fas fa-clock me-2" aria-hidden="true"></i>Stay signed in</h2>
        </div>
        <div class="cg-settings-card__body">
            <?php if ($persistent_ok): ?>
            <div class="cg-settings-status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <div>
                    <strong>This device is saving your login.</strong>
                    Your session is kept for about <?php echo (int) $stay_signed_days; ?> days and refreshed when you use Kanban
                    (same as cinegrid.net).
                </div>
            </div>
            <?php else: ?>
            <div class="cg-settings-status cg-settings-status--warn">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <div>
                    <strong>No saved login detected on this device.</strong>
                    If you are signed in, try refreshing the page. Otherwise sign in again to enable stay-signed-in.
                </div>
            </div>
            <?php endif; ?>
            <p class="text-muted small mb-0">
                Closing the browser does not sign you out. Use <strong>Logout</strong> in the menu when you want to end this session.
            </p>
        </div>
    </div>

    <div class="cg-settings-card">
        <div class="cg-settings-card__header">
            <h2 class="cg-settings-card__title"><i class="fas fa-shield-alt me-2" aria-hidden="true"></i>Signed-in devices</h2>
        </div>
        <div class="cg-settings-card__body">
            <p class="text-muted small mb-3">
                Browsers and devices where your CineGrid account is currently signed in to Kanban. Sign out any device you do not recognize.
            </p>
            <div id="cgKanbanDeviceList" class="cg-settings-device-list">
                <div class="text-muted py-2"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Loading devices…</div>
            </div>
            <button type="button" class="cg-settings-btn cg-settings-btn--danger mt-3" id="cgKanbanRevokeAllDevicesBtn">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign out all other devices
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    var listEl = document.getElementById('cgKanbanDeviceList');
    var revokeAllBtn = document.getElementById('cgKanbanRevokeAllDevicesBtn');
    var csrfToken = <?php echo json_encode($settings_csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    if (!listEl) return;

    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }
    function fmtDate(s) {
        if (!s) return '—';
        try {
            return new Date(String(s).replace(' ', 'T')).toLocaleString();
        } catch (e) {
            return s;
        }
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
    function revokeSession(sessionId) {
        return fetch('/api/user_sessions/revoke.php', {
            method: 'POST',
            credentials: 'include',
            headers: csrfHeaders(),
            body: JSON.stringify({ action: 'revoke', session_id: sessionId }),
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                portalAlert(res.message || (res.success ? 'Device signed out.' : 'Failed'), {
                    title: res.success ? 'Signed out' : 'Could not sign out',
                    variant: res.success ? 'success' : 'danger',
                });
                if (res.success) loadDevices();
            });
    }
    function loadDevices() {
        listEl.innerHTML = '<div class="text-muted py-2"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Loading devices…</div>';
        fetch('/api/user_sessions/list.php', { credentials: 'include', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.sessions || !data.sessions.length) {
                    listEl.innerHTML = '<p class="text-muted mb-0">No active sessions found.</p>';
                    return;
                }
                var html = '<div class="list-group list-group-flush">';
                data.sessions.forEach(function (s) {
                    html += '<div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">';
                    html += '<div><strong>' + esc(s.device_name) + '</strong>';
                    if (s.is_current) {
                        html += ' <span class="badge bg-success ms-1">This device</span>';
                    }
                    html += '<br><small class="text-muted">Last active: ' + esc(fmtDate(s.last_used_at || s.created_at)) + '</small>';
                    if (s.location) {
                        html += '<br><small class="text-muted"><i class="fas fa-location-dot me-1" aria-hidden="true"></i>' + esc(s.location) + '</small>';
                    }
                    if (s.ip_address) {
                        html += '<br><small class="text-muted">IP: ' + esc(s.ip_address) + '</small>';
                    }
                    html += '</div>';
                    if (!s.is_current) {
                        html += '<button type="button" class="btn btn-sm btn-outline-danger cg-kanban-revoke-device" data-id="' + s.id + '">Sign out</button>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                listEl.innerHTML = html;
                listEl.querySelectorAll('.cg-kanban-revoke-device').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = parseInt(btn.getAttribute('data-id'), 10);
                        if (!id) return;
                        portalConfirm('This device will need to sign in again to use Kanban.', {
                            title: 'Sign out this device?',
                            okText: 'Sign out',
                            cancelText: 'Cancel',
                            variant: 'danger',
                        }).then(function (ok) {
                            if (ok) revokeSession(id);
                        });
                    });
                });
            })
            .catch(function () {
                listEl.innerHTML = '<p class="text-danger mb-0">Could not load devices.</p>';
            });
    }
    if (revokeAllBtn) {
        revokeAllBtn.addEventListener('click', function () {
            portalConfirm('Every other browser and device will be signed out of Kanban.', {
                title: 'Sign out all other devices?',
                okText: 'Sign out all',
                cancelText: 'Cancel',
                variant: 'danger',
            }).then(function (ok) {
                if (!ok) return;
                fetch('/api/user_sessions/revoke.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ action: 'revoke_all' }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        portalAlert(res.message || (res.success ? 'Signed out of all other devices.' : 'Failed'), {
                            title: res.success ? 'Done' : 'Could not sign out',
                            variant: res.success ? 'success' : 'danger',
                        });
                        if (res.success) loadDevices();
                    });
            });
        });
    }
    loadDevices();
})();
</script>
</main>
</body>
</html>
