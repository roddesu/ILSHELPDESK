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
$search   = trim($_GET['search']      ?? '');
$fConcern = $_GET['concern']          ?? '';
$fSubject = $_GET['subject']          ?? '';
$fDesc    = trim($_GET['description'] ?? '');
$fDept    = $_GET['department']       ?? '';
$fUrgency = $_GET['urgency']          ?? '';
$fDate    = $_GET['date_needed']      ?? '';

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
if ($fConcern)  { $where .= " AND t.concern_type = ?";        $params[] = $fConcern; }
if ($fSubject)  { $where .= " AND t.subject = ?";             $params[] = $fSubject; }
if ($fDesc)     { $where .= " AND t.issue_description LIKE ?"; $params[] = "%$fDesc%"; }
if ($fDept)     { $where .= " AND u.department = ?";          $params[] = $fDept; }
if ($fUrgency)  { $where .= " AND t.urgency_level = ?";       $params[] = $fUrgency; }
if ($fDate)     { $where .= " AND t.date_needed = ?";         $params[] = $fDate; }

$sql = "SELECT t.*, u.first_name, u.last_name, u.school_email, u.department FROM tickets t JOIN users u ON t.user_id = u.id $where ORDER BY t.created_at DESC";

$countStmt = $db->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON t.user_id = u.id $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$listStmt = $db->prepare("$sql LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();

$departments = ['CICT', 'CAMS', 'CENG', 'CAS', 'CIT', 'CBA', 'COED', 'CNRS'];
$subjects    = ['LMS Account', 'UB Mail Account', 'EBrahman Account', 'Ubian Account'];

$qBase = http_build_query(array_filter(['search' => $search, 'concern' => $fConcern, 'subject' => $fSubject, 'description' => $fDesc, 'department' => $fDept, 'urgency' => $fUrgency, 'date_needed' => $fDate]));
$qBase = $qBase ? '&' . $qBase : '';
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
<nav class="navbar ils-navbar sticky-top">
    <div class="container-fluid px-3">
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
                    <span class="notif-dot" id="notif-badge" style="display:none;"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end p-0 shadow border-0" aria-labelledby="notifDropdown" style="width:300px;">
                    <li><div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light rounded-top"><h6 class="mb-0 small fw-bold">Notifications</h6></div></li>
                    <div id="notif-list" style="max-height:300px; overflow-y:auto;"></div>
                    <li><a class="dropdown-item text-center small text-primary border-top py-2 rounded-bottom" id="notif-view-all" href="#">View All</a></li>
                </ul>
            </div>
            <span class="d-none d-lg-inline text-dark fw-semibold small">ILS Support</span>
            <div class="dropdown">
                <a href="#" class="user-avatar text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($user['profile_image']): ?>
                        <img src="/ILSHD/uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                    <?php endif; ?>
                </a>
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
    <div class="card ils-card p-4">

        <!-- Header -->
        <div class="mb-3">
            <h1 class="section-heading mb-0">Support Tickets</h1>
            <p class="text-muted mb-0 small">Ticket Overview</p>
        </div>

        <!-- Donut Overview -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="donut-wrap">
                            <canvas id="donutPending"></canvas>
                            <div class="donut-center" style="color:var(--ils-orange);">
                                <?= $pendingPct ?>%<small>Pending</small>
                            </div>
                        </div>
                        <div class="fw-bold" style="color:var(--ils-orange);"><?= $pendingPct ?>%<br><small class="fw-normal text-muted" style="font-size:0.75rem;">Pending</small></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="donut-wrap">
                            <canvas id="donutResolved"></canvas>
                            <div class="donut-center" style="color:var(--ils-green);">
                                <?= $resolvedPct ?>%<small>Resolved</small>
                            </div>
                        </div>
                        <div class="fw-bold" style="color:var(--ils-green);"><?= $resolvedPct ?>%<br><small class="fw-normal text-muted" style="font-size:0.75rem;">Resolved</small></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + View Analytics -->
        <form method="GET" class="d-flex gap-3 mb-3 align-items-center">
            <div class="input-group flex-grow-1">
                <span class="input-group-text bg-white border-end-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input type="text" name="search" class="form-control border-start-0 search-auto-submit"
                       placeholder="Search tickets..." value="<?= htmlspecialchars($search) ?>">
                <?php if ($fConcern): ?><input type="hidden" name="concern"     value="<?= htmlspecialchars($fConcern) ?>"><?php endif; ?>
                <?php if ($fSubject): ?><input type="hidden" name="subject"     value="<?= htmlspecialchars($fSubject) ?>"><?php endif; ?>
                <?php if ($fDesc):    ?><input type="hidden" name="description" value="<?= htmlspecialchars($fDesc) ?>"><?php endif; ?>
                <?php if ($fDept):    ?><input type="hidden" name="department"  value="<?= htmlspecialchars($fDept) ?>"><?php endif; ?>
                <?php if ($fUrgency): ?><input type="hidden" name="urgency"     value="<?= htmlspecialchars($fUrgency) ?>"><?php endif; ?>
                <?php if ($fDate):    ?><input type="hidden" name="date_needed" value="<?= htmlspecialchars($fDate) ?>"><?php endif; ?>
            </div>
            <a href="/ILSHD/admin/analytics.php" class="btn btn-ils-orange text-nowrap">View Analytics</a>
        </form>

        <!-- Filters -->
        <form method="GET" class="mb-3">
            <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
            <div class="row g-2">
                <div class="col">
                    <select name="concern" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Concern</option>
                        <option value="Incident" <?= $fConcern === 'Incident' ? 'selected' : '' ?>>Incident</option>
                        <option value="Request"  <?= $fConcern === 'Request'  ? 'selected' : '' ?>>Request</option>
                    </select>
                </div>
                <div class="col">
                    <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s ?>" <?= $fSubject === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <input type="text" name="description" class="form-control form-control-sm"
                           placeholder="Description" value="<?= htmlspecialchars($fDesc) ?>"
                           onblur="this.form.submit()">
                </div>
                <div class="col">
                    <select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d ?>" <?= $fDept === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <select name="urgency" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Urgency</option>
                        <option value="Medium" <?= $fUrgency === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High"   <?= $fUrgency === 'High'   ? 'selected' : '' ?>>High</option>
                        <option value="Low"    <?= $fUrgency === 'Low'    ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col">
                    <input type="date" name="date_needed" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($fDate) ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="background:#f8f9ff; color:var(--ils-gray); font-size:0.85rem; font-weight:600;">
                        <th class="py-3">Ticket ID</th>
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
                        <td class="ticket-id">#<?= $t['id'] ?></td>
                        <td><?= htmlspecialchars($t['concern_type']) ?></td>
                        <td><?= htmlspecialchars($t['subject']) ?></td>
                        <td style="max-width:200px;" class="text-truncate small"><?= htmlspecialchars($t['issue_description']) ?></td>
                        <td>
                            <div class="req-name"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></div>
                            <div class="req-email">(<?= htmlspecialchars($t['school_email']) ?>)</div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['department']) ?></span></td>
                        <td>
                            <span class="badge badge-<?= strtolower($t['urgency_level']) ?>"><?= $t['urgency_level'] ?></span>
                        </td>
                        <td class="small"><?= $t['date_needed'] ? formatDateShort($t['date_needed']) : '—' ?></td>
                        <td>
                            <a href="/ILSHD/admin/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-ils-orange btn-sm">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-end align-items-center gap-2 mt-3">
            <?php if ($page > 1): ?>
                <a class="text-muted text-decoration-none small" href="?page=<?= $page - 1 ?><?= $qBase ?>">&#8592; Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="rounded text-decoration-none <?= $i === $page ? 'btn-ils-green text-white' : 'text-muted' ?>"
                   href="?page=<?= $i ?><?= $qBase ?>"
                   style="padding:4px 10px; font-size:0.85rem; <?= $i === $page ? 'background:var(--ils-green);' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
                <a class="text-muted text-decoration-none small" href="?page=<?= $page + 1 ?><?= $qBase ?>">Next &#8594;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- .card -->
</main>

<script>
function makeDonut(id, pct, color, bg) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{ data: [pct, 100 - pct], backgroundColor: [color, bg], borderWidth: 0 }]
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
