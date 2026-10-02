<?php
$page_title = 'My Wallet';

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

// CSRF must be generated while the session is still open. requireLogin() calls
// session_write_close() for concurrency; if we generate the token after that,
// it never persists and wallet API calls fail validation.
$wallet_csrf = CSRFProtection::generateToken();

requireLogin();

/** NPR per 1 CinePoint (1 CinePoint = 100 NPR). Used for display and future payment settlement. */
if (!defined('CINEPOINT_NPR_RATE')) {
    define('CINEPOINT_NPR_RATE', 100);
}

/** Set to true when eSewa merchant API + callback handlers are implemented. */
if (!defined('WALLET_PAYMENT_ESEWA_ENABLED')) {
    define('WALLET_PAYMENT_ESEWA_ENABLED', false);
}
/** Set to true when Fonepay integration is implemented. */
if (!defined('WALLET_PAYMENT_FONEPAY_ENABLED')) {
    define('WALLET_PAYMENT_FONEPAY_ENABLED', false);
}

$pdo = getDB();
ensureUsersCinePointsColumn();
require_once $cg_public_root . '/includes/wallet_transfer_history.php';
ensureWalletTransferHistoryTable($pdo);
$recent_recipients = wallet_get_recent_recipients($pdo, (int) $_SESSION['user_id'], 12);

$show_transfer_ok = isset($_GET['transfer_ok']) && $_GET['transfer_ok'] === '1';

$balance = getCurrentUserCinePoints();
$balance_npr = $balance * (int) CINEPOINT_NPR_RATE;

$cg_wallet_on_portal = defined('CG_KANBAN_SUBDOMAIN_PORTAL') && CG_KANBAN_SUBDOMAIN_PORTAL;
if ($cg_wallet_on_portal) {
    $cg_kph_board_menu_actions = false;
    $cg_kph_include_site_wallet_styles = true;
    $cg_kph_body_extra_class = 'cg-wallet-route';
    require_once __DIR__ . '/includes/cg_kanban_portal_header.php';
} else {
    require_once $cg_public_root . '/includes/header.php';
}
?>

<?php if ($cg_wallet_on_portal): ?>
<div class="fmain-content cg-wallet-page">
<?php endif; ?>

<div class="container py-5" style="width: 80%; max-width: 100%;">
    <h1 class="h3 fw-bold mb-1">My Wallet</h1>
    <p class="text-muted small mb-4">Your balance is stored as CinePoints on your account—the same value shown in the admin user list. <strong>1 CinePoint = <?php echo (int) CINEPOINT_NPR_RATE; ?> NPR.</strong></p>

    <?php if ($show_transfer_ok): ?>
        <div class="alert alert-success" role="alert">CinePoints transfer completed successfully.</div>
    <?php endif; ?>
    <div id="walletTransferSuccessAlert" class="alert alert-success d-none" role="status" aria-live="polite"></div>

    <div id="walletBalanceCard" class="card border-0 shadow-sm mb-4" style="border-radius: 12px; transition: box-shadow 0.2s ease;">
        <div class="card-body p-4 text-center">
            <div class="text-muted small text-uppercase mb-2" style="letter-spacing: 0.06em;" id="walletBalanceCardLabel">Available balance</div>
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <i class="fas fa-coins" style="color: #ff3c3c; font-size: 1.75rem;"></i>
                <span class="display-6 fw-bold mb-0" id="walletBalanceCp"><?php echo number_format($balance); ?></span>
            </div>
            <div class="text-muted fw-semibold">CinePoints</div>
            <div class="small text-muted mt-2" id="walletBalanceNprLine">≈ Rs. <?php echo number_format($balance_npr); ?> NPR at <?php echo (int) CINEPOINT_NPR_RATE; ?> NPR per CinePoint</div>
        </div>
    </div>

    <h2 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing: 0.04em;">Add CinePoints</h2>
    <div class="card border shadow-sm mb-3" style="border-radius: 12px;">
        <div class="card-body p-4">
            <label for="wallet_topup_amount" class="form-label small fw-semibold">Amount (NPR)</label>
            <div class="input-group input-group-lg mb-3">
                <span class="input-group-text">Rs.</span>
                <input type="number" class="form-control" id="wallet_topup_amount" name="wallet_topup_amount" min="0" step="1" placeholder="e.g. 500" aria-describedby="walletPayHelp" autocomplete="off">
            </div>
            <p id="walletPayHelp" class="small text-muted mb-3 mb-md-4">Choose a payment method. Checkout opens in a new step once gateways are connected.</p>

            <div class="row g-2">
                <div class="col-md-6">
                    <button type="button" class="btn btn-lg w-100 py-3 d-flex align-items-center justify-content-center gap-2 wallet-pay-esewa" style="border-radius: 10px; background: #60bb46; border: none; color: #fff;"<?php echo WALLET_PAYMENT_ESEWA_ENABLED ? '' : ' disabled'; ?> title="<?php echo WALLET_PAYMENT_ESEWA_ENABLED ? 'Pay with eSewa' : 'eSewa payment is not available yet'; ?>">
                        <i class="fas fa-mobile-screen-button"></i>
                        <span class="fw-bold">eSewa</span>
                    </button>
                </div>
                <div class="col-md-6">
                    <button type="button" class="btn btn-lg w-100 py-3 d-flex align-items-center justify-content-center gap-2 wallet-pay-fonepay" style="border-radius: 10px; background: #5c2d91; border: none; color: #fff;"<?php echo WALLET_PAYMENT_FONEPAY_ENABLED ? '' : ' disabled'; ?> title="<?php echo WALLET_PAYMENT_FONEPAY_ENABLED ? 'Pay with Fonepay' : 'Fonepay payment is not available yet'; ?>">
                        <i class="fas fa-credit-card"></i>
                        <span class="fw-bold">Fonepay</span>
                    </button>
                </div>
            </div>

            <?php if (!WALLET_PAYMENT_ESEWA_ENABLED || !WALLET_PAYMENT_FONEPAY_ENABLED): ?>
                <div class="alert alert-secondary border-0 mt-3 mb-0 small" role="status">
                    <i class="fas fa-info-circle me-1"></i>
                    Online payment is temporarily unavailable while we connect eSewa and Fonepay APIs. The buttons stay disabled until credentials and server-side verification are in place.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="h6 fw-bold text-uppercase text-muted mb-3 mt-4" style="letter-spacing: 0.04em;">Transfer / Gift CinePoints</h2>
    <div class="card border shadow-sm mb-3" style="border-radius: 12px;">
        <div class="card-body p-4">
            <?php if ($balance < 1): ?>
                <p class="small text-muted mb-3 mb-md-0">You need at least <strong>1 CinePoint</strong> in your balance to transfer or gift to another user.</p>
            <?php endif; ?>

            <div id="walletRecentRecipientsWrap" class="<?php echo empty($recent_recipients) ? 'd-none' : ''; ?> mb-3">
                <p class="small fw-semibold text-muted mb-2">Recently sent — tap to send again</p>
                <div class="d-flex flex-wrap gap-2" id="walletRecentRecipientsList" role="list">
                    <?php foreach ($recent_recipients as $ru): ?>
                        <?php
                        $ruPayload = [
                            'id' => (int) $ru['id'],
                            'name' => $ru['name'],
                            'email' => $ru['email'],
                            'avatar_url' => $ru['avatar_url'],
                        ];
                        ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill wallet-recent-user d-inline-flex align-items-center gap-2 py-2 px-3 text-start" role="listitem"
                            data-receiver-id="<?php echo (int) $ru['id']; ?>"
                            data-user-json="<?php echo htmlspecialchars(json_encode($ruPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $balance < 1 ? 'disabled' : ''; ?>
                            style="<?php echo $balance < 1 ? 'opacity:0.5;' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($ru['avatar_url']); ?>" alt="" width="28" height="28" class="rounded-circle flex-shrink-0" style="object-fit:cover;background:#eee;" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&amp;s=64';">
                            <span class="text-truncate" style="max-width: 160px;"><?php echo htmlspecialchars($ru['name'] !== '' ? $ru['name'] : $ru['email']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <label for="transfer_email_lookup" class="form-label small fw-semibold">Recipient email</label>
            <div class="input-group mb-3">
                <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                <input type="email" class="form-control" id="transfer_email_lookup" placeholder="user@example.com" autocomplete="email" autocapitalize="off">
                <button type="button" class="btn btn-outline-primary" id="transferCheckUserBtn" <?php echo $balance < 1 ? 'disabled' : ''; ?>>
                    <i class="fas fa-user-check me-1"></i>Check user
                </button>
            </div>
            <p id="transferLookupMsg" class="small mb-3 text-danger d-none"></p>
            <p id="transferActionMsg" class="small mb-3 d-none" role="status"></p>

            <div id="transferRecipientPreview" class="d-none mb-3">
                <p class="small text-muted mb-2">Found user — <strong>click their profile</strong> to select, then enter an amount.</p>
                <div class="d-flex align-items-center gap-3 p-3 border rounded-3 transfer-recipient-card" id="transferRecipientCard" role="button" tabindex="0" style="cursor: pointer; border-width: 2px !important; transition: border-color 0.2s, box-shadow 0.2s;">
                    <img src="" alt="" class="rounded-circle flex-shrink-0" id="transferRecipientAvatar" width="64" height="64" style="object-fit: cover; background: #eee;" referrerpolicy="no-referrer">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold text-truncate" id="transferRecipientName"></div>
                        <div class="small text-muted text-truncate" id="transferRecipientEmail"></div>
                    </div>
                    <span class="badge bg-secondary d-none" id="transferSelectedBadge">Selected</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2 d-none" id="transferClearRecipientBtn">Change recipient</button>
            </div>

            <div id="transferAmountBlock" class="d-none">
                <label for="transfer_amount" class="form-label small fw-semibold">Amount (CinePoints)</label>
                <div class="input-group input-group-lg mb-2">
                    <span class="input-group-text"><i class="fas fa-coins" style="color:#ff3c3c;"></i></span>
                    <input type="number" class="form-control" id="transfer_amount" min="1" <?php echo $balance > 0 ? 'max="' . (int) $balance . '"' : ''; ?> step="1" placeholder="e.g. 50" <?php echo $balance < 1 ? 'disabled' : ''; ?>>
                </div>
                <p class="small text-muted mb-3">Your balance: <strong id="transferBalanceHint"><?php echo number_format($balance); ?></strong> CinePoints</p>
                <button type="button" class="btn btn-danger w-100 py-2" id="transferSubmitBtn" disabled>
                    <i class="fas fa-paper-plane me-2"></i>Transfer
                </button>
            </div>

        </div>
    </div>

</div>

<?php if ($cg_wallet_on_portal): ?>
</div>
<?php endif; ?>

<!-- Full-page overlay for transfer progress -->
<div id="walletTransferProgressOverlay" class="wallet-transfer-progress-overlay d-none" aria-hidden="true" aria-live="polite" role="dialog" aria-modal="true" aria-labelledby="walletTransferProgressText">
    <div class="wallet-transfer-progress-overlay__backdrop"></div>
    <div class="wallet-transfer-progress-overlay__dialog shadow-lg">
        <p class="fw-semibold text-dark mb-3 text-center" id="walletTransferProgressText">Transferring CinePoints…</p>
        <div class="progress wallet-transfer-progress-track">
            <div class="progress-bar progress-bar-striped progress-bar-animated wallet-transfer-progress-bar" role="progressbar" id="walletTransferProgressBar" style="width: 5%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="5"></div>
        </div>
    </div>
</div>
<style>
.wallet-transfer-progress-overlay {
    position: fixed;
    inset: 0;
    z-index: 5000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.wallet-transfer-progress-overlay.d-none {
    display: none !important;
}
.wallet-transfer-progress-overlay__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}
.wallet-transfer-progress-overlay__dialog {
    position: relative;
    z-index: 1;
    background: #fff;
    border-radius: 14px;
    padding: 1.75rem 2rem;
    min-width: min(380px, 94vw);
    max-width: 420px;
    border: 1px solid rgba(0, 0, 0, 0.06);
}
.wallet-transfer-progress-track {
    height: 22px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.08);
    overflow: hidden;
}
.wallet-transfer-progress-bar {
    background-color: #dc2626 !important;
    border-radius: 999px;
}
.wallet-transfer-progress-bar.progress-bar-animated {
    animation-duration: 2.75s;
}
#walletBalanceCard.wallet-balance-preview {
    box-shadow: 0 0 0 2px rgba(255, 60, 60, 0.22), 0 0.5rem 1rem rgba(0, 0, 0, 0.06) !important;
}
</style>

<script>
(function () {
    var WALLET_CSRF = <?php echo json_encode($wallet_csrf); ?>;
    var walletBalance = <?php echo (int) $balance; ?>;
    var nprPerCp = <?php echo (int) CINEPOINT_NPR_RATE; ?>;

    var elBalanceCp = document.getElementById('walletBalanceCp');
    var elBalanceNpr = document.getElementById('walletBalanceNprLine');
    var elNavMain = document.getElementById('cinepointsNavBalanceMain');
    var elNavMobile = document.getElementById('cinepointsNavBalanceMobile');
    var elTransferSuccess = document.getElementById('walletTransferSuccessAlert');
    var nprInputTopup = document.getElementById('wallet_topup_amount');
    var balanceCard = document.getElementById('walletBalanceCard');
    var balanceCardLabel = document.getElementById('walletBalanceCardLabel');

    var balanceDisplayedCp = walletBalance;
    var topupBalanceAnim = null;
    var TOPUP_BALANCE_ANIM_MS = 140;

    function formatCp(n) {
        return Number(n).toLocaleString();
    }

    function getTopupNprWhole() {
        if (!nprInputTopup) return 0;
        var n = parseInt(nprInputTopup.value, 10);
        if (isNaN(n) || n < 0) return 0;
        return n;
    }

    function getProjectedBalanceCp() {
        var addCp = Math.floor(getTopupNprWhole() / nprPerCp);
        return walletBalance + addCp;
    }

    function renderBalanceCardValues(cp) {
        cp = Math.max(0, Math.round(cp));
        if (elBalanceCp) elBalanceCp.textContent = formatCp(cp);
        if (elBalanceNpr) {
            elBalanceNpr.textContent =
                '≈ Rs. ' +
                formatCp(cp * nprPerCp) +
                ' NPR at ' +
                nprPerCp +
                ' NPR per CinePoint';
        }
    }

    function setBalancePreviewUi(active) {
        if (balanceCard) balanceCard.classList.toggle('wallet-balance-preview', active);
        if (balanceCardLabel) {
            balanceCardLabel.textContent = active ? 'Balance after this top-up' : 'Available balance';
        }
    }

    function syncTopupProjectedBalance() {
        var target = getProjectedBalanceCp();
        var preview = getTopupNprWhole() > 0;
        setBalancePreviewUi(preview);

        if (target === balanceDisplayedCp) {
            renderBalanceCardValues(target);
            balanceDisplayedCp = target;
            return;
        }

        if (topupBalanceAnim) {
            cancelAnimationFrame(topupBalanceAnim);
            topupBalanceAnim = null;
        }

        var startCp = balanceDisplayedCp;
        var t0 = performance.now();

        function tick(now) {
            var u = Math.min(1, (now - t0) / TOPUP_BALANCE_ANIM_MS);
            var eased = 1 - (1 - u) * (1 - u);
            var v = Math.round(startCp + (target - startCp) * eased);
            balanceDisplayedCp = v;
            renderBalanceCardValues(v);
            if (u < 1) {
                topupBalanceAnim = requestAnimationFrame(tick);
            } else {
                balanceDisplayedCp = target;
                renderBalanceCardValues(target);
                topupBalanceAnim = null;
            }
        }

        topupBalanceAnim = requestAnimationFrame(tick);
    }

    function applyWalletBalanceEverywhere(newBal) {
        walletBalance = Math.max(0, parseInt(newBal, 10) || 0);
        syncTopupProjectedBalance();
        if (elNavMain) elNavMain.textContent = formatCp(walletBalance);
        if (elNavMobile) elNavMobile.textContent = formatCp(walletBalance) + ' CinePoints';
        var hint = document.getElementById('transferBalanceHint');
        if (hint) hint.textContent = formatCp(walletBalance);
        if (amountInput) {
            if (walletBalance > 0) {
                amountInput.max = walletBalance;
                amountInput.disabled = false;
            } else {
                amountInput.removeAttribute('max');
                amountInput.value = '';
                amountInput.disabled = true;
            }
        }
        if (lookupBtn) lookupBtn.disabled = walletBalance < 1;
        document.querySelectorAll('.wallet-recent-user').forEach(function (b) {
            b.disabled = walletBalance < 1;
            b.style.opacity = walletBalance < 1 ? '0.5' : '';
        });
    }

    function setProgressPct(p) {
        if (!progressBar) return;
        p = Math.max(0, Math.min(100, p));
        progressBar.style.width = p + '%';
        progressBar.setAttribute('aria-valuenow', String(Math.round(p)));
    }

    var lookupBtn = document.getElementById('transferCheckUserBtn');
    var emailInput = document.getElementById('transfer_email_lookup');
    var lookupMsg = document.getElementById('transferLookupMsg');
    var actionMsg = document.getElementById('transferActionMsg');
    var previewWrap = document.getElementById('transferRecipientPreview');
    var recipientCard = document.getElementById('transferRecipientCard');
    var recipientAvatar = document.getElementById('transferRecipientAvatar');
    var recipientName = document.getElementById('transferRecipientName');
    var recipientEmail = document.getElementById('transferRecipientEmail');
    var selectedBadge = document.getElementById('transferSelectedBadge');
    var clearBtn = document.getElementById('transferClearRecipientBtn');
    var amountBlock = document.getElementById('transferAmountBlock');
    var amountInput = document.getElementById('transfer_amount');
    var submitBtn = document.getElementById('transferSubmitBtn');
    var progressOverlay = document.getElementById('walletTransferProgressOverlay');
    var progressBar = document.getElementById('walletTransferProgressBar');
    var progressText = document.getElementById('walletTransferProgressText');

    var TRANSFER_PROGRESS_TICK_MS = 580;
    var TRANSFER_PROGRESS_STEP_PCT = 5;
    var TRANSFER_OVERLAY_HIDE_DELAY_MS = 2900;

    function showTransferOverlay(show) {
        if (!progressOverlay) return;
        if (show) {
            progressOverlay.classList.remove('d-none');
            document.body.style.overflow = 'hidden';
            progressOverlay.setAttribute('aria-hidden', 'false');
        } else {
            progressOverlay.classList.add('d-none');
            document.body.style.overflow = '';
            progressOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    var pendingUser = null;
    var selectedUser = null;

    function showLookupError(t) {
        if (!lookupMsg) return;
        lookupMsg.textContent = t || '';
        lookupMsg.classList.toggle('d-none', !t);
    }
    function showActionMsg(t, ok) {
        if (!actionMsg) return;
        actionMsg.textContent = t || '';
        actionMsg.classList.toggle('d-none', !t);
        actionMsg.classList.remove('text-danger', 'text-success');
        actionMsg.classList.add(ok ? 'text-success' : 'text-danger');
    }
    function clearSelection() {
        selectedUser = null;
        pendingUser = null;
        if (recipientCard) {
            recipientCard.classList.remove('border-danger', 'shadow-sm');
            recipientCard.style.boxShadow = '';
        }
        if (selectedBadge) selectedBadge.classList.add('d-none');
        if (clearBtn) clearBtn.classList.add('d-none');
        if (amountBlock) amountBlock.classList.add('d-none');
        if (amountInput) amountInput.value = '';
        if (previewWrap) previewWrap.classList.add('d-none');
        if (submitBtn) submitBtn.disabled = true;
    }
    function validateTransferButton() {
        if (!submitBtn || !amountInput) return;
        var amt = parseInt(amountInput.value, 10);
        var ok = selectedUser && !isNaN(amt) && amt >= 1 && amt <= walletBalance;
        submitBtn.disabled = !ok;
    }

    if (lookupBtn && emailInput) {
        lookupBtn.addEventListener('click', function () {
            showLookupError('');
            showActionMsg('', true);
            if (elTransferSuccess) elTransferSuccess.classList.add('d-none');
            clearSelection();
            var email = (emailInput.value || '').trim();
            if (!email) {
                showLookupError('Enter an email address.');
                return;
            }
            lookupBtn.disabled = true;
            fetch('/api/wallet_lookup_recipient.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, csrf_token: WALLET_CSRF })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    lookupBtn.disabled = false;
                    if (!data.ok) {
                        showLookupError(data.error || 'User not found.');
                        return;
                    }
                    pendingUser = data.user;
                    selectedUser = null;
                    recipientAvatar.src = data.user.avatar_url || '';
                    recipientAvatar.alt = data.user.name || '';
                    recipientAvatar.onerror = function () {
                        this.onerror = null;
                        this.src = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=128';
                    };
                    recipientName.textContent = data.user.name || '—';
                    recipientEmail.textContent = data.user.email || '';
                    previewWrap.classList.remove('d-none');
                    selectedBadge.classList.add('d-none');
                    amountBlock.classList.add('d-none');
                    if (recipientCard) {
                        recipientCard.classList.remove('border-danger', 'shadow-sm');
                        recipientCard.style.boxShadow = '';
                    }
                    clearBtn.classList.remove('d-none');
                    submitBtn.disabled = true;
                })
                .catch(function () {
                    lookupBtn.disabled = false;
                    showLookupError('Network error. Try again.');
                });
        });
    }

    function selectRecipientCard() {
        if (!pendingUser || !recipientCard) return;
        selectedUser = pendingUser;
        selectedBadge.classList.remove('d-none');
        recipientCard.classList.add('border-danger');
        recipientCard.style.boxShadow = '0 0 0 2px rgba(220,53,69,0.35)';
        amountBlock.classList.remove('d-none');
        showTransferOverlay(false);
        validateTransferButton();
    }

    function pickRecentRecipient(user) {
        if (!user || !user.id || walletBalance < 1) return;
        if (elTransferSuccess) elTransferSuccess.classList.add('d-none');
        showLookupError('');
        showActionMsg('', true);
        pendingUser = user;
        selectedUser = null;
        if (emailInput) emailInput.value = user.email || '';
        if (recipientAvatar) {
            recipientAvatar.src = user.avatar_url || '';
            recipientAvatar.alt = user.name || '';
            recipientAvatar.onerror = function () {
                this.onerror = null;
                this.src = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=128';
            };
        }
        if (recipientName) recipientName.textContent = user.name || '—';
        if (recipientEmail) recipientEmail.textContent = user.email || '';
        if (previewWrap) previewWrap.classList.remove('d-none');
        if (selectedBadge) selectedBadge.classList.add('d-none');
        if (amountBlock) amountBlock.classList.add('d-none');
        if (recipientCard) {
            recipientCard.classList.remove('border-danger', 'shadow-sm');
            recipientCard.style.boxShadow = '';
        }
        if (clearBtn) clearBtn.classList.remove('d-none');
        if (submitBtn) submitBtn.disabled = true;
        selectRecipientCard();
        if (amountInput) {
            setTimeout(function () {
                amountInput.focus();
            }, 50);
        }
    }

    function upsertRecentRecipient(u) {
        var wrap = document.getElementById('walletRecentRecipientsWrap');
        var list = document.getElementById('walletRecentRecipientsList');
        if (!wrap || !list || !u || !u.id) return;
        wrap.classList.remove('d-none');
        var sid = String(u.id);
        var existing = list.querySelector('.wallet-recent-user[data-receiver-id="' + sid + '"]');
        if (existing) {
            var img = existing.querySelector('img');
            var lbl = existing.querySelector('span');
            if (img) img.src = u.avatar_url || img.src;
            if (lbl) lbl.textContent = (u.name && u.name.trim()) || u.email || 'User';
            existing.setAttribute('data-user-json', JSON.stringify(u));
            list.insertBefore(existing, list.firstChild);
            existing.disabled = walletBalance < 1;
            existing.style.opacity = walletBalance < 1 ? '0.5' : '';
            return;
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('role', 'listitem');
        btn.className =
            'btn btn-outline-secondary btn-sm rounded-pill wallet-recent-user d-inline-flex align-items-center gap-2 py-2 px-3 text-start';
        btn.setAttribute('data-receiver-id', sid);
        btn.setAttribute('data-user-json', JSON.stringify(u));
        var im = document.createElement('img');
        im.src = u.avatar_url || '';
        im.alt = '';
        im.width = 28;
        im.height = 28;
        im.className = 'rounded-circle flex-shrink-0';
        im.style.objectFit = 'cover';
        im.style.background = '#eee';
        im.onerror = function () {
            this.onerror = null;
            this.src = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=128';
        };
        var sp = document.createElement('span');
        sp.className = 'text-truncate';
        sp.style.maxWidth = '160px';
        sp.textContent = (u.name && u.name.trim()) || u.email || 'User';
        btn.appendChild(im);
        btn.appendChild(sp);
        btn.disabled = walletBalance < 1;
        btn.style.opacity = walletBalance < 1 ? '0.5' : '';
        list.insertBefore(btn, list.firstChild);
        while (list.children.length > 12) {
            list.removeChild(list.lastChild);
        }
    }

    var recentRecipientsList = document.getElementById('walletRecentRecipientsList');
    if (recentRecipientsList) {
        recentRecipientsList.addEventListener('click', function (e) {
            var btn = e.target.closest('.wallet-recent-user');
            if (!btn) return;
            var raw = btn.getAttribute('data-user-json');
            if (!raw) return;
            try {
                pickRecentRecipient(JSON.parse(raw));
            } catch (err) {}
        });
    }

    if (recipientCard) {
        recipientCard.addEventListener('click', selectRecipientCard);
        recipientCard.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectRecipientCard();
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            clearSelection();
            showLookupError('');
            if (emailInput) emailInput.value = '';
        });
    }

    if (amountInput) {
        amountInput.addEventListener('input', validateTransferButton);
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!selectedUser) return;
            var amt = parseInt(amountInput.value, 10);
            if (isNaN(amt) || amt < 1 || amt > walletBalance) {
                showActionMsg('Enter an amount between 1 and your balance.', false);
                actionMsg.classList.remove('d-none');
                return;
            }
            showActionMsg('', true);
            if (elTransferSuccess) elTransferSuccess.classList.add('d-none');
            submitBtn.disabled = true;
            showTransferOverlay(true);
            setProgressPct(8);
            var name = selectedUser.name || selectedUser.email || 'recipient';
            progressText.textContent = 'Transferring CinePoints to ' + name;
            var step = setInterval(function () {
                var w = parseFloat(progressBar.style.width) || 0;
                if (w < 88) setProgressPct(Math.min(88, w + TRANSFER_PROGRESS_STEP_PCT));
            }, TRANSFER_PROGRESS_TICK_MS);

            fetch('/api/wallet_transfer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    receiver_id: selectedUser.id,
                    amount: amt,
                    csrf_token: WALLET_CSRF
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    clearInterval(step);
                    setProgressPct(100);
                    if (data.ok) {
                        progressText.textContent = 'Transfer complete';
                        applyWalletBalanceEverywhere(data.new_balance);
                        if (elTransferSuccess) {
                            var a = parseInt(data.amount, 10) || amt;
                            var rname = data.receiver_name || name;
                            elTransferSuccess.textContent =
                                'Sent ' + formatCp(a) + ' CinePoint' + (a === 1 ? '' : 's') + ' to ' + rname + '.';
                            elTransferSuccess.classList.remove('d-none');
                            try {
                                elTransferSuccess.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            } catch (e2) {}
                        }
                        if (data.receiver) {
                            upsertRecentRecipient(data.receiver);
                        }
                        if (amountInput) amountInput.value = '';
                        validateTransferButton();
                        if (walletBalance < 1) {
                            clearSelection();
                            showLookupError('');
                            if (emailInput) emailInput.value = '';
                        }
                        setTimeout(function () {
                            showTransferOverlay(false);
                            setProgressPct(5);
                            progressText.textContent = 'Transferring CinePoints…';
                        }, TRANSFER_OVERLAY_HIDE_DELAY_MS);
                    } else {
                        showTransferOverlay(false);
                        setProgressPct(5);
                        progressText.textContent = 'Transferring CinePoints…';
                        showActionMsg(data.error || 'Transfer failed.', false);
                        submitBtn.disabled = false;
                    }
                })
                .catch(function () {
                    clearInterval(step);
                    showTransferOverlay(false);
                    setProgressPct(5);
                    progressText.textContent = 'Transferring CinePoints…';
                    showActionMsg('Network error. Try again.', false);
                    submitBtn.disabled = false;
                });
        });
    }

    if (nprInputTopup) {
        nprInputTopup.addEventListener('input', syncTopupProjectedBalance);
    }
    syncTopupProjectedBalance();
})();
</script>
<?php if ($cg_wallet_on_portal): ?>
<script>
(function () {
  var KANBAN_THEME_STORAGE_KEY = 'kanban_theme_mode';
  function applyKanbanTheme(mode) {
    var isDark = mode === 'dark';
    document.body.classList.toggle('kanban-theme-dark', isDark);
    document.querySelectorAll('[data-kanban-theme-toggle]').forEach(function (toggleBtn) {
      if (!toggleBtn.hasAttribute('data-kanban-theme-toggle-label')) return;
      var label = isDark ? 'Light mode' : 'Dark mode';
      var iconClass = isDark ? 'fa-sun' : 'fa-moon';
      toggleBtn.innerHTML = '<i class="fas ' + iconClass + '" aria-hidden="true"></i><span class="cg-kanban-portal-theme-label">' + label + '</span>';
      toggleBtn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      toggleBtn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    });
  }
  if (!window.__cgKanbanThemeToggleWired) {
    document.querySelectorAll('[data-kanban-theme-toggle]').forEach(function (b) {
      b.addEventListener('click', function () {
        var next = document.body.classList.contains('kanban-theme-dark') ? 'light' : 'dark';
        applyKanbanTheme(next);
        try { localStorage.setItem('kanban_theme_mode', next); } catch (e) {}
      });
    });
  }
  try { applyKanbanTheme(localStorage.getItem('kanban_theme_mode') || 'light'); } catch (e) { applyKanbanTheme('light'); }
})();
</script>
</main>
</body>
</html>
<?php else: ?>
<?php require_once $cg_public_root . '/includes/footer.php'; ?>
<?php endif; ?>
