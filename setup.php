<?php
/**
 * ILS Help Desk — Setup / Installer
 * Visit this page once to create the database, tables, and admin account.
 * Delete or rename this file after setup is complete.
 */

// ── Requirements check ─────────────────────────────────────────────────────
$requirements = [
    'PHP ≥ 7.4'          => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO extension'       => extension_loaded('pdo'),
    'PDO MySQL driver'    => extension_loaded('pdo_mysql'),
    'config/ writable'    => is_writable(__DIR__ . '/config'),
];

$uploadsDir    = __DIR__ . '/uploads';
$uploadsOk     = is_dir($uploadsDir) ? is_writable($uploadsDir) : @mkdir($uploadsDir, 0755, true);
$requirements['uploads/ directory'] = (bool)$uploadsOk;

$reqAllPass = !in_array(false, $requirements, true);

// ── Handle POST ─────────────────────────────────────────────────────────────
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost     = trim($_POST['db_host']     ?? 'localhost');
    $dbUser     = trim($_POST['db_user']     ?? 'root');
    $dbPass     = $_POST['db_pass']          ?? '';
    $dbName     = trim($_POST['db_name']     ?? 'ilshd_db');
    $adminFirst = trim($_POST['admin_first'] ?? '');
    $adminLast  = trim($_POST['admin_last']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass']       ?? '';
    $adminConf  = $_POST['admin_conf']       ?? '';

    // Validate
    if (!$dbHost || !$dbUser || !$dbName) {
        $errors[] = 'Database host, user, and name are required.';
    }
    if (!$adminFirst || !$adminLast || !$adminEmail || !$adminPass) {
        $errors[] = 'All admin account fields are required.';
    }
    if ($adminPass && $adminConf && $adminPass !== $adminConf) {
        $errors[] = 'Admin passwords do not match.';
    }
    if ($adminPass && strlen($adminPass) < 6) {
        $errors[] = 'Admin password must be at least 6 characters.';
    }

    if (empty($errors)) {
        try {
            // Step 1: Connect without selecting a database
            $pdo = new PDO(
                "mysql:host=$dbHost;charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Step 2: Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Step 3: Create tables
            $statements = [
                'users table' => "
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        first_name VARCHAR(100) NOT NULL,
                        middle_initial VARCHAR(5) DEFAULT NULL,
                        last_name VARCHAR(100) NOT NULL,
                        suffix VARCHAR(20) DEFAULT NULL,
                        department VARCHAR(50) NOT NULL DEFAULT 'ILS',
                        classification VARCHAR(50) NOT NULL DEFAULT 'Admin',
                        school_email VARCHAR(150) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        role ENUM('student','admin') NOT NULL DEFAULT 'student',
                        phone VARCHAR(30) DEFAULT NULL,
                        profile_image VARCHAR(255) DEFAULT NULL,
                        remember_token VARCHAR(64) DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB
                ",
                'tickets table' => "
                    CREATE TABLE IF NOT EXISTS tickets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        concern_type ENUM('Request','Incident') NOT NULL,
                        urgency_level ENUM('Low','Medium','High') NOT NULL,
                        subject VARCHAR(100) NOT NULL,
                        issue_description VARCHAR(255) NOT NULL,
                        additional_comments TEXT DEFAULT NULL,
                        device_type VARCHAR(50) DEFAULT NULL,
                        attachment VARCHAR(255) DEFAULT NULL,
                        date_needed DATE DEFAULT NULL,
                        status ENUM('Pending','Resolved') NOT NULL DEFAULT 'Pending',
                        resolved_date DATE DEFAULT NULL,
                        resolved_comment TEXT DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB
                ",
                'tickets AUTO_INCREMENT' => "ALTER TABLE tickets AUTO_INCREMENT = 1001",
                'notifications table' => "
                    CREATE TABLE IF NOT EXISTS notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        ticket_id INT NOT NULL,
                        type ENUM('submitted','resolved') NOT NULL,
                        message VARCHAR(255) NOT NULL,
                        is_read TINYINT(1) NOT NULL DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB
                ",
                'password_resets table' => "
                    CREATE TABLE IF NOT EXISTS password_resets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(150) NOT NULL,
                        token VARCHAR(64) NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        expires_at DATETIME NOT NULL
                    ) ENGINE=InnoDB
                ",
                // Updates for existing databases (idempotent via error suppression)
                'update users phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL",
                'update users image' => "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL",
                'update users token' => "ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) DEFAULT NULL",
                'update tickets resolved_date' => "ALTER TABLE tickets ADD COLUMN resolved_date DATE DEFAULT NULL",
                'update tickets resolved_comment' => "ALTER TABLE tickets ADD COLUMN resolved_comment TEXT DEFAULT NULL",
            ];

            foreach ($statements as $label => $sql) {
                try {
                    $pdo->exec(trim($sql));
                } catch (PDOException $e) {
                    // Ignore "Duplicate key" (1061) and "Duplicate column" (1060)
                    $msg = $e->getMessage();
                    if (strpos($msg, '1061') === false && strpos($msg, '1060') === false) {
                        throw new PDOException("Failed at [$label]: " . $e->getMessage());
                    }
                }
            }

            // Step 4: Create admin account
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO users (first_name, last_name, department, classification, school_email, password, role)
                VALUES (?, ?, 'ILS', 'Admin', ?, ?, 'admin')
                ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'admin'
            ")->execute([$adminFirst, $adminLast, $adminEmail, $hash]);

            // Step 5: Create uploads dir
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }

            // Step 6: Rewrite config/db.php
            $configPath = __DIR__ . '/config/db.php';
            $dbPassEsc  = addslashes($dbPass);
            $configContent = <<<PHP
<?php
date_default_timezone_set('Asia/Manila');
define('DB_HOST', '$dbHost');
define('DB_USER', '$dbUser');
define('DB_PASS', '$dbPassEsc');
define('DB_NAME', '$dbName');

function getDB() {
    static \$pdo = null;
    if (\$pdo === null) {
        try {
            \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException \$e) {
            die("Database connection failed: " . \$e->getMessage());
        }
    }
    return \$pdo;
}
PHP;
            file_put_contents($configPath, $configContent);

            $success = true;

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Default form values
$dbHost     = $_POST['db_host']      ?? 'localhost';
$dbUser     = $_POST['db_user']      ?? 'root';
$dbName     = $_POST['db_name']      ?? 'ilshd_db';
$adminFirst = $_POST['admin_first']  ?? '';
$adminLast  = $_POST['admin_last']   ?? '';
$adminEmail = $_POST['admin_email']  ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — ILS Help Desk</title>
    <link rel="stylesheet" href="/ILSHD/css/main.css">
    <style>
        body { background: var(--bg); }

        .setup-wrap {
            max-width: 560px;
            margin: 40px auto;
            padding: 0 16px 60px;
        }

        .setup-logo {
            text-align: center;
            margin-bottom: 28px;
            display: flex; justify-content: center; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .setup-logo .ils-script   { font-family: 'Brush Script MT', cursive; font-size: 3.5rem; color: var(--yellow); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .setup-logo .ils-helpdesk { font-size: 2.3rem; font-weight: 800; color: var(--green); transition: transform 0.4s ease; }
        .setup-logo .setup-label  { font-size: 0.8rem; color: var(--text-light); letter-spacing: 0.08em; text-transform: uppercase; margin-top: 4px; width: 100%; }
        .setup-logo:hover .ils-script { transform: scale(1.1) rotate(-3deg); }
        .setup-logo:hover .ils-helpdesk { transform: translateX(4px); }

        .setup-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 28px 28px 24px;
            margin-bottom: 20px;
        }

        .setup-card h2 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
        }

        .setup-card h2 .step-num {
            width: 24px;
            height: 24px;
            background: var(--green);
            color: #fff;
            border-radius: 50%;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Requirements checklist */
        .req-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .req-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            padding: 8px 10px;
            border-radius: 8px;
        }
        .req-item.pass { background: #F0FFF4; color: var(--green); }
        .req-item.fail { background: #FFF0F0; color: var(--red); }
        .req-icon { font-size: 1rem; flex-shrink: 0; }

        .req-warn {
            margin-top: 12px;
            padding: 10px 12px;
            background: #FFF3E0;
            border-radius: 8px;
            font-size: 0.82rem;
            color: var(--orange);
        }

        /* Two-column grid for form fields */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .form-group { margin-bottom: 0; }

        /* Submit button full-width */
        .btn-setup {
            width: 100%;
            padding: 13px;
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: filter 0.15s;
        }
        .btn-setup:hover  { filter: brightness(0.93); }
        .btn-setup:active { transform: scale(0.99); }
        .btn-setup:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Success screen */
        .success-box {
            text-align: center;
            padding: 40px 28px;
        }
        .success-circle {
            width: 80px;
            height: 80px;
            background: var(--green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .success-circle svg { color: #fff; }
        .success-box h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; }
        .success-box p  { font-size: 0.9rem; color: var(--text-gray); margin-bottom: 20px; }

        .creds-box {
            background: var(--bg);
            border-radius: 8px;
            padding: 14px 18px;
            text-align: left;
            font-size: 0.875rem;
            margin-bottom: 24px;
        }
        .creds-box .cred-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid var(--border); }
        .creds-box .cred-row:last-child { border-bottom: none; }
        .creds-box .cred-label { color: var(--text-gray); font-weight: 500; }
        .creds-box .cred-val   { font-weight: 700; }

        .success-links { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .success-links a {
            padding: 11px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .btn-login { background: var(--yellow); color: #fff; }
        .btn-admin { background: var(--green);  color: #fff; }

        .security-warn {
            background: #FFF3E0;
            border: 1px solid #FFE0B2;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.82rem;
            color: #E65100;
            margin-top: 20px;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .error-list {
            background: #FFF0F0;
            border: 1px solid #FFCDD2;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .error-list p { color: var(--red); font-size: 0.875rem; margin: 0 0 4px; }
        .error-list p:last-child { margin-bottom: 0; }

        .divider-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 12px;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="setup-wrap">

    <!-- Logo -->
    <div class="setup-logo">
        <span class="ils-script">ils.</span>
        <span class="ils-helpdesk">Help Desk</span>
        <p class="setup-label">Installation Setup</p>
    </div>

    <?php if ($success): ?>
    <!-- ── Success Screen ── -->
    <div class="setup-card">
        <div class="success-box">
            <div class="success-circle">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1>Setup Complete!</h1>
            <p>The database, tables, and admin account have been created successfully.</p>

            <div class="creds-box">
                <div class="cred-row">
                    <span class="cred-label">Database</span>
                    <span class="cred-val"><?= htmlspecialchars($_POST['db_name'] ?? 'ilshd_db') ?></span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Admin Email</span>
                    <span class="cred-val"><?= htmlspecialchars($_POST['admin_email'] ?? '') ?></span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Admin Password</span>
                    <span class="cred-val" style="color:var(--text-light);">(as entered)</span>
                </div>
            </div>

            <div class="success-links">
                <a href="/ILSHD/" class="btn-login">Go to Login</a>
            </div>

            <div class="security-warn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span><strong>Security Notice:</strong> Delete or rename <code>setup.php</code> from your server after installation to prevent unauthorized re-setup.</span>
            </div>
        </div>
    </div>

    <?php else: ?>

    <!-- ── Errors ── -->
    <?php if (!empty($errors)): ?>
    <div class="error-list">
        <?php foreach ($errors as $err): ?>
            <p>&#x2715; <?= htmlspecialchars($err) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Step 1: Requirements ── -->
    <div class="setup-card">
        <h2><span class="step-num">1</span> System Requirements</h2>
        <ul class="req-list">
            <?php foreach ($requirements as $label => $pass): ?>
            <li class="req-item <?= $pass ? 'pass' : 'fail' ?>">
                <span class="req-icon"><?= $pass ? '&#x2714;' : '&#x2718;' ?></span>
                <?= htmlspecialchars($label) ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$reqAllPass): ?>
        <div class="req-warn">
            &#9888; Fix the items above before running setup. If <code>config/</code> is not writable, run:
            <code>chmod 755 config/</code> in your terminal.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Setup Form ── -->
    <form method="POST" novalidate>

        <!-- Step 2: Database -->
        <div class="setup-card">
            <h2><span class="step-num">2</span> Database Configuration</h2>

            <div class="form-grid">
                <div class="form-group">
                    <label for="db_host">DB Host</label>
                    <input type="text" id="db_host" name="db_host" class="form-control"
                           value="<?= htmlspecialchars($dbHost) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_name">DB Name</label>
                    <input type="text" id="db_name" name="db_name" class="form-control"
                           value="<?= htmlspecialchars($dbName) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_user">DB User</label>
                    <input type="text" id="db_user" name="db_user" class="form-control"
                           value="<?= htmlspecialchars($dbUser) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">DB Password</label>
                    <input type="password" id="db_pass" name="db_pass" class="form-control"
                           placeholder="Leave empty if none">
                </div>
            </div>

            <p style="font-size:0.78rem;color:var(--text-light);margin-top:10px;">
                XAMPP defaults: host <strong>localhost</strong>, user <strong>root</strong>, no password.
            </p>
        </div>

        <!-- Step 3: Admin Account -->
        <div class="setup-card">
            <h2><span class="step-num">3</span> Admin Account</h2>

            <div class="form-grid">
                <div class="form-group">
                    <label for="admin_first">First Name</label>
                    <input type="text" id="admin_first" name="admin_first" class="form-control"
                           value="<?= htmlspecialchars($_POST['admin_first'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_last">Last Name</label>
                    <input type="text" id="admin_last" name="admin_last" class="form-control"
                           value="<?= htmlspecialchars($_POST['admin_last'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label for="admin_email">Email</label>
                <input type="email" id="admin_email" name="admin_email" class="form-control"
                       placeholder="e.g., admin@ils.local"
                       value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
            </div>

            <div class="form-grid" style="margin-top:12px;">
                <div class="form-group">
                    <label for="admin_pass">Password</label>
                    <input type="password" id="admin_pass" name="admin_pass" class="form-control"
                           placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="admin_conf">Confirm Password</label>
                    <input type="password" id="admin_conf" name="admin_conf" class="form-control"
                           placeholder="Repeat password" required>
                </div>
            </div>
        </div>

        <!-- Step 4: Run -->
        <div class="setup-card">
            <h2><span class="step-num">4</span> Run Setup</h2>
            <p style="font-size:0.875rem;color:var(--text-gray);margin-bottom:16px;">
                This will create the database <strong><?= htmlspecialchars($dbName) ?></strong>,
                all required tables, and the admin account.
                Existing tables will <strong>not</strong> be dropped.
            </p>
            <button type="submit" class="btn-setup" <?= !$reqAllPass ? 'disabled title="Fix requirements first"' : '' ?>>
                &#x25B6; Run Setup
            </button>
        </div>

    </form>
    <?php endif; ?>

</div><!-- .setup-wrap -->
<script src="/ILSHD/js/main.js"></script>
<script>
document.querySelector('form')?.addEventListener('submit', function() {
    const btn = this.querySelector('.btn-setup');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Setting up...';
});
</script>
</body>
</html>
