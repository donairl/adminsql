<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to main page
header('Location: main.php');
exit;
?>
