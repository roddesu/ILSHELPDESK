<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$userId      = $_SESSION['user_id'];
$user        = getCurrentUser();
$unreadCount = getUnreadCount($userId);
$counts      = getTicketCounts($userId);

$perPage = 3;
$page    = max(1, (int)($_GET['page'] ?? 1));
$search  = trim($_GET['search'] ?? '');
$offset  = ($page - 1) * $perPage;

$db = getDB();

$where  = "WHERE t.user_id = ?";
$params = [$userId];
if ($search !== '') {
    $where   .= " AND (t.subject LIKE ? OR t.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$totalStmt = $db->prepare("SELECT COUNT(*) FROM tickets t $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$listStmt = $db->prepare("SELECT t.* FROM tickets t $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets — ILS Help Desk</title>
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
                <a class="nav-link active" href="/ILSHD/user/my-tickets.php">My Tickets</a>
            </li>
        </ul>
    </div>
</div>

<!-- Content -->
<div class="container py-4">
    <h2 class="section-heading mb-4">My Tickets</h2>

    <!-- Overview pills -->
    <div class="d-flex flex-wrap gap-3 mb-4">
        <div class="overview-pill pending">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Pending &nbsp;<span class="badge rounded-pill" style="background:#F0A500; color:#fff;"><?= $counts['Pending'] ?></span>
        </div>
        <div class="overview-pill resolved">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Resolved &nbsp;<span class="badge rounded-pill" style="background:#2E8B4A; color:#fff;"><?= $counts['Resolved'] ?></span>
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-4">
        <div class="input-group" style="max-width:420px;">
            <span class="input-group-text bg-white border-end-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </span>
            <input type="text" name="search" class="form-control border-start-0 search-auto-submit"
                   placeholder="Search tickets…" value="<?= htmlspecialchars($search) ?>">
        </div>
    </form>

    <!-- Table -->
    <?php if (empty($tickets)): ?>
        <div class="text-center py-5 text-muted">No tickets found.</div>
    <?php else: ?>
    <div class="card ils-card mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ticket ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td class="ticket-id">#<?= $t['id'] ?></td>
                        <td><?= htmlspecialchars($t['subject']) ?></td>
                        <td>
                            <?php if ($t['status'] === 'Pending'): ?>
                                <span class="badge badge-medium">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-low">Resolved</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDateShort($t['created_at']) ?></td>
                        <td>
                            <a href="/ILSHD/user/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-yellow btn-sm">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-muted">
            Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $total) ?> of <?= $total ?> tickets
        </small>
        <nav>
            <ul class="pagination mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">&lsaquo;</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">&rsaquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div><!-- .container -->

<footer class="py-3 text-center ils-footer mt-4">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
</body>
</html>
