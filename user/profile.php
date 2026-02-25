<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$userId = $_SESSION['user_id'];
$user   = getCurrentUser();
$unreadCount = getUnreadCount($userId);

$error   = '';
$success = '';
$section = $_GET['section'] ?? '';

// Handle change phone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();

    if ($_POST['action'] === 'change_phone') {
        $phone = trim($_POST['phone'] ?? '');
        if (!$phone) {
            $error = 'Please enter a phone number.';
        } else {
            $db->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $userId]);
            $success = 'Phone number updated.';
            $user = getCurrentUser();
        }
    } elseif ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'Please fill in all fields.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
            $success = 'Password updated successfully.';
        }
    } elseif ($_POST['action'] === 'upload_image') {
        if (!empty($_FILES['profile_image']['name'])) {
            $file = $_FILES['profile_image'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG, and GIF files are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'File size must be less than 5MB.';
            } else {
                $newname = 'pfp_' . $userId . '_' . time() . '.' . $ext;
                $target = __DIR__ . '/../uploads/' . $newname;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$newname, $userId]);
                    $success = 'Profile picture updated successfully.';
                    $user = getCurrentUser();
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        } else {
            $error = 'Please select an image.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body style="background:var(--ils-bg);">

<!-- Navbar -->
<nav class="navbar ils-navbar sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="/ILSHD/user/submit-ticket.php">
            <span class="ils-script">ils.</span>
            <span class="ils-helpdesk" style="font-size:1rem;">Help Desk</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="/ILSHD/user/notifications.php" class="bell-wrap" aria-label="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-dot"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown">
                <a href="#" class="user-avatar text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($user['profile_image']): ?>
                        <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/ILSHD/user/profile.php">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/ILSHD/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Nav Tabs -->
<div class="border-bottom bg-white">
    <div class="container">
        <ul class="nav ils-tabs">
            <li class="nav-item">
                <a class="nav-link" href="/ILSHD/user/submit-ticket.php">Submit Ticket</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/ILSHD/user/my-tickets.php">My Tickets</a>
            </li>
        </ul>
    </div>
</div>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="section-heading mb-4">My Profile</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="card ils-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                        <div class="position-relative">
                            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center bg-secondary text-white" style="width:100px; height:100px; font-size:2.5rem;">
                                <?php if ($user['profile_image']): ?>
                                    <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar" class="w-100 h-100" style="object-fit:cover;">
                                <?php else: ?>
                                    <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-light position-absolute bottom-0 end-0 rounded-circle border shadow-sm d-flex align-items-center justify-content-center" style="width:32px;height:32px;" data-bs-toggle="modal" data-bs-target="#photoModal" title="Change Picture">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                        </div>
                        <div class="text-center text-md-start flex-grow-1">
                            <h3 class="h4 mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                            <p class="text-muted mb-2"><?= htmlspecialchars(ucfirst($user['role'])) ?> &bull; <?= htmlspecialchars($user['department']) ?></p>
                            <p class="mb-0 text-muted small"><?= htmlspecialchars($user['school_email']) ?></p>
                        </div>
                    </div>
                    <hr class="my-4">

                    <h5 class="mb-3">Contact Information</h5>
                    <form method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="change_phone">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09123456789">
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-outline-primary">Update Phone</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card ils-card">
                <div class="card-body p-4">
                    <h5 class="mb-3">Security</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-yellow">Change Password</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Upload Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload_image">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required>
                        <div class="form-text">Allowed formats: JPG, PNG, GIF. Max size: 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-yellow">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
