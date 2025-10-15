<?php
require_once '../include/database.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<h1>Register Page</h1>";
    echo '<a href="Login.html">Go to Login Page</a>';
    exit();
}

$user_type = (strpos($username, 'admin_') === 0) ? 'admin' : 'customer';
$table = ($user_type === 'admin') ? 'admin' : 'customer';

// 检查用户名是否存在
$stmt = $conn->prepare("SELECT * FROM $table WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: Login.html?register_error=Username already exists");
    exit();
}

// 注册用户
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO $table (username, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password_hash);

if ($stmt->execute()) {
    header("Location: Login.html?register_success=Registration successful. Please login.");
    exit();
} else {
    header("Location: Login.html?register_error=Registration failed");
    exit();
}
?>