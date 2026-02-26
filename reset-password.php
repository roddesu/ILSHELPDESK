<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    header('Location: /ILSHD/user/submit-ticket.php');
    exit;
}

$email    = $_SESSION['reset_email']    ?? '';
$verified = $_SESSION['reset_verified'] ?? false;
if (!$email || !$verified) {
    header('Location: /ILSHD/forgot-password.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass    = $_POST['password']         ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$pass || !$confirm) {
        $error = 'Please fill in both fields.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $db = getDB();
        $db->prepare("UPDATE users SET password = ? WHERE school_email = ?")->execute([password_hash($pass, PASSWORD_DEFAULT), $email]);
        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        unset($_SESSION['reset_email'], $_SESSION['reset_verified']);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Password — ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body class="d-flex flex-column min-vh-100" style="background:var(--ils-bg);">
<div class="d-flex flex-grow-1 align-items-center justify-content-center py-4">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="auth-logo mb-2">
                <span class="ils-script">ils.</span>
                <span class="ils-helpdesk">Help Desk</span>
            </div>
        </div>

        <div class="card shadow-sm border-0 p-4">
            <?php if ($success): ?>
                <div class="text-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-3 mx-auto"
                         style="width:72px;height:72px;background:#E8F5E9;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 class="h4 mb-2">Password Reset Successfully</h2>
                    <p class="text-muted mb-4" style="font-size:0.9rem;">Your password has been updated. You can now log in with your new password.</p>
                    <div class="d-grid">
                        <a href="/ILSHD/" class="btn btn-yellow btn-lg">Back to Login</a>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted text-center mb-4" style="font-size:0.875rem;">Create new password</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="mb-3">
                        <label for="pw1" class="form-label">New password</label>
                        <div class="input-group">
                            <input type="password" id="pw1" name="password" class="form-control"
                                   placeholder="Enter new password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('pw1', this)" aria-label="Toggle password">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="pw2" class="form-label">Confirm password</label>
                        <div class="input-group">
                            <input type="password" id="pw2" name="confirm_password" class="form-control"
                                   placeholder="Confirm new password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('pw2', this)" aria-label="Toggle password">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-yellow btn-lg">Save Password</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<footer class="py-3 text-center ils-footer">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
</body>
</html>
