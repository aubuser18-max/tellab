<?php
// users/auth_check.php - Verify user is active and not expired
// Assumes session has already been started in the calling page

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../common/config.php';
$conn = getDB();
$user_id = $_SESSION['user_id'];

$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$conn->close();

if (!$user) {
    session_destroy();
    header("Location: login.php?error=account_not_found");
    exit();
}

if ($user['status'] !== 'active') {
    session_destroy();
    header("Location: login.php?error=account_inactive");
    exit();
}

if ($user['expiry_timestamp'] && strtotime($user['expiry_timestamp']) < time()) {
    session_destroy();
    header("Location: login.php?error=account_expired");
    exit();
}

// Refresh session data
$_SESSION['user_username'] = $user['username'];
?>