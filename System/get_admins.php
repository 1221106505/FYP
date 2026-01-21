<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 检查是否是superadmin
$adminId = $_SESSION['admin_id'] ?? 0;
if (!$adminId) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// 检查当前管理员角色
$stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();

if (!$currentAdmin || $currentAdmin['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin only.']);
    exit;
}

try {
    $query = "SELECT a.auto_id, a.username, a.email, a.role, a.status, 
                     a.created_at, a.updated_at, a.last_login, a.email_verified
              FROM admin a
              ORDER BY CASE WHEN a.role = 'superadmin' THEN 1 ELSE 2 END, a.username";
    
    $result = $conn->query($query);
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    
    echo json_encode(['success' => true, 'admins' => $admins]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>