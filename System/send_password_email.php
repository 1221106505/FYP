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
$adminId = $data['admin_id'] ?? 0;
$email = $data['email'] ?? '';

if (empty($adminId) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Admin ID and email are required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 获取管理员信息
    $stmt = $pdo->prepare("SELECT username, email FROM admin WHERE auto_id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
        exit;
    }
    
    // 生成新的随机密码
    $newPassword = bin2hex(random_bytes(8));
    $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // 更新密码
    $stmt = $pdo->prepare("UPDATE admin SET password_hash = ? WHERE auto_id = ?");
    $stmt->execute([$password_hash, $adminId]);
    
    $pdo->commit();
    
    // 这里应该实现真正的邮件发送
    // 暂时只返回成功信息
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset email sent (simulated)',
        'generated_password' => $newPassword // 在实际应用中不应该返回这个
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>