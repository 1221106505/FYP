<?php
// check_username.php
require_once '../include/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'invalid';
    exit();
}

$username = $_POST['username'] ?? '';

if (empty($username) || strlen($username) < 3) {
    echo 'invalid';
    exit();
}

// 检查用户名格式
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo 'invalid';
    exit();
}

// 检查admin表
$stmt = $conn->prepare("SELECT username FROM admin WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo 'taken';
    $stmt->close();
    exit();
}
$stmt->close();

// 检查customer表
$stmt = $conn->prepare("SELECT username FROM customer WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo 'taken';
} else {
    echo 'available';
}

$stmt->close();
$conn->close();
?>