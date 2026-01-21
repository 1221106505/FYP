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

// 检查是否是superadmin
$currentAdminId = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT role FROM admin WHERE auto_id = ?");
$stmt->execute([$currentAdminId]);
$currentAdmin = $stmt->fetch();

if (!$currentAdmin || $currentAdmin['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$role = $data['role'] ?? 'admin';
$permissions = $data['permissions'] ?? [];

if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 检查用户名是否已存在
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }
    
    // 检查邮箱是否已存在
    if ($email) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            exit;
        }
    }
    
    // 生成随机密码
    $password = bin2hex(random_bytes(8)); // 16字符的随机密码
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 插入新管理员
    $stmt = $pdo->prepare("
        INSERT INTO admin (username, password_hash, email, role, status, email_verified) 
        VALUES (?, ?, ?, ?, 'active', 0)
    ");
    $stmt->execute([$username, $password_hash, $email, $role]);
    
    $newAdminId = $pdo->lastInsertId();
    
    // 如果是普通admin，设置权限
    if ($role === 'admin' && !empty($permissions)) {
        // 获取所有权限定义
        $stmt = $pdo->query("SELECT permission_id, permission_key FROM permission_definitions");
        $allPermissions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($permissions as $permission) {
            $permissionKey = $permission['permission_key'];
            if (isset($allPermissions[$permissionKey])) {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_permissions (admin_id, permission_id, permission_value, granted_by) 
                    VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$newAdminId, $allPermissions[$permissionKey], $currentAdminId]);
            }
        }
    }
    
    // 如果是superadmin，自动赋予所有权限
    if ($role === 'superadmin') {
        $stmt = $pdo->query("SELECT permission_id FROM permission_definitions");
        $allPermissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($allPermissionIds as $permissionId) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_permissions (admin_id, permission_id, permission_value, granted_by) 
                VALUES (?, ?, 1, ?)
            ");
            $stmt->execute([$newAdminId, $permissionId, $currentAdminId]);
        }
    }
    
    $pdo->commit();
    
    // 返回生成的密码（用于发送email）
    echo json_encode([
        'success' => true,
        'message' => 'Admin created successfully',
        'admin_id' => $newAdminId,
        'generated_password' => $password
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>