<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查session（可选，根据需求）
// if (!isset($_SESSION['admin_id'])) {
//     echo json_encode(['success' => false, 'error' => 'Not logged in']);
//     exit;
// }

try {
    $stmt = $pdo->query("
        SELECT permission_id, permission_key, description, category 
        FROM permission_definitions 
        ORDER BY category, permission_key
    ");
    $permissions = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'permissions' => $permissions]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>