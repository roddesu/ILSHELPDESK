<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/db.php';

function formatDate($dateStr) {
    if (!$dateStr) return '';
    $ts = strtotime($dateStr);
    return date('F j, Y', $ts);
}

function formatDateShort($dateStr) {
    if (!$dateStr) return '';
    $ts = strtotime($dateStr);
    return date('M j, Y', $ts);
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->i < 1 && $diff->h === 0 && $diff->d === 0) return 'Just now';
    if ($diff->h === 0 && $diff->d === 0) return $diff->i . 'm ago';
    if ($diff->d === 0) return $diff->h . 'h ago';
    if ($diff->d < 7) return $diff->d . 'd ago';
    return date('M j', strtotime($datetime));
}

function getUnreadCount($userId) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function hasPendingTicket($userId) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM tickets WHERE user_id = ? AND status = 'Pending' LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() !== false;
}

function getTicketCounts($userId) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM tickets WHERE user_id = ? GROUP BY status");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $counts = ['Pending' => 0, 'Resolved' => 0];
    foreach ($rows as $r) {
        $counts[$r['status']] = $r['cnt'];
    }
    return $counts;
}

function getIssueDescriptions($subject) {
    $map = [
        'LMS Account'       => ['System shows an error', 'Cannot log in to the system', 'Slow System Performance', 'Software or feature setup needed', 'Account suddenly locked', 'Password not working'],
        'UB Mail Account'   => ['System shows an error', 'Cannot log in', 'Cannot receive emails', 'Password not working'],
        'EBrahman Account'  => ['Request password reset', 'Update Profile Information', 'Cannot log in', 'Account suddenly locked'],
        'Ubian Account'     => ['System shows an error', 'Password reset required', 'Cannot log in', 'Slow System Performance'],
    ];
    return $map[$subject] ?? [];
}

function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function expandEmailFromInput($input) {
    $input = trim($input ?? '');
    // If input doesn't contain '@', assume it's a student number and append domain
    if (strpos($input, '@') === false) {
        return $input . '@ub.edu.ph'; // Update this to your school's domain
    }
    return $input;
}

function getAdminUsers() {
    $db   = getDB();
    $stmt = $db->query("SELECT id, school_email FROM users WHERE role = 'admin'");
    return $stmt->fetchAll();
}

function createNotification($userId, $ticketId, $type, $message) {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, ticket_id, type, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $ticketId, $type, $message]);
}

function getAdminUnreadCount() {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id IN (SELECT id FROM users WHERE role='admin') AND is_read = 0");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function sendEmail($to, $subject, $message) {
    // Load PHPMailer classes manually
    // Ensure the PHPMailer folder is in includes/PHPMailer-master/src/
    $phpMailerPath = __DIR__ . '/PHPMailer-master/src/';
    if (!file_exists($phpMailerPath . 'PHPMailer.php')) {
        die("<strong>Error:</strong> PHPMailer is missing. Please download PHPMailer and place the 'src' folder in: <code>" . $phpMailerPath . "</code>");
    }

    require_once $phpMailerPath . 'Exception.php';
    require_once $phpMailerPath . 'PHPMailer.php';
    require_once $phpMailerPath . 'SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ils.helpdesk.official@gmail.com';
        $mail->Password   = 'tpph xkps bgqb qhvi';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('ils.helpdesk.official@gmail.com', 'ILS Help Desk');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Email Template with Logo
        $logoColor  = '#F0A500'; // Yellow
        $brandColor = '#2E8B4A'; // Green
        
        $htmlContent = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .email-wrapper { width: 100%; background-color: #f4f4f4; padding: 40px 0; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eeeeee; margin-bottom: 20px; }
                .logo-script { font-family: "Brush Script MT", cursive; font-size: 32px; color: ' . $logoColor . '; font-weight: normal; }
                .logo-text { font-family: Arial, sans-serif; font-size: 20px; color: ' . $brandColor . '; font-weight: bold; margin-left: 5px; }
                .content { font-size: 16px; line-height: 1.6; color: #333333; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eeeeee; text-align: center; font-size: 12px; color: #999999; }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-container">
                    <div class="header">
                        <!-- Replace the lines below with <img src="https://your-school.edu/logo.png" width="150"> if you have a hosted image -->
                        <span class="logo-script">ils.</span><span class="logo-text">Help Desk</span>
                    </div>
                    <div class="content">
                        ' . $message . '
                    </div>
                    <div class="footer">
                        &copy; ' . date('Y') . ' ILSSupport. All rights reserved.<br>
                        This is an automated message, please do not reply.
                    </div>
                </div>
            </div>
        </body>
        </html>';

        $mail->Body = $htmlContent;

        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

function sendTicketConfirmation($to) {
    $subject = "Ticket Received";
    $message = "Thank you for submitting your ticket. Our support team will work on resolving the issue within the next 1–2 days. We appreciate your patience.";
    sendEmail($to, $subject, $message);
}

function cleanupExpiredTokens() {
    $db = getDB();
    $db->query("DELETE FROM password_resets WHERE expires_at < NOW()");
}
