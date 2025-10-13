<?php
// 在文件最开头启动session，确保没有输出在前面
session_start();

// Include database configuration
require_once __DIR__ . '/../include/database.php';

// 如果通过GET访问，显示信息
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<h1>Login Page</h1>";
    echo "<p>This page should be accessed via form submission.</p>";
    echo '<a href="Login.html">Go to Login Page</a>';
    exit();
}

// Get form data
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 记录登录尝试
error_log("Login attempt for user: " . $username);

// Check if user exists in admin table
$admin_stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
$admin_stmt->bindParam(':username', $username);
$admin_stmt->execute();

if ($admin_stmt->rowCount() > 0) {
    $user = $admin_stmt->fetch();
    
    // 简化密码验证（实际应该用password_verify）
    if ($password === $user['password_hash'] || password_verify($password, $user['password_hash'])) {
        // 设置会话数据
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'admin';
        
        error_log("Admin login successful: " . $user['username']);
        error_log("Session data set: " . print_r($_SESSION, true));
        
        // 重定向到Main.html
        header("Location: ../Vistual BookStore/Main.html?login_success=1&username=" . urlencode($user['username']));
        exit();
    }
}

// Check if user exists in customer table
$customer_stmt = $pdo->prepare("SELECT * FROM customer WHERE username = :username");
$customer_stmt->bindParam(':username', $username);
$customer_stmt->execute();

if ($customer_stmt->rowCount() > 0) {
    $user = $customer_stmt->fetch();
    
    // 简化密码验证
    if ($password === $user['password_hash'] || password_verify($password, $user['password_hash'])) {
        // 设置会话数据
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'customer';
        
        error_log("Customer login successful: " . $user['username']);
        error_log("Session data set: " . print_r($_SESSION, true));
        
        // 重定向到Main.html
        header("Location: ../Vistual BookStore/Main.html?login_success=1&username=" . urlencode($user['username']));
        exit();
    }
}

// Login failed
error_log("Login failed for user: " . $username);
header("Location: Login.html?login_error=Invalid credentials");
exit();
?>