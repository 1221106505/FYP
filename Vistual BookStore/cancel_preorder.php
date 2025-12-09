<?php
// /cart/cancel_preorder.php
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
$reason = isset($data['reason']) ? $conn->real_escape_string($data['reason']) : '';

if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid pre-order item']);
    exit();
}

try {
    // 验证预购项目是否属于当前用户
    $stmt = $conn->prepare("
        SELECT * FROM cart 
        WHERE cart_id = ? AND customer_id = ? AND is_pre_order = 1
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Pre-order item not found']);
        exit();
    }
    
    // 取消预购
    $stmt = $conn->prepare("
        UPDATE cart 
        SET pre_order_status = 'cancelled'
        WHERE cart_id = ? AND customer_id = ?
    ");
    $stmt->bind_param("ii", $cart_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pre-order cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to cancel pre-order']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error in cancel_preorder.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>