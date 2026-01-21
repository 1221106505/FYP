<?php
// get_admin_detail.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$admin_id = $_GET['admin_id'] ?? null;

if (empty($admin_id)) {
    echo json_encode(['success' => false, 'error' => 'Admin ID is required']);
    exit;
}

try {
    // 获取管理员基本信息
    $stmt = $conn->prepare("
        SELECT 
            auto_id, 
            username, 
            email, 
            role, 
            created_at, 
            updated_at, 
            last_login
        FROM admin 
        WHERE auto_id = ?
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
        exit;
    }
    
    // 获取管理员权限
    $permissions = [];
    $stmt = $conn->prepare("
        SELECT 
            pd.permission_key,
            pd.description,
            pd.category
        FROM admin_permissions ap
        JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
        WHERE ap.admin_id = ? AND ap.permission_value = 1
        ORDER BY pd.category, pd.permission_key
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    $admin['permissions'] = $permissions;
    
    echo json_encode([
        'success' => true,
        'admin' => $admin
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>