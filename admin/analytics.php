<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db   = getDB();
$user = getCurrentUser();
$unreadCount = getAdminUnreadCount();

// Stat totals
$totalRow      = $db->query("SELECT COUNT(*) as t, SUM(status='Resolved') as r, SUM(status='Pending') as p FROM tickets")->fetch();
$totalCount    = (int)$totalRow['t'];
$resolvedCount = (int)$totalRow['r'];
$pendingCount  = (int)$totalRow['p'];

// Most common issues — Incident
$incidentRows = $db->query("SELECT issue_description, COUNT(*) as cnt FROM tickets WHERE concern_type='Incident' GROUP BY issue_description ORDER BY cnt DESC LIMIT 5")->fetchAll();
// Most common issues — Request
$requestRows  = $db->query("SELECT issue_description, COUNT(*) as cnt FROM tickets WHERE concern_type='Request' GROUP BY issue_description ORDER BY cnt DESC LIMIT 5")->fetchAll();

// Tickets this week (Mon-Sun)
$weekData = [];
for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', strtotime("monday this week +$i days"));
    $row = $db->prepare("SELECT SUM(status='Pending') as p, SUM(status='Resolved') as r FROM tickets WHERE DATE(created_at) = ?");
    $row->execute([$day]);
    $r = $row->fetch();
    $weekData[] = ['day' => date('D', strtotime($day)), 'pending' => (int)$r['p'], 'resolved' => (int)$r['r']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — ILS Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ILSHD/css/custom.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="section-heading mb-1">Reports &amp; Analytics</h1>
            <p class="text-muted mb-0" style="font-size:0.875rem;">Overview of support ticket activity</p>
        </div>
        <a href="/ILSHD/admin/tickets.php" class="btn btn-outline-secondary">
            &larr; Back to Tickets
        </a>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card ils-card p-3 h-100 d-flex flex-row align-items-center gap-3">
                <div class="stat-icon-box" style="background:#E8F5E9;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $resolvedCount ?></h3>
                    <div class="text-muted" style="font-size:0.875rem;">Resolved Tickets</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card ils-card p-3 h-100 d-flex flex-row align-items-center gap-3">
                <div class="stat-icon-box" style="background:#FFF3E0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--ils-orange)" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $pendingCount ?></h3>
                    <div class="text-muted" style="font-size:0.875rem;">Pending Tickets</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card ils-card p-3 h-100 d-flex flex-row align-items-center gap-3">
                <div class="stat-icon-box" style="background:#E3E9F8;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--ils-blue)" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $totalCount ?></h3>
                    <div class="text-muted" style="font-size:0.875rem;">Total Tickets</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-3">
        <!-- Most common issues -->
        <div class="col-md-6">
            <div class="card ils-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Most Common Issues</h6>
                        <div class="chart-tab-group">
                            <button class="chart-tab-btn active" onclick="showTab(this,'incident')">Incident</button>
                            <button class="chart-tab-btn" onclick="showTab(this,'request')">Request</button>
                        </div>
                    </div>
                    <div id="incident-chart" style="height:250px;">
                        <canvas id="incidentBar"></canvas>
                    </div>
                    <div id="request-chart" style="height:250px; display:none;">
                        <canvas id="requestBar"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly line chart -->
        <div class="col-md-6">
            <div class="card ils-card h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Tickets This Week</h6>
                    <div style="height:286px;">
                        <canvas id="weekLine"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const incidentLabels = <?= json_encode(array_column($incidentRows, 'issue_description')) ?>;
const incidentData   = <?= json_encode(array_column($incidentRows, 'cnt')) ?>;
const requestLabels  = <?= json_encode(array_column($requestRows, 'issue_description')) ?>;
const requestData    = <?= json_encode(array_column($requestRows, 'cnt')) ?>;
const weekLabels     = <?= json_encode(array_column($weekData, 'day')) ?>;
const weekPending    = <?= json_encode(array_column($weekData, 'pending')) ?>;
const weekResolved   = <?= json_encode(array_column($weekData, 'resolved')) ?>;

function makeBar(id, labels, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: color, borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1 } },
                y: { ticks: { font: { size: 11 } } }
            }
        }
    });
}

makeBar('incidentBar', incidentLabels, incidentData, '#F57C00');
makeBar('requestBar',  requestLabels,  requestData,  '#2E8B4A');

new Chart(document.getElementById('weekLine'), {
    type: 'line',
    data: {
        labels: weekLabels,
        datasets: [
            {
                label: 'Pending',
                data: weekPending,
                borderColor: '#F0A500',
                backgroundColor: 'rgba(240,165,0,0.08)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#F0A500'
            },
            {
                label: 'Resolved',
                data: weekResolved,
                borderColor: '#2E8B4A',
                backgroundColor: 'rgba(46,139,74,0.08)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#2E8B4A'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

function showTab(btn, tab) {
    document.querySelectorAll('.chart-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('incident-chart').style.display = tab === 'incident' ? '' : 'none';
    document.getElementById('request-chart').style.display  = tab === 'request'  ? '' : 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/notifications.js"></script>
</body>
</html>
