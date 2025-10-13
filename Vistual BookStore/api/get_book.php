<?php
session_start();
require_once __DIR__ . '/../include/database.php';

header('Content-Type: application/json');

// 检查管理员权限
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}
// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$price = $_GET['price'] ?? 'all';
$sort = $_GET['sort'] ?? 'title';
$limit = $_GET['limit'] ?? '';

try {
    // Build query
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

    if ($category !== 'all' && !empty($category)) {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }

    // Add price filtering
    if ($price !== 'all') {
        switch ($price) {
            case '0-20':
                $sql .= " AND b.price <= 20";
                break;
            case '20-50':
                $sql .= " AND b.price BETWEEN 20 AND 50";
                break;
            case '50-100':
                $sql .= " AND b.price BETWEEN 50 AND 100";
                break;
            case '100+':
                $sql .= " AND b.price > 100";
                break;
        }
    }

    // Add sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY b.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY b.price DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY b.publish_date DESC";
            break;
        default:
            $sql .= " ORDER BY b.title ASC";
    }

    // Add limit if specified
    if (!empty($limit) && is_numeric($limit)) {
        $sql .= " LIMIT ?";
        $params[] = (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    echo json_encode([
        'success' => true, 
        'books' => $books
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>