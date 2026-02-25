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

$isPending   = $ticket['status'] === 'Pending';
$unreadCount = getAdminUnreadCount();
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
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle"><?= $unreadCount ?></span>
                    <?php endif; ?>
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
        <a href="/ILSHD/admin/tickets.php" class="btn btn-outline-secondary mb-4">
            &larr; Back to Tickets
        </a>

        <?php if ($justResolved): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Ticket #<?= $id ?> has been marked as Resolved and the student has been notified.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="h4 mb-0">Ticket Description</h2>
                    </div>
                    <?php if (!$isPending): ?>
                        <div class="ms-auto badge bg-success-subtle text-success d-flex align-items-center gap-1 px-3 py-2 rounded-pill">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Resolved
                        </div>
                    <?php else: ?>
                        <div class="ms-auto badge bg-warning-subtle text-warning px-3 py-2 rounded-pill">Pending</div>
                    <?php endif; ?>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Ticket ID</label>
                        <div class="fw-bold">#<?= $ticket['id'] ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Urgency Level</label>
                        <div>
                            <span class="badge bg-<?= $ticket['urgency_level'] === 'High' ? 'danger' : ($ticket['urgency_level'] === 'Medium' ? 'warning' : 'secondary') ?>">
                                <?= $ticket['urgency_level'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Date Created</label>
                        <div><?= formatDate($ticket['created_at']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Concern Type</label>
                        <div><?= htmlspecialchars($ticket['concern_type']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Subject</label>
                        <div><?= htmlspecialchars($ticket['subject']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Date Needed</label>
                        <div><?= $ticket['date_needed'] ? formatDate($ticket['date_needed']) : '—' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Requester</label>
                        <div><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Email</label>
                        <div><a href="mailto:<?= htmlspecialchars($ticket['school_email']) ?>" class="text-decoration-none"><?= htmlspecialchars($ticket['school_email']) ?></a></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Department</label>
                        <div><?= htmlspecialchars($ticket['department']) ?></div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded mb-3">
                    <label class="text-muted small text-uppercase fw-bold mb-2">Issue Description</label>
                    <p class="mb-0"><?= htmlspecialchars($ticket['issue_description']) ?></p>
                </div>

                <?php if ($ticket['additional_comments']): ?>
                <div class="bg-light p-3 rounded mb-3">
                    <label class="text-muted small text-uppercase fw-bold mb-2">Additional Comments</label>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['additional_comments'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($ticket['attachment']): ?>
                <div class="mb-4">
                    <strong class="me-2">Attachment:</strong>
                    <a href="/ILSHD/uploads/<?= htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="text-primary text-decoration-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <?= htmlspecialchars($ticket['attachment']) ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (!$isPending): ?>
                    <hr>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Resolved Date</label>
                            <div><?= $ticket['resolved_date'] ? formatDate($ticket['resolved_date']) : '—' ?></div>
                        </div>
                        <?php if ($ticket['resolved_comment']): ?>
                        <div class="col-12">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Resolution Comment</label>
                            <div class="p-3 bg-success-subtle text-success-emphasis rounded">
                                <?= nl2br(htmlspecialchars($ticket['resolved_comment'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isPending): ?>
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="/ILSHD/admin/tickets.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal">
                        Resolve Ticket
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Resolve Modal -->
    <?php if ($isPending): ?>
    <div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Ticket #<?= $id ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p class="text-muted mb-3">Add an optional comment for the student before marking this ticket as resolved.</p>
                        <div class="mb-3">
                            <label for="resolved_comment" class="form-label">Comment (optional)</label>
                            <textarea id="resolved_comment" name="resolved_comment" class="form-control" rows="3"
                                      placeholder="e.g. Your account has been reset successfully…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark as Resolved</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
</body>
</html>
