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

$departments     = ['CICT', 'CAMS', 'CENG', 'CAS', 'CIT', 'CBA', 'COED', 'CNRS'];
$classifications = ['Student', 'Faculty', 'Staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first  = trim($_POST['first_name'] ?? '');
    $mi     = trim($_POST['middle_initial'] ?? '');
    $last   = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $dept   = $_POST['department'] ?? '';
    $class  = $_POST['classification'] ?? '';
    $email  = trim($_POST['email'] ?? '');
    $pass   = trim($_POST['password'] ?? '');

    if (!$first || !$last || !$dept || !$class || !$email || !$pass) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -10) !== '@ub.edu.ph') {
        $error = 'Please enter a valid @ub.edu.ph email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($dept, $departments) || !in_array($class, $classifications)) {
        $error = 'Invalid department or classification.';
    } else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE school_email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['signup_data'] = [
                'first_name' => $first,
                'middle_initial' => $mi,
                'last_name' => $last,
                'suffix' => $suffix,
                'department' => $dept,
                'classification' => $class,
                'email' => $email,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT)
            ];
            $_SESSION['signup_code'] = $code;
            sendEmail($email, "Verify Registration", "Your verification code is: <b>$code</b>");
            header('Location: /ILSHD/signup-verify.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — ILS Help Desk</title>
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

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" novalidate>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                               placeholder="First name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-auto" style="width:90px;">
                        <label for="middle_initial" class="form-label">M.I.</label>
                        <input type="text" id="middle_initial" name="middle_initial" class="form-control"
                               placeholder="M.I." maxlength="2" value="<?= htmlspecialchars($_POST['middle_initial'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                               placeholder="Last name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-auto" style="width:110px;">
                        <label for="suffix" class="form-label">Suffix</label>
                        <input type="text" id="suffix" name="suffix" class="form-control"
                               placeholder="Jr, III…" value="<?= htmlspecialchars($_POST['suffix'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                    <select id="department" name="department" class="form-select" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="classification" class="form-label">Classification <span class="text-danger">*</span></label>
                    <select id="classification" name="classification" class="form-select" required>
                        <option value="">Select classification</option>
                        <?php foreach ($classifications as $c): ?>
                            <option value="<?= $c ?>" <?= ($_POST['classification'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">School Email <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@ub.edu.ph" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="At least 6 characters" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', this)" aria-label="Toggle password">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-yellow btn-lg">Create Account</button>
                </div>
            </form>
            <?php endif; ?>

            <p class="text-center mt-3 mb-0" style="font-size:0.875rem;">
                Already have an account? <a href="/ILSHD/login.php" style="color:var(--ils-green);">Login</a>
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
