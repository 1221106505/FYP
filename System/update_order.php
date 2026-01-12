<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 获取输入数据
$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
$status = isset($input['status']) ? $conn->real_escape_string($input['status']) : '';

if ($order_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid order ID: ' . $order_id
    ]);
    exit();
}

if (empty($status)) {
    echo json_encode([
        'success' => false,
        'error' => 'Status is required'
    ]);
    exit();
}

try {
    // 检查订单是否存在
    $check_sql = "SELECT order_id FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Order not found: ' . $order_id
        ]);
        exit();
    }
    $check_stmt->close();
    
    // 更新订单状态
    // 先检查是否有 updated_at 字段
    $sql = "UPDATE orders SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Order updated successfully',
            'order_id' => $order_id,
            'new_status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Update failed: ' . $conn->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>