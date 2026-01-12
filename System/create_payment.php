<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../include/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);

// 验证必需字段
$required_fields = ['order_id', 'customer_id', 'payment_method', 'amount'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit();
    }
}

$order_id = intval($input['order_id']);
$customer_id = intval($input['customer_id']);
$payment_method = $conn->real_escape_string($input['payment_method']);
$amount = floatval($input['amount']);
$transaction_id = isset($input['transaction_id']) ? $conn->real_escape_string($input['transaction_id']) : null;
$notes = isset($input['notes']) ? $conn->real_escape_string($input['notes']) : '';

// 验证支付方式
$valid_methods = ['credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash_on_delivery'];
if (!in_array($payment_method, $valid_methods)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid payment method'
    ]);
    exit();
}

try {
    // 验证订单是否存在且属于该客户
    $order_sql = "SELECT total_amount, order_status FROM orders WHERE order_id = ? AND customer_id = ?";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("ii", $order_id, $customer_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    
    if ($order_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Order not found or does not belong to customer'
        ]);
        exit();
    }
    
    $order = $order_result->fetch_assoc();
    $order_total = floatval($order['total_amount']);
    
    // 检查订单是否已取消
    if ($order['order_status'] === 'cancelled') {
        echo json_encode([
            'success' => false,
            'error' => 'Cannot process payment for cancelled order'
        ]);
        exit();
    }
    
    // 检查支付金额是否合理
    if ($amount <= 0 || $amount > $order_total * 1.1) { // 允许10%超付（比如包含小费）
        echo json_encode([
            'success' => false,
            'error' => 'Invalid payment amount. Must be between 0 and ' . ($order_total * 1.1)
        ]);
        exit();
    }
    
    // 生成交易ID（如果没有提供）
    if (empty($transaction_id)) {
        $transaction_id = generateTransactionId($order_id, $customer_id, $payment_method);
    }
    
    // 检查交易ID是否唯一
    $check_txn_sql = "SELECT payment_id FROM payments WHERE transaction_id = ?";
    $check_txn_stmt = $conn->prepare($check_txn_sql);
    $check_txn_stmt->bind_param("s", $transaction_id);
    $check_txn_stmt->execute();
    
    if ($check_txn_stmt->get_result()->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Transaction ID already exists'
        ]);
        exit();
    }
    $check_txn_stmt->close();
    
    // 插入支付记录
    $sql = "INSERT INTO payments (
                order_id, 
                customer_id, 
                payment_method, 
                payment_status, 
                amount, 
                transaction_id, 
                payment_date,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisdss", $order_id, $customer_id, $payment_method, $amount, $transaction_id, $notes);
    
    if ($stmt->execute()) {
        $payment_id = $stmt->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment created successfully',
            'payment_id' => $payment_id,
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'next_step' => 'Process payment using process_payment.php endpoint'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create payment: ' . $conn->error
        ]);
    }
    
    $stmt->close();
    $order_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();

// 生成交易ID的辅助函数
function generateTransactionId($order_id, $customer_id, $method) {
    $prefix = strtoupper(substr($method, 0, 3));
    $timestamp = time();
    $random = rand(1000, 9999);
    return "TXN_{$prefix}_{$order_id}_{$customer_id}_{$timestamp}_{$random}";
}
?>