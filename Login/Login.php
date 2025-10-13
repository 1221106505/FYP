<?php
session_start();
require_once __DIR__ . '/../include/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<h1>Login Page</h1>";
    echo "<p>This page should be accessed via form submission.</p>";
    echo '<a href="Login.html">Go to Login Page</a>';
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 检查admin表
$admin_stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
$admin_stmt->bindParam(':username', $username);
$admin_stmt->execute();

if ($admin_stmt->rowCount() > 0) {
    $user = $admin_stmt->fetch();
    if (password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'admin';
        // 重定向到 Vistual BookStore/Main.html
        header("Location: ../Vistual BookStore/Main.html?login_success=1&username=" . urlencode($user['username']));
        exit();
    }
}

// 检查customer表
$customer_stmt = $pdo->prepare("SELECT * FROM customer WHERE username = :username");
$customer_stmt->bindParam(':username', $username);
$customer_stmt->execute();

if ($customer_stmt->rowCount() > 0) {
    $user = $customer_stmt->fetch();
    if (password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'customer';
        // 重定向到 Vistual BookStore/Main.html
        header("Location: ../Vistual BookStore/Main.html?login_success=1&username=" . urlencode($user['username']));
        exit();
    }
}

header("Location: Login.html?login_error=Invalid credentials");
exit();
?>