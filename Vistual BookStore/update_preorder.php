<?php
// /cart/update_preorder.php
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
$action = isset($data['action']) ? $data['action'] : '';
$notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

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
    
    $cart_item = $result->fetch_assoc();
    
    if ($action === 'confirm') {
        // 确认预购
        $stmt = $conn->prepare("
            UPDATE cart 
            SET pre_order_status = 'confirmed',
                expected_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            WHERE cart_id = ? AND customer_id = ?
        ");
        $stmt->bind_param("ii", $cart_id, $user_id);
        
    } elseif ($action === 'update_status') {
        $status = isset($data['status']) ? $data['status'] : '';
        $valid_statuses = ['pending', 'confirmed', 'available', 'cancelled'];
        
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit();
        }
        
        $stmt = $conn->prepare("
            UPDATE cart 
            SET pre_order_status = ?
            WHERE cart_id = ? AND customer_id = ?
        ");
        $stmt->bind_param("sii", $status, $cart_id, $user_id);
        
    } elseif ($action === 'update_quantity') {
        $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
        
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        $stmt = $conn->prepare("
            UPDATE cart 
            SET quantity = ? 
            WHERE cart_id = ? AND customer_id = ?
        ");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit();
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pre-order updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update pre-order']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error in update_preorder.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>