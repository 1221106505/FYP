<?php
session_start();
require_once '../include/database.php'; // 确保路径正确

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("
        SELECT 
            c.cart_id,
            c.customer_id,
            c.book_id,
            c.quantity,
            c.added_date,
            c.is_pre_order,
            c.pre_order_status,
            c.expected_date,
            b.title,
            b.author,
            b.price,
            b.stock_quantity,
            b.pre_order_available,
            cat.category_name
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        LEFT JOIN categories cat ON b.category_id = cat.category_id
        WHERE c.customer_id = ?
        ORDER BY c.added_date DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart_items = [];
    $total_items = 0;
    $total_price = 0;
    
    while ($row = $result->fetch_assoc()) {
        $subtotal = $row['price'] * $row['quantity'];
        $cart_items[] = $row;
        $total_items += $row['quantity'];
        $total_price += $subtotal;
    }
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items,
        'total_items' => $total_items,
        'total_price' => $total_price
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>