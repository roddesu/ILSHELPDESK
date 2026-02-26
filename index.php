<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? '/ILSHD/admin/tickets.php' : '/ILSHD/user/submit-ticket.php'));
    exit;
}

require_once __DIR__ . '/includes/auth_actions.php';

// Initialize view variables to prevent undefined variable warnings
$successMsg      = $successMsg      ?? null;
$loginError      = $loginError      ?? null;
$signupError     = $signupError     ?? null;
$forgotError     = $forgotError     ?? null;
$forgotSuccess   = $forgotSuccess   ?? null;
$showVerifyModal = $showVerifyModal ?? false;
$verifyError     = $verifyError     ?? null;
$verifySuccess   = $verifySuccess   ?? null;
$showResetModal  = $showResetModal  ?? false;
$resetError      = $resetError      ?? null;
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
                        <label for="email" class="form-label">Email</label>
                        <input type="text" id="email" name="email" class="form-control"
                               placeholder="Enter your email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Enter your password" required>
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
                        <a href="#" class="text-decoration-none small" style="color:var(--ils-green);" data-bs-toggle="modal" data-bs-target="#forgotModal" data-bs-dismiss="modal">Forgot Password?</a>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-yellow btn-lg">Login</button>
                    </div>
                    <p class="text-center small mb-0">Don't have an account? <a href="#" class="text-decoration-none fw-semibold" style="color:var(--ils-green);" data-bs-target="#signupModal" data-bs-toggle="modal">Sign up</a></p>
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
                <h5 class="fw-bold mb-1">Reset your password</h5>
                <p class="text-muted small mb-4">Enter your School Email, and we'll send you a code to reset your password.</p>

                <?php if ($forgotError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($forgotError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($forgotSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($forgotSuccess) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="forgot">
                    <div class="mb-4">
                        <label class="form-label">School Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </span>
                            <input type="email" name="email" class="form-control" placeholder="you@ub.edu.ph"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-yellow btn-lg">Request Reset Link</button>
                    </div>
                </form>
                <p class="text-center small mb-0">
                    <a href="#" class="text-decoration-none fw-semibold" style="color:var(--ils-green);" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Back to Login</a>
                </p>
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
                <h5 class="fw-bold mb-1">Verify Code</h5>
                <p class="text-muted small mb-4">Provide the verification code sent to your email to proceed.</p>

                <?php if ($verifySuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $verifySuccess ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($verifyError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($verifyError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="verify">
                    <div class="mb-4">
                        <label class="form-label">Verification Code</label>
                        <input type="text" name="code" class="form-control form-control-lg text-center"
                               placeholder="Enter code" maxlength="6"
                               style="letter-spacing:0.3em; font-size:1.4rem;" required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-yellow btn-lg">Confirm</button>
                    </div>
                </form>
                <form method="POST" class="text-center">
                    <input type="hidden" name="action" value="verify">
                    <button type="submit" name="resend" class="btn btn-link text-decoration-none" style="color:var(--ils-green); font-size:0.875rem;">Resend Verification Code</button>
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
                <h5 class="fw-bold mb-1">Create New Password</h5>
                <p class="text-muted small mb-4">Choose a new password for your account.</p>

                <?php if ($resetError): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($resetError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="reset_password">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" id="rpw1" name="password" class="form-control" placeholder="Enter new password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('rpw1', this)" aria-label="Toggle password">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="rpw2" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('rpw2', this)" aria-label="Toggle password">
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
            </div>
        </div>
    </div>
</div>


<footer class="py-3 text-center ils-footer">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
<?php if ($showResetModal): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('resetModal')).show());</script>
<?php elseif ($showVerifyModal): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('verifyModal')).show());</script>
<?php elseif ($forgotError || $forgotSuccess): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('forgotModal')).show());</script>
<?php elseif ($loginError || $successMsg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('loginModal')).show());</script>
<?php elseif ($signupError): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('signupModal')).show());</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const p = document.getElementById('spass');
    const c = document.getElementById('scpass');
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
});
</script>
</body>
</html>
