<?php
// Include database configuration
require_once __DIR__ . '/../include/database.php';

// Get form data
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 如果通过GET访问，显示表单
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<h1>Register Page</h1>";
    echo "<p>This page should be accessed via form submission.</p>";
    echo '<a href="Login.html">Go to Login Page</a>';
    exit();
}

// Auto-detect user type based on username pattern
$user_type = 'customer'; // Default to customer

if (strpos($username, 'admin_') === 0) {
    $user_type = 'admin';
}

// Determine which table to insert into based on user type
$table = ($user_type === 'admin') ? 'admin' : 'customer';

// Check if username already exists in either table
$check_admin_stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
$check_admin_stmt->bindParam(':username', $username);
$check_admin_stmt->execute();

$check_customer_stmt = $pdo->prepare("SELECT * FROM customer WHERE username = :username");
$check_customer_stmt->bindParam(':username', $username);
$check_customer_stmt->execute();

if ($check_admin_stmt->rowCount() > 0 || $check_customer_stmt->rowCount() > 0) {
    // Username already exists - 重定向回Login页面并显示错误
    header("Location: Login.html?register_error=Username already exists");
    exit();
}

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$insert_stmt = $pdo->prepare("INSERT INTO $table (username, password_hash) VALUES (:username, :password_hash)");
$insert_stmt->bindParam(':username', $username);
$insert_stmt->bindParam(':password_hash', $password_hash);

if ($insert_stmt->execute()) {
    // Registration successful - 直接重定向回Login页面并显示成功消息
    header("Location: Login.html?register_success=Registration successful. Please login.");
    exit();
} else {
    // Registration failed
    header("Location: Login.html?register_error=Registration failed");
    exit();
}
?>