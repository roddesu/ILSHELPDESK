<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /ILSHD/index.php?login_required=1');
        exit;
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: /ILSHD/admin/tickets.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /ILSHD/user/submit-ticket.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    // Hardcoded admin uses id=0 — return session data as synthetic user
    if ($_SESSION['user_id'] === 0) {
        $parts = explode(' ', $_SESSION['name'], 2);
        return [
            'id'            => 0,
            'first_name'    => $parts[0],
            'last_name'     => $parts[1] ?? '',
            'school_email'  => $_SESSION['email'],
            'role'          => $_SESSION['role'],
            'profile_image' => null,
        ];
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function loginUser($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email']   = $user['school_email'];
}

function logoutUser() {
    session_destroy();
}
