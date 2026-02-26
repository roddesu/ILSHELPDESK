<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    header('Location: /ILSHD/user/submit-ticket.php');
    exit;
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'] ?? '';

if (empty($email)) {
    header('Location: /ILSHD/forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $db = getDB();
        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
           ->execute([$email, $code, $expires]);

        sendEmail($email, "Password Reset", "Your verification code is: <b>$code</b>");
        $success = "A new verification code has been sent.";
    } else {
        $code = trim($_POST['code'] ?? '');

        if (!$code) {
            $error = 'Please enter the verification code.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$email, $code]);
            $row  = $stmt->fetch();

            if ($row) {
                $_SESSION['reset_verified'] = true;
                header('Location: /ILSHD/reset-password.php');
                exit;
            } else {
                $error = 'Invalid or expired code. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code — ILS Help Desk</title>
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
            <p class="text-muted text-center mb-4" style="font-size:0.875rem;">
                Provide the verification code sent to your email to proceed.
            </p>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-4">
                    <label for="code" class="form-label">Verification Code</label>
                    <input type="text" id="code" name="code" class="form-control form-control-lg text-center"
                           placeholder="Enter code" maxlength="6"
                           value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                           style="letter-spacing:0.3em; font-size:1.4rem;" required>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-yellow btn-lg">Confirm</button>
                </div>
            </form>

            <form method="POST" class="text-center">
                <button type="submit" name="resend" class="btn btn-link text-decoration-none" style="color:var(--ils-green); font-size:0.875rem;">Resend Verification Code</button>
            </form>
        </div>
    </div>
</div>
<footer class="py-3 text-center ils-footer">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
