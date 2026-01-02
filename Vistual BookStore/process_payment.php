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
$required_fields = ['payment_id', 'payment_status'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit();
    }
}

$payment_id = intval($input['payment_id']);
$payment_status = $conn->real_escape_string($input['payment_status']);
$transaction_id = isset($input['transaction_id']) ? $conn->real_escape_string($input['transaction_id']) : null;
$notes = isset($input['notes']) ? $conn->real_escape_string($input['notes']) : '';

// 验证支付状态
$valid_statuses = ['pending', 'completed', 'failed', 'refunded'];
if (!in_array($payment_status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid payment status'
    ]);
    exit();
}

try {
    // 开始事务
    $conn->begin_transaction();
    
    // 获取当前支付信息
    $payment_sql = "SELECT * FROM payments WHERE payment_id = ? FOR UPDATE";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("i", $payment_id);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    
    if ($payment_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Payment not found'
        ]);
        exit();
    }
    
    $payment = $payment_result->fetch_assoc();
    $current_status = $payment['payment_status'];
    
    // 检查状态转换是否有效
    if (!isValidStatusTransition($current_status, $payment_status)) {
        echo json_encode([
            'success' => false,
            'error' => "Invalid status transition from $current_status to $payment_status"
        ]);
        exit();
    }
    
    // 更新支付状态
    $update_sql = "UPDATE payments 
                   SET payment_status = ?, 
                       transaction_id = COALESCE(?, transaction_id),
                       updated_at = NOW() 
                   WHERE payment_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $payment_status, $transaction_id, $payment_id);
    
    if (!$update_stmt->execute()) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update payment status: ' . $conn->error
        ]);
        exit();
    }
    
    // 如果支付完成，更新订单状态
    if ($payment_status === 'completed') {
        // 标记支付日期
        $complete_sql = "UPDATE payments SET payment_date = NOW() WHERE payment_id = ?";
        $complete_stmt = $conn->prepare($complete_sql);
        $complete_stmt->bind_param("i", $payment_id);
        $complete_stmt->execute();
        $complete_stmt->close();
        
        // 检查订单是否已完全支付
        $order_id = $payment['order_id'];
        $check_payment_sql = "
            SELECT 
                o.total_amount,
                COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as total_paid
            FROM orders o
            LEFT JOIN payments p ON o.order_id = p.order_id
            WHERE o.order_id = ?
            GROUP BY o.order_id
        ";
        
        $check_stmt = $conn->prepare($check_payment_sql);
        $check_stmt->bind_param("i", $order_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $data = $check_result->fetch_assoc();
            $order_total = floatval($data['total_amount']);
            $total_paid = floatval($data['total_paid']);
            
            // 如果已支付完成或超额支付，更新订单状态
            if ($total_paid >= $order_total) {
                // 更新订单状态为已支付
                $update_order_sql = "UPDATE orders SET status = 'paid', updated_at = NOW() WHERE order_id = ?";
                $update_order_stmt = $conn->prepare($update_order_sql);
                $update_order_stmt->bind_param("i", $order_id);
                $update_order_stmt->execute();
                $update_order_stmt->close();
                
                // 如果超额支付，记录超额部分（可创建退款记录）
                if ($total_paid > $order_total) {
                    $overpayment = $total_paid - $order_total;
                    // 可以在这里创建退款记录或标记为信用额度
                }
            }
        }
        $check_stmt->close();
    }
    
    // 提交事务
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_id' => $payment_id,
        'old_status' => $current_status,
        'new_status' => $payment_status,
        'order_id' => $payment['order_id']
    ]);
    
    $payment_stmt->close();
    $update_stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();

// 验证状态转换是否有效
function isValidStatusTransition($from, $to) {
    $allowed_transitions = [
        'pending' => ['completed', 'failed'],
        'completed' => ['refunded'],
        'failed' => ['pending'], // 允许重新尝试
        'refunded' => [] // 退款后不能再更改
    ];
    
    return in_array($to, $allowed_transitions[$from] ?? []);
}
?>