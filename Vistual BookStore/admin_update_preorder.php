<?php
// admin_update_preorder.php
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
$status = isset($data['status']) ? $data['status'] : '';
$notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

if ($pre_order_id <= 0 || empty($status)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid parameters'
    ]);
    exit();
}

// 验证状态
$valid_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid status'
    ]);
    exit();
}

try {
    // 检查预购订单是否存在
    $check_sql = "SELECT pre_order_id FROM pre_orders WHERE pre_order_id = ?";
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
    $check_stmt->close();
    
    // 更新预购订单
    $update_sql = "UPDATE pre_orders 
                   SET status = ?, 
                       notes = IFNULL(?, notes),
                       updated_at = NOW()
                   WHERE pre_order_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    
    if ($notes) {
        $update_stmt->bind_param("ssi", $status, $notes, $pre_order_id);
    } else {
        $update_stmt->bind_param("ssi", $status, $notes, $pre_order_id);
    }
    
    if ($update_stmt->execute()) {
        // 获取更新后的数据
        $get_sql = "SELECT * FROM pre_orders WHERE pre_order_id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("i", $pre_order_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $updated_order = $get_result->fetch_assoc();
        $get_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pre-order updated successfully',
            'preorder' => $updated_order
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update pre-order: ' . $conn->error
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