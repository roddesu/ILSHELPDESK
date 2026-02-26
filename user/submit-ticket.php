<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudent();

$user = getCurrentUser();
$success = '';
$error = '';

// Subjects available based on getIssueDescriptions in functions.php
$subjects = ['LMS Account', 'UB Mail Account', 'EBrahman Account', 'Ubian Account'];
$issueMap = [];
foreach ($subjects as $s) {
    $issueMap[$s] = getIssueDescriptions($s);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $concern = $_POST['concern_type'] ?? '';
    $urgency = $_POST['urgency_level'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $desc    = $_POST['issue_description'] ?? '';
    $comment = trim($_POST['additional_comments'] ?? '');
    $device  = trim($_POST['device_type'] ?? '');
    $date    = $_POST['date_needed'] ?? null;
    
    if (!$concern || !$urgency || !$subject || !$desc) {
        $error = 'Please fill in all required fields.';
    } else {
        // Handle Attachment
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            // Sanitize filename
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['attachment']['name']));
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachmentPath = $fileName;
            } else {
                $error = 'Failed to upload attachment.';
            }
        }

        if (!$error) {
            $db = getDB();
            try {
                $stmt = $db->prepare("INSERT INTO tickets (user_id, concern_type, urgency_level, subject, issue_description, additional_comments, device_type, attachment, date_needed, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt->execute([$user['id'], $concern, $urgency, $subject, $desc, $comment, $device, $attachmentPath, $date ?: null]);
                $ticketId = $db->lastInsertId();

                // Create Notification
                createNotification($user['id'], $ticketId, 'submitted', "Ticket #$ticketId submitted: $subject");

                // Send Email Confirmation using the new function
                sendTicketConfirmation($user['school_email']);

                $success = 'Ticket submitted successfully! A confirmation email has been sent.';
                // Clear POST to prevent resubmission
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
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--ils-green);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">ILS Help Desk</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="submit-ticket.php">Submit Ticket</a></li>
                    <li class="nav-item"><a class="nav-link" href="my-tickets.php">My Tickets</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0 fw-bold text-dark">Submit a Ticket</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Concern Type <span class="text-danger">*</span></label>
                                    <select name="concern_type" class="form-select" required>
                                        <option value="">Select...</option>
                                        <option value="Request">Request</option>
                                        <option value="Incident">Incident</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Urgency Level <span class="text-danger">*</span></label>
                                    <select name="urgency_level" class="form-select" required>
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <select name="subject" id="subject" class="form-select" required onchange="updateIssues()">
                                    <option value="">Select Subject...</option>
                                    <?php foreach ($subjects as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                                <select name="issue_description" id="issue_description" class="form-select" required>
                                    <option value="">Select Subject first...</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Additional Comments</label>
                                <textarea name="additional_comments" class="form-control" rows="3"></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Device Type</label>
                                    <input type="text" name="device_type" class="form-control" placeholder="e.g. Laptop, Mobile">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Needed</label>
                                    <input type="date" name="date_needed" class="form-control">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Attachment</label>
                                <input type="file" name="attachment" class="form-control">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-yellow btn-lg">Submit Ticket</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const issueMap = <?= json_encode($issueMap) ?>;
        
        function updateIssues() {
            const subject = document.getElementById('subject').value;
            const issueSelect = document.getElementById('issue_description');
            
            issueSelect.innerHTML = '<option value="">Select Issue...</option>';
            
            if (subject && issueMap[subject]) {
                issueMap[subject].forEach(issue => {
                    const option = document.createElement('option');
                    option.value = issue;
                    option.textContent = issue;
                    issueSelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>