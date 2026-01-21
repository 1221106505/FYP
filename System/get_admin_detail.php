<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查session
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$adminId = $_GET['admin_id'] ?? 0;

// 检查是否是superadmin
$currentAdminId = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT role FROM admin WHERE auto_id = ?");
$stmt->execute([$currentAdminId]);
$currentAdmin = $stmt->fetch();

if (!$currentAdmin || $currentAdmin['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    exit;
}

try {
    // 获取管理员基本信息
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE auto_id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
        exit;
    }
    
    // 获取管理员权限
    $permissions = [];
    if ($admin['role'] === 'admin') {
        $stmt = $pdo->prepare("
            SELECT pd.permission_key, pd.description, pd.category, ap.permission_value
            FROM admin_permissions ap
            JOIN permission_definitions pd ON ap.permission_id = pd.permission_id
            WHERE ap.admin_id = ?
        ");
        $stmt->execute([$adminId]);
        $permissions = $stmt->fetchAll();
    }
    
    $admin['permissions'] = $permissions;
    
    echo json_encode(['success' => true, 'admin' => $admin]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>