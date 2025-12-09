<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode([
        'success' => false,
        'error' => 'Please login to view pre-orders'
    ]);
    exit();
}

$customer_id = $_SESSION['user_id'];

// 如果使用独立的 pre_orders 表
$sql = "SELECT po.*, b.title, b.author, b.price, b.pre_order_price
        FROM pre_orders po
        JOIN books b ON po.book_id = b.book_id
        WHERE po.customer_id = ?
        ORDER BY po.order_date DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$pre_orders = [];
while ($row = $result->fetch_assoc()) {
    $pre_orders[] = $row;
}

echo json_encode([
    'success' => true,
    'pre_orders' => $pre_orders,
    'count' => count($pre_orders)
]);

$stmt->close();
$conn->close();
?>