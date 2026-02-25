<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db   = getDB();
$user = getCurrentUser();

// Mark all admin notifications as read
$db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id IN (SELECT id FROM users WHERE role='admin')")->execute();

// Fetch admin notifications
$stmt = $db->query("SELECT n.*, u.first_name, u.last_name, u.profile_image FROM notifications n JOIN users u ON n.user_id = u.id WHERE n.user_id IN (SELECT id FROM users WHERE role='admin') ORDER BY n.created_at DESC");
$notifs = $stmt->fetchAll();

// Also fetch student-submitted notifications (type=submitted) — linked to admin
$stmt2 = $db->query("
    SELECT n.*, u.first_name, u.last_name, u.profile_image, u.department
    FROM notifications n
    JOIN tickets t ON n.ticket_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE n.type = 'submitted'
    ORDER BY n.created_at DESC
    LIMIT 50
");
$notifs = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Admin</title>
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
                <a href="/ILSHD/admin/notifications.php" class="btn btn-link text-decoration-none position-relative" aria-label="Notifications">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </a>
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
                        <li><a class="dropdown-item" href="#">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/ILSHD/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Notifications</h1>
            <a href="/ILSHD/admin/tickets.php" class="btn btn-outline-secondary">
                &larr; Back
            </a>
        </div>

        <div class="card">
            <div class="list-group list-group-flush">
            <?php if (empty($notifs)): ?>
                <div class="list-group-item text-center text-muted py-5">No notifications yet.</div>
            <?php else: ?>
                <?php foreach ($notifs as $n): ?>
                <a href="/ILSHD/admin/view-ticket.php?id=<?= $n['ticket_id'] ?>" class="list-group-item list-group-item-action d-flex gap-3 py-3 <?= !$n['is_read'] ? 'bg-light' : '' ?>">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px; overflow:hidden;">
                        <?php if (!empty($n['profile_image'])): ?>
                            <img src="/ILSHD/uploads/<?= htmlspecialchars($n['profile_image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($n['first_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-success mb-1">
                            Ticket #<?= $n['ticket_id'] ?> has been Submitted
                        </div>
                        <div class="text-muted small">
                            <?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?>
                            submitted a new support ticket.
                        </div>
                    </div>
                    <div class="text-muted small text-nowrap"><?= timeAgo($n['created_at']) ?></div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
