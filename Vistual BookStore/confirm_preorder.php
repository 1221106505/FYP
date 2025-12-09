<?php
// /cart/confirm_preorder.php
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
    echo json_encode(['success' => false, 'error' => 'Invalid pre-order item']);
    exit();
}

try {
    // 验证预购项目是否属于当前用户且状态为available
    $stmt = $conn->prepare("
        SELECT * FROM cart 
        WHERE cart_id = ? AND customer_id = ? 
        AND is_pre_order = 1 AND pre_order_status = 'available'
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Available pre-order item not found']);
        exit();
    }
    
    // 创建订单从预购项目
    $cart_item = $result->fetch_assoc();
    
    // 获取书籍价格信息
    $book_stmt = $conn->prepare("SELECT price FROM books WHERE book_id = ?");
    $book_stmt->bind_param("i", $cart_item['book_id']);
    $book_stmt->execute();
    $book_result = $book_stmt->get_result();
    $book = $book_result->fetch_assoc();
    $book_stmt->close();
    
    // 创建订单
    $total_amount = $cart_item['quantity'] * $book['price'];
    
    $order_stmt = $conn->prepare("
        INSERT INTO orders (
            customer_id, total_amount, status, 
            shipping_address, billing_address,
            contact_phone, contact_email
        ) VALUES (
            ?, ?, 'confirmed',
            (SELECT address FROM customer WHERE auto_id = ?),
            (SELECT address FROM customer WHERE auto_id = ?),
            (SELECT phone FROM customer WHERE auto_id = ?),
            (SELECT email FROM customer WHERE auto_id = ?)
        )
    ");
    $order_stmt->bind_param("idiiiii", $user_id, $total_amount, $user_id, $user_id, $user_id, $user_id);
    
    if ($order_stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // 添加订单项目
        $order_item_stmt = $conn->prepare("
            INSERT INTO order_items (order_id, book_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $subtotal = $cart_item['quantity'] * $book['price'];
        $order_item_stmt->bind_param("iiiid", $order_id, $cart_item['book_id'], $cart_item['quantity'], $book['price'], $subtotal);
        $order_item_stmt->execute();
        $order_item_stmt->close();
        
        // 更新库存
        $update_stmt = $conn->prepare("
            UPDATE books 
            SET stock_quantity = stock_quantity - ?,
                total_sales = total_sales + ?
            WHERE book_id = ?
        ");
        $update_stmt->bind_param("iii", $cart_item['quantity'], $cart_item['quantity'], $cart_item['book_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // 从购物车移除预购项目
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ?");
        $delete_stmt->bind_param("i", $cart_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pre-order confirmed and order created successfully',
            'order_id' => $order_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create order']);
    }
    
    $order_stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>