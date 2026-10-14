<?php
session_start();
// Remove all session variables
$_SESSION = array();
// Destroy the session memory
session_destroy();
// Redirect to login page
header("Location: login.php");
exit();
?>