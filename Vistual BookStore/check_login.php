<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查用户是否登录
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode([
        'logged_in' => true,
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['user_type'] ?? 'customer'
    ]);
} else {
    echo json_encode([
        'logged_in' => false,
        'username' => '',
        'role' => ''
    ]);
}
?>