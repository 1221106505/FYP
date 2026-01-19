<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// 获取 customer_id
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid customer ID'
    ]);
    exit();
}

try {
    // 首先获取客户基本信息
    $customer_sql = "SELECT * FROM customer WHERE auto_id = ?";
    $customer_stmt = $conn->prepare($customer_sql);
    $customer_stmt->bind_param("i", $customer_id);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Customer not found'
        ]);
        exit();
    }
    
    $customer = $customer_result->fetch_assoc();
    $customer_stmt->close();
    
    // 获取客户的所有订单（包含支付信息）
    $orders_sql = "SELECT 
                    o.*,
                    p.payment_method,
                    p.payment_status,
                    p.amount AS payment_amount,
                    p.transaction_id,
                    p.payment_date,
                    p.created_at AS payment_created_at
                   FROM orders o
                   LEFT JOIN payments p ON o.order_id = p.order_id
                   WHERE o.customer_id = ? 
                   ORDER BY o.order_date DESC";
    
    $orders_stmt = $conn->prepare($orders_sql);
    $orders_stmt->bind_param("i", $customer_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    $orders = [];
    $total_spent = 0;
    $completed_payments = 0;
    
    if ($orders_result && $orders_result->num_rows > 0) {
        while ($order = $orders_result->fetch_assoc()) {
            $order_id = $order['order_id'];
            
            // 获取每个订单的订单项详情
            $items_sql = "SELECT 
                            oi.*,
                            b.title,
                            b.author,
                            b.cover_image,
                            b.price AS original_price
                          FROM order_items oi
                          LEFT JOIN books b ON oi.book_id = b.book_id
                          WHERE oi.order_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            $order_items_total = 0;
            while ($item = $items_result->fetch_assoc()) {
                $item['item_total'] = $item['quantity'] * $item['unit_price'];
                $order_items_total += $item['item_total'];
                $items[] = $item;
            }
            $items_stmt->close();
            
            // 计算统计数据
            if ($order['payment_status'] === 'completed') {
                $total_spent += $order['total_amount'];
                $completed_payments++;
            }
            
            // 格式化支付状态显示
            $order['payment_status_display'] = formatPaymentStatus($order['payment_status']);
            $order['payment_method_display'] = formatPaymentMethod($order['payment_method']);
            
            $order['items'] = $items;
            $order['items_total'] = $order_items_total;
            $orders[] = $order;
        }
    }
    
    $orders_stmt->close();
    
    // 获取支付统计信息
    $payment_stats_sql = "SELECT 
                            COUNT(*) AS total_payments,
                            SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) AS total_completed,
                            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) AS failed_count
                          FROM payments p
                          JOIN orders o ON p.order_id = o.order_id
                          WHERE o.customer_id = ?";
    
    $stats_stmt = $conn->prepare($payment_stats_sql);
    $stats_stmt->bind_param("i", $customer_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $payment_stats = $stats_result->fetch_assoc();
    $stats_stmt->close();
    
    echo json_encode([
        'success' => true,
        'customer' => [
            'customer_id' => $customer['auto_id'],
            'username' => $customer['username'],
            'email' => $customer['email'] ?? '',
            'first_name' => $customer['first_name'] ?? '',
            'last_name' => $customer['last_name'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'address' => $customer['address'] ?? '',
            'city' => $customer['city'] ?? '',
            'state' => $customer['state'] ?? '',
            'zip_code' => $customer['zip_code'] ?? '',
            'country' => $customer['country'] ?? ''
        ],
        'orders' => $orders,
        'order_count' => count($orders),
        'payment_stats' => [
            'total_spent' => $total_spent,
            'completed_payments' => $completed_payments,
            'total_payments' => $payment_stats['total_payments'] ?? 0,
            'pending_payments' => $payment_stats['pending_count'] ?? 0,
            'failed_payments' => $payment_stats['failed_count'] ?? 0
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();

// 辅助函数：格式化支付状态
function formatPaymentStatus($status) {
    $status_map = [
        'pending' => 'pending',
        'completed' => 'completed',
        'failed' => 'failed',
        'refunded' => 'refunded'
    ];
    return $status_map[$status] ?? $status;
}

// 辅助函数：格式化支付方式
function formatPaymentMethod($method) {
    $method_map = [
        'credit_card' => 'credit_card',
        'debit_card' => 'debit_card',
        'paypal' => 'paypal',
        'bank_transfer' => 'bank_transfer',
        'cash_on_delivery' => 'cash_on_delivery'
    ];
    return $method_map[$method] ?? $method;
}
?>