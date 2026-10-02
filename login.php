<?php
/**
 * Kanban sign-in: email/password (same users table) + Google OAuth on this host.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_portal_config.php';
require_once __DIR__ . '/includes/freelance_projects.php';

cg_kanban_auth_debug_log('login.php bootstrap', ['authenticated' => cg_kanban_is_authenticated()]);

if (cg_kanban_is_authenticated() && cg_user_can_access_freelance_kanban()) {
    $path = '/boards';
    if (isset($_GET['redirect']) && (string) $_GET['redirect'] !== '') {
        $n = cg_kanban_normalize_redirect_path((string) $_GET['redirect']);
        if ($n !== null && !cg_kanban_is_login_url_path($n)) {
            $path = $n;
        }
    }
    $dest = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . $path;
    cg_kanban_log_redirect($dest, 'login: already authenticated with kanban access');
    header('Location: ' . $dest, true, 302);
    exit;
}

$error = '';
if (cg_kanban_is_authenticated() && !cg_user_can_access_freelance_kanban()) {
    $error = 'You are signed in, but this account does not have freelancer access. '
        . 'Become a freelancer at cinegrid.net/become_freelancer or sign out and use a freelancer account.';
}
$email = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'Invalid email or password';
            } elseif (isset($user['merged_into_user_id']) && $user['merged_into_user_id'] !== null) {
                $error = 'This account has been merged. Please use your primary account.';
            } elseif (isset($user['is_merged']) && (int)$user['is_merged'] === 1) {
                $error = 'This account has been merged. Please use your primary account.';
            } elseif (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
                $error = 'Your account has been suspended. ' . ($user['suspension_reason'] ?? 'No reason provided.');
            } elseif (empty($user['password']) || $user['password'] === null) {
                $error = 'Invalid email or password';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
                $_SESSION['is_verified'] = $user['is_verified'] ?? 0;
                $_SESSION['user_role'] = $user['role'] ?? 'customer';

                session_regenerate_id(true);

                if (function_exists('cg_kanban_persist_login_for_days')) {
                    cg_kanban_persist_login_for_days((int)$user['id']);
                }

                if (!cg_user_can_access_freelance_kanban()) {
                    $error = 'This account does not have freelancer access. '
                        . 'Become a freelancer at cinegrid.net/become_freelancer or use a different account.';
                } else {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                $redirect_to = '/boards';
                if (!empty($_POST['redirect'])) {
                    $n = cg_kanban_normalize_redirect_path((string) $_POST['redirect']);
                    if ($n !== null && !cg_kanban_is_login_url_path($n)) {
                        $redirect_to = $n;
                    }
                }
                if (preg_match('/^https?:\/\//', $redirect_to)) {
                    $parsed = parse_url($redirect_to);
                    $expected = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
                    $rh = isset($parsed['host']) ? preg_replace('/:\d+$/', '', $parsed['host']) : '';
                    if ($expected !== '' && $rh !== '' && strcasecmp($rh, $expected) === 0) {
                        $redirect_to = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
                    } else {
                        $redirect_to = '/boards';
                    }
                } else {
                    $redirect_to = str_replace(['http://', 'https://'], '', $redirect_to);
                    if (strpos($redirect_to, '..') !== false) {
                        $redirect_to = '/boards';
                    }
                }
                if (strpos($redirect_to, '/') !== 0) {
                    $redirect_to = '/' . $redirect_to;
                }
                if (cg_kanban_is_login_url_path($redirect_to)) {
                    $redirect_to = '/boards';
                }

                cg_kanban_log_redirect($redirect_to, 'login: POST success');
                header('Location: ' . $redirect_to);
                exit;
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch (Exception $e) {
            error_log('kanban login: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

$redirect_url = '/boards';
if (isset($_GET['redirect']) && (string) $_GET['redirect'] !== '') {
    $n = cg_kanban_normalize_redirect_path((string) $_GET['redirect']);
    if ($n !== null && !cg_kanban_is_login_url_path($n)) {
        $redirect_url = $n;
    }
}
$return_after_login = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . $redirect_url;
$google_url = CG_PORTAL_SELF_ORIGIN . '/google_login?redirect=' . rawurlencode($return_after_login);

$stay_signed_days = function_exists('cgPersistentSessionTtlSeconds')
    ? (int) round(cgPersistentSessionTtlSeconds() / 86400)
    : 30;
$err = $_GET['error'] ?? '';
if ($err === 'not_freelancer' && $error === '') {
    $error = 'This account cannot use Kanban. Become a freelancer at cinegrid.net/become_freelancer '
        . 'or sign in with a freelancer account.';
}
$msg = isset($_GET['message']) ? urldecode((string)$_GET['message']) : '';
$reason = $_GET['reason'] ?? '';
$register_url = rtrim(CG_PORTAL_SELF_ORIGIN, '/') . '/register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — CineGrid Kanban</title>
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
        .login-container {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
        }
        .login-header { text-align: center; margin-bottom: 28px; }
        .login-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .login-brand-icon {
            width: 2.5rem;
            height: 2.5rem;
            flex-shrink: 0;
            color: #ff3c3c;
        }
        .login-brand-text {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }
        .login-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .login-header p { color: #6b7280; font-size: 0.95rem; margin: 0; }
        .login-options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 8px; }
        .btn-signin-option {
            width: 100%;
            padding: 14px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-signin-cinegrid {
            background: #ff3c3c;
            color: #fff;
        }
        .btn-signin-cinegrid:hover {
            background: #e02e2e;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 60, 60, 0.45);
        }
        .btn-signin-cinegrid-logo {
            width: 20px;
            height: 20px;
            object-fit: contain;
            flex-shrink: 0;
            border-radius: 4px;
            /* CineGrid favicon on red: render as white mark */
            filter: brightness(0) invert(1);
        }
        .btn-signin-google {
            background: #fff;
            color: #1f2937;
            border: 2px solid #e5e7eb;
        }
        .btn-signin-google:hover {
            background: #f9fafb;
            color: #111827;
            border-color: #d1d5db;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }
        .login-form-section { display: none; margin-top: 20px; }
        .login-form-section.show { display: block; }
        .divider {
            display: flex; align-items: center; text-align: center; margin: 20px 0;
            color: #6b7280; font-size: 0.9rem;
        }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #e5e7eb; }
        .divider span { padding: 0 12px; }
        .form-group { margin-bottom: 18px; }
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
        .form-check { margin-bottom: 16px; }
        .form-check-label { color: #6b7280; font-size: 0.9rem; }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #ff3c3c;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-login:hover { background: #e62e2e; transform: translateY(-1px); }
        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .back-link { text-align: center; margin-top: 22px; }
        .back-link a {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .back-link a:hover { color: #ff3c3c; text-decoration: underline; }
    </style>
    <script>
        function showEmailForm() {
            document.getElementById('emailForm').classList.add('show');
            document.getElementById('email').focus();
        }
        <?php if ($error !== '' || ($msg !== '' && $err === '')): ?>
        document.addEventListener('DOMContentLoaded', function() { showEmailForm(); });
        <?php endif; ?>
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-brand" role="group" aria-label="Kanban">
                <svg class="login-brand-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                    <rect x="48" y="128" width="112" height="256" rx="24" fill="currentColor"/>
                    <rect x="200" y="80" width="112" height="352" rx="24" fill="currentColor"/>
                    <rect x="352" y="160" width="112" height="224" rx="24" fill="currentColor"/>
                </svg>
                <span class="login-brand-text">Kanban</span>
            </div>
            <h1>Sign in</h1>
            <p>Continue to your boards</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($err !== ''): ?>
            <div class="alert alert-danger">
                <strong><?php echo htmlspecialchars($err); ?></strong>
                <?php if ($msg !== ''): ?>
                    <div class="mt-1"><?php echo htmlspecialchars($msg); ?></div>
                <?php endif; ?>
                <?php if ($reason !== ''): ?>
                    <div class="text-muted small mt-1"><?php echo htmlspecialchars($reason); ?></div>
                <?php endif; ?>
            </div>
        <?php elseif ($msg !== '' && $err === ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="login-options">
            <button type="button" class="btn-signin-option btn-signin-cinegrid" onclick="showEmailForm()">
                <img src="https://cinegrid.net/uploads/fav.png" alt="" width="20" height="20" class="btn-signin-cinegrid-logo" aria-hidden="true">
                Sign in with CineGrid
            </button>
            <a class="btn-signin-option btn-signin-google" href="<?php echo htmlspecialchars($google_url); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Sign in with Google
            </a>
        </div>

        <div class="login-form-section" id="emailForm">
            <div class="divider"><span>or</span></div>
            <form method="post" action="">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                <div class="form-group">
                    <label class="form-label" for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="you@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Password">
                </div>
                <p class="text-muted small mb-3">You will stay signed in on this device until you log out manually (about <?php echo (int) $stay_signed_days; ?> days of inactivity).</p>
                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt me-2"></i>Sign in</button>
            </form>
        </div>

        <div class="back-link">
            <a href="<?php echo htmlspecialchars($register_url, ENT_QUOTES, 'UTF-8'); ?>">Create an account</a>
        </div>
    </div>
</body>
</html>
