<?php
// common/config.php - Railway MySQL version with correct credentials

// Database configuration (from Railway connection string)
define('DB_HOST', 'hopper.proxy.rlwy.net');
define('DB_PORT', '39171');                // Railway MySQL port
define('DB_USER', 'root');
define('DB_PASS', 'NTeymqHZArsWVLXmWtrOoqaqtmbWzLvU');  // Railway password
define('DB_NAME', 'railway');               // Railway default database name

// Create connection (with port)
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Security script (unchanged)
function getSecurityScript() {
    $is_admin = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    if ($is_admin) {
        return "<script>document.addEventListener('contextmenu', event => event.preventDefault()); document.addEventListener('selectstart', event => event.preventDefault());</script>";
    } else {
        return "<script>document.addEventListener('contextmenu', event => event.preventDefault()); document.addEventListener('selectstart', event => event.preventDefault()); document.addEventListener('keydown', function(e) { if (e.ctrlKey && (e.key === '+' || e.key === '-' || e.key === '0' || e.key === '=')) e.preventDefault(); }); document.addEventListener('touchmove', function(e) { if (e.scale !== 1) e.preventDefault(); }, { passive: false }); let lastTouchEnd = 0; document.addEventListener('touchend', function(e) { const now = (new Date()).getTime(); if (now - lastTouchEnd <= 300) e.preventDefault(); lastTouchEnd = now; }, false);</script>";
    }
}
?>