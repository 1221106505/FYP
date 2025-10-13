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
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category) && $category !== 'all') {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY b.stock_quantity ASC, b.title ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'books' => $books]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>