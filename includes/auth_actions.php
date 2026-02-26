<?php
// Initialize state variables for modals
$loginError = '';
$signupError = '';
$forgotError = '';
$forgotSuccess = '';
$verifyError = '';
$verifySuccess = '';
$showVerifyModal = false;
$resetError = '';
$showResetModal = false;

// Dropdown data
$departments     = ['CICT', 'CAMS', 'CENG', 'CAS', 'CIT', 'CBA', 'COED', 'CNRS'];
$classifications = ['Student', 'Faculty', 'Staff'];

// Handle URL messages
$successMsg = isset($_GET['registered']) ? 'Account created successfully! You can now login.' : '';
if (isset($_GET['login_required'])) {
    $loginError = 'You must log in to access that page.';
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- LOGIN ---
    if ($_POST['action'] === 'login') {
        $email    = expandEmailFromInput($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if (!$email || !$password) {
            $loginError = 'Please fill in all fields.';
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
                    $loginError = 'Account not found. Please sign up and verify your email.';
                } else {
                    $loginError = 'Incorrect password.';
                }
            }
        }

    // --- SIGNUP ---
    } elseif ($_POST['action'] === 'signup') {
        $first  = trim($_POST['first_name'] ?? '');
        $mi     = trim($_POST['middle_initial'] ?? '');
        $last   = trim($_POST['last_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $dept   = $_POST['department'] ?? '';
        $class  = $_POST['classification'] ?? '';
        $email  = trim($_POST['email'] ?? '');
        $pass   = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        if (!$first || !$last || !$dept || !$class || !$email || !$pass || !$confirm) {
            $signupError = 'Please fill in all required fields.';
        } elseif ($pass !== $confirm) {
            $signupError = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -10) !== '@ub.edu.ph') {
            $signupError = 'Please enter a valid @ub.edu.ph email address.';
        } elseif (strlen($pass) < 6) {
            $signupError = 'Password must be at least 6 characters.';
        } elseif (!in_array($dept, $departments) || !in_array($class, $classifications)) {
            $signupError = 'Invalid department or classification.';
        } else {
            $db = getDB();
            $check = $db->prepare("SELECT id FROM users WHERE school_email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $signupError = 'This email is already registered.';
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

    // --- FORGOT PASSWORD ---
    } elseif ($_POST['action'] === 'forgot') {
        cleanupExpiredTokens();
        $email = trim($_POST['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -10) !== '@ub.edu.ph') {
            $forgotError = 'Please enter a valid @ub.edu.ph email address.';
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

                $_SESSION['reset_email'] = $email;
                sendEmail($email, "Password Reset", "Your verification code is: <b>$code</b>");
                $showVerifyModal = true;
                $verifySuccess = "A verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong>.<br><small class='text-muted'>(Code: $code)</small>";
            } else {
                $forgotSuccess = "If that email is registered, a code has been sent.";
            }
        }

    // --- VERIFY CODE ---
    } elseif ($_POST['action'] === 'verify') {
        $email = $_SESSION['reset_email'] ?? '';
        if (!$email) {
            $verifyError = 'Session expired. Please request a new code.';
            $showVerifyModal = true;
        } else {
            if (isset($_POST['resend'])) {
                $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $db = getDB();
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$email, $code, $expires]);
                sendEmail($email, "Password Reset", "Your verification code is: <b>$code</b>");
                $verifySuccess = "A new verification code has been sent.<br><small class='text-muted'>(Code: $code)</small>";
                $showVerifyModal = true;
            } else {
                $code = trim($_POST['code'] ?? '');
                $db   = getDB();
                $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
                $stmt->execute([$email, $code]);
                if ($stmt->fetch()) {
                    $_SESSION['reset_verified'] = true;
                    $showResetModal = true;
                } else {
                    $verifyError = 'Invalid or expired code.';
                    $showVerifyModal = true;
                }
            }
        }

    // --- RESET PASSWORD ---
    } elseif ($_POST['action'] === 'reset_password') {
        $email    = $_SESSION['reset_email']    ?? '';
        $verified = $_SESSION['reset_verified'] ?? false;

        if (!$email || !$verified) {
            $resetError = 'Session expired. Please request a new code.';
            $showResetModal = true;
        } else {
            $pass    = $_POST['password']         ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!$pass || !$confirm) {
                $resetError = 'Please fill in both fields.';
                $showResetModal = true;
            } elseif ($pass !== $confirm) {
                $resetError = 'Passwords do not match.';
                $showResetModal = true;
            } elseif (strlen($pass) < 6) {
                $resetError = 'Password must be at least 6 characters.';
                $showResetModal = true;
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $db   = getDB();
                $db->prepare("UPDATE users SET password = ? WHERE school_email = ?")->execute([$hash, $email]);
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                unset($_SESSION['reset_email'], $_SESSION['reset_verified']);
                $successMsg = 'Password updated successfully! You can now login.';
            }
        }
    }
}