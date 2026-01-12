<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 禁用错误输出到浏览器
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 打开错误日志
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// 包含数据库配置文件
require_once '../include/database.php';

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取POST数据
$input = file_get_contents('php://input');

// 检查是否为空
if (empty($input)) {
    echo json_encode([
        'success' => false,
        'error' => 'Empty request body'
    ]);
    exit();
}

$data = json_decode($input, true);

// 验证JSON数据
if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
    error_log('Invalid JSON input: ' . json_last_error_msg() . ' Input: ' . substr($input, 0, 200));
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON data'
    ]);
    exit();
}

// 检查必需字段
$required_fields = ['customer_id', 'cart_ids', 'total_amount', 'payment_method'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    error_log('Missing fields: ' . implode(', ', $missing_fields));
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// 获取数据
$customer_id = intval($data['customer_id']);
$total_amount = floatval($data['total_amount']);
$payment_method = $conn->real_escape_string($data['payment_method']);

// 确保cart_ids是数组
if (isset($data['cart_ids']) && !is_array($data['cart_ids'])) {
    $cart_ids = [$data['cart_ids']];
} else {
    $cart_ids = is_array($data['cart_ids']) ? $data['cart_ids'] : [];
}

// 获取可选字段
$shipping_address = isset($data['shipping_address']) ? $conn->real_escape_string($data['shipping_address']) : 'Default Address';
$contact_phone = isset($data['contact_phone']) ? $conn->real_escape_string($data['contact_phone']) : '';
$contact_email = isset($data['contact_email']) ? $conn->real_escape_string($data['contact_email']) : '';
$notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

// 验证cart_ids
if (empty($cart_ids)) {
    echo json_encode([
        'success' => false,
        'error' => 'No cart items selected'
    ]);
    exit();
}

// 开始事务
$conn->autocommit(false);

try {
    // 1. 验证购物车项目
    $cart_ids_str = implode(',', array_map('intval', $cart_ids));
    
    $cart_sql = "SELECT c.cart_id, c.book_id, c.quantity, b.price, b.title, b.stock_quantity, b.pre_order_available
                 FROM cart c 
                 JOIN books b ON c.book_id = b.book_id 
                 WHERE c.cart_id IN ($cart_ids_str) AND c.customer_id = ?";
    
    error_log("Cart SQL: $cart_sql with customer_id: $customer_id");
    
    $cart_stmt = $conn->prepare($cart_sql);
    if (!$cart_stmt) {
        throw new Exception('Failed to prepare cart statement: ' . $conn->error);
    }
    
    $cart_stmt->bind_param("i", $customer_id);
    if (!$cart_stmt->execute()) {
        throw new Exception('Failed to execute cart query: ' . $cart_stmt->error);
    }
    
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        throw new Exception('No valid cart items found for this customer');
    }
    
    $order_items = [];
    $total_verification = 0;
    
    while ($item = $cart_result->fetch_assoc()) {
        // 检查是否为预购商品
        if ($item['pre_order_available'] == 1) {
            throw new Exception("Book '{$item['title']}' is a pre-order item and cannot be purchased directly");
        }
        
        // 检查库存
        if ($item['stock_quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for '{$item['title']}'. Available: {$item['stock_quantity']}, Requested: {$item['quantity']}");
        }
        
        $order_items[] = [
            'cart_id' => $item['cart_id'],
            'book_id' => $item['book_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'subtotal' => $item['price'] * $item['quantity'],
            'title' => $item['title']
        ];
        
        $total_verification += $item['price'] * $item['quantity'];
    }
    $cart_stmt->close();
    
    // 验证总金额（允许小许差异）
    $expected_total = $total_verification + 5.00 + ($total_verification * 0.06); // 运费5 + 6%税
    if (abs($total_amount - $expected_total) > 0.01) {
        error_log("Total mismatch. Received: $total_amount, Expected: $expected_total");
        // 这里不抛出错误，但记录日志
    }
    
    // 2. 创建订单
    $order_sql = "INSERT INTO orders (
                    customer_id, 
                    total_amount, 
                    status, 
                    shipping_address, 
                    billing_address, 
                    contact_phone, 
                    contact_email, 
                    notes
                  ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)";
    
    error_log("Order SQL: $order_sql");
    
    $order_stmt = $conn->prepare($order_sql);
    if (!$order_stmt) {
        throw new Exception('Failed to prepare order statement: ' . $conn->error);
    }
    
    $billing_address = $shipping_address;
    
    $order_stmt->bind_param(
        "idsssss",
        $customer_id, 
        $total_amount, 
        $shipping_address, 
        $billing_address, 
        $contact_phone, 
        $contact_email, 
        $notes
    );
    
    if (!$order_stmt->execute()) {
        throw new Exception('Failed to create order: ' . $order_stmt->error);
    }
    
    $order_id = $order_stmt->insert_id;
    $order_stmt->close();
    
    error_log("Order created with ID: $order_id");
    
    // 3. 创建订单项并更新库存
    foreach ($order_items as $item) {
        // 插入订单项
        $item_sql = "INSERT INTO order_items (order_id, book_id, quantity, unit_price, subtotal) 
                     VALUES (?, ?, ?, ?, ?)";
        
        error_log("Item SQL for book {$item['book_id']}: $item_sql");
        
        $item_stmt = $conn->prepare($item_sql);
        if (!$item_stmt) {
            throw new Exception('Failed to prepare item statement: ' . $conn->error);
        }
        
        $item_stmt->bind_param("iiidd", 
            $order_id, 
            $item['book_id'], 
            $item['quantity'], 
            $item['unit_price'], 
            $item['subtotal']
        );
        
        if (!$item_stmt->execute()) {
            throw new Exception('Failed to create order item: ' . $item_stmt->error);
        }
        $item_stmt->close();
        
        // 更新库存
        $update_sql = "UPDATE books 
                       SET stock_quantity = stock_quantity - ?, 
                           updated_at = NOW()
                       WHERE book_id = ?";
        
        error_log("Update stock SQL for book {$item['book_id']}: $update_sql");
        
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            throw new Exception('Failed to prepare update statement: ' . $conn->error);
        }
        
        $update_stmt->bind_param("ii", $item['quantity'], $item['book_id']);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update stock: ' . $update_stmt->error);
        }
        $update_stmt->close();
        
        error_log("Stock updated for book {$item['book_id']}");
    }
    
    // 4. 从购物车中移除已购买的商品
    $delete_sql = "DELETE FROM cart WHERE cart_id IN ($cart_ids_str) AND customer_id = ?";
    
    error_log("Delete cart SQL: $delete_sql");
    
    $delete_stmt = $conn->prepare($delete_sql);
    if (!$delete_stmt) {
        throw new Exception('Failed to prepare delete statement: ' . $conn->error);
    }
    
    $delete_stmt->bind_param("i", $customer_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('Failed to clear cart items: ' . $delete_stmt->error);
    }
    $delete_stmt->close();
    
    error_log("Cart items deleted");
    
    // 5. 创建完整的支付记录
    $payment_sql = "INSERT INTO payments (
                        order_id, 
                        customer_id, 
                        payment_method, 
                        payment_status, 
                        amount,
                        transaction_id,
                        payment_date,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 'completed', ?, ?, NOW(), NOW(), NOW())";
    
    error_log("Payment SQL: $payment_sql");
    
    $payment_stmt = $conn->prepare($payment_sql);
    if (!$payment_stmt) {
        throw new Exception('Failed to prepare payment statement: ' . $conn->error);
    }
    
    // 生成唯一的交易ID
    $timestamp = time();
    $random_suffix = bin2hex(random_bytes(3));
    $transaction_id = "TXN_" . $order_id . "_" . $timestamp . "_" . $random_suffix;
    
    // 绑定参数: order_id(i), customer_id(i), payment_method(s), amount(d), transaction_id(s)
    $payment_stmt->bind_param("iisds", 
        $order_id, 
        $customer_id, 
        $payment_method, 
        $total_amount, 
        $transaction_id
    );
    
    if (!$payment_stmt->execute()) {
        throw new Exception('Failed to create payment record: ' . $payment_stmt->error);
    }
    
    $payment_id = $payment_stmt->insert_id;
    $payment_stmt->close();
    
    error_log("Payment record created with ID: $payment_id, transaction_id: $transaction_id");
    
    // 6. 更新订单状态为 confirmed
    $update_order_sql = "UPDATE orders SET status = 'confirmed', updated_at = NOW() WHERE order_id = ?";
    $update_order_stmt = $conn->prepare($update_order_sql);
    $update_order_stmt->bind_param("i", $order_id);
    $update_order_stmt->execute();
    $update_order_stmt->close();
    
    error_log("Order status updated to confirmed");
    
    // 提交事务
    $conn->commit();
    error_log("Transaction committed successfully");
    
    // 成功响应
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $order_id,
        'payment_id' => $payment_id,
        'transaction_id' => $transaction_id,
        'total_amount' => $total_amount,
        'item_count' => count($order_items),
        'items' => array_column($order_items, 'title'),
        'payment_method' => $payment_method,
        'payment_status' => 'completed'
    ]);
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    error_log('Transaction failed: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// 恢复自动提交
$conn->autocommit(true);
$conn->close();
?>