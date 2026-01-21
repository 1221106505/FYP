<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查session - 使用 admin_id
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
$adminId = $data['admin_id'] ?? 0;
$email = $data['email'] ?? '';

if (empty($adminId) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Admin ID and email are required']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 获取管理员信息
    $stmt = $conn->prepare("SELECT username, email FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
        exit;
    }
    
    // 生成新的随机密码
    $newPassword = bin2hex(random_bytes(8));
    $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // 更新密码
    $stmt = $conn->prepare("UPDATE admin SET password_hash = ? WHERE auto_id = ?");
    $stmt->bind_param("si", $password_hash, $adminId);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successful',
        'generated_password' => $newPassword,
        'email_sent_to' => $email
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>