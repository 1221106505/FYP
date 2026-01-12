<?php
session_start();
require_once '../include/database.php';

// 添加调试信息
error_log("Login attempt started");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<h1>Login Page</h1>";
    echo "<p>This page should be accessed via form submission.</p>";
    echo '<a href="Login.html">Go to Login Page</a>';
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

error_log("Login attempt for username: " . $username);

// 检查数据库连接
if (!$conn) {
    error_log("Database connection failed");
    header("Location: Login.html?login_error=Database connection failed");
    exit();
}

// 检查admin表
$stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
if (!$stmt) {
    error_log("Admin prepare failed: " . $conn->error);
    header("Location: Login.html?login_error=Database error");
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    error_log("Admin user found: " . $user['username']);
    
    // 调试密码验证
    error_log("Input password: " . $password);
    error_log("Stored hash: " . $user['password_hash']);
    error_log("Password verify result: " . (password_verify($password, $user['password_hash']) ? 'true' : 'false'));
    error_log("Direct compare result: " . ($password === $user['password_hash'] ? 'true' : 'false'));
    
    if (password_verify($password, $user['password_hash']) || $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['logged_in'] = true;
        
        error_log("Admin login successful, redirecting...");
        
        // 确保重定向URL正确
        $redirect_url = "../System/Main.html?login_success=1&username=" . urlencode($user['username']) . "&role=admin";
        header("Location: " . $redirect_url);
        exit();
    } else {
        error_log("Admin password verification failed");
    }
}

$stmt->close();

// 检查customer表
$stmt = $conn->prepare("SELECT * FROM customer WHERE username = ?");
if (!$stmt) {
    error_log("Customer prepare failed: " . $conn->error);
    header("Location: Login.html?login_error=Database error");
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    error_log("Customer user found: " . $user['username']);
    
    // 调试密码验证
    error_log("Input password: " . $password);
    error_log("Stored hash: " . $user['password_hash']);
    error_log("Password verify result: " . (password_verify($password, $user['password_hash']) ? 'true' : 'false'));
    error_log("Direct compare result: " . ($password === $user['password_hash'] ? 'true' : 'false'));
    
    if (password_verify($password, $user['password_hash']) || $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'customer';
        $_SESSION['logged_in'] = true;
        
        error_log("Customer login successful, redirecting...");
        
        $redirect_url = "../System/Main.html?login_success=1&username=" . urlencode($user['username']) . "&role=customer";
        header("Location: " . $redirect_url);
        exit();
    } else {
        error_log("Customer password verification failed");
    }
}

$stmt->close();
$conn->close();

error_log("Login failed for username: " . $username);
header("Location: Login.html?login_error=Invalid username or password");
exit();
?>