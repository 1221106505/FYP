<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed']);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$adminId = $_SESSION['admin_id'];

try {
    // 获取管理员基本信息
    $stmt = $conn->prepare("SELECT auto_id, username, role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin) {
        $response = [
            'success' => true,
            'admin_id' => $admin['auto_id'],
            'username' => $admin['username'],
            'role' => $admin['role'],
            'isSuperAdmin' => ($admin['role'] === 'superadmin')
        ];
        
        // 如果不是超级管理员，获取权限
        if ($admin['role'] !== 'superadmin') {
            $permStmt = $conn->prepare("
                SELECT pd.permission_key, pd.description, pd.category 
                FROM admin_permissions ap
                JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
                WHERE ap.admin_id = ? AND ap.permission_value = 1
            ");
            $permStmt->bind_param("i", $adminId);
            $permStmt->execute();
            $permResult = $permStmt->get_result();
            
            $permissions = [];
            while ($perm = $permResult->fetch_assoc()) {
                $permissions[] = $perm;
            }
            
            $response['permissions'] = $permissions;
            
            // 根据权限确定可访问的页面
            $accessibleSections = ['dashboard']; // 默认都有仪表板
            
            if (hasPermissionInArray($permissions, 'view_books')) {
                $accessibleSections[] = 'book-inventory';
            }
            if (hasPermissionInArray($permissions, 'manage_orders')) {
                $accessibleSections[] = 'orders';
            }
            if (hasPermissionInArray($permissions, 'manage_categories')) {
                $accessibleSections[] = 'categories';
            }
            if (hasPermissionInArray($permissions, 'view_analytics')) {
                $accessibleSections[] = 'analytics';
            }
            
            $response['accessible_sections'] = $accessibleSections;
        } else {
            // 超级管理员有所有权限
            $response['permissions'] = [];
            $response['accessible_sections'] = ['dashboard', 'book-inventory', 'orders', 'categories', 'analytics', 'admin-management'];
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function hasPermissionInArray($permissions, $permissionKey) {
    foreach ($permissions as $perm) {
        if ($perm['permission_key'] === $permissionKey) {
            return true;
        }
    }
    return false;
}
?>