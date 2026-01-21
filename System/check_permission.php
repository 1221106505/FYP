<?php
// check_permission.php - 检查管理员权限
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

function checkPermission($permissionKey) {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    // 如果是超级管理员，拥有所有权限
    $stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin && $admin['role'] === 'superadmin') {
        return true; // 超级管理员返回 true，拥有所有权限
    }
    
    // 检查具体权限
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM admin_permissions ap
        JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
        WHERE ap.admin_id = ? 
          AND pd.permission_key = ?
          AND ap.permission_value = 1
    ");
    $stmt->bind_param("is", $admin_id, $permissionKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row && $row['count'] > 0);
}

function getAdminPermissions() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        return [];
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    // 获取所有权限
    $stmt = $conn->prepare("
        SELECT pd.permission_key, pd.description, pd.category
        FROM admin_permissions ap
        JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
        WHERE ap.admin_id = ? AND ap.permission_value = 1
        ORDER BY pd.category
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    return $permissions;
}

function isSuperAdmin() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    $stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    return ($admin && $admin['role'] === 'superadmin');
}

function getAdminRole() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    
    $admin_id = $_SESSION['admin_id'];
    
    $stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    return $admin ? $admin['role'] : null;
}
?>