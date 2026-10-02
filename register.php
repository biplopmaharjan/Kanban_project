<?php
/**
 * Kanban-only signup: same OTP flow as main register.php, design aligned with kanban login.php.
 * Role is always freelance (Kanban access) — not shown in the UI.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_portal_config.php';
require_once __DIR__ . '/includes/freelance_projects.php';

if (cg_kanban_is_authenticated() && cg_user_can_access_freelance_kanban()) {
    $path = '/';
    if (isset($_GET['redirect']) && (string) $_GET['redirect'] !== '') {
        $n = cg_kanban_normalize_redirect_path((string) $_GET['redirect']);
        if ($n !== null && !cg_kanban_is_login_url_path($n)) {
            $path = $n;
        }
    }
    $dest = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . $path;
    cg_kanban_log_redirect($dest, 'register: already authenticated with kanban access');
    header('Location: ' . $dest, true, 302);
    exit;
}

define('CG_KANBAN_VERIFY_OTP_URL', 'https://cinegrid.net/auth_system/api/verify_otp.php');
$login_url = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . '/login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Create account — CineGrid Kanban</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <style>
        body {
            background: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 24px 16px;
        }
        .reg-container {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
            padding: 36px 32px;
            width: 100%;
            max-width: 440px;
        }
        .reg-header { text-align: center; margin-bottom: 24px; }
        .reg-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .reg-brand-icon { width: 2.5rem; height: 2.5rem; flex-shrink: 0; color: #ff3c3c; }
        .reg-brand-text {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }
        .reg-header h1 { font-size: 1.25rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .reg-header p { color: #6b7280; font-size: 0.95rem; margin: 0; }
        .form-label { font-weight: 500; color: #374151; margin-bottom: 6px; font-size: 0.9rem; }
        .form-control {
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #ff3c3c;
            box-shadow: 0 0 0 3px rgba(255, 60, 60, 0.15);
            outline: none;
        }
        .password-wrap { position: relative; }
        .password-wrap .form-control { padding-right: 44px; }
        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
        }
        .toggle-pw:hover { color: #374151; }
        .btn-reg {
            width: 100%;
            padding: 12px;
            background: #ff3c3c;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-reg:hover { background: #e62e2e; }
        .form-check-label { color: #6b7280; font-size: 0.88rem; }
        .form-check-label a { color: #ff3c3c; font-weight: 500; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #6b7280; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .back-link a:hover { color: #ff3c3c; text-decoration: underline; }
        .otp-input-container { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-bottom: 1rem; }
        .otp-input {
            width: 40px;
            height: 45px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .otp-input:focus {
            border-color: #ff3c3c;
            box-shadow: 0 0 0 2px rgba(255, 60, 60, 0.2);
            outline: none;
        }
    </style>
</head>
<body>
    <div class="reg-container">
        <div class="reg-header">
            <div class="reg-brand" aria-label="Kanban">
                <svg class="reg-brand-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="48" y="128" width="112" height="256" rx="24" fill="currentColor"/><rect x="200" y="80" width="112" height="352" rx="24" fill="currentColor"/><rect x="352" y="160" width="112" height="224" rx="24" fill="currentColor"/></svg>
                <span class="reg-brand-text">Kanban</span>
            </div>
            <h1>Create your account</h1>
            <p>Full name, email, phone & location — then verify your email</p>
        </div>

        <div id="formValidationMessage"></div>

        <form id="registrationForm" method="post" action="#">
            <input type="hidden" name="user_type" id="user_type" value="freelance">

            <div class="mb-3">
                <label class="form-label" for="name">Full name</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="Your full name" required autocomplete="name" pattern="^[A-Za-z\s\-'\.]+$">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Phone number</label>
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="9XXXXXXXXX or 0XXXXXXXX" required maxlength="10" autocomplete="tel">
            </div>
            <div class="mb-3">
                <label class="form-label" for="location">Location</label>
                <input type="text" class="form-control" id="location" name="location" placeholder="City or area" autocomplete="address-level2">
                <div class="form-text">Optional suggestions as you type</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <div class="password-wrap">
                    <input type="password" class="form-control" id="password" name="password" required minlength="6" autocomplete="new-password" placeholder="At least 6 characters">
                    <button type="button" class="toggle-pw" data-target="#password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm password</label>
                <div class="password-wrap">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password" placeholder="Repeat password">
                    <button type="button" class="toggle-pw" data-target="#confirm_password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                <label class="form-check-label" for="terms">
                    I agree to the <a href="https://cinegrid.net/term-of-service.php" target="_blank" rel="noopener">Terms</a>
                    and <a href="https://cinegrid.net/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>
                </label>
            </div>
            <button type="submit" class="btn-reg" id="registerBtn">Continue — verify email &amp; phone</button>
        </form>

        <div class="back-link">
            <a href="<?php echo htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8'); ?>">Already have an account? Sign in</a>
        </div>
    </div>

    <div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-semibold" id="otpModalLabel">Verify your email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="d-flex justify-content-center gap-2 mb-2">
                        <span class="badge bg-primary" id="otpStepBadge1">1. Email</span>
                        <span class="badge bg-secondary" id="otpStepBadge2">2. Phone</span>
                    </div>
                    <p class="text-muted small mb-1" id="otpStepHint">We sent different 6-digit codes to your email and phone.</p>
                    <p class="fw-bold mb-1" id="userEmail"></p>
                    <p class="fw-bold text-muted mb-3" id="userPhoneMasked" style="display:none;"></p>
                    <form id="otpForm">
                        <label class="form-label small" id="otpCodeLabel">Enter email verification code</label>
                        <div class="otp-input-container" id="otpContainer">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" aria-label="Digit <?php echo $i + 1; ?>" />
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="otp" id="otpHidden" required>
                        <button type="submit" class="btn-reg" id="verifyBtn">Verify email code</button>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="resendBtn">Resend codes</button>
                    </form>
                    <div id="otpMessage" class="mt-3 small"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cinegrid.net/includes/location_suggestions.js"></script>
    <script>
(function () {
    const VERIFY_OTP_URL = <?php echo json_encode(CG_KANBAN_VERIFY_OTP_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const LOGIN_URL = <?php echo json_encode($login_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const registrationForm = document.getElementById('registrationForm');
    const otpModalEl = document.getElementById('otpModal');
    const otpModal = new bootstrap.Modal(otpModalEl);
    const otpForm = document.getElementById('otpForm');
    const otpInputs = document.querySelectorAll('#otpContainer .otp-input');
    const otpHidden = document.getElementById('otpHidden');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resendBtn');
    const otpMessage = document.getElementById('otpMessage');
    const userEmailSpan = document.getElementById('userEmail');
    const userPhoneMasked = document.getElementById('userPhoneMasked');
    const otpModalLabel = document.getElementById('otpModalLabel');
    const otpStepHint = document.getElementById('otpStepHint');
    const otpCodeLabel = document.getElementById('otpCodeLabel');
    const otpStepBadge1 = document.getElementById('otpStepBadge1');
    const otpStepBadge2 = document.getElementById('otpStepBadge2');

    let formData = {};
    let otpStep = 'email';
    let maskedPhone = '';

    document.querySelectorAll('.toggle-pw').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const sel = btn.getAttribute('data-target');
            const input = document.querySelector(sel);
            if (!input) return;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            } else {
                input.type = 'password';
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            }
        });
    });

    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function () {
        let v = this.value.replace(/[^0-9]/g, '');
        if (v.length > 0) {
            if (v[0] === '9') v = v.substring(0, 10);
            else if (v[0] === '0') v = v.substring(0, 9);
            else v = v[0] === '' ? '' : v.substring(0, 1);
        }
        this.value = v;
    });

    function showFormMessage(msg, type) {
        const el = document.getElementById('formValidationMessage');
        el.innerHTML = '<div class="alert alert-' + type + ' py-2 small">' + msg + '</div>';
    }

    function validateForm() {
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.replace(/\D/g, '');
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const terms = document.getElementById('terms').checked;

        const nameRegex = /^[A-Za-z]+(\s[A-Za-z]+)+$/;
        if (!nameRegex.test(name)) {
            showFormMessage('Please enter your full name (at least two words).', 'danger');
            return false;
        }
        const emailRegex = /^[A-Za-z0-9._%+-]+@(gmail\.com|yahoo\.com|outlook\.com|hotmail\.com|[A-Za-z0-9.-]+\.(com|edu|edu\.np))$/;
        if (!emailRegex.test(email)) {
            showFormMessage('Please use a supported email provider or organization domain.', 'danger');
            return false;
        }
        if (!/^9\d{9}$/.test(phone) && !/^0\d{8}$/.test(phone)) {
            showFormMessage('Phone must start with 9 (10 digits) or 0 (9 digits).', 'danger');
            return false;
        }
        if (password.length < 6) {
            showFormMessage('Password must be at least 6 characters.', 'danger');
            return false;
        }
        if (password !== confirmPassword) {
            showFormMessage('Passwords do not match.', 'danger');
            return false;
        }
        if (!terms) {
            showFormMessage('Please accept the Terms and Privacy Policy.', 'danger');
            return false;
        }
        document.getElementById('formValidationMessage').innerHTML = '';
        return true;
    }

    function clearOtpInputs() {
        otpInputs.forEach(function (i) { i.value = ''; });
        otpHidden.value = '';
    }

    function setOtpStep(step, phoneMask) {
        otpStep = step;
        if (phoneMask) maskedPhone = phoneMask;
        clearOtpInputs();
        if (step === 'email') {
            otpModalLabel.textContent = 'Verify your email';
            otpStepHint.textContent = 'We sent different 6-digit codes to your email and phone.';
            otpCodeLabel.textContent = 'Enter email verification code';
            verifyBtn.textContent = 'Verify email code';
            otpStepBadge1.className = 'badge bg-primary';
            otpStepBadge2.className = 'badge bg-secondary';
            userEmailSpan.style.display = '';
            userEmailSpan.textContent = formData.email || '';
            userPhoneMasked.style.display = 'none';
        } else {
            otpModalLabel.textContent = 'Verify your phone';
            otpStepHint.textContent = 'Enter the different 6-digit code sent by SMS.';
            otpCodeLabel.textContent = 'Enter SMS verification code';
            verifyBtn.textContent = 'Verify & create account';
            otpStepBadge1.className = 'badge bg-success';
            otpStepBadge2.className = 'badge bg-primary';
            userEmailSpan.style.display = 'none';
            userPhoneMasked.style.display = '';
            userPhoneMasked.textContent = maskedPhone || '';
        }
        otpInputs[0].focus();
    }

    otpInputs.forEach(function (input, index) {
        input.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 1);
            if (e.target.value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
            updateHiddenOTP();
            if (Array.from(otpInputs).every(function (i) { return i.value.length === 1; })) {
                setTimeout(verifyCurrentStep, 80);
            }
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) otpInputs[index - 1].focus();
        });
        input.addEventListener('paste', function (e) {
            var paste = (e.clipboardData || window.clipboardData).getData('text').trim();
            if (/^\d{6}$/.test(paste)) {
                for (var i = 0; i < 6; i++) otpInputs[i].value = paste[i];
                updateHiddenOTP();
                otpInputs[5].focus();
                setTimeout(verifyCurrentStep, 80);
            }
            e.preventDefault();
        });
    });

    function updateHiddenOTP() {
        otpHidden.value = Array.from(otpInputs).map(function (i) { return i.value; }).join('');
    }

    otpModalEl.addEventListener('hidden.bs.modal', function () {
        otpForm.reset();
        clearOtpInputs();
        otpStep = 'email';
        otpMessage.innerHTML = '';
        verifyBtn.disabled = false;
        verifyBtn.textContent = 'Verify email code';
        resendBtn.disabled = false;
        resendBtn.textContent = 'Resend codes';
    });

    otpModalEl.addEventListener('shown.bs.modal', function () {
        otpInputs[0].focus();
    });

    registrationForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!validateForm()) return;
        formData = {
            name: document.getElementById('name').value.trim(),
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
            phone: document.getElementById('phone').value.replace(/\D/g, ''),
            location: document.getElementById('location').value.trim(),
            user_type: 'freelance'
        };
        sendOTP();
    });

    otpForm.addEventListener('submit', function (e) {
        e.preventDefault();
        updateHiddenOTP();
        verifyCurrentStep();
    });

    resendBtn.addEventListener('click', sendOTP);

    function sendOTP() {
        var fd = new FormData();
        fd.append('action', 'send_otp');
        fd.append('email', formData.email);
        fd.append('name', formData.name);
        fd.append('phone', formData.phone);
        fd.append('location', formData.location);
        fd.append('user_type', formData.user_type);

        fetch(VERIFY_OTP_URL, { method: 'POST', body: fd, credentials: 'include', mode: 'cors' })
            .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (err) { throw new Error(t || 'bad json'); } }); })
            .then(function (data) {
                if (data.success) {
                    maskedPhone = data.masked_phone || '';
                    setOtpStep('email', maskedPhone);
                    otpModal.show();
                    startResendTimer();
                } else {
                    showFormMessage(data.message || 'Could not send codes.', 'danger');
                }
            })
            .catch(function () {
                showFormMessage('Network error. Try again.', 'danger');
            });
    }

    function verifyCurrentStep() {
        updateHiddenOTP();
        var code = otpHidden.value.trim();
        if (code.length !== 6) {
            otpMessage.innerHTML = '<span class="text-danger">Enter the 6-digit code.</span>';
            return;
        }
        if (otpStep === 'email') verifyEmailOTP(code);
        else verifySmsOTP(code);
    }

    function verifyEmailOTP(code) {
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying…';
        var fd = new FormData();
        fd.append('action', 'verify_email_otp');
        fd.append('email', formData.email);
        fd.append('otp', code);

        fetch(VERIFY_OTP_URL, { method: 'POST', body: fd, credentials: 'include', mode: 'cors' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    setOtpStep('sms', data.masked_phone || maskedPhone);
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify & create account';
                    otpMessage.innerHTML = '<span class="text-success">' + (data.message || 'Email verified.') + '</span>';
                } else {
                    otpMessage.innerHTML = '<span class="text-danger">' + (data.message || 'Invalid email code') + '</span>';
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify email code';
                }
            })
            .catch(function () {
                otpMessage.innerHTML = '<span class="text-danger">Network error.</span>';
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify email code';
            });
    }

    function verifySmsOTP(code) {
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying…';

        var fd = new FormData();
        fd.append('action', 'verify_sms_otp');
        fd.append('email', formData.email);
        fd.append('password', formData.password);
        fd.append('name', formData.name);
        fd.append('phone', formData.phone);
        fd.append('location', formData.location);
        fd.append('user_type', formData.user_type);
        fd.append('otp', code);

        fetch(VERIFY_OTP_URL, { method: 'POST', body: fd, credentials: 'include', mode: 'cors' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.step === 'sms' && !data.account_created) {
                    setOtpStep('sms', data.masked_phone || maskedPhone);
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify & create account';
                    otpMessage.innerHTML = '<span class="text-success">' + (data.message || 'Email verified.') + '</span>';
                    return;
                }
                if (data.success) {
                    otpMessage.innerHTML = '<span class="text-success">Account created. Redirecting…</span>';
                    setTimeout(function () {
                        window.location.href = LOGIN_URL + '?message=' + encodeURIComponent('Account created. Sign in with your email and password.');
                    }, 1200);
                } else {
                    if (data.step === 'email') setOtpStep('email', maskedPhone);
                    otpMessage.innerHTML = '<span class="text-danger">' + (data.message || 'Verification failed') + '</span>';
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = otpStep === 'email' ? 'Verify email code' : 'Verify & create account';
                }
            })
            .catch(function () {
                otpMessage.innerHTML = '<span class="text-danger">Network error.</span>';
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify & create account';
            });
    }

    function startResendTimer() {
        var t = 60;
        resendBtn.disabled = true;
        var id = setInterval(function () {
            resendBtn.textContent = 'Resend (' + t + 's)';
            t--;
            if (t < 0) {
                clearInterval(id);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend codes';
            }
        }, 1000);
    }
})();
    </script>
</body>
</html>
