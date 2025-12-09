<?php
// /cart/check_book_preorder.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$book_id = isset($data['book_id']) ? intval($data['book_id']) : 0;

if ($book_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid book']);
    exit();
}

try {
    // 检查书籍是否可预购
    $stmt = $conn->prepare("
        SELECT 
            book_id,
            title,
            author,
            price,
            pre_order_price,
            pre_order_available,
            stock_quantity,
            pre_order_deadline
        FROM books 
        WHERE book_id = ?
    ");
    
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Book not found']);
        exit();
    }
    
    $book = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'book' => $book,
        'can_preorder' => $book['pre_order_available'] == 1,
        'message' => $book['pre_order_available'] == 1 ? 'Book is available for pre-order' : 'Book is not available for pre-order'
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error in check_book_preorder.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>