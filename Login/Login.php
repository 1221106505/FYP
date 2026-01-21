<?php
// Login.php - 完整版（修复session变量兼容性）
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

// 检查admin表（包含所有管理员，用role区分）
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
    
    // 获取角色，如果没有设置则默认为普通管理员
    $role = isset($user['role']) ? $user['role'] : 'admin';
    
    // 调试信息
    error_log("User role from database: " . $role);
    error_log("User identity from database: " . $user['identity']);
    error_log("Input password: " . $password);
    error_log("Stored hash: " . $user['password_hash']);
    error_log("Password verify result: " . (password_verify($password, $user['password_hash']) ? 'true' : 'false'));
    error_log("Direct compare result: " . ($password === $user['password_hash'] ? 'true' : 'false'));
    
    // 密码验证
    if (password_verify($password, $user['password_hash']) || $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['auto_id'];
        $_SESSION['admin_id'] = $user['auto_id'];  // 兼容其他文件需要的admin_id
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'admin';  // 身份类型统一为admin
        $_SESSION['user_role'] = $role;    // 权限级别：superadmin 或 admin
        $_SESSION['identity'] = 'admin';   // 与数据库中的identity字段一致
        $_SESSION['logged_in'] = true;
        
        error_log("Admin login successful, user_id={$user['auto_id']}, admin_id={$user['auto_id']}, user_type=admin, user_role={$role}, identity=admin, redirecting...");
        
        // 检查是否有重定向URL
        if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
            // 如果有重定向参数，跳转到指定页面
            $redirect_url = urldecode($_GET['redirect']) . "?login_success=1&username=" . urlencode($user['username']) . "&identity=admin&role=" . urlencode($role);
            error_log("Redirecting to: " . $redirect_url);
        } elseif (isset($_SESSION['return_url']) && !empty($_SESSION['return_url'])) {
            // 如果有session中的返回URL
            $redirect_url = $_SESSION['return_url'] . "?login_success=1&username=" . urlencode($user['username']) . "&identity=admin&role=" . urlencode($role);
            unset($_SESSION['return_url']);
            error_log("Redirecting to session URL: " . $redirect_url);
        } else {
            // 否则跳转到Main页面
            $redirect_url = "../System/Main.html?login_success=1&username=" . urlencode($user['username']) . "&identity=admin&role=" . urlencode($role);
            error_log("Redirecting to default Main page: " . $redirect_url);
        }
        
        $stmt->close();
        $conn->close();
        
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
        // 注意：客户不需要设置 admin_id
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = 'customer';  // 身份类型为customer
        $_SESSION['user_role'] = 'customer';  // 权限级别为customer
        $_SESSION['identity'] = 'customer';   // 与数据库中的identity字段一致
        $_SESSION['logged_in'] = true;
        
        error_log("Customer login successful, user_id={$user['auto_id']}, user_type=customer, user_role=customer, identity=customer, redirecting...");
        
        // 检查是否有重定向URL
        if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
            // 如果有重定向参数，跳转到指定页面
            $redirect_url = urldecode($_GET['redirect']) . "?login_success=1&username=" . urlencode($user['username']) . "&identity=customer&role=customer";
            error_log("Redirecting to: " . $redirect_url);
        } elseif (isset($_SESSION['return_url']) && !empty($_SESSION['return_url'])) {
            // 如果有session中的返回URL
            $redirect_url = $_SESSION['return_url'] . "?login_success=1&username=" . urlencode($user['username']) . "&identity=customer&role=customer";
            unset($_SESSION['return_url']);
            error_log("Redirecting to session URL: " . $redirect_url);
        } else {
            // 否则跳转到Main页面
            $redirect_url = "../System/Main.html?login_success=1&username=" . urlencode($user['username']) . "&identity=customer&role=customer";
            error_log("Redirecting to default Main page: " . $redirect_url);
        }
        
        $stmt->close();
        $conn->close();
        
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