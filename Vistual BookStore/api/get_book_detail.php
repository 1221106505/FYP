<?php
session_start();
require_once __DIR__ . '/../include/database.php';

header('Content-Type: application/json');

// 检查管理员权限
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'error' => 'No book ID provided']));
}

$bookId = $_GET['id'];

try {
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE b.book_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if ($book) {
        echo json_encode(['success' => true, 'book' => $book]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Book not found']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>