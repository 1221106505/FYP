<?php
// get_admins.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查session
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once '../include/database.php';

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// 获取当前管理员角色
$currentAdminId = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();

if (!$currentAdmin) {
    echo json_encode(['success' => false, 'error' => 'Admin not found']);
    exit;
}

// 检查是否是superadmin
if ($currentAdmin['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    exit;
}

try {
    // 获取所有管理员
    $query = "SELECT 
                auto_id,
                username,
                email,
                role,
                created_at,
                updated_at,
                email_verified,
                last_login
              FROM admin
              ORDER BY 
                CASE WHEN role = 'superadmin' THEN 1 ELSE 2 END,
                username";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        // 确定状态
        $status = 'active';
        $lastLoginTime = strtotime($row['last_login']);
        $thirtyDaysAgo = strtotime('-30 days');
        
        if (!$row['email_verified']) {
            $status = 'pending';
        } else if (!$row['last_login'] || $lastLoginTime < $thirtyDaysAgo) {
            $status = 'inactive';
        }
        
        // 获取权限数量
        $permissionCount = 0;
        if ($row['role'] === 'admin') {
            $permStmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM admin_permissions ap
                JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
                WHERE ap.admin_id = ? AND ap.permission_value = 1
            ");
            $permStmt->bind_param("i", $row['auto_id']);
            $permStmt->execute();
            $permResult = $permStmt->get_result();
            $permData = $permResult->fetch_assoc();
            $permissionCount = $permData['count'] ?? 0;
        } else {
            $permissionCount = 'All';
        }
        
        $admins[] = [
            'auto_id' => $row['auto_id'],
            'username' => $row['username'],
            'email' => $row['email'] ?: 'Not set',
            'role' => $row['role'],
            'created_at' => date('Y-m-d H:i:s', strtotime($row['created_at'])),
            'last_login' => $row['last_login'] ? date('Y-m-d H:i:s', strtotime($row['last_login'])) : 'Never',
            'permission_count' => $permissionCount
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'admins' => $admins,
        'count' => count($admins)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>