<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$email = $_SESSION['admin_reset_email'] ?? '';
if (!$email) { header('Location: /ILSHD/admin/forgot-password.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $db = getDB();
        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
           ->execute([$email, $code, $expires]);

        sendEmail($email, "Admin Password Reset", "Your verification code is: <b>$code</b>");
        $success = "A new verification code has been sent.";
    } else {
        $code = trim($_POST['code'] ?? '');
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$email, $code]);
        if ($stmt->fetch()) {
            $_SESSION['admin_reset_verified'] = true;
            header('Location: /ILSHD/admin/reset-password.php');
            exit;
        } else {
            $error = 'Invalid or expired code.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body class="d-flex flex-column min-vh-100" style="background:var(--ils-bg);">
<div class="d-flex flex-grow-1 align-items-center justify-content-center py-4">
    <div class="auth-card">
        <div class="card shadow-sm border-0 p-4">
            <div class="auth-logo text-center mb-4">
                <span class="ils-script">ils.</span>
                <span class="ils-helpdesk">Help Desk</span>
            </div>

            <h2 class="h4 mb-2">Verify Code</h2>
            <p class="text-muted mb-4" style="font-size:0.9rem;">Enter the 6-digit code sent to your email.</p>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="code" class="form-label">Verification Code</label>
                    <input type="text" id="code" name="code" class="form-control text-center" maxlength="6"
                           placeholder="000000" style="letter-spacing:0.2em; font-size:1.2rem;" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-yellow btn-lg">Confirm</button>
                </div>
            </form>

            <form method="POST" class="text-center mt-3">
                <button type="submit" name="resend" class="btn btn-link text-decoration-none" style="color:var(--ils-green); font-size:0.875rem;">Resend Verification Code</button>
            </form>
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
