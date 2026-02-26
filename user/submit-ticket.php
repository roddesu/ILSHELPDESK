<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudent();

$user = getCurrentUser();
$userId = $user['id'];
$unreadCount = getUnreadCount($userId);

$subjects = ['LMS Account', 'UB Mail Account', 'EBrahman Account', 'Ubian Account'];
$issueMap = [];
foreach ($subjects as $s) {
    $issueMap[$s] = getIssueDescriptions($s);
}

$db = getDB();

// Check for pending ticket
$pendingStmt = $db->prepare("SELECT id FROM tickets WHERE user_id = ? AND status = 'Pending' LIMIT 1");
$pendingStmt->execute([$userId]);
$pendingTicket = $pendingStmt->fetch();

$ticketSubmitted = false;
$ticketId        = null;
$error           = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendingTicket) {
    $concern = $_POST['concern_type']       ?? '';
    $urgency = $_POST['urgency_level']      ?? '';
    $subject = $_POST['subject']            ?? '';
    $desc    = $_POST['issue_description']  ?? '';
    $comment = trim($_POST['additional_comments'] ?? '');
    $device  = $_POST['device_type']        ?? '';
    $date    = $_POST['date_needed']        ?? null;

    if (!$concern || !$urgency || !$subject || !$desc) {
        $error = 'Please fill in all required fields.';
    } else {
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName   = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['attachment']['name']));
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachmentPath = $fileName;
            } else {
                $error = 'Failed to upload attachment.';
            }
        }

        if (!$error) {
            try {
                $stmt = $db->prepare("INSERT INTO tickets (user_id, concern_type, urgency_level, subject, issue_description, additional_comments, device_type, attachment, date_needed, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt->execute([$userId, $concern, $urgency, $subject, $desc, $comment, $device, $attachmentPath, $date ?: null]);
                $ticketId = $db->lastInsertId();

                createNotification($userId, $ticketId, 'submitted', "Ticket #$ticketId submitted: $subject");
                sendTicketConfirmation($user['school_email']);

                $ticketSubmitted = true;
                $_POST = [];
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Ticket — ILS Help Desk</title>
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
                <a class="nav-link active" href="/ILSHD/user/submit-ticket.php">Submit Ticket</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/ILSHD/user/my-tickets.php">My Tickets</a>
            </li>
        </ul>
    </div>
</div>

<!-- Content -->
<div class="container py-4">

<?php if ($ticketSubmitted): ?>
    <!-- Success State -->
    <div class="ils-state-screen">
        <div class="rounded-circle d-flex align-items-center justify-content-center mb-4"
             style="width:88px;height:88px;background:var(--ils-green);">
            <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h2 class="fw-bold mb-2">Ticket Submitted</h2>
        <p class="fw-semibold mb-2">Your ticket #<?= $ticketId ?> has been successfully submitted!</p>
        <p class="text-muted mb-4" style="max-width:340px; font-size:0.9rem;">
            Thank you for submitting your ticket. Our support team will work on resolving the issue within the next 1–2 days. We appreciate your patience.
        </p>
        <a href="/ILSHD/user/my-tickets.php" class="btn btn-ils-blue btn-lg px-4">View My Tickets</a>
    </div>

<?php elseif ($pendingTicket): ?>
    <!-- Cannot Submit State -->
    <div class="ils-state-screen">
        <div class="mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
            </svg>
        </div>
        <h2 class="fw-bold mb-3" style="max-width:300px;">You cannot submit another ticket until the current ticket has been resolved</h2>
        <a href="/ILSHD/user/my-tickets.php" class="btn btn-ils-blue btn-lg px-4">View My Tickets</a>
    </div>

<?php else: ?>
    <!-- Submit Form -->
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h2 class="section-heading mb-4">Submit a Ticket</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card ils-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Ticket Details</h6>
                    <form method="POST" enctype="multipart/form-data">

                        <div class="mb-3">
                            <div class="d-flex flex-wrap gap-4 align-items-center">
                                <div>
                                    <label class="form-label mb-2">Type of Concern</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="concern_type" id="concern_request" value="Request" <?= ($_POST['concern_type'] ?? '') === 'Request' ? 'checked' : '' ?> required>
                                            <label class="form-check-label" for="concern_request">Request</label>
                                        </div>
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="radio" name="concern_type" id="concern_incident" value="Incident" <?= ($_POST['concern_type'] ?? '') === 'Incident' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="concern_incident">Incident</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="ms-auto" style="min-width:180px;">
                                    <label class="form-label">Urgency Level</label>
                                    <select name="urgency_level" class="form-select" required>
                                        <option value="">Urgency Level</option>
                                        <option value="Low"    <?= ($_POST['urgency_level'] ?? '') === 'Low'    ? 'selected' : '' ?>>Low</option>
                                        <option value="Medium" <?= ($_POST['urgency_level'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="High"   <?= ($_POST['urgency_level'] ?? '') === 'High'   ? 'selected' : '' ?>>High</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <select name="subject" id="subject" class="form-select" required onchange="updateIssues()">
                                <option value="">Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s ?>" <?= ($_POST['subject'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Issue Description</label>
                            <select name="issue_description" id="issue_description" class="form-select" required>
                                <option value="">Issue Description</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Additional Comments:</label>
                            <textarea name="additional_comments" class="form-control" rows="3" placeholder="Additional comments..."><?= htmlspecialchars($_POST['additional_comments'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Device Type</label>
                            <select name="device_type" class="form-select">
                                <option value="">Device Type</option>
                                <option value="Laptop"       <?= ($_POST['device_type'] ?? '') === 'Laptop'       ? 'selected' : '' ?>>Laptop</option>
                                <option value="Desktop"      <?= ($_POST['device_type'] ?? '') === 'Desktop'      ? 'selected' : '' ?>>Desktop</option>
                                <option value="Mobile Phone" <?= ($_POST['device_type'] ?? '') === 'Mobile Phone' ? 'selected' : '' ?>>Mobile Phone</option>
                                <option value="Tablet"       <?= ($_POST['device_type'] ?? '') === 'Tablet'       ? 'selected' : '' ?>>Tablet</option>
                                <option value="Other"        <?= ($_POST['device_type'] ?? '') === 'Other'        ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <label class="btn btn-outline-secondary btn-sm mb-0" for="attachment" style="cursor:pointer;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    Attach a file
                                </label>
                                <span class="text-muted small">Max file size: 40MB</span>
                            </div>
                            <input type="file" id="attachment" name="attachment" class="d-none">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-yellow btn-lg">Submit Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div><!-- .container -->

<footer class="py-3 text-center ils-footer mt-4">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
<script src="/ILSHD/js/notifications.js"></script>
<script>
const issueMap = <?= json_encode($issueMap) ?>;

function updateIssues() {
    const subject  = document.getElementById('subject').value;
    const issueSel = document.getElementById('issue_description');
    issueSel.innerHTML = '<option value="">Issue Description</option>';
    if (subject && issueMap[subject]) {
        issueMap[subject].forEach(issue => {
            const opt = document.createElement('option');
            opt.value       = issue;
            opt.textContent = issue;
            issueSel.appendChild(opt);
        });
    }
}

// Pre-select issue if returning from error
document.addEventListener('DOMContentLoaded', function() {
    const savedSubject = <?= json_encode($_POST['subject'] ?? '') ?>;
    const savedIssue   = <?= json_encode($_POST['issue_description'] ?? '') ?>;
    if (savedSubject) {
        document.getElementById('subject').value = savedSubject;
        updateIssues();
        if (savedIssue) document.getElementById('issue_description').value = savedIssue;
    }
});
</script>
</body>
</html>
