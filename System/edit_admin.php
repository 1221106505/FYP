<?php
// edit_admin.php - 专门处理编辑管理员
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查session
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// 检查是否是superadmin
$currentAdminId = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();

if (!$currentAdmin || $currentAdmin['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$admin_id = $data['admin_id'] ?? null;
$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$role = $data['role'] ?? 'admin';
$permissions = $data['permissions'] ?? [];

error_log("Editing admin $admin_id: username=$username, role=$role, permissions=" . json_encode($permissions));

if (empty($admin_id)) {
    echo json_encode(['success' => false, 'error' => 'Admin ID is required for editing']);
    exit;
}

if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 检查要编辑的管理员是否存在
    $stmt = $conn->prepare("SELECT username, role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingAdmin = $result->fetch_assoc();
    
    if (!$existingAdmin) {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
        exit;
    }
    
    // 检查用户名是否已存在（排除当前编辑的管理员）
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin WHERE username = ? AND auto_id != ?");
    $stmt->bind_param("si", $username, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }
    
    // 检查邮箱是否已存在（排除当前编辑的管理员）- 允许空邮箱
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin WHERE email = ? AND email != '' AND auto_id != ?");
        $stmt->bind_param("si", $email, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            exit;
        }
    }
    
    // 更新管理员信息
    $stmt = $conn->prepare("
        UPDATE admin 
        SET username = ?, email = ?, role = ?, updated_at = NOW()
        WHERE auto_id = ?
    ");
    $stmt->bind_param("sssi", $username, $email, $role, $admin_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update admin: " . $stmt->error);
    }
    
    // 先删除所有现有权限
    $stmt = $conn->prepare("DELETE FROM admin_permissions WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    
    // 处理权限
    $permissionsCount = 0;
    if ($role === 'superadmin') {
        // 如果是superadmin，赋予所有权限
        $stmt = $conn->query("SELECT permission_id FROM permission_definitions");
        while ($row = $stmt->fetch_assoc()) {
            $insertStmt = $conn->prepare("
                INSERT INTO admin_permissions (admin_id, permission_id, permission_value, granted_by, granted_at) 
                VALUES (?, ?, 1, ?, NOW())
            ");
            $insertStmt->bind_param("iii", $admin_id, $row['permission_id'], $currentAdminId);
            $insertStmt->execute();
            $permissionsCount++;
        }
        $permissionsCount = 'All';
    } else if ($role === 'admin' && !empty($permissions)) {
        // 如果是普通admin，设置选中的权限
        foreach ($permissions as $permission) {
            // 处理权限数据格式
            if (is_array($permission) && isset($permission['permission_key'])) {
                $permissionKey = $permission['permission_key'];
            } else if (is_string($permission)) {
                $permissionKey = $permission;
            } else {
                continue;
            }
            
            // 获取权限ID
            $stmt = $conn->prepare("SELECT permission_id FROM permission_definitions WHERE permission_key = ?");
            $stmt->bind_param("s", $permissionKey);
            $stmt->execute();
            $result = $stmt->get_result();
            $permRow = $result->fetch_assoc();
            
            if ($permRow) {
                // 插入新权限
                $insertStmt = $conn->prepare("
                    INSERT INTO admin_permissions (admin_id, permission_id, permission_value, granted_by, granted_at) 
                    VALUES (?, ?, 1, ?, NOW())
                ");
                $insertStmt->bind_param("iii", $admin_id, $permRow['permission_id'], $currentAdminId);
                
                if (!$insertStmt->execute()) {
                    error_log("Failed to insert permission $permissionKey: " . $insertStmt->error);
                } else {
                    $permissionsCount++;
                }
            } else {
                error_log("Permission not found: $permissionKey");
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin updated successfully',
        'admin_id' => $admin_id,
        'role' => $role,
        'permissions_count' => $permissionsCount
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>