<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /ILSHD/user/submit-ticket.php');
    exit;
}

$error   = '';
$success = '';

// Clean up expired tokens
cleanupExpiredTokens();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -10) !== '@ub.edu.ph') {
        $error = 'Please enter a valid @ub.edu.ph email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE school_email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
               ->execute([$email, $code, $expires]);

            session_start();
            $_SESSION['reset_email'] = $email;

            sendEmail($email, "Password Reset", "Your verification code is: <b>$code</b>");
            $success = "A verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong>.<br>
                        <small class='text-muted'>(Demo code: <strong>$code</strong>)</small>";
        } else {
            $success = "If that email is registered, a code has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — ILS Help Desk</title>
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

            <h2 class="fw-bold mb-1" style="font-size:1.1rem;">Forgot Password</h2>
            <p class="text-muted mb-3" style="font-size:0.875rem;">
                Please provide your school email address to receive a password reset code.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <div class="d-grid mt-2">
                    <a href="/ILSHD/verify-code.php" class="btn btn-yellow">Enter Verification Code</a>
                </div>
            <?php else: ?>
            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">School Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@ub.edu.ph"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-yellow btn-lg">Request Reset Code</button>
                </div>
            </form>
            <?php endif; ?>

            <p class="text-center mt-3 mb-0" style="font-size:0.875rem;">
                <a href="/ILSHD/login.php" style="color:var(--ils-green);">Back to Login</a>
            </p>
        </div>
    </div>
</div>
<footer class="py-3 text-center ils-footer">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
