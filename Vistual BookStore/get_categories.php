<?php
session_start();
require_once __DIR__ . '/../include/database.php';

header('Content-Type: application/json');

// 检查管理员权限
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

try {
    $sql = "SELECT * FROM categories ORDER BY name";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();

    echo json_encode(['success' => true, 'categories' => $categories]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>