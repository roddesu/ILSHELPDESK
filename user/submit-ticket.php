<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$user        = getCurrentUser();
$userId      = $_SESSION['user_id'];
$unreadCount = getUnreadCount($userId);
$hasPending  = hasPendingTicket($userId);

$error   = '';
$success = false;
$newId   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasPending) {
    $concern    = $_POST['concern_type']      ?? '';
    $urgency    = $_POST['urgency_level']     ?? '';
    $subject    = $_POST['subject']           ?? '';
    $issue      = $_POST['issue_description'] ?? '';
    $comments   = trim($_POST['additional_comments'] ?? '');
    $device     = $_POST['device_type']       ?? '';
    $dateNeeded = $_POST['date_needed']       ?? '';

    $allowed_concerns = ['Request', 'Incident'];
    $allowed_urgency  = ['Low', 'Medium', 'High'];
    $allowed_subjects = ['LMS Account', 'UB Mail Account', 'EBrahman Account', 'Ubian Account'];

    if (!in_array($concern, $allowed_concerns) || !in_array($urgency, $allowed_urgency) || !in_array($subject, $allowed_subjects) || !$issue) {
        $error = 'Please fill in all required fields.';
    } else {
        $attachment = null;
        if (!empty($_FILES['attachment']['name'])) {
            $file    = $_FILES['attachment'];
            $maxSize = 40 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $error = 'File size exceeds 40MB limit.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload failed.';
            } else {
                $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename  = uniqid('attach_') . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
                $attachment = $filename;
            }
        }

        if (!$error) {
            $db   = getDB();
            $stmt = $db->prepare("INSERT INTO tickets (user_id, concern_type, urgency_level, subject, issue_description, additional_comments, device_type, attachment, date_needed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $concern, $urgency, $subject, $issue, $comments ?: null, $device ?: null, $attachment, $dateNeeded ?: null]);
            $newId = $db->lastInsertId();

            $admins = getAdminUsers();
            foreach ($admins as $admin) {
                createNotification($admin['id'], $newId, 'submitted',
                    "Ticket #$newId has been submitted by {$user['first_name']} {$user['last_name']}.");
                
                $msg = "Hello,<br><br>A new ticket (#$newId) has been submitted by {$user['first_name']} {$user['last_name']}.<br><strong>Subject:</strong> " . htmlspecialchars($subject) . "<br><br>Please check the dashboard.";
                sendEmail($admin['school_email'], "New Ticket #$newId Submitted", $msg);
            }

            $success    = true;
            $hasPending = true;
        }
    }
}

$issueMap = [
    'LMS Account'      => ['System shows an error', 'Cannot log in to the system', 'Slow System Performance', 'Software or feature setup needed', 'Account suddenly locked', 'Password not working'],
    'UB Mail Account'  => ['System shows an error', 'Cannot log in', 'Cannot receive emails', 'Password not working'],
    'EBrahman Account' => ['Request password reset', 'Update Profile Information', 'Cannot log in', 'Account suddenly locked'],
    'Ubian Account'    => ['System shows an error', 'Password reset required', 'Cannot log in', 'Slow System Performance'],
];
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
                    <span class="notif-dot" id="notif-badge" style="display: none;"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end p-0 shadow border-0" aria-labelledby="notifDropdown" style="width: 300px;">
                    <li><div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light rounded-top"><h6 class="mb-0 small fw-bold">Notifications</h6></div></li>
                    <div id="notif-list" style="max-height: 300px; overflow-y: auto;"></div>
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

    <?php if ($success): ?>
    <div class="ils-state-screen">
        <div class="rounded-circle d-flex align-items-center justify-content-center mb-3"
             style="width:72px;height:72px;background:#E8F5E9;">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="var(--ils-green)" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h2 class="fw-bold mb-2">Ticket Submitted</h2>
        <p class="mb-1">Your ticket <strong>#<?= $newId ?></strong> has been successfully submitted!</p>
        <p class="text-muted mb-4" style="font-size:0.9rem;">Our support team will review your ticket and get back to you as soon as possible. Thank you for your patience.</p>
        <a href="/ILSHD/user/my-tickets.php" class="btn btn-ils-blue">View My Tickets</a>
    </div>

    <?php elseif ($hasPending): ?>
    <div class="ils-state-screen">
        <div class="rounded-circle d-flex align-items-center justify-content-center mb-3"
             style="width:72px;height:72px;background:#FFF8EE;">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="var(--ils-yellow)" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
            </svg>
        </div>
        <p class="mb-4 text-muted">You cannot submit another ticket until the current ticket has been resolved.</p>
        <a href="/ILSHD/user/my-tickets.php" class="btn btn-yellow">View My Tickets</a>
    </div>

    <?php else: ?>
    <h2 class="section-heading mb-4">Submit a Ticket</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="ticket-form">
        <div class="card ils-card mb-4">
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Type of Concern</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="concern_type" id="concern_request" value="Request"
                                   <?= ($_POST['concern_type'] ?? 'Request') === 'Request' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="concern_request">Request</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="concern_type" id="concern_incident" value="Incident"
                                   <?= ($_POST['concern_type'] ?? '') === 'Incident' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="concern_incident">Incident</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="urgency_level" class="form-label fw-semibold">Urgency Level</label>
                    <select id="urgency_level" name="urgency_level" class="form-select" required>
                        <option value="">Select urgency</option>
                        <?php foreach (['Low', 'Medium', 'High'] as $u): ?>
                            <option value="<?= $u ?>" <?= ($_POST['urgency_level'] ?? '') === $u ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="subject" class="form-label fw-semibold">Subject</label>
                    <select id="subject" name="subject" class="form-select" required>
                        <option value="">Select subject</option>
                        <?php foreach (array_keys($issueMap) as $s): ?>
                            <option value="<?= $s ?>" <?= ($_POST['subject'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="issue_description" class="form-label fw-semibold">Issue Description</label>
                    <select id="issue_description" name="issue_description" class="form-select" required>
                        <option value="">Select subject first</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="additional_comments" class="form-label fw-semibold">Additional Comments</label>
                    <textarea id="additional_comments" name="additional_comments" class="form-control" rows="3"
                              placeholder="Describe your issue in more detail..."><?= htmlspecialchars($_POST['additional_comments'] ?? '') ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="device_type" class="form-label fw-semibold">Device Type</label>
                        <select id="device_type" name="device_type" class="form-select">
                            <option value="">Select device</option>
                            <?php foreach (['Desktop', 'Laptop', 'Mobile', 'Tablet'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($_POST['device_type'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="date_needed" class="form-label fw-semibold">Date Needed</label>
                        <input type="date" id="date_needed" name="date_needed" class="form-control"
                               value="<?= htmlspecialchars($_POST['date_needed'] ?? '') ?>">
                    </div>
                </div>

                <div class="mt-3">
                    <label for="attachment" class="form-label fw-semibold">Attach a File <small class="text-muted fw-normal">(max 40MB)</small></label>
                    <input type="file" id="attachment" name="attachment" class="form-control">
                </div>
            </div>
        </div>

        <div class="d-grid d-md-flex">
            <button type="submit" class="btn btn-yellow btn-lg px-5">Submit Ticket</button>
        </div>
    </form>
    <?php endif; ?>

</div><!-- .container -->

<footer class="py-3 text-center ils-footer mt-4">
    &copy; 2026 ILSSupport. All rights reserved.
</footer>

<script>
const issueMap   = <?= json_encode($issueMap) ?>;
const subjectSel = document.getElementById('subject');
const issueSel   = document.getElementById('issue_description');
const savedIssue = <?= json_encode($_POST['issue_description'] ?? '') ?>;

function updateIssues() {
    const subject = subjectSel ? subjectSel.value : '';
    if (!issueSel) return;
    issueSel.innerHTML = '<option value="">Select issue</option>';
    (issueMap[subject] || []).forEach(function(opt) {
        const o = document.createElement('option');
        o.value = opt;
        o.textContent = opt;
        if (opt === savedIssue) o.selected = true;
        issueSel.appendChild(o);
    });
}
if (subjectSel) {
    subjectSel.addEventListener('change', updateIssues);
    updateIssues();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ILSHD/js/main.js"></script>
<script src="/ILSHD/js/notifications.js"></script>
</body>
</html>
