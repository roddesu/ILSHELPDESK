<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) { exit; }

$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();
$db = getDB();

// Handle Mark Read
if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    if ($isAdmin) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id IN (SELECT id FROM users WHERE role='admin')")->execute();
    } else {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
    }
    echo 'ok';
    exit;
}

// Fetch Notifications
if ($isAdmin) {
    $stmt = $db->query("
        SELECT ticket_id, type, message, MIN(is_read) AS is_read, MAX(created_at) AS created_at
        FROM notifications
        WHERE user_id IN (SELECT id FROM users WHERE role='admin')
        GROUP BY ticket_id, type, message
        ORDER BY created_at DESC LIMIT 5
    ");
    $notifs = $stmt->fetchAll();
    $unread = getAdminUnreadCount();
    $viewAllLink = '/ILSHD/admin/notifications.php';
} else {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $notifs = $stmt->fetchAll();
    $unread = getUnreadCount($userId);
    $viewAllLink = '/ILSHD/user/notifications.php';
}

$html = '';
if (empty($notifs)) {
    $html .= '<li class="text-center py-3 text-muted small">No notifications</li>';
} else {
    foreach ($notifs as $n) {
        $link = ($isAdmin ? '/ILSHD/admin/view-ticket.php?id=' : '/ILSHD/user/view-ticket.php?id=') . $n['ticket_id'];
        $bgClass = !$n['is_read'] ? 'bg-light' : '';
        $msg = htmlspecialchars($n['message']);
        $time = timeAgo($n['created_at']);
        
        $html .= '<li><a href="'.$link.'" class="dropdown-item py-2 '.$bgClass.'" style="white-space:normal; border-bottom:1px solid #f0f0f0;">
                    <div class="small text-dark fw-semibold mb-1">'.$msg.'</div>
                    <div class="text-muted small" style="font-size:0.75rem;">'.$time.'</div>
                  </a></li>';
    }
}

header('Content-Type: application/json');
echo json_encode(['html' => $html, 'unread' => $unread, 'viewAll' => $viewAllLink]);