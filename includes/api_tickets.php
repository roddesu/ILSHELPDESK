<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) { exit; }

$userId = $_SESSION['user_id'];
$db = getDB();

$perPage = 3;
$page    = max(1, (int)($_GET['page'] ?? 1));
$search  = trim($_GET['search'] ?? '');
$offset  = ($page - 1) * $perPage;

$where  = "WHERE t.user_id = ?";
$params = [$userId];
if ($search !== '') {
    $where   .= " AND (t.subject LIKE ? OR t.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$listStmt = $db->prepare("SELECT t.* FROM tickets t $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();

$html = '';
foreach ($tickets as $t) {
    $statusBadge = $t['status'] === 'Pending' 
        ? '<span class="badge badge-medium">Pending</span>' 
        : '<span class="badge badge-low">Resolved</span>';
    
    $html .= '<tr>
        <td class="ticket-id">#' . $t['id'] . '</td>
        <td>' . htmlspecialchars($t['subject']) . '</td>
        <td>' . $statusBadge . '</td>
        <td>' . formatDateShort($t['created_at']) . '</td>
        <td>
            <a href="/ILSHD/user/view-ticket.php?id=' . $t['id'] . '" class="btn btn-yellow btn-sm">View</a>
        </td>
    </tr>';
}

$counts = getTicketCounts($userId);

header('Content-Type: application/json');
echo json_encode(['html' => $html, 'counts' => $counts]);