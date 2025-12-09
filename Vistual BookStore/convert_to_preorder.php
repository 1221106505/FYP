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
$action = isset($data['action']) ? $data['action'] : 'convert';

if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid cart item']);
    exit();
}

try {
    // 检查购物车项目是否属于当前用户
    $stmt = $conn->prepare("
        SELECT c.*, b.title, b.price, b.stock_quantity 
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        WHERE c.cart_id = ? AND c.customer_id = ?
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Cart item not found']);
        exit();
    }
    
    $cart_item = $result->fetch_assoc();
    
    if ($action === 'cancel') {
        // 取消预购 - 恢复为普通购物车项目
        $stmt = $conn->prepare("UPDATE cart SET is_pre_order = 0, pre_order_status = 'pending' WHERE cart_id = ?");
        $stmt->bind_param("i", $cart_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Pre-order cancelled']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to cancel pre-order']);
        }
    } else {
        // 转换为预购
        // 检查库存是否不足
        if ($cart_item['stock_quantity'] <= 0) {
            // 更新购物车项目为预购
            $stmt = $conn->prepare("UPDATE cart SET is_pre_order = 1, pre_order_status = 'pending', expected_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE cart_id = ?");
            $stmt->bind_param("i", $cart_id);
            
            if ($stmt->execute()) {
                // 创建预购记录到 pre_orders 表
                $stmt2 = $conn->prepare("
                    INSERT INTO pre_orders (customer_id, book_id, quantity, total_amount, expected_delivery_date) 
                    VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                ");
                $price = $cart_item['quantity'] * $cart_item['price'];
                $stmt2->bind_param("iiid", $user_id, $cart_item['book_id'], $cart_item['quantity'], $price);
                $stmt2->execute();
                $stmt2->close();
                
                echo json_encode(['success' => true, 'message' => 'Converted to pre-order']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to convert to pre-order']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Item is still in stock']);
        }
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>