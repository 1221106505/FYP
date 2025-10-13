<?php
session_start();
require_once __DIR__ . '/../include/database.php';

header('Content-Type: application/json');

// 检查管理员权限
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

$book_id = $input['id'] ?? '';
$title = $input['title'] ?? '';
$author = $input['author'] ?? '';
$category_id = $input['category_id'] ?? '';
$price = $input['price'] ?? '';
$stock_quantity = $input['stock_quantity'] ?? '';
$isbn = $input['isbn'] ?? '';
$description = $input['description'] ?? '';
$publisher = $input['publisher'] ?? '';
$publish_date = $input['publish_date'] ?? '';

try {
    if (empty($book_id)) {
        // 新增书籍
        $sql = "INSERT INTO books (title, author, category_id, price, stock_quantity, isbn, description, publisher, publish_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $author, $category_id, $price, $stock_quantity, $isbn, $description, $publisher, $publish_date]);
    } else {
        // 更新书籍
        $sql = "UPDATE books SET title=?, author=?, category_id=?, price=?, stock_quantity=?, isbn=?, description=?, publisher=?, publish_date=?
                WHERE book_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $author, $category_id, $price, $stock_quantity, $isbn, $description, $publisher, $publish_date, $book_id]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>