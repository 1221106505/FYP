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
    
    // 修改：获取订单信息，使用正确的字段名
    $orders_sql = "SELECT 
                    o.order_id,
                    o.customer_id,
                    o.customer_name,
                    o.order_date,
                    o.total_amount,
                    o.status,  -- 直接使用 status，而不是 order_status
                    o.shipping_address,
                    o.billing_address,
                    o.contact_phone,
                    o.contact_email,
                    o.payment_method,
                    p.payment_id,
                    p.payment_status,
                    p.amount AS payment_amount,
                    p.transaction_id,
                    p.payment_date
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
                            oi.order_item_id,
                            oi.book_id,
                            oi.quantity,
                            oi.unit_price,
                            b.title,
                            b.author,
                            b.cover_image,
                            b.price AS original_price,
                            c.category_name
                          FROM order_items oi
                          LEFT JOIN books b ON oi.book_id = b.book_id
                          LEFT JOIN categories c ON b.category_id = c.category_id
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
            $payment_status = $order['payment_status'] ?? null;
            if ($payment_status === 'completed' || $payment_status === 'success') {
                $payment_amount = $order['payment_amount'] ?? $order['total_amount'];
                $total_spent += floatval($payment_amount);
                $completed_payments++;
            }
            
            // 格式化订单状态显示 - 确保有默认值
            $order_status = $order['status'] ?? 'pending';
            $order['status'] = $order_status; // 确保有 status 字段
            $order['status_display'] = formatOrderStatus($order_status);
            $order['status_class'] = getStatusClass($order_status);
            
            // 格式化支付状态显示
            $order['payment_status_display'] = formatPaymentStatus($payment_status);
            $order['payment_method_display'] = formatPaymentMethod($order['payment_method']);
            
            $order['items'] = $items;
            $order['item_count'] = count($items);
            
            // 格式化日期
            if (isset($order['order_date'])) {
                $order['order_date_formatted'] = date('Y-m-d H:i', strtotime($order['order_date']));
            }
            
            $orders[] = $order;
        }
    }
    
    $orders_stmt->close();
    
    // 获取订单状态统计
    $status_stats_sql = "SELECT 
                            status,
                            COUNT(*) as count,
                            SUM(total_amount) as total_amount
                         FROM orders 
                         WHERE customer_id = ?
                         GROUP BY status";
    
    $stats_stmt = $conn->prepare($status_stats_sql);
    $stats_stmt->bind_param("i", $customer_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $status_stats = [];
    
    while ($row = $stats_result->fetch_assoc()) {
        $status_stats[] = $row;
    }
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
        'status_stats' => $status_stats,
        'payment_stats' => [
            'total_spent' => $total_spent,
            'completed_payments' => $completed_payments,
            'total_orders' => count($orders)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();

// 辅助函数：格式化订单状态
function formatOrderStatus($status) {
    if (empty($status)) return 'Pending';
    
    $status_map = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    
    return $status_map[strtolower($status)] ?? ucfirst($status);
}

// 辅助函数：获取状态类名
function getStatusClass($status) {
    $status = strtolower($status);
    $class_map = [
        'confirmed' => 'status-confirmed',
        'processing' => 'status-confirmed',
        'shipped' => 'status-shipped',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled'
    ];
    
    return $class_map[$status] ?? 'status-Confirmed';
}

// 辅助函数：格式化支付状态
function formatPaymentStatus($status) {
    if (empty($status)) return 'Confirmed';

    $status_map = [
        'completed' => 'completed',
        'success' => 'completed',
        'failed' => 'failed',
        'refunded' => 'refunded'
    ];
    
    $status = strtolower($status);
    return $status_map[$status] ?? $status;
}

// 辅助函数：格式化支付方式
function formatPaymentMethod($method) {
    if (empty($method)) return 'Not Specified';
    
    $method_map = [
        'card' => 'Credit Card',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'cod' => 'Cash on Delivery',
        'cash_on_delivery' => 'Cash on Delivery',
        'bank_transfer' => 'Bank Transfer',
        'paypal' => 'PayPal'
    ];
    
    $method = strtolower($method);
    return $method_map[$method] ?? ucwords(str_replace('_', ' ', $method));
}
?>