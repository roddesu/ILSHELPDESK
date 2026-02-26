<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/ILSHD/admin/tickets.php' : '/ILSHD/user/submit-ticket.php'));
    exit;
}

require_once __DIR__ . '/includes/auth_actions.php';
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
            <button type="button" class="btn btn-yellow btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
            <button type="button" class="btn btn-outline-warning btn-lg" style="color:var(--ils-yellow); border-color:var(--ils-yellow);" data-bs-toggle="modal" data-bs-target="#signupModal">Sign Up</button>
        </div>
    </div>
</div>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">Welcome Back</h3>
                    <p class="text-muted small">Login to your account</p>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($successMsg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($loginError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($loginError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label for="email" class="form-label">Student Number</label>
                        <input type="text" id="email" name="email" class="form-control"
                               placeholder="Student Number"
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
                            <label class="form-check-label small" for="remember">Remember me</label>
                        </div>
                        <button type="button" class="btn btn-link text-decoration-none small p-0" style="color:var(--ils-green);" data-bs-target="#forgotModal" data-bs-toggle="modal">Forgot Password?</button>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-yellow btn-lg">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">Create Account</h3>
                    <p class="text-muted small">Join the help desk system</p>
                </div>

                <?php if ($signupError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($signupError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="signup">
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="First name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-auto" style="width:80px;">
                            <label class="form-label small">M.I.</label>
                            <input type="text" name="middle_initial" class="form-control" placeholder="M.I." maxlength="2" value="<?= htmlspecialchars($_POST['middle_initial'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="Last name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-auto" style="width:90px;">
                            <label class="form-label small">Suffix</label>
                            <input type="text" name="suffix" class="form-control" placeholder="Jr..." value="<?= htmlspecialchars($_POST['suffix'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Department <span class="text-danger">*</span></label>
                        <select name="department" class="form-select" required>
                            <option value="">Select department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Classification <span class="text-danger">*</span></label>
                        <select name="classification" class="form-select" required>
                            <option value="">Select classification</option>
                            <?php foreach ($classifications as $c): ?>
                                <option value="<?= $c ?>" <?= ($_POST['classification'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">School Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="you@ub.edu.ph" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="spass" name="password" class="form-control" placeholder="Min. 6 chars" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('spass', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="scpass" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('scpass', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-yellow btn-lg">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">Forgot Password</h3>
                    <p class="text-muted small">Enter your email to receive a reset code</p>
                </div>

                <?php if ($forgotSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($forgotSuccess) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($forgotError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($forgotError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="forgot">
                    <div class="mb-3">
                        <label class="form-label small">School Email</label>
                        <input type="email" name="email" class="form-control" placeholder="you@ub.edu.ph" required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-yellow btn-lg">Send Code</button>
                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-link text-decoration-none small" style="color:var(--ils-green);" data-bs-target="#loginModal" data-bs-toggle="modal">Back to Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Verify Code Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">Verify Code</h3>
                    <p class="text-muted small">Enter the code sent to your email</p>
                </div>

                <?php if ($verifySuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $verifySuccess ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($verifyError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($verifyError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="verify">
                    <div class="mb-4">
                        <input type="text" name="code" class="form-control form-control-lg text-center"
                               placeholder="000000" maxlength="6"
                               style="letter-spacing:0.3em; font-size:1.4rem;" required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-yellow btn-lg">Confirm</button>
                    </div>
                    <div class="text-center d-flex justify-content-between">
                        <button type="submit" name="resend" value="1" class="btn btn-link text-decoration-none small p-0" style="color:var(--ils-green);">Resend Code</button>
                        <button type="button" class="btn btn-link text-decoration-none small p-0 text-muted" data-bs-target="#forgotModal" data-bs-toggle="modal">Change Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">Reset Password</h3>
                    <p class="text-muted small">Create a new password for your account</p>
                </div>

                <?php if ($resetError): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($resetError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="reset_password">
                    <div class="mb-3">
                        <label class="form-label small">New Password</label>
                        <div class="input-group">
                            <input type="password" id="rpass" name="password" class="form-control" placeholder="At least 6 characters" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('rpass', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="rcpass" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('rcpass', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-yellow btn-lg">Save Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<footer class="py-3 text-center ils-footer">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
<?php if ($loginError || $successMsg): ?>
<script>
    var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
</script>
<?php endif; ?>
<?php if ($signupError): ?>
<script>
    var signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
    signupModal.show();
</script>
<?php endif; ?>
<?php if ($forgotError || $forgotSuccess): ?>
<script>
    var forgotModal = new bootstrap.Modal(document.getElementById('forgotModal'));
    forgotModal.show();
</script>
<?php endif; ?>
<?php if ($showVerifyModal): ?>
<script>
    var verifyModal = new bootstrap.Modal(document.getElementById('verifyModal'));
    verifyModal.show();
</script>
<?php endif; ?>
<?php if ($showResetModal): ?>
<script>
    var resetModal = new bootstrap.Modal(document.getElementById('resetModal'));
    resetModal.show();
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupValidation(passId, confirmId) {
        const p = document.getElementById(passId);
        const c = document.getElementById(confirmId);
        if (!p || !c) return;

        const validate = () => {
            if (c.value === '') {
                c.classList.remove('is-invalid', 'is-valid');
            } else if (p.value !== c.value) {
                c.classList.add('is-invalid');
                c.classList.remove('is-valid');
            } else {
                c.classList.remove('is-invalid');
                c.classList.add('is-valid');
            }
        };
        p.addEventListener('input', validate);
        c.addEventListener('input', validate);
    }
    setupValidation('spass', 'scpass'); // Signup
    setupValidation('rpass', 'rcpass'); // Reset Password
});
</script>
</body>
</html>
