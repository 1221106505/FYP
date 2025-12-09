<?php
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
$action = isset($data['action']) ? $data['action'] : 'update';
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 0;

try {
    // 验证购物车项目是否属于当前用户
    $stmt = $conn->prepare("SELECT * FROM cart WHERE cart_id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Cart item not found']);
        exit();
    }
    
    $cart_item = $result->fetch_assoc();
    $book_id = $cart_item['book_id'];
    
    if ($action === 'remove') {
        // 删除购物车项目
        $stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove item']);
        }
    } elseif ($action === 'update') {
        // 检查库存
        $stmt = $conn->prepare("SELECT stock_quantity FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book_result = $stmt->get_result();
        $book = $book_result->fetch_assoc();
        
        if ($quantity > $book['stock_quantity'] && $book['stock_quantity'] > 0) {
            echo json_encode(['success' => false, 'error' => 'Insufficient stock']);
            exit();
        }
        
        // 更新数量
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Quantity updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update quantity']);
        }
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>