<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$userId = $_SESSION['user_id'];
$user   = getCurrentUser();
$unreadCount = getAdminUnreadCount();

$error   = '';
$success = '';

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
    <title>Admin Profile — ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body style="background:var(--ils-bg);">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="/ILSHD/admin/tickets.php">
                <span class="ils-script">ils.</span>
                <span class="ils-helpdesk" style="font-size:1rem;">Help Desk</span>
            </a>
            <div class="d-flex align-items-center gap-3">
            <div class="dropdown">
                <a href="#" class="bell-wrap text-decoration-none" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notif-dot" id="notif-badge" style="display: none;"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end p-0 shadow border-0" aria-labelledby="notifDropdown" style="width: 300px;">
                    <li><div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light rounded-top"><h6 class="mb-0 small fw-bold">Notifications</h6></div></li>
                    <div id="notif-list" style="max-height: 300px; overflow-y: auto;"></div>
                    <li><a class="dropdown-item text-center small text-primary border-top py-2 rounded-bottom" id="notif-view-all" href="#">View All</a></li>
                </ul>
            </div>
                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                            <?php if ($user['profile_image']): ?>
                                <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="" class="rounded-circle" style="width:32px; height:32px;">
                            <?php else: ?>
                                <span class="text-white fw-bold"><?= strtoupper(substr($user['first_name'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="d-none d-lg-inline">ILS Support</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/ILSHD/admin/profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/ILSHD/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-4">
            <!-- Left Column: Profile Card -->
            <div class="col-lg-4">
                <div class="card ils-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-5">
                        <div class="position-relative d-inline-block mb-4">
                            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center bg-secondary text-white mx-auto shadow-sm" style="width:140px; height:140px; font-size:3.5rem;">
                                <?php if ($user['profile_image']): ?>
                                    <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar" class="w-100 h-100" style="object-fit:cover;">
                                <?php else: ?>
                                    <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-light position-absolute bottom-0 end-0 rounded-circle border shadow-sm d-flex align-items-center justify-content-center" style="width:36px;height:36px;" data-bs-toggle="modal" data-bs-target="#photoModal" title="Change Picture">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                        </div>
                        
                        <h3 class="fw-bold mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                        <p class="text-muted mb-3"><?= htmlspecialchars($user['school_email']) ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">
                                <?= htmlspecialchars(ucfirst($user['role'])) ?>
                            </span>
                            <span class="badge bg-light text-dark border rounded-pill px-3">
                                <?= htmlspecialchars($user['department']) ?>
                            </span>
                        </div>

                        <div class="border-top pt-4">
                            <small class="text-muted text-uppercase fw-bold" style="font-size:0.75rem; letter-spacing:1px;">Member Since</small>
                            <div class="fw-medium mt-1"><?= formatDate($user['created_at']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Details & Settings -->
            <div class="col-lg-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Personal Info -->
                <div class="card ils-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom">
                        <h5 class="mb-0 fw-bold">Personal Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_phone">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted text-uppercase fw-bold">First Name</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['first_name']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Last Name</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['last_name']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Department</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['department']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Classification</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['classification']) ?>" disabled>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white text-muted">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 12.284 3 6V5z" /></svg>
                                        </span>
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09123456789">
                                        <button type="submit" class="btn btn-outline-primary">Update</button>
                                    </div>
                                    <div class="form-text">This is the only editable personal field.</div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security -->
                <div class="card ils-card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 px-4 border-bottom">
                        <h5 class="mb-0 fw-bold">Security Settings</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted text-uppercase fw-bold">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <div class="col-12 text-end mt-4">
                                    <button type="submit" class="btn btn-yellow px-4">Change Password</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
<script src="/ILSHD/js/notifications.js"></script>
</body>
</html>