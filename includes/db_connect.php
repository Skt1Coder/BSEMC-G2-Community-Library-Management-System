<?php

$host = 'localhost';
$dbname = 'library_db';
$username = 'root'; // Default XAMPP username
$password = ''; // Default XAMPP password (currently empty)

try {
    // PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Forces PHP to display detailed errors if a database query fails
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>