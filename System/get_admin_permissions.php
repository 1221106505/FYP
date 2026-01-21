<?php
// get_admin_permissions.php - 获取当前管理员权限
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$adminId = $_SESSION['admin_id'];

try {
    // 检查是否是超级管理员
    $stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin && $admin['role'] === 'superadmin') {
        echo json_encode([
            'success' => true,
            'permissions' => [], // 超级管理员权限为空，代表所有权限
            'accessible_sections' => ['dashboard', 'book-inventory', 'orders', 'categories', 'analytics', 'admin-management']
        ]);
        exit;
    }
    
    // 获取普通管理员权限
    $stmt = $conn->prepare("
        SELECT pd.permission_key, pd.description, pd.category 
        FROM admin_permissions ap
        JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
        WHERE ap.admin_id = ? AND ap.permission_value = 1
    ");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    // 根据权限确定可访问页面
    $accessibleSections = ['dashboard']; // 默认都有仪表板
    
    $sectionMapping = [
        'view_books' => 'book-inventory',
        'manage_books' => 'book-inventory',
        'manage_orders' => 'orders',
        'manage_categories' => 'categories',
        'view_analytics' => 'analytics'
    ];
    
    foreach ($permissions as $perm) {
        if (isset($sectionMapping[$perm['permission_key']]) && 
            !in_array($sectionMapping[$perm['permission_key']], $accessibleSections)) {
            $accessibleSections[] = $sectionMapping[$perm['permission_key']];
        }
    }
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'accessible_sections' => $accessibleSections,
        'count' => count($permissions)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>