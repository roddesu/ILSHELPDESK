<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);
$user = getCurrentUser();

if (!$id) { header('Location: /ILSHD/admin/tickets.php'); exit; }

$stmt = $db->prepare("SELECT t.*, u.first_name, u.last_name, u.school_email, u.department FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) { header('Location: /ILSHD/admin/tickets.php'); exit; }

$error   = '';
$success = '';

// Handle resolve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket['status'] === 'Pending') {
    $comment = trim($_POST['resolved_comment'] ?? '');

    $db->prepare("UPDATE tickets SET status = 'Resolved', resolved_date = CURDATE(), resolved_comment = ? WHERE id = ?")
       ->execute([$comment ?: null, $id]);

    // 1. Send Notification to User's Bell Icon
    createNotification($ticket['user_id'], $id, 'resolved',
        "Your Ticket #$id has been resolved.");

    // 2. Send Email to User
    $msg = "Hello {$ticket['first_name']},<br><br>Your ticket (#$id) has been marked as <strong>Resolved</strong>.<br>" . ($comment ? "<br><strong>Resolution Comment:</strong><br>" . nl2br(htmlspecialchars($comment)) : "") . "<br><br>Thank you,<br>ILS Help Desk";
    sendEmail($ticket['school_email'], "Ticket #$id Resolved", $msg);

    header("Location: /ILSHD/admin/view-ticket.php?id=$id&resolved=1");
    exit;
}

// Re-fetch after possible update
$stmt->execute([$id]);
$ticket = $stmt->fetch();

$isPending    = $ticket['status'] === 'Pending';
$unreadCount  = getAdminUnreadCount();
$justResolved = isset($_GET['resolved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $id ?> — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
</head>
<body style="background:var(--ils-bg);">

<!-- Navbar -->
<nav class="navbar ils-navbar sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="/ILSHD/admin/tickets.php">
            <span class="ils-script">ils.</span>
            <span class="ils-helpdesk" style="font-size:1rem;">Help Desk</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <!-- Bell -->
            <div class="dropdown">
                <a href="#" class="bell-wrap" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="notif-dot" id="notif-badge" style="display:none;"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end p-0 shadow border-0" aria-labelledby="notifDropdown" style="width:300px;">
                    <li><div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light rounded-top"><h6 class="mb-0 small fw-bold">Notifications</h6></div></li>
                    <div id="notif-list" style="max-height:300px;overflow-y:auto;"></div>
                    <li><a class="dropdown-item text-center small border-top py-2 rounded-bottom" style="color:var(--ils-green);" id="notif-view-all" href="/ILSHD/admin/notifications.php">View All</a></li>
                </ul>
            </div>
            <!-- Avatar -->
            <div class="dropdown">
                <button class="btn btn-link p-0 d-flex align-items-center gap-2 text-decoration-none border-0" type="button" data-bs-toggle="dropdown">
                    <span class="user-avatar">
                        <?php if ($user['profile_image']): ?>
                            <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </span>
                    <span class="d-none d-lg-inline small fw-semibold" style="color:var(--ils-text);">ILS Support</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/ILSHD/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4" style="max-width:800px;">
    <?php if ($justResolved): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Ticket #<?= $id ?> has been marked as Resolved and the student has been notified.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card ils-card p-4">
        <!-- Card header -->
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="stat-icon-box" style="background:#E8F5E9;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <h5 class="mb-0 fw-bold">Ticket #<?= $id ?></h5>
            <?php if (!$isPending): ?>
                <span class="ms-auto badge rounded-pill px-3 py-2" style="background:#E8F5E9;color:var(--ils-green);font-size:0.8rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Resolved
                </span>
            <?php else: ?>
                <span class="ms-auto badge rounded-pill px-3 py-2" style="background:#FFF3E0;color:var(--ils-orange);font-size:0.8rem;">Pending</span>
            <?php endif; ?>
        </div>

        <!-- 2-column detail grid -->
        <div class="row g-0 mb-3">
            <div class="col-6 pe-4 border-end">
                <div class="detail-item d-flex justify-content-between">
                    <span class="detail-label">Ticket ID</span>
                    <span class="detail-val ticket-id">#<?= $ticket['id'] ?></span>
                </div>
                <div class="detail-item d-flex justify-content-between">
                    <span class="detail-label">Concern Type</span>
                    <span class="detail-val"><?= htmlspecialchars($ticket['concern_type']) ?></span>
                </div>
                <div class="detail-item d-flex justify-content-between">
                    <span class="detail-label">Subject</span>
                    <span class="detail-val"><?= htmlspecialchars($ticket['subject']) ?></span>
                </div>
            </div>
            <div class="col-6 ps-4">
                <div class="detail-item d-flex justify-content-between">
                    <span class="detail-label">Urgency</span>
                    <span class="detail-val">
                        <span class="badge badge-<?= strtolower($ticket['urgency_level']) ?>">
                            <?= htmlspecialchars($ticket['urgency_level']) ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item d-flex justify-content-between">
                    <span class="detail-label">Date Created</span>
                    <span class="detail-val"><?= formatDate($ticket['created_at']) ?></span>
                </div>

            </div>
        </div>

        <!-- Requester info -->
        <div class="detail-item d-flex justify-content-between">
            <span class="detail-label">Requester</span>
            <span class="detail-val"><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></span>
        </div>
        <div class="detail-item d-flex justify-content-between">
            <span class="detail-label">Email</span>
            <span class="detail-val">
                <a href="mailto:<?= htmlspecialchars($ticket['school_email']) ?>" class="text-decoration-none" style="color:var(--ils-green);">
                    <?= htmlspecialchars($ticket['school_email']) ?>
                </a>
            </span>
        </div>
        <div class="detail-item d-flex justify-content-between">
            <span class="detail-label">Department</span>
            <span class="detail-val"><?= htmlspecialchars($ticket['department']) ?></span>
        </div>

        <!-- Issue Description -->
        <div class="mt-4 mb-3">
            <div class="detail-label mb-2">Issue Description</div>
            <div class="p-3 rounded" style="background:#F8F9FB;border:1px solid var(--ils-border);font-size:0.9rem;">
                <?= nl2br(htmlspecialchars($ticket['issue_description'])) ?>
            </div>
        </div>

        <?php if ($ticket['additional_comments']): ?>
        <div class="mb-3">
            <div class="detail-label mb-2">Additional Comments</div>
            <div class="p-3 rounded" style="background:#F8F9FB;border:1px solid var(--ils-border);font-size:0.9rem;">
                <?= nl2br(htmlspecialchars($ticket['additional_comments'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ticket['attachment']): ?>
        <div class="detail-item d-flex justify-content-between">
            <span class="detail-label">Attachment</span>
            <span class="detail-val">
                <a href="/ILSHD/uploads/<?= htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="text-decoration-none" style="color:var(--ils-green);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    <?= htmlspecialchars($ticket['attachment']) ?>
                </a>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!$isPending): ?>
            <hr class="my-3">
            <div class="detail-item d-flex justify-content-between">
                <span class="detail-label">Resolved Date</span>
                <span class="detail-val"><?= $ticket['resolved_date'] ? formatDate($ticket['resolved_date']) : '—' ?></span>
            </div>
            <?php if ($ticket['resolved_comment']): ?>
            <div class="mt-3">
                <div class="detail-label mb-2">Resolution Comment</div>
                <div class="p-3 rounded" style="background:#E8F5E9;color:var(--ils-green);font-size:0.9rem;">
                    <?= nl2br(htmlspecialchars($ticket['resolved_comment'])) ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isPending): ?>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <a href="/ILSHD/admin/tickets.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="button" class="btn btn-ils-green" data-bs-toggle="modal" data-bs-target="#resolveModal">
                Resolve
            </button>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Resolve Modal -->
<?php if ($isPending): ?>
<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Resolve Ticket #<?= $id ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size:0.875rem;">Add an optional comment for the student before marking this ticket as resolved.</p>
                    <div class="mb-3">
                        <label for="resolved_comment" class="form-label">Comment (optional)</label>
                        <textarea id="resolved_comment" name="resolved_comment" class="form-control" rows="3"
                                  placeholder="e.g. Your account has been reset successfully…"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ils-green">Mark as Resolved</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
<script src="/ILSHD/js/notifications.js"></script>
</body>
</html>
