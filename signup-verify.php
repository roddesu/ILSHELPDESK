<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { header('Location: /ILSHD/user/submit-ticket.php'); exit; }

if (!isset($_SESSION['signup_data'])) {
    header('Location: /ILSHD/signup.php');
    exit;
}

$email = $_SESSION['signup_data']['email'];
$error = '';
$success = '';

// Allow user to cancel/restart if they entered the wrong email
if (isset($_GET['cancel'])) {
    unset($_SESSION['signup_data'], $_SESSION['signup_code']);
    header('Location: /ILSHD/signup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['signup_code'] = $code;
        sendEmail($email, "Verify Registration", "Your verification code is: <b>$code</b>");
        $success = "A new verification code has been sent.";
    } else {
        $code = trim($_POST['code'] ?? '');
        if ($code === ($_SESSION['signup_code'] ?? '')) {
            $d = $_SESSION['signup_data'];
            $db = getDB();
            
            try {
                $stmt = $db->prepare("INSERT INTO users (first_name, middle_initial, last_name, suffix, department, classification, school_email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'student')");
                $stmt->execute([$d['first_name'], $d['middle_initial'], $d['last_name'], $d['suffix'], $d['department'], $d['classification'], $d['email'], $d['password_hash']]);
                
                unset($_SESSION['signup_data']);
                unset($_SESSION['signup_code']);
                
                header('Location: /ILSHD/login.php?registered=1');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Integrity constraint violation (Duplicate entry)
                    $error = "This email has already been registered.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Invalid verification code.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Registration — ILS Help Desk</title>
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

            <h2 class="fw-bold mb-1" style="font-size:1.1rem;">Verify Email</h2>
            <p class="text-muted mb-3" style="font-size:0.875rem;">
                A verification code has been sent to <strong><?= htmlspecialchars($email) ?></strong>.
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
                           placeholder="000000" maxlength="6"
                           style="letter-spacing:0.3em; font-size:1.4rem;" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-yellow btn-lg">Complete Registration</button>
                </div>
            </form>

            <form method="POST" class="text-center mt-3">
                <button type="submit" name="resend" class="btn btn-link text-decoration-none" style="color:var(--ils-green); font-size:0.875rem;">Resend Verification Code</button>
            </form>
            <div class="text-center mt-2">
                <a href="?cancel=1" class="text-decoration-none text-muted small">Change Email / Start Over</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>