<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$userId = $_SESSION['user_id'];
$user   = getCurrentUser();
$db     = getDB();

// Mark all as read
$db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);

// Fetch all
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifs = $stmt->fetchAll();

$unreadCount = 0; // reset after marking read
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — ILS Help Desk</title>
    <link rel="stylesheet" href="/ILSHD/css/main.css">
    <link rel="stylesheet" href="/ILSHD/css/user.css">
</head>
<body>
<div class="mobile-wrap">
    <!-- Header -->
    <header class="app-header">
        <div class="ils-logo">
            <span class="ils-script">ils.</span>
            <span class="ils-helpdesk">Help Desk</span>
        </div>
        <div class="header-right">
            <a href="/ILSHD/user/notifications.php" class="bell-btn" aria-label="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </a>
            <a href="/ILSHD/user/profile.php" class="avatar-btn" aria-label="Profile">
                <?php if ($user['profile_image']): ?>
                    <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar">
                <?php else: ?>
                    <span><?= strtoupper(substr($user['first_name'], 0, 1)) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <!-- Nav Tabs -->
    <nav class="nav-tabs">
        <a href="/ILSHD/user/submit-ticket.php" class="nav-tab">Submit Ticket</a>
        <a href="/ILSHD/user/my-tickets.php" class="nav-tab">My Tickets</a>
    </nav>

    <div class="app-content">
        <h2 class="section-title">Notifications</h2>

        <div class="notif-list">
            <?php if (empty($notifs)): ?>
                <p style="text-align:center; color:var(--text-light); padding:40px 20px;">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifs as $n): ?>
                <a href="<?= $n['ticket_id'] ? '/ILSHD/user/view-ticket.php?id=' . $n['ticket_id'] : '#' ?>"
                   class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                    <div class="notif-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <div class="notif-body">
                        <div class="notif-title">
                            Ticket #<?= $n['ticket_id'] ?> has been
                            <?= $n['type'] === 'resolved' ? 'Resolved' : 'Submitted' ?>
                        </div>
                        <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                    <div class="notif-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
                <?php endforeach; ?>
                <p class="notif-end">— No more notifications —</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="/ILSHD/js/main.js"></script>
</body>
</html>
