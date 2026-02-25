<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
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
$success = '';

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
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $db   = getDB();
        $db->prepare("UPDATE users SET password = ? WHERE school_email = ?")->execute([$hash, $email]);
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
    <title>Reset Password — ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body class="d-flex flex-column min-vh-100" style="background:var(--ils-bg);">
<div class="d-flex flex-grow-1 align-items-center justify-content-center py-4">
    <div class="auth-card">
        <div class="card shadow-sm border-0 p-4">
            <div class="auth-logo text-center mb-3">
                <span class="ils-script">ils.</span>
                <span class="ils-helpdesk">Help Desk</span>
            </div>

            <?php if ($success): ?>
                <div class="text-center py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:64px;height:64px;background:#E8F5E9;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 class="fw-bold mb-2">Password Updated!</h2>
                    <p class="text-muted mb-4" style="font-size:0.875rem;">Your password has been successfully changed.</p>
                    <div class="d-grid">
                        <a href="/ILSHD/login.php" class="btn btn-yellow btn-lg">Back to Login</a>
                    </div>
                </div>
            <?php else: ?>
                <h2 class="fw-bold mb-1" style="font-size:1.1rem;">Create New Password</h2>
                <p class="text-muted mb-3" style="font-size:0.875rem;">Enter your new password below.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="At least 6 characters" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', this)" aria-label="Toggle">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                   placeholder="Repeat password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password', this)" aria-label="Toggle">
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
