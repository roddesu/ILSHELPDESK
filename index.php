<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/ILSHD/admin/tickets.php' : '/ILSHD/user/submit-ticket.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body class="d-flex flex-column min-vh-100" style="background:var(--ils-bg);">
<div class="d-flex flex-grow-1 align-items-center justify-content-center py-5">
    <div class="auth-card text-center">
        <div class="mb-4">
            <div class="auth-logo mb-2">
                <span class="ils-script">ils.</span>
                <span class="ils-helpdesk">Help Desk</span>
            </div>
            <p class="text-muted mb-0" style="font-size:0.9rem;">School IT Support System</p>
        </div>

        <div class="d-grid gap-3">
            <a href="/ILSHD/login.php" class="btn btn-yellow btn-lg">Login</a>
            <a href="/ILSHD/signup.php" class="btn btn-outline-warning btn-lg" style="color:var(--ils-yellow); border-color:var(--ils-yellow);">Sign Up</a>
        </div>
    </div>
</div>
<footer class="py-3 text-center ils-footer">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
