<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$user        = getCurrentUser();
$unreadCount = getAdminUnreadCount();
$db          = getDB();

// Overview counts
$totals = $db->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status")->fetchAll();
$counts = ['Pending' => 0, 'Resolved' => 0, 'total' => 0];
foreach ($totals as $r) {
    $counts[$r['status']] = (int)$r['cnt'];
    $counts['total']     += (int)$r['cnt'];
}
$pendingPct  = $counts['total'] > 0 ? round($counts['Pending']  / $counts['total'] * 100) : 0;
$resolvedPct = $counts['total'] > 0 ? round($counts['Resolved'] / $counts['total'] * 100) : 0;

// Filters
$search      = trim($_GET['search']      ?? '');
$fConcern    = $_GET['concern']          ?? '';
$fSubject    = $_GET['subject']          ?? '';
$fDesc       = trim($_GET['description'] ?? '');
$fDept       = $_GET['department']       ?? '';
$fUrgency    = $_GET['urgency']          ?? '';
$fDate       = $_GET['date_needed']      ?? '';

$perPage = 5;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where  .= " AND (t.id LIKE ? OR t.subject LIKE ? OR t.issue_description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.school_email LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
}
if ($fConcern)  { $where .= " AND t.concern_type = ?";   $params[] = $fConcern; }
if ($fSubject)  { $where .= " AND t.subject = ?";        $params[] = $fSubject; }
if ($fDesc)     { $where .= " AND t.issue_description LIKE ?"; $params[] = "%$fDesc%"; }
if ($fDept)     { $where .= " AND u.department = ?";     $params[] = $fDept; }
if ($fUrgency)  { $where .= " AND t.urgency_level = ?";  $params[] = $fUrgency; }
if ($fDate)     { $where .= " AND t.date_needed = ?";    $params[] = $fDate; }

$sql   = "SELECT t.*, u.first_name, u.last_name, u.school_email, u.department FROM tickets t JOIN users u ON t.user_id = u.id $where ORDER BY t.created_at DESC";
$total = (int)$db->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id $where")->execute($params) ? 0 : 0;

$countStmt = $db->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$listStmt = $db->prepare("$sql LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();

$departments = ['CICT', 'CAMS', 'CENG', 'CAS', 'CIT', 'CBA', 'COED', 'CNRS'];
$subjects    = ['LMS Account', 'UB Mail Account', 'EBrahman Account', 'Ubian Account'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets — ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Support Tickets</h1>
                <p class="text-muted mb-0">Ticket Overview</p>
            </div>
        </div>

        <!-- Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="d-inline-block position-relative mb-3">
                            <canvas id="donutPending" width="120" height="120"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <div class="fw-bold fs-4 text-warning"><?= $pendingPct ?>%</div>
                                <div class="small text-muted">Pending</div>
                            </div>
                        </div>
                        <h5 class="card-title text-warning"><?= $pendingPct ?>% Pending</h5>
                        <p class="card-text text-muted"><?= $counts['Pending'] ?> tickets</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="d-inline-block position-relative mb-3">
                            <canvas id="donutResolved" width="120" height="120"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <div class="fw-bold fs-4 text-success"><?= $resolvedPct ?>%</div>
                                <div class="small text-muted">Resolved</div>
                            </div>
                        </div>
                        <h5 class="card-title text-success"><?= $resolvedPct ?>% Resolved</h5>
                        <p class="card-text text-muted"><?= $counts['Resolved'] ?> tickets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <form method="GET" class="d-flex flex-grow-1">
                                <input type="text" name="search" class="form-control" placeholder="Search tickets..." value="<?= htmlspecialchars($search) ?>">
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <a href="/ILSHD/admin/analytics.php" class="btn btn-outline-primary w-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            View Analytics
                        </a>
                    </div>
                </div>
                <form method="GET" class="mt-3">
                    <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <select name="concern" class="form-select" onchange="this.form.submit()">
                                <option value="">All Concerns</option>
                                <option value="Request"  <?= $fConcern === 'Request'  ? 'selected' : '' ?>>Request</option>
                                <option value="Incident" <?= $fConcern === 'Incident' ? 'selected' : '' ?>>Incident</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="subject" class="form-select" onchange="this.form.submit()">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s ?>" <?= $fSubject === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="description" class="form-control" placeholder="Description…" value="<?= htmlspecialchars($fDesc) ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="department" class="form-select" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d ?>" <?= $fDept === $d ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="urgency" class="form-select" onchange="this.form.submit()">
                                <option value="">All Urgency</option>
                                <option value="Low"    <?= $fUrgency === 'Low'    ? 'selected' : '' ?>>Low</option>
                                <option value="Medium" <?= $fUrgency === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="High"   <?= $fUrgency === 'High'   ? 'selected' : '' ?>>High</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_needed" class="form-control" value="<?= htmlspecialchars($fDate) ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                        <?php if ($fConcern || $fSubject || $fDesc || $fDept || $fUrgency || $fDate): ?>
                            <a href="/ILSHD/admin/tickets.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="btn btn-outline-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket ID</th>
                                <th>Concern</th>
                                <th>Subject</th>
                                <th>Description</th>
                                <th>Requester</th>
                                <th>Department</th>
                                <th>Urgency</th>
                                <th>Date Needed</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">No tickets found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><strong>#<?= $t['id'] ?></strong></td>
                                <td><?= htmlspecialchars($t['concern_type']) ?></td>
                                <td><?= htmlspecialchars($t['subject']) ?></td>
                                <td style="max-width:200px;" class="text-truncate"><?= htmlspecialchars($t['issue_description']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($t['school_email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($t['department']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $t['urgency_level'] === 'High' ? 'danger' : ($t['urgency_level'] === 'Medium' ? 'warning' : 'secondary') ?>">
                                        <?= $t['urgency_level'] ?>
                                    </span>
                                </td>
                                <td><?= $t['date_needed'] ? formatDateShort($t['date_needed']) : '—' ?></td>
                                <td>
                                    <a href="/ILSHD/admin/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav aria-label="Ticket pagination" class="mt-3">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&concern=<?= urlencode($fConcern) ?>&subject=<?= urlencode($fSubject) ?>&department=<?= urlencode($fDept) ?>&urgency=<?= urlencode($fUrgency) ?>&date_needed=<?= urlencode($fDate) ?>">Previous</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&concern=<?= urlencode($fConcern) ?>&subject=<?= urlencode($fSubject) ?>&department=<?= urlencode($fDept) ?>&urgency=<?= urlencode($fUrgency) ?>&date_needed=<?= urlencode($fDate) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&concern=<?= urlencode($fConcern) ?>&subject=<?= urlencode($fSubject) ?>&department=<?= urlencode($fDept) ?>&urgency=<?= urlencode($fUrgency) ?>&date_needed=<?= urlencode($fDate) ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </main>

<script>
function makeDonut(id, pct, color, bg) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [pct, 100 - pct],
                backgroundColor: [color, bg],
                borderWidth: 0,
            }]
        },
        options: {
            cutout: '72%',
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            animation: { duration: 600 }
        }
    });
}
makeDonut('donutPending',  <?= $pendingPct  ?>, '#F57C00', '#FFF3E0');
makeDonut('donutResolved', <?= $resolvedPct ?>, '#2E8B4A', '#E8F5E9');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
<script src="/ILSHD/js/notifications.js"></script>
</body>
</html>
