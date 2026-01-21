<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $stmt = $conn->query("
        SELECT permission_id, permission_key, description, category 
        FROM permission_definitions 
        ORDER BY category, permission_key
    ");
    $permissions = [];
    while ($row = $stmt->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'permissions' => $permissions,
        'count' => count($permissions)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>