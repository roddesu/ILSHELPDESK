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
        header('Location: /ILSHD/login.php');
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
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email']   = $user['school_email'];
}

function logoutUser() {
    session_destroy();
}
