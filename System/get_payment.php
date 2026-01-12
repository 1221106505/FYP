<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../include/database.php';

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

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 获取POST数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['payment_id']) || !isset($data['transaction_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: payment_id and transaction_id are required'
    ]);
    exit();
}

$payment_id = intval($data['payment_id']);
$transaction_id = $conn->real_escape_string($data['transaction_id']);

try {
    // 更新支付状态为完成
    $update_sql = "UPDATE payments 
                   SET payment_status = 'completed', 
                       payment_date = NOW() 
                   WHERE payment_id = ? AND transaction_id = ?";
    
    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $payment_id, $transaction_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update payment: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('No payment record found with the provided details');
    }
    
    $stmt->close();
    
    // 获取支付详情 - 仅查询存在的列
    $select_sql = "SELECT 
        p.payment_id, 
        p.order_id, 
        p.customer_id, 
        p.payment_method, 
        p.payment_status, 
        p.amount, 
        p.transaction_id, 
        p.payment_date,
        p.created_at, 
        p.updated_at,
        o.order_date, 
        o.status as order_status,
        o.total_amount,
        c.username, 
        c.email,
        c.first_name,
        c.last_name
    FROM payments p
    JOIN orders o ON p.order_id = o.order_id
    JOIN customer c ON p.customer_id = c.auto_id
    WHERE p.payment_id = ?";
    
    $select_stmt = $conn->prepare($select_sql);
    if (!$select_stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $select_stmt->bind_param("i", $payment_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Payment details not found');
    }
    
    $payment = $result->fetch_assoc();
    $select_stmt->close();
    
    // 成功响应
    echo json_encode([
        'success' => true,
        'message' => 'Payment completed successfully',
        'payment' => $payment
    ]);
    
} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>