<?php
// /cart/cancel_preorder_item.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$pre_order_id = isset($data['pre_order_id']) ? intval($data['pre_order_id']) : 0;

if ($pre_order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid pre-order']);
    exit();
}

try {
    // 验证预购订单是否属于当前用户
    $stmt = $conn->prepare("
        SELECT * FROM pre_orders 
        WHERE pre_order_id = ? AND customer_id = ?
    ");
    $stmt->bind_param("ii", $pre_order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Pre-order not found']);
        exit();
    }
    
    // 取消预购订单
    $stmt = $conn->prepare("
        UPDATE pre_orders 
        SET status = 'cancelled'
        WHERE pre_order_id = ? AND customer_id = ?
    ");
    $stmt->bind_param("ii", $pre_order_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pre-order cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to cancel pre-order']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error in cancel_preorder_item.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>