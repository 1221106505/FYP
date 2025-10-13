<?php
// Database configuration for BookStore
$host = 'localhost';
$dbname = 'BookStore';
$username = 'root';
$password = '';

try {
    // Create connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 设置字符集
    $pdo->exec("SET NAMES utf8mb4");
    
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>