<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$userId = $_SESSION['user_id'];
$user   = getCurrentUser();
$id     = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /ILSHD/user/my-tickets.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: /ILSHD/user/my-tickets.php');
    exit;
}

$unreadCount = getUnreadCount($userId);
$isPending   = $ticket['status'] === 'Pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $id ?> — ILS Help Desk</title>
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
            <a href="/ILSHD/user/profile.php" class="user-avatar text-decoration-none" aria-label="Profile">
                <?php if ($user['profile_image']): ?>
                    <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar">
                <?php else: ?>
                    <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                <?php endif; ?>
            </a>
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
                <a class="nav-link active" href="/ILSHD/user/my-tickets.php">My Tickets</a>
            </li>
        </ul>
    </div>
</div>

<!-- Content -->
<div class="container py-4">
    <a href="/ILSHD/user/my-tickets.php" class="d-inline-flex align-items-center gap-1 mb-4 text-decoration-none" style="color:var(--ils-gray); font-size:0.9rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to My Tickets
    </a>

    <div class="card ils-card">
        <div class="card-body p-4">
            <!-- Card header -->
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:44px;height:44px;background:#E8F5E9;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
                <h2 class="fw-bold mb-0" style="font-size:1.2rem;"><?= $isPending ? 'Ticket Description' : 'Ticket Resolved' ?></h2>
                <?php if (!$isPending): ?>
                    <span class="ms-auto badge badge-low d-flex align-items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Resolved
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($isPending): ?>
                <div class="alert" style="background:#EEF9F2; border-color:#B2DFC0; color:var(--ils-green); font-size:0.875rem;">
                    You will receive a notification automatically when your ticket is marked Resolved.
                </div>
            <?php endif; ?>

            <!-- Detail rows -->
            <div class="row g-0">
                <div class="col-12">
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Ticket ID</span>
                        <span class="detail-val ticket-id">#<?= $ticket['id'] ?></span>
                    </div>
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Urgency Level</span>
                        <span class="detail-val">
                            <span class="badge badge-<?= strtolower($ticket['urgency_level']) ?>"><?= $ticket['urgency_level'] ?></span>
                        </span>
                    </div>
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Concern Type</span>
                        <span class="detail-val"><?= htmlspecialchars($ticket['concern_type']) ?></span>
                    </div>
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Subject</span>
                        <span class="detail-val"><?= htmlspecialchars($ticket['subject']) ?></span>
                    </div>
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Issue Description</span>
                        <span class="detail-val"><?= htmlspecialchars($ticket['issue_description']) ?></span>
                    </div>
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Date Created</span>
                        <span class="detail-val"><?= formatDate($ticket['created_at']) ?></span>
                    </div>
                    <?php if ($ticket['date_needed']): ?>
                    <div class="detail-item d-flex justify-content-between">
                        <span class="detail-label">Date Needed</span>
                        <span class="detail-val"><?= formatDate($ticket['date_needed']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($ticket['additional_comments']): ?>
            <div class="mt-3">
                <p class="detail-label mb-1">Additional Comments</p>
                <p class="mb-0" style="font-size:0.9rem;"><?= nl2br(htmlspecialchars($ticket['additional_comments'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!$isPending): ?>
            <hr class="my-4">
            <div class="detail-item d-flex justify-content-between">
                <span class="detail-label">Resolved Date</span>
                <span class="detail-val"><?= formatDate($ticket['resolved_date']) ?></span>
            </div>
            <?php if ($ticket['resolved_comment']): ?>
            <div class="mt-3">
                <p class="detail-label mb-1">Comment from Support</p>
                <p class="mb-0" style="font-size:0.9rem;"><?= nl2br(htmlspecialchars($ticket['resolved_comment'])) ?></p>
            </div>
            <?php endif; ?>
            <div class="mt-4">
                <a href="/ILSHD/user/my-tickets.php" class="btn btn-yellow">View My Tickets</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div><!-- .container -->

<footer class="py-3 text-center ils-footer mt-4">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
</body>
</html>
