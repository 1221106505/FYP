<?php
// /cart/process_preorder.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$cart_id = isset($data['cart_id']) ? intval($data['cart_id']) : 0;

if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid cart item']);
    exit();
}

try {
    // 检查是否是预购项目
    $stmt = $conn->prepare("
        SELECT c.*, b.title, b.price, b.stock_quantity 
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        WHERE c.cart_id = ? AND c.customer_id = ? AND c.is_pre_order = 1
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Pre-order item not found']);
        exit();
    }
    
    $cart_item = $result->fetch_assoc();
    
    // 检查书籍是否已经到货
    $check_stmt = $conn->prepare("SELECT stock_quantity FROM books WHERE book_id = ?");
    $check_stmt->bind_param("i", $cart_item['book_id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $book = $check_result->fetch_assoc();
    
    if ($book['stock_quantity'] > 0) {
        // 标记为可用
        $stmt = $conn->prepare("UPDATE cart SET pre_order_status = 'available' WHERE cart_id = ?");
        $stmt->bind_param("i", $cart_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Pre-order is now available for checkout']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to process pre-order']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Item is still out of stock']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error in process_preorder.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>