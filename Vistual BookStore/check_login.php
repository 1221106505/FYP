<?php
// 确保session在文件开头启动
session_start();

// 设置正确的header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// 调试信息（完成后可以删除）
error_log("check_login.php accessed - Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

$response = [
    'logged_in' => false,
    'username' => '',
    'user_type' => ''
];

if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $response['logged_in'] = true;
    $response['username'] = $_SESSION['username'];
    $response['user_type'] = $_SESSION['user_type'] ?? 'unknown';
    
    error_log("User is logged in: " . $_SESSION['username']);
} else {
    error_log("No user session found");
}

echo json_encode($response);
?>