<?php
// common/config.php - Complete and correct version

// Database configuration
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_USER', 'if0_41412895');
define('DB_PASS', 'XEmhBNTfI4xPR9');
define('DB_NAME', 'if0_41412895_tel_project');

// Create connection
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // In production, you might want to log this instead of dying
        die("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Security script (no session_start here!)
function getSecurityScript() {
    $is_admin = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    if ($is_admin) {
        return "<script>document.addEventListener('contextmenu', event => event.preventDefault()); document.addEventListener('selectstart', event => event.preventDefault());</script>";
    } else {
        return "<script>document.addEventListener('contextmenu', event => event.preventDefault()); document.addEventListener('selectstart', event => event.preventDefault()); document.addEventListener('keydown', function(e) { if (e.ctrlKey && (e.key === '+' || e.key === '-' || e.key === '0' || e.key === '=')) e.preventDefault(); }); document.addEventListener('touchmove', function(e) { if (e.scale !== 1) e.preventDefault(); }, { passive: false }); let lastTouchEnd = 0; document.addEventListener('touchend', function(e) { const now = (new Date()).getTime(); if (now - lastTouchEnd <= 300) e.preventDefault(); lastTouchEnd = now; }, false);</script>";
    }
}
?>