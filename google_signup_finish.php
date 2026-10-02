<?php
/**
 * New Google users on Kanban: phone + location + SMS OTP, freelance role.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_portal_config.php';

$dbPath = dirname(__DIR__) . '/public_html/includes/db.php';
if (!file_exists($dbPath)) {
    die('Database configuration not found');
}
require_once $dbPath;

function cg_kanban_check_database_schema(PDO $pdo): bool {
    try {
        $stmt = $pdo->query('DESCRIBE users');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['Field'] === 'password' && $column['Null'] === 'NO' && $column['Default'] === null) {
                try {
                    $pdo->exec('ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL');
                } catch (Exception $e) {
                    error_log('kanban google_signup_finish schema: ' . $e->getMessage());
                    return false;
                }
            }
        }
        return true;
    } catch (Exception $e) {
        error_log('kanban google_signup_finish schema: ' . $e->getMessage());
        return false;
    }
}

function cg_kanban_redirect_same_host(string $url): bool {
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    $p = parse_url($url);
    $h = preg_replace('/:\d+$/', '', $p['host'] ?? '');
    $cur = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    return $h !== '' && strcasecmp($h, $cur) === 0;
}

function cg_kanban_safe_redirect_after_oauth(): string {
    $url = $_SESSION['google_oauth_redirect'] ?? CG_PORTAL_BASE_URL;
    if (cg_kanban_redirect_same_host($url)) {
        return $url;
    }
    return CG_PORTAL_BASE_URL;
}

function cg_kanban_mask_phone(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone);
    $len = strlen($phone);
    if ($len < 4) {
        return '****';
    }
    return str_repeat('*', max(0, $len - 4)) . substr($phone, -4);
}

if (cg_kanban_is_authenticated()) {
    require_once __DIR__ . '/includes/freelance_projects.php';
    if (cg_user_can_access_freelance_kanban()) {
        $path = '/';
        if (isset($_GET['redirect']) && (string)$_GET['redirect'] !== '') {
            $n = cg_kanban_normalize_redirect_path((string)$_GET['redirect']);
            if ($n !== null && !cg_kanban_is_login_url_path($n)) {
                $path = $n;
            }
        }
        $dest = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . $path;
        cg_kanban_log_redirect($dest, 'google_signup_finish: already authenticated');
        header('Location: ' . $dest, true, 302);
        exit;
    }
}

$profile_error = '';
$show_sms_otp = !empty($_SESSION['google_awaiting_sms']);
$sms_masked_phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_google_sms'])) {
    unset($_SESSION['google_awaiting_sms'], $_SESSION['google_pending_phone'], $_SESSION['google_pending_location']);
    $show_sms_otp = false;
    if (!empty($_SESSION['google_email'])) {
        require_once dirname(__DIR__) . '/public_html/auth_system/includes/otp_service.php';
        (new OTPService())->deleteByEmail((string) $_SESSION['google_email']);
    }
}

// Verify SMS then create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_sms_otp']) && !empty($_SESSION['google_awaiting_sms'])) {
    $smsOtp = trim((string) ($_POST['google_sms_otp'] ?? ''));
    $name = $_SESSION['google_name'] ?? '';
    $email = $_SESSION['google_email'] ?? '';
    $picture = $_SESSION['google_picture'] ?? '';
    $role = 'freelance';
    $phone = preg_replace('/\D/', '', (string) ($_SESSION['google_pending_phone'] ?? ''));
    $location = trim((string) ($_SESSION['google_pending_location'] ?? ''));

    if (!$name || !$email || $phone === '') {
        header('Location: /login?error=oauth_failed&message=' . urlencode('Session expired. Please sign in with Google again.'));
        exit;
    }
    if (!preg_match('/^\d{6}$/', $smsOtp)) {
        $profile_error = 'Enter the 6-digit SMS code.';
        $show_sms_otp = true;
        $sms_masked_phone = cg_kanban_mask_phone($phone);
    } else {
        try {
            require_once dirname(__DIR__) . '/public_html/auth_system/includes/otp_service.php';
            require_once dirname(__DIR__) . '/public_html/auth_system/setup/ensure_dual_otp_schema.php';
            $pdo = getDB();
            cg_ensure_dual_otp_schema($pdo);
            $otpService = new OTPService();
            $row = $otpService->getPendingOTP($email, 'google_signup');
            if (!$row || strtotime($row['expires_at']) < time()) {
                $profile_error = 'Code expired. Please request a new code.';
                $show_sms_otp = true;
                $sms_masked_phone = cg_kanban_mask_phone($phone);
            } elseif ((string) ($row['sms_otp'] ?? '') !== $smsOtp) {
                $profile_error = 'Invalid SMS verification code.';
                $show_sms_otp = true;
                $sms_masked_phone = cg_kanban_mask_phone($phone);
            } else {
                $otpService->markUsed((int) $row['id']);
                $otpService->deleteByEmail($email);
                cg_kanban_check_database_schema($pdo);

                $stmt = $pdo->query('SHOW COLUMNS FROM users');
                $columns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $insertFields = ['name', 'email', 'role', 'password', 'is_verified'];
                $insertValues = [$name, $email, $role, null, 1];
                $placeholders = ['?', '?', '?', '?', '?'];

                if (in_array('phone', $columns, true)) {
                    $insertFields[] = 'phone';
                    $insertValues[] = $phone;
                    $placeholders[] = '?';
                }
                if (in_array('location', $columns, true)) {
                    $insertFields[] = 'location';
                    $insertValues[] = $location;
                    $placeholders[] = '?';
                }
                if (in_array('profile_pic', $columns, true) && $picture !== '') {
                    $insertFields[] = 'profile_pic';
                    $insertValues[] = $picture;
                    $placeholders[] = '?';
                }
                if (in_array('email_verified', $columns, true)) {
                    $insertFields[] = 'email_verified';
                    $insertValues[] = 1;
                    $placeholders[] = '?';
                }
                if (in_array('phone_verified', $columns, true)) {
                    $insertFields[] = 'phone_verified';
                    $insertValues[] = 1;
                    $placeholders[] = '?';
                }

                $sql = 'INSERT INTO users (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
                $user_id = $pdo->lastInsertId();

                try {
                    $notifierPath = dirname(__DIR__) . '/public_html/includes/admin_notification_service.php';
                    if (file_exists($notifierPath)) {
                        require_once $notifierPath;
                        $notifier = new AdminNotificationService();
                        $notifier->notifyNewUser($user_id, $name, $email, $phone, $location, $role);
                    }
                } catch (Exception $e) {
                    error_log('kanban google_signup_finish notify: ' . $e->getMessage());
                }

                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;

                unset(
                    $_SESSION['google_name'],
                    $_SESSION['google_email'],
                    $_SESSION['google_role'],
                    $_SESSION['google_picture'],
                    $_SESSION['google_phone'],
                    $_SESSION['google_awaiting_sms'],
                    $_SESSION['google_pending_phone'],
                    $_SESSION['google_pending_location']
                );
                $redirect_url = cg_kanban_safe_redirect_after_oauth();
                unset($_SESSION['google_oauth_redirect'], $_SESSION['google_oauth_kanban_portal']);
                session_regenerate_id(true);
                if (function_exists('cg_kanban_persist_login_for_days')) {
                    cg_kanban_persist_login_for_days((int) $user_id);
                }
                session_write_close();
                header('Location: ' . $redirect_url);
                exit;
            }
        } catch (Exception $e) {
            error_log('kanban google_signup_finish sms verify: ' . $e->getMessage());
            $profile_error = 'Could not complete registration. Please try again.';
            $show_sms_otp = true;
        }
    }
}

// Phone + location → send SMS (do not create yet)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'], $_POST['location']) && empty($_POST['google_sms_otp']) && empty($_POST['reset_google_sms'])) {
    $phone = preg_replace('/\D/', '', trim((string) ($_POST['phone'] ?? '')));
    $location = trim((string) ($_POST['location'] ?? ''));
    $role = 'freelance';
    $name = $_SESSION['google_name'] ?? '';
    $email = $_SESSION['google_email'] ?? '';
    $picture = $_SESSION['google_picture'] ?? '';

    if (!$name || !$email) {
        header('Location: /login?error=oauth_failed&message=' . urlencode('Session expired. Please sign in with Google again.'));
        exit;
    }

    $isValidPhone = (bool) preg_match('/^9\d{9}$/', $phone) || (bool) preg_match('/^0\d{8}$/', $phone);
    if (!$isValidPhone) {
        $profile_error = 'Phone number must start with 9 (10 digits) or 0 (9 digits).';
    } else {
        try {
            $pdo = getDB();

            $checkStmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $checkStmt->execute([$email]);
            $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                if (!empty($existingUser['merged_into_user_id']) || (!empty($existingUser['is_merged']) && (int) $existingUser['is_merged'] === 1)) {
                    unset($_SESSION['google_name'], $_SESSION['google_email'], $_SESSION['google_role'], $_SESSION['google_picture'], $_SESSION['google_phone'], $_SESSION['google_oauth_redirect'], $_SESSION['google_oauth_kanban_portal']);
                    header('Location: /login?error=account_merged');
                    exit;
                }
                if (isset($existingUser['is_verified']) && (int) $existingUser['is_verified'] === 0) {
                    $reason = $existingUser['suspension_reason'] ?? 'No reason provided.';
                    unset($_SESSION['google_name'], $_SESSION['google_email'], $_SESSION['google_role'], $_SESSION['google_picture'], $_SESSION['google_phone'], $_SESSION['google_oauth_redirect'], $_SESSION['google_oauth_kanban_portal']);
                    header('Location: /login?error=suspended&reason=' . urlencode($reason));
                    exit;
                }

                $_SESSION['user_id'] = $existingUser['id'];
                $_SESSION['user_name'] = $existingUser['name'];
                $_SESSION['user_role'] = $existingUser['role'] ?? 'customer';

                if ($picture !== '') {
                    try {
                        $c = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
                        if ($c->rowCount() > 0 && empty($existingUser['profile_pic'])) {
                            $u = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
                            $u->execute([$picture, $existingUser['id']]);
                        }
                    } catch (Throwable $e) {
                        error_log('kanban google_signup_finish profile_pic: ' . $e->getMessage());
                    }
                }

                unset($_SESSION['google_name'], $_SESSION['google_email'], $_SESSION['google_role'], $_SESSION['google_picture'], $_SESSION['google_phone']);
                $redirect_url = cg_kanban_safe_redirect_after_oauth();
                unset($_SESSION['google_oauth_redirect'], $_SESSION['google_oauth_kanban_portal']);
                session_regenerate_id(true);
                if (function_exists('cg_kanban_persist_login_for_days')) {
                    cg_kanban_persist_login_for_days((int) $existingUser['id']);
                }
                session_write_close();
                header('Location: ' . $redirect_url);
                exit;
            }

            $phoneDup = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND phone IS NOT NULL AND phone != ""');
            $phoneDup->execute([$phone]);
            if ($phoneDup->fetch()) {
                $profile_error = 'This phone number is already registered.';
            } else {
                require_once dirname(__DIR__) . '/public_html/auth_system/includes/otp_service.php';
                require_once dirname(__DIR__) . '/public_html/includes/SMS.php';
                $otpService = new OTPService();
                $phone = $otpService->normalizePhone($phone);
                if ($otpService->emailExists($email)) {
                    $profile_error = 'This email is already registered. Please log in instead.';
                } elseif ($otpService->phoneExists($phone)) {
                    $profile_error = 'This phone number is already registered. Please log in or use a different number.';
                } elseif ($otpService->getOTPCount($email, $phone) >= 3) {
                    $profile_error = 'Too many verification attempts. Please wait and try again.';
                } else {
                    $smsOtp = $otpService->generateOTP();
                    if (!$otpService->storeDualOTP($email, $phone, null, $smsOtp, 'google_signup', true)) {
                        $profile_error = 'Failed to store verification code.';
                    } else {
                        $sms = new SMS();
                        if (!$sms->sendOTP($phone, $smsOtp, 'google_signup')) {
                            $otpService->deleteByEmail($email);
                            $profile_error = 'Could not send SMS verification code. Check your phone number and try again.';
                        } else {
                            $otpService->logSendAttempt($email, $phone);
                            $_SESSION['google_awaiting_sms'] = true;
                            $_SESSION['google_pending_phone'] = $phone;
                            $_SESSION['google_pending_location'] = $location;
                            $show_sms_otp = true;
                            $sms_masked_phone = cg_kanban_mask_phone($phone);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('kanban google_signup_finish: ' . $e->getMessage());
            $profile_error = 'Could not start phone verification. Please try again.';
        }
    }
}

$name = $_SESSION['google_name'] ?? '';
$email = $_SESSION['google_email'] ?? '';
if ($name === '' || $email === '') {
    header('Location: /login?error=oauth_failed&message=' . urlencode('Please start sign-in from the login page.'));
    exit;
}

if ($show_sms_otp && $sms_masked_phone === '' && !empty($_SESSION['google_pending_phone'])) {
    $sms_masked_phone = cg_kanban_mask_phone((string) $_SESSION['google_pending_phone']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $show_sms_otp ? 'Verify phone' : 'Complete profile'; ?> — CineGrid Kanban</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <style>
        body {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 45%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 24px 16px;
        }
        .card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: none; max-width: 440px; width: 100%; }
        .badge-freelance { background: #dc2626; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <?php if ($show_sms_otp): ?>
                <div class="text-center mb-4">
                    <h1 class="h4 fw-bold">Verify your phone</h1>
                    <p class="text-muted small mb-0">SMS code sent to <strong><?php echo htmlspecialchars($sms_masked_phone); ?></strong></p>
                </div>
                <?php if ($profile_error !== ''): ?>
                    <div class="alert alert-danger py-2"><?php echo htmlspecialchars($profile_error); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="google_sms_otp" class="form-label">SMS verification code</label>
                        <input type="text" class="form-control text-center" id="google_sms_otp" name="google_sms_otp"
                               inputmode="numeric" pattern="\d{6}" maxlength="6" required
                               placeholder="6-digit code" autocomplete="one-time-code"
                               style="letter-spacing: 0.3em; font-size: 1.35rem; font-weight: 600;">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="fas fa-check me-2"></i>Verify &amp; create account
                    </button>
                </form>
                <form method="post" action="" class="mt-3 text-center">
                    <input type="hidden" name="reset_google_sms" value="1">
                    <button type="submit" class="btn btn-link btn-sm">Wrong number? Change phone</button>
                </form>
            <?php else: ?>
                <div class="text-center mb-4">
                    <h1 class="h4 fw-bold">Almost there</h1>
                    <p class="text-muted small mb-2">Welcome, <?php echo htmlspecialchars($name); ?></p>
                    <span class="badge badge-freelance">Freelancer</span>
                    <p class="text-muted small mt-3 mb-0">Add your phone number and location. We will send an SMS code to verify your phone.</p>
                </div>
                <?php if ($profile_error !== ''): ?>
                    <div class="alert alert-danger py-2"><?php echo htmlspecialchars($profile_error); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="phone" class="form-label"><i class="fas fa-phone me-2"></i>Phone number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required
                               placeholder="9XXXXXXXXX or 0XXXXXXXX" pattern="^(9\d{9}|0\d{8})$" maxlength="10"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="mb-4">
                        <label for="location" class="form-label"><i class="fas fa-map-marker-alt me-2"></i>Location</label>
                        <input type="text" class="form-control" id="location" name="location" required placeholder="City or area"
                               value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="fas fa-sms me-2"></i>Send SMS code
                    </button>
                </form>
            <?php endif; ?>
            <p class="text-center small text-muted mt-3 mb-0"><?php echo htmlspecialchars($email); ?></p>
        </div>
    </div>
</body>
</html>
