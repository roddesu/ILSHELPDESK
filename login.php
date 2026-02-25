<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/ILSHD/admin/tickets.php' : '/ILSHD/user/submit-ticket.php'));
    exit;
}

$error = '';
$successMsg = isset($_GET['registered']) ? 'Account created successfully! You can now login.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE school_email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            loginUser($user);
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + 30 * 86400, '/');
            }
            header('Location: ' . ($user['role'] === 'admin' ? '/ILSHD/admin/tickets.php' : '/ILSHD/user/submit-ticket.php'));
            exit;
        } else {
            if (!$user) {
                $error = 'Account not found. Please sign up and verify your email.';
            } else {
                $error = 'Incorrect password.';
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
    <title>Login — ILS Help Desk</title>
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

            <?php if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($successMsg) ?>
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
                    <label for="email" class="form-label">School Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@ub.edu.ph"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Enter password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', this)" aria-label="Toggle password">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                               <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="/ILSHD/forgot-password.php" class="text-decoration-none" style="color:var(--ils-green); font-size:0.875rem;">Forgot Password?</a>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-yellow btn-lg">Login</button>
                </div>
            </form>

            <p class="text-center mt-3 mb-0" style="font-size:0.875rem;">
                Don't have an account? <a href="/ILSHD/signup.php" style="color:var(--ils-green);">Sign up</a>
            </p>
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
