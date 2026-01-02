<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);

// 验证必需字段
$required_fields = ['payment_id', 'refund_amount', 'refund_reason'];
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
$refund_amount = floatval($input['refund_amount']);
$refund_reason = $conn->real_escape_string($input['refund_reason']);
$refund_notes = isset($input['refund_notes']) ? $conn->real_escape_string($input['refund_notes']) : '';

try {
    // 开始事务
    $conn->begin_transaction();
    
    // 获取原始支付信息
    $payment_sql = "SELECT * FROM payments WHERE payment_id = ? AND payment_status = 'completed' FOR UPDATE";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("i", $payment_id);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    
    if ($payment_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Completed payment not found'
        ]);
        exit();
    }
    
    $payment = $payment_result->fetch_assoc();
    $original_amount = floatval($payment['amount']);
    
    // 验证退款金额
    if ($refund_amount <= 0 || $refund_amount > $original_amount) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid refund amount. Must be between 0 and ' . $original_amount
        ]);
        exit();
    }
    
    // 生成退款交易ID
    $refund_transaction_id = "REF_" . $payment['transaction_id'] . "_" . time();
    
    // 创建退款记录（作为新的支付记录）
    $refund_sql = "INSERT INTO payments (
                        order_id, 
                        customer_id, 
                        payment_method, 
                        payment_status, 
                        amount, 
                        transaction_id,
                        notes
                    ) VALUES (?, ?, 'refund', 'refunded', ?, ?, ?)";
    
    $refund_stmt = $conn->prepare($refund_sql);
    $refund_amount_negative = -$refund_amount; // 退款金额为负数
    $refund_notes_full = "Refund for payment #{$payment_id}. Reason: {$refund_reason}. Notes: {$refund_notes}";
    
    $refund_stmt->bind_param("iidss", 
        $payment['order_id'], 
        $payment['customer_id'], 
        $refund_amount_negative, 
        $refund_transaction_id,
        $refund_notes_full
    );
    
    if (!$refund_stmt->execute()) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create refund record: ' . $conn->error
        ]);
        exit();
    }
    
    $refund_id = $refund_stmt->insert_id;
    
    // 标记原始支付记录为已退款（部分退款）
    if ($refund_amount < $original_amount) {
        $update_payment_sql = "UPDATE payments SET notes = CONCAT(notes, ?) WHERE payment_id = ?";
        $update_notes = " | Partial refund of {$refund_amount} completed. Refund ID: {$refund_id}";
        $update_stmt = $conn->prepare($update_payment_sql);
        $update_stmt->bind_param("si", $update_notes, $payment_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // 全额退款
        $update_payment_sql = "UPDATE payments SET payment_status = 'refunded' WHERE payment_id = ?";
        $update_stmt = $conn->prepare($update_payment_sql);
        $update_stmt->bind_param("i", $payment_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    // 提交事务
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully',
        'refund_id' => $refund_id,
        'original_payment_id' => $payment_id,
        'refund_amount' => $refund_amount,
        'transaction_id' => $refund_transaction_id
    ]);
    
    $payment_stmt->close();
    $refund_stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>