<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    $db = getDB();
    $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
}

logoutUser();
setcookie('remember_token', '', time() - 3600, '/');
header('Location: /ILSHD/index.php');
exit;
