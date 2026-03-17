<?php
// admin/logout.php - Admin logout script

require_once '../common/config.php';

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>