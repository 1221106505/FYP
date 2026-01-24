<?php
// check_login.php
session_start();
header('Content-Type: application/json');

// 初始化响应数组
$response = [
    'logged_in' => false,
    'username' => '',
    'user_type' => '',
    'user_id' => null,
    'role' => 'customer', // 默认值
    'is_superadmin' => false
];

if (isset($_SESSION['user_id'])) {
    $response['logged_in'] = true;
    $response['username'] = $_SESSION['username'];
    $response['user_id'] = $_SESSION['user_id'];
    
    // 获取用户类型
    if (isset($_SESSION['user_type'])) {
        $response['user_type'] = $_SESSION['user_type'];
    }
    
    // 获取角色 - 检查不同的会话变量名
    if (isset($_SESSION['role'])) {
        $response['role'] = $_SESSION['role'];
    } elseif (isset($_SESSION['user_role'])) {
        $response['role'] = $_SESSION['user_role'];
    }
    
    // 检查是否是超级管理员
    if (isset($_SESSION['is_superadmin'])) {
        $response['is_superadmin'] = (bool)$_SESSION['is_superadmin'];
    } elseif (isset($_SESSION['superadmin'])) {
        $response['is_superadmin'] = (bool)$_SESSION['superadmin'];
    } elseif ($response['role'] === 'superadmin') {
        $response['is_superadmin'] = true;
    }
    
    // 如果user_type是admin但role未设置，尝试推断
    if ($response['user_type'] === 'admin' && $response['role'] === 'customer') {
        // 检查是否有超级管理员标记
        if ($response['is_superadmin']) {
            $response['role'] = 'superadmin';
        } else {
            $response['role'] = 'admin';
        }
    }
}

echo json_encode($response);
?>