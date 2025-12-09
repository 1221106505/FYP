<?php
// admin_cancel_preorder.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bookstore';

// 创建连接
$conn = new mysqli($host, $username, $password, $database);

// 检查连接
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);

$pre_order_id = isset($data['pre_order_id']) ? intval($data['pre_order_id']) : 0;
$reason = isset($data['reason']) ? $conn->real_escape_string($data['reason']) : '';

if ($pre_order_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid pre-order ID'
    ]);
    exit();
}

try {
    // 检查预购订单是否存在
    $check_sql = "SELECT status FROM pre_orders WHERE pre_order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $pre_order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Pre-order not found'
        ]);
        exit();
    }
    
    $current_status = $check_result->fetch_assoc()['status'];
    $check_stmt->close();
    
    // 如果已经是取消状态，直接返回
    if ($current_status === 'cancelled') {
        echo json_encode([
            'success' => true,
            'message' => 'Pre-order is already cancelled'
        ]);
        exit();
    }
    
    // 更新为取消状态
    $update_sql = "UPDATE pre_orders 
                   SET status = 'cancelled', 
                       notes = CONCAT(IFNULL(notes, ''), '\n\nCancelled by admin. Reason: ', ?),
                       updated_at = NOW()
                   WHERE pre_order_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $reason, $pre_order_id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Pre-order cancelled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to cancel pre-order: ' . $conn->error
        ]);
    }
    
    $update_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>